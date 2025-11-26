<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenTimestamps\Serialization\TimestampFile;
use OpenTimestamps\Ops\OpReturnOp;
use OpenTimestamps\Ops\BitcoinBlockHeaderOp;
use OpenTimestamps\Verification\BitcoinSPVVerifier;

class VerifyCommand extends Command
{
    protected static $defaultName = 'verify';

    protected function configure(): void
    {
        $this
            ->setDescription('Verify one or more .ots timestamp files against files or detached digests')
            ->addArgument('files', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'File paths or detached digests')
            ->addOption('ots', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Paths to corresponding .ots files')
            ->addOption('detached', null, InputOption::VALUE_NONE, 'Use detached timestamp mode')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Verbose output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = $input->getArgument('files');
        $otsFiles = $input->getOption('ots');
        $detached = $input->getOption('detached');
        $verbose = $input->getOption('verbose');

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
                $output->writeln('<error>OTS file not found: ' . $otsFile . '</error>');
                $allPassed = false;
                continue;
            }

            $tsFile = TimestampFile::deserialize(file_get_contents($otsFile));

            $digest = $detached ? hex2bin($file) : hash_file('sha256', $file, true);
            if ($digest === false) {
                $output->writeln('<error>Invalid file or detached digest: ' . $file . '</error>');
                $allPassed = false;
                continue;
            }

            $leafHash = $tsFile->computeLeafHash();
            if ($leafHash !== $digest) {
                $output->writeln('<error>Leaf hash does not match file digest: ' . $file . '</error>');
                $allPassed = false;
                continue;
            }

            if ($verbose) {
                $output->writeln('[Verify] Leaf hash matches.');
            }

            // Bitcoin OP_RETURN and SPV verification
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

            $output->writeln('<info>Timestamp verified for ' . $file . '</info>');
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }
}

