<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Op\OpReturnOp;
use OpenTimestamps\Op\BitcoinBlockHeaderOp;
use OpenTimestamps\Verification\BitcoinSPVVerifier;
use OpenTimestamps\Exception\SerializationException;

class VerifyCommand extends Command
{
    protected static $defaultName = 'verify';

    protected function configure(): void
    {
        $this
            ->setName('verify')
            ->setDescription('Verify one or more .ots timestamp files against files or detached digests')
            ->addArgument('files', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'File paths or detached digests (hex)')
            ->addOption('ots', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Paths to corresponding .ots files')
            ->addOption('detached', null, InputOption::VALUE_NONE, 'Use detached timestamp mode');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = $input->getArgument('files');
        $otsFiles = $input->getOption('ots');
        $detached = $input->getOption('detached');
        $verbose = $output->isVerbose();

        if (count($files) !== count($otsFiles)) {
            $output->writeln('<error>The number of files and .ots files must match.</error>');
            return Command::FAILURE;
        }

        $spvVerifier = new BitcoinSPVVerifier();
        $allPassed = true;

        foreach ($files as $i => $file) {
            $otsFile = $otsFiles[$i];
            $output->writeln("[Verify] Processing $file with $otsFile");

            if (!file_exists($otsFile)) {
                $output->writeln("<error>OTS file not found: $otsFile</error>");
                $allPassed = false;
                continue;
            }

            try {
                $tsFile = TimestampFile::deserialize(file_get_contents($otsFile));
            } catch (SerializationException $e) {
                $output->writeln('<error>Failed to parse OTS file: ' . $e->getMessage() . '</error>');
                $allPassed = false;
                continue;
            }

            // Compute digest
            if ($detached) {
                $digest = hex2bin($file);
                if ($digest === false || strlen($digest) !== 32) {
                    $output->writeln('<error>Invalid hex digest (must be 32 bytes) for detached timestamp: ' . $file . '</error>');
                    $allPassed = false;
                    continue;
                }
            } else {
                if (!file_exists($file)) {
                    $output->writeln("<error>File not found: $file</error>");
                    $allPassed = false;
                    continue;
                }
                $digest = hash_file('sha256', $file, true);
            }

            try {
                $leafHash = $tsFile->computeRoot($digest);
                $merkleRoot = $tsFile->getMerkleRoot($digest);
            } catch (\Exception $e) {
                $output->writeln('<error>Error computing root: ' . $e->getMessage() . '</error>');
                $allPassed = false;
                continue;
            }

            $output->writeln('<info>Leaf hash: ' . bin2hex($leafHash) . '</info>');
            $output->writeln('<info>Merkle root: ' . bin2hex($merkleRoot) . '</info>');

            // Bitcoin SPV verification (if OP_RETURN present)
            $opReturn = null;
            $blockOp = null;

            foreach ($tsFile->getOps() as $op) {
                if ($op instanceof OpReturnOp) $opReturn = $op;
                if ($op instanceof BitcoinBlockHeaderOp) $blockOp = $op;
            }

            if ($opReturn) {
                try {
                    $isValid = $spvVerifier->verify($opReturn->getTxId(), $opReturn, $blockOp ?? null);
                    if ($isValid) {
                        $output->writeln('<info>Bitcoin SPV attestation verified!</info>');
                    } else {
                        $output->writeln('<error>Bitcoin SPV verification failed!</error>');
                        $allPassed = false;
                    }
                } catch (\Exception $e) {
                    $output->writeln('<error>SPV verification error: ' . $e->getMessage() . '</error>');
                    $allPassed = false;
                }
            } else {
                $output->writeln('<comment>No Bitcoin attestation found.</comment>');
            }

            $output->writeln("<info>Timestamp verified for $file</info>");
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }
}

