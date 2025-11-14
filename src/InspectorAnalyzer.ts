import * as child_process from 'child_process';
import * as fs from 'fs';
import * as path from 'path';
import * as vscode from 'vscode';

interface InspectorError {
  line: number;
  type: string;
  exception: string;
  message: string;
}

interface InspectorFileResult {
  file: string;
  errors: InspectorError[];
}

interface InspectorResult {
  files?: InspectorFileResult[];
  summary?: {
    total_files: number;
    files_with_errors: number;
    total_errors: number;
  };
  performance?: {
    cache_hits: number;
    cache_misses: number;
    files_scanned: number;
    analysis_time_ms: number;
    cache_stats?: {
      total_files: number;
      cache_size_bytes: number;
    };
  };
  error?: {
    message: string;
    file?: string;
    line?: number;
  };
}

export class InspectorAnalyzer {
  private diagnosticCollection: vscode.DiagnosticCollection;
  private outputChannel: vscode.OutputChannel;
  private statusBarItem: vscode.StatusBarItem;
  private extensionPath: string;
  private logLineCount: number = 0;

  constructor(diagnosticCollection: vscode.DiagnosticCollection, extensionPath: string) {
    this.diagnosticCollection = diagnosticCollection;
    this.extensionPath = extensionPath;
    this.outputChannel = vscode.window.createOutputChannel('PHP Exception Inspector');
    this.statusBarItem = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Left, 100);
  }

  /**
   * Append line to output channel with automatic cleanup when exceeding max lines
   */
  private appendLog(message: string): void {
    this.logLineCount++;

    const config = vscode.workspace.getConfiguration('phpExceptionInspector');
    const maxLogLines = config.get<number>('maxLogLines', 1000);

    if (this.logLineCount > maxLogLines) {
      this.outputChannel.clear();
      this.logLineCount = 0;
      this.outputChannel.appendLine(`--- Log cleared due to size limit (${maxLogLines} lines) ---`);
    }

    this.outputChannel.appendLine(message);
  }

  /**
   * Find Inspector executable path in the extension's php-bin directory
   */
  private findInspectorExecutable(): string | null {
    const inspectorPath = path.join(this.extensionPath, 'php-bin', 'php-exception-inspector');

    if (fs.existsSync(inspectorPath)) {
      return inspectorPath;
    }

    return null;
  }

  /**
   * Analyze a document using Inspector
   */
  public async analyzeDocument(document: vscode.TextDocument): Promise<void> {
    // Clear previous diagnostics for this document
    this.diagnosticCollection.delete(document.uri);

    // Don't analyze unsaved files
    if (document.isUntitled) {
      return;
    }

    const inspectorPath = this.findInspectorExecutable();
    if (!inspectorPath) {
      const message =
        'PHP Exception Inspector executable not found in php-bin/. Please ensure the extension is properly installed.';
      this.appendLog(`Error: ${message}`);
      vscode.window.showErrorMessage(message);
      return;
    }

    this.appendLog(`Analyzing: ${document.fileName}`);
    this.appendLog(`Using PHP Exception Inspector: ${inspectorPath}`);

    // Show status bar notification
    this.statusBarItem.text = '$(sync~spin) PHP Exception Inspector: Analyzing...';
    this.statusBarItem.show();

    try {
      const result = await this.runInspector(inspectorPath, document.fileName);
      this.processResult(document, result);
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      this.appendLog(`Error running PHP Exception Inspector: ${errorMessage}`);
      vscode.window.showErrorMessage(`PHP Exception Inspector analysis failed: ${errorMessage}`);
    } finally {
      // Hide status bar notification when done
      this.statusBarItem.hide();
    }
  }

  /**
   * Run inspector command and parse output
   */
  private runInspector(inspectorPath: string, filePath: string): Promise<InspectorResult> {
    return new Promise((resolve, reject) => {
      const config = vscode.workspace.getConfiguration('phpExceptionInspector');
      const noProjectScan = config.get<boolean>('noProjectScan', false);

      const args = [filePath];
      if (noProjectScan) {
        args.unshift('--no-project-scan');
      }

      const process = child_process.spawn(inspectorPath, args);
      let stdout = '';
      let stderr = '';

      process.stdout.on('data', (data) => {
        stdout += data.toString();
      });

      process.stderr.on('data', (data) => {
        stderr += data.toString();
      });

      process.on('close', (code) => {
        this.appendLog(`PHP Exception Inspector exit code: ${code}`);

        if (stderr) {
          this.appendLog(`Stderr: ${stderr}`);
        }

        if (!stdout) {
          reject(new Error('No output from PHP Exception Inspector'));
          return;
        }

        try {
          const result = JSON.parse(stdout) as InspectorResult;
          resolve(result);
        } catch (error) {
          this.appendLog(`Failed to parse JSON: ${stdout}`);
          reject(new Error(`Failed to parse PHP Exception Inspector output: ${error}`));
        }
      });

      process.on('error', (error) => {
        reject(new Error(`Failed to run PHP Exception Inspector: ${error.message}`));
      });
    });
  }

  /**
   * Process PHP Exception Inspector result and create diagnostics
   */
  private processResult(document: vscode.TextDocument, result: InspectorResult): void {
    if (result.error) {
      this.appendLog(`PHP Exception Inspector error: ${result.error.message}`);
      vscode.window.showErrorMessage(`PHP Exception Inspector error: ${result.error.message}`);
      return;
    }

    if (result.performance) {
      const perf = result.performance;
      const cacheHitRate =
        perf.cache_hits + perf.cache_misses > 0
          ? ((perf.cache_hits / (perf.cache_hits + perf.cache_misses)) * 100).toFixed(1)
          : '0';

      this.appendLog('--- Performance Statistics ---');
      this.appendLog(`Analysis time: ${perf.analysis_time_ms.toFixed(2)}ms`);
      this.appendLog(`Files scanned: ${perf.files_scanned}`);
      this.appendLog(`Cache hits: ${perf.cache_hits}`);
      this.appendLog(`Cache misses: ${perf.cache_misses}`);
      this.appendLog(`Cache hit rate: ${cacheHitRate}%`);

      if (perf.cache_stats) {
        this.appendLog(`Cached files: ${perf.cache_stats.total_files}`);
        this.appendLog(`Cache size: ${(perf.cache_stats.cache_size_bytes / 1024).toFixed(2)} KB`);
      }

      this.appendLog('----------------------------');
    }

    if (!result.files || result.files.length === 0) {
      this.appendLog('No errors found');
      return;
    }

    const diagnostics: vscode.Diagnostic[] = [];

    for (const fileResult of result.files) {
      for (const error of fileResult.errors) {
        const line = Math.max(0, error.line - 1); // Convert to 0-based
        const range = document.lineAt(line).range;

        const diagnostic = new vscode.Diagnostic(
          range,
          `${error.message}: ${error.exception}`,
          vscode.DiagnosticSeverity.Warning
        );

        diagnostic.source = 'PHP Exception Inspector';
        diagnostic.code = error.type;

        // Store exception name for quick fix
        (diagnostic as any).exceptionName = error.exception;

        diagnostics.push(diagnostic);
      }
    }

    this.diagnosticCollection.set(document.uri, diagnostics);

    const errorCount = diagnostics.length;
    this.appendLog(`Found ${errorCount} issue(s)`);
  }

  /**
   * Dispose of resources
   */
  public dispose(): void {
    this.outputChannel.dispose();
    this.statusBarItem.dispose();
  }
}
