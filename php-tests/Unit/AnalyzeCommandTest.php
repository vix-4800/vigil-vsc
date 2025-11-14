<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vix\ExceptionInspector\Command\AnalyzeCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AnalyzeCommandTest extends TestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $command = new AnalyzeCommand();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithCleanFile(): void
    {
        $filePath = __DIR__ . '/../Fixtures/CleanCode.php';

        $this->commandTester->execute([
            'path' => $filePath,
        ]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $result = json_decode($output, true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertEquals(0, $result['summary']['total_errors']);
    }

    public function testExecuteWithErrorFile(): void
    {
        $filePath = __DIR__ . '/../Fixtures/UndocumentedThrows.php';

        $this->commandTester->execute([
            'path' => $filePath,
        ]);

        $this->assertEquals(1, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $result = json_decode($output, true);

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['summary']['total_errors']);
    }

    public function testExecuteWithNonExistentPath(): void
    {
        $this->commandTester->execute([
            'path' => '/non/existent/path.php',
        ]);

        $this->assertEquals(1, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $result = json_decode($output, true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('does not exist', $result['error']['message']);
    }

    public function testExecuteWithDirectory(): void
    {
        $dirPath = __DIR__ . '/../Fixtures';

        $this->commandTester->execute([
            'path' => $dirPath,
        ]);

        $output = $this->commandTester->getDisplay();
        $result = json_decode($output, true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertGreaterThanOrEqual(2, $result['summary']['total_files']);
    }

    public function testJsonOutputIsValid(): void
    {
        $filePath = __DIR__ . '/../Fixtures/CleanCode.php';

        $this->commandTester->execute([
            'path' => $filePath,
        ]);

        $output = $this->commandTester->getDisplay();

        // Output should be valid JSON
        $result = json_decode($output, true);
        $this->assertNotNull($result);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }
}
