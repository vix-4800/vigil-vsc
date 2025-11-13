import * as child_process from 'child_process';
import * as fs from 'fs';
import * as path from 'path';
import * as vscode from 'vscode';

interface VigilError {
  line: number;
  type: string;
  exception: string;
  message: string;
}

interface VigilFileResult {
  file: string;
  errors: VigilError[];
}

interface VigilResult {
  files?: VigilFileResult[];
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

export class VigilAnalyzer {
  private diagnosticCollection: vscode.DiagnosticCollection;
  private outputChannel: vscode.OutputChannel;
  private statusBarItem: vscode.StatusBarItem;

  constructor(diagnosticCollection: vscode.DiagnosticCollection) {
    this.diagnosticCollection = diagnosticCollection;
    this.outputChannel = vscode.window.createOutputChannel('Vigil');
    this.statusBarItem = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Left, 100);
  }

  /**
   * Find vigil executable path
   */
  private findVigilExecutable(): string | null {
    const config = vscode.workspace.getConfiguration('vigil');
    const configuredPath = config.get<string>('executablePath');

    // If user configured a path, use it
    if (configuredPath && fs.existsSync(configuredPath)) {
      return configuredPath;
    }

    // Look for vigil in workspace
    const workspaceFolders = vscode.workspace.workspaceFolders;
    if (workspaceFolders) {
      for (const folder of workspaceFolders) {
        // Check for bin/vigil in workspace root
        const vigilPath = path.join(folder.uri.fsPath, 'bin', 'vigil');
        if (fs.existsSync(vigilPath)) {
          return vigilPath;
        }

        // Check for ../bin/vigil (if extension is in subdirectory)
        const parentVigilPath = path.join(folder.uri.fsPath, '..', 'bin', 'vigil');
        if (fs.existsSync(parentVigilPath)) {
          return parentVigilPath;
        }
      }
    }

    // Try to find in PATH
    try {
      child_process.execSync('which vigil', { encoding: 'utf-8' });
      return 'vigil';
    } catch {
      // vigil not found in PATH
    }

    return null;
  }

  /**
   * Analyze a document using Vigil
   */
  public async analyzeDocument(document: vscode.TextDocument): Promise<void> {
    // Clear previous diagnostics for this document
    this.diagnosticCollection.delete(document.uri);

    // Don't analyze unsaved files
    if (document.isUntitled) {
      return;
    }

    const vigilPath = this.findVigilExecutable();
    if (!vigilPath) {
      const message =
        'Vigil executable not found. Please install Vigil or configure the path in settings.';
      this.outputChannel.appendLine(`Error: ${message}`);
      vscode.window.showErrorMessage(message);
      return;
    }

    this.outputChannel.appendLine(`Analyzing: ${document.fileName}`);
    this.outputChannel.appendLine(`Using Vigil: ${vigilPath}`);

    // Show status bar notification
    this.statusBarItem.text = '$(sync~spin) Vigil: Analyzing...';
    this.statusBarItem.show();

    try {
      const result = await this.runVigil(vigilPath, document.fileName);
      this.processResult(document, result);
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      this.outputChannel.appendLine(`Error running Vigil: ${errorMessage}`);
      vscode.window.showErrorMessage(`Vigil analysis failed: ${errorMessage}`);
    } finally {
      // Hide status bar notification when done
      this.statusBarItem.hide();
    }
  }

  /**
   * Run vigil command and parse output
   */
  private runVigil(vigilPath: string, filePath: string): Promise<VigilResult> {
    return new Promise((resolve, reject) => {
      const config = vscode.workspace.getConfiguration('vigil');
      const noProjectScan = config.get<boolean>('noProjectScan', false);

      const args = [filePath];
      if (noProjectScan) {
        args.unshift('--no-project-scan');
      }

      const process = child_process.spawn(vigilPath, args);
      let stdout = '';
      let stderr = '';

      process.stdout.on('data', (data) => {
        stdout += data.toString();
      });

      process.stderr.on('data', (data) => {
        stderr += data.toString();
      });

      process.on('close', (code) => {
        this.outputChannel.appendLine(`Vigil exit code: ${code}`);

        if (stderr) {
          this.outputChannel.appendLine(`Stderr: ${stderr}`);
        }

        if (!stdout) {
          reject(new Error('No output from Vigil'));
          return;
        }

        try {
          const result = JSON.parse(stdout) as VigilResult;
          resolve(result);
        } catch (error) {
          this.outputChannel.appendLine(`Failed to parse JSON: ${stdout}`);
          reject(new Error(`Failed to parse Vigil output: ${error}`));
        }
      });

      process.on('error', (error) => {
        reject(new Error(`Failed to run Vigil: ${error.message}`));
      });
    });
  }

  /**
   * Process Vigil result and create diagnostics
   */
  private processResult(document: vscode.TextDocument, result: VigilResult): void {
    if (result.error) {
      this.outputChannel.appendLine(`Vigil error: ${result.error.message}`);
      vscode.window.showErrorMessage(`Vigil error: ${result.error.message}`);
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

        diagnostic.source = 'Vigil';
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
