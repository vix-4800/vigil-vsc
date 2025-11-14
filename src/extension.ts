import * as vscode from 'vscode';
import { InspectorAnalyzer } from './InspectorAnalyzer';

let diagnosticCollection: vscode.DiagnosticCollection;
let analyzer: InspectorAnalyzer;

export function activate(context: vscode.ExtensionContext) {
  console.log('PHP Exception Inspector is now active');

  // Create diagnostic collection for displaying errors
  diagnosticCollection = vscode.languages.createDiagnosticCollection('phpExceptionInspector');
  context.subscriptions.push(diagnosticCollection);

  // Initialize analyzer
  analyzer = new InspectorAnalyzer(diagnosticCollection, context.extensionPath);

  // Register command to manually analyze current file
  const analyzeCommand = vscode.commands.registerCommand(
    'phpExceptionInspector.analyzeFile',
    async () => {
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
      vscode.window.showInformationMessage('PHP Exception Inspector analysis complete');
    }
  );

  context.subscriptions.push(analyzeCommand);

  // Analyze on file open
  const onOpenDisposable = vscode.workspace.onDidOpenTextDocument(async (document) => {
    const config = vscode.workspace.getConfiguration('phpExceptionInspector');
    if (config.get<boolean>('analyzeOnOpen') && document.languageId === 'php') {
      await analyzer.analyzeDocument(document);
    }
  });
  context.subscriptions.push(onOpenDisposable);

  // Analyze on file save
  const onSaveDisposable = vscode.workspace.onDidSaveTextDocument(async (document) => {
    const config = vscode.workspace.getConfiguration('phpExceptionInspector');
    if (config.get<boolean>('analyzeOnSave') && document.languageId === 'php') {
      await analyzer.analyzeDocument(document);
    }
  });
  context.subscriptions.push(onSaveDisposable);

  // Analyze currently opened PHP files
  vscode.workspace.textDocuments.forEach(async (document) => {
    const config = vscode.workspace.getConfiguration('phpExceptionInspector');
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
