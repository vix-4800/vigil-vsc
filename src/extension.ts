import * as vscode from 'vscode';
import { VigilAnalyzer } from './vigilAnalyzer';

let diagnosticCollection: vscode.DiagnosticCollection;
let analyzer: VigilAnalyzer;

export function activate(context: vscode.ExtensionContext) {
  console.log('Vigil PHP Analyzer is now active');

  // Create diagnostic collection for displaying errors
  diagnosticCollection = vscode.languages.createDiagnosticCollection('vigil');
  context.subscriptions.push(diagnosticCollection);

  // Initialize analyzer
  analyzer = new VigilAnalyzer(diagnosticCollection);

  // Register command to manually analyze current file
  const analyzeCommand = vscode.commands.registerCommand('vigil.analyzeFile', async () => {
    const editor = vscode.window.activeTextEditor;
    if (!editor) {
      vscode.window.showWarningMessage('No active editor found');
      return;
    }

    if (editor.document.languageId !== 'php') {
      vscode.window.showWarningMessage('Current file is not a PHP file');
      return;
    }

    await analyzer.analyzeDocument(editor.document);
    vscode.window.showInformationMessage('Vigil analysis complete');
  });

  context.subscriptions.push(analyzeCommand);

  // Analyze on file open
  const onOpenDisposable = vscode.workspace.onDidOpenTextDocument(async (document) => {
    const config = vscode.workspace.getConfiguration('vigil');
    if (config.get<boolean>('analyzeOnOpen') && document.languageId === 'php') {
      await analyzer.analyzeDocument(document);
    }
  });
  context.subscriptions.push(onOpenDisposable);

  // Analyze on file save
  const onSaveDisposable = vscode.workspace.onDidSaveTextDocument(async (document) => {
    const config = vscode.workspace.getConfiguration('vigil');
    if (config.get<boolean>('analyzeOnSave') && document.languageId === 'php') {
      await analyzer.analyzeDocument(document);
    }
  });
  context.subscriptions.push(onSaveDisposable);

  // Analyze currently opened PHP files
  vscode.workspace.textDocuments.forEach(async (document) => {
    const config = vscode.workspace.getConfiguration('vigil');
    if (config.get<boolean>('analyzeOnOpen') && document.languageId === 'php') {
      await analyzer.analyzeDocument(document);
    }
  });

  // Clear diagnostics when file is closed
  const onCloseDisposable = vscode.workspace.onDidCloseTextDocument((document) => {
    diagnosticCollection.delete(document.uri);
  });
  context.subscriptions.push(onCloseDisposable);
}

export function deactivate() {
  if (diagnosticCollection) {
    diagnosticCollection.clear();
    diagnosticCollection.dispose();
  }
  if (analyzer) {
    analyzer.dispose();
  }
}
