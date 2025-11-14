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

  constructor(diagnosticCollection: vscode.DiagnosticCollection, extensionPath: string) {
    this.diagnosticCollection = diagnosticCollection;
    this.extensionPath = extensionPath;
    this.outputChannel = vscode.window.createOutputChannel('PHP Exception Inspector');
    this.statusBarItem = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Left, 100);
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
      this.outputChannel.appendLine(`Error: ${message}`);
      vscode.window.showErrorMessage(message);
      return;
    }

    this.outputChannel.appendLine(`Analyzing: ${document.fileName}`);
    this.outputChannel.appendLine(`Using PHP Exception Inspector: ${inspectorPath}`);

    // Show status bar notification
    this.statusBarItem.text = '$(sync~spin) PHP Exception Inspector: Analyzing...';
    this.statusBarItem.show();

    try {
      const result = await this.runInspector(inspectorPath, document.fileName);
      this.processResult(document, result);
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      this.outputChannel.appendLine(`Error running PHP Exception Inspector: ${errorMessage}`);
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
        this.outputChannel.appendLine(`PHP Exception Inspector exit code: ${code}`);

        if (stderr) {
          this.outputChannel.appendLine(`Stderr: ${stderr}`);
        }

        if (!stdout) {
          reject(new Error('No output from PHP Exception Inspector'));
          return;
        }

        try {
          const result = JSON.parse(stdout) as InspectorResult;
          resolve(result);
        } catch (error) {
          this.outputChannel.appendLine(`Failed to parse JSON: ${stdout}`);
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
      this.outputChannel.appendLine(`PHP Exception Inspector error: ${result.error.message}`);
      vscode.window.showErrorMessage(`PHP Exception Inspector error: ${result.error.message}`);
      return;
    }

    if (!result.files || result.files.length === 0) {
      this.outputChannel.appendLine('No errors found');
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

        diagnostics.push(diagnostic);
      }
    }

    this.diagnosticCollection.set(document.uri, diagnostics);

    const errorCount = diagnostics.length;
    this.outputChannel.appendLine(`Found ${errorCount} issue(s)`);
  }

  /**
   * Dispose of resources
   */
  public dispose(): void {
    this.statusBarItem.dispose();
  }
}
