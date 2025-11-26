<?php
namespace OpenTimestamps\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use OpenTimestamps\CLI\StampCommand;
use OpenTimestamps\CLI\VerifyCommand;
use OpenTimestamps\Verification\BitcoinSPVVerifier;
use OpenTimestamps\Ops\OpReturnOp;
use OpenTimestamps\Ops\BitcoinBlockHeaderOp;
use OpenTimestamps\Serialization\TimestampFile;

class OTSBatchSPVTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        TestHelper::deleteFiles($this->tmpFiles);
    }

    public function testDetachedBatchSPVVerification()
    {
        $app = new Application();
        $app->add(new StampCommand());
        $app->add(new VerifyCommand());

        // 1️⃣ Create multiple test files
        $fileA = TestHelper::createTempFile("File A content");
        $fileB = TestHelper::createTempFile("File B content");
        $this->tmpFiles = [$fileA, $fileB];

        // 2️⃣ Stamp files
        foreach ([$fileA, $fileB] as $file) {
            $command = $app->find('stamp');
            $tester = new CommandTester($command);
            $tester->execute(['file' => $file]);
            $this->assertStringContainsString('.ots', $tester->getDisplay());
            $this->tmpFiles[] = $file . '.ots';
        }

        // 3️⃣ Compute detached digest
        $digestA = hash_file('sha256', $fileA);
        $digestB = hash_file('sha256', $fileB);

        // 4️⃣ Prepare mocked SPV client
        $spvVerifier = new BitcoinSPVVerifier();
        $spvVerifierReflection = new \ReflectionClass($spvVerifier);
        $httpProp = $spvVerifierReflection->getProperty('http');
        $httpProp->setAccessible(true);
        $httpProp->setValue($spvVerifier, SPVMock::getMockedClient());

        // 5️⃣ Attach dummy OpReturnOp to timestamp files for SPV test
        foreach ([$fileA.'.ots', $fileB.'.ots'] as $otsFile) {
            $tsFile = TimestampFile::deserialize(file_get_contents($otsFile));
            $tsFile->addOp(new OpReturnOp('mocktxid123456'));
            file_put_contents($otsFile, $tsFile->serialize());
        }

        // 6️⃣ Verify batch of detached digests
        $files = [$digestA, $digestB];
        $otsFiles = [$fileA.'.ots', $fileB.'.ots'];

        foreach ($files as $i => $digest) {
            $command = $app->find('verify');
            $tester = new CommandTester($command);
            $tester->execute([
                'file' => $digest,
                'ots' => $otsFiles[$i],
                '--detached' => true
            ]);
            $this->assertStringContainsString('Timestamp verified successfully', $tester->getDisplay());
        }
    }
}
