import * as assert from 'assert';
import * as vscode from 'vscode';

suite('Extension Test Suite', () => {
  vscode.window.showInformationMessage('Start all tests.');

  test('Extension should be present', () => {
    assert.ok(vscode.extensions.getExtension('undefined_publisher.vigil-analyzer'));
  });

  test('Should activate extension', async function() {
    this.timeout(60000);
    const extension = vscode.extensions.getExtension('undefined_publisher.vigil-analyzer');
    if (extension) {
      await extension.activate();
      assert.ok(extension.isActive);
    }
  });

  test('Should register vigil.analyzeFile command', async () => {
    const commands = await vscode.commands.getCommands(true);
    assert.ok(commands.includes('vigil.analyzeFile'));
  });

  test('Should have proper configuration', () => {
    const config = vscode.workspace.getConfiguration('vigil');
    assert.ok(config !== undefined);

    // Check if default values exist
    const executablePath = config.get('executablePath');
    const analyzeOnSave = config.get('analyzeOnSave');
    const analyzeOnOpen = config.get('analyzeOnOpen');

    assert.strictEqual(typeof executablePath, 'string');
    assert.strictEqual(typeof analyzeOnSave, 'boolean');
    assert.strictEqual(typeof analyzeOnOpen, 'boolean');
  });
});
