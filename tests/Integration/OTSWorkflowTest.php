<?php
namespace OpenTimestamps\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use OpenTimestamps\CLI\StampCommand;
use OpenTimestamps\CLI\VerifyCommand;
use OpenTimestamps\CLI\MergeCommand;
use OpenTimestamps\CLI\UpgradeCommand;

class OTSWorkflowTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        TestHelper::deleteFiles($this->tmpFiles);
    }

    public function testEndToEndFlow()
    {
        $app = new Application();
        $app->add(new StampCommand());
        $app->add(new VerifyCommand());
        $app->add(new MergeCommand());
        $app->add(new UpgradeCommand());

        // 1️⃣ Create test files
        $file1 = TestHelper::createTempFile("Hello World");
        $file2 = TestHelper::createTempFile("OpenTimestamps PHP");
        $this->tmpFiles = [$file1, $file2];

        // 2️⃣ Stamp files
        foreach ([$file1, $file2] as $file) {
            $command = $app->find('stamp');
            $tester = new CommandTester($command);
            $tester->execute(['file' => $file]);
            $this->assertStringContainsString('.ots', $tester->getDisplay());
            $this->tmpFiles[] = $file . '.ots';
        }

        // 3️⃣ Verify stamped files
        foreach ([[$file1, $file1.'.ots'], [$file2, $file2.'.ots']] as [$file, $ots]) {
            $command = $app->find('verify');
            $tester = new CommandTester($command);
            $tester->execute([
                'file' => $file,
                'ots' => $ots
            ]);
            $this->assertStringContainsString('Timestamp verified successfully', $tester->getDisplay());
        }

        // 4️⃣ Merge .ots files
        $mergedFile = tempnam(sys_get_temp_dir(), 'merged.ots');
        $command = $app->find('merge');
        $tester = new CommandTester($command);
        $tester->execute([
            'otsFiles' => [$file1.'.ots', $file2.'.ots'],
            '--out' => $mergedFile
        ]);
        $this->assertFileExists($mergedFile);
        $this->tmpFiles[] = $mergedFile;

        // 5️⃣ Upgrade merged file
        $upgradedFile = tempnam(sys_get_temp_dir(), 'upgraded.ots');
        $command = $app->find('upgrade');
        $tester = new CommandTester($command);
        $tester->execute([
            'ots' => $mergedFile,
            '--out' => $upgradedFile
        ]);
        $this->assertFileExists($upgradedFile);
        $this->tmpFiles[] = $upgradedFile;

        // 6️⃣ Verify upgraded file
        $command = $app->find('verify');
        $tester = new CommandTester($command);
        $tester->execute([
            'file' => $file1,
            'ots' => $upgradedFile
        ]);
        $this->assertStringContainsString('Timestamp verified successfully', $tester->getDisplay());
    }
}
