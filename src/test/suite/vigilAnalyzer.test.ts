import * as assert from 'assert';
import * as vscode from 'vscode';
import { VigilAnalyzer } from '../../vigilAnalyzer';

suite('VigilAnalyzer Test Suite', () => {
  let diagnosticCollection: vscode.DiagnosticCollection;
  let analyzer: VigilAnalyzer;

  setup(() => {
    diagnosticCollection = vscode.languages.createDiagnosticCollection('vigil-test');
    analyzer = new VigilAnalyzer(diagnosticCollection);
  });

  teardown(() => {
    diagnosticCollection.dispose();
  });

  test('VigilAnalyzer should be instantiated', () => {
    assert.ok(analyzer);
    assert.ok(analyzer instanceof VigilAnalyzer);
  });

  test('Should handle untitled documents gracefully', async () => {
    const doc = await vscode.workspace.openTextDocument({
      content: '<?php\necho "test";\n',
      language: 'php',
    });

    // Should not throw error for untitled document
    await assert.doesNotReject(async () => {
      await analyzer.analyzeDocument(doc);
    });
  });

  test('Diagnostic collection should be empty initially', () => {
    const allDiagnostics = diagnosticCollection.get(vscode.Uri.parse('test://test.php'));
    assert.ok(allDiagnostics === undefined || allDiagnostics.length === 0);
  });

  test('Should export analyzeDocument method', () => {
    assert.ok(typeof analyzer.analyzeDocument === 'function');
  });
});
