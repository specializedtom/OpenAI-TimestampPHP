<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../../vendor/autoload.php';

use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Calendar\CalendarClient;
use OpenTimestamps\Exception\SerializationException;
use OpenTimestamps\CLI\PoolLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StampCommand extends Command
{
    protected static $defaultName = 'stamp';

    protected function configure(): void
    {
        $this
            ->setName('stamp')
            ->setDescription('Stamp a file or digest to the OTS calendar')
            ->addArgument('file', InputArgument::REQUIRED, 'File path or detached digest (hex)')
            ->addOption('detached', null, InputOption::VALUE_NONE, 'Use detached timestamp mode')
            ->addOption('out', null, InputOption::VALUE_OPTIONAL, 'Output .ots file path')
            ->addOption('pools', null, InputOption::VALUE_OPTIONAL, 'JSON file with default pools', 'default_pools.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $detached = $input->getOption('detached');
        $outFile = $input->getOption('out');
        $poolsFile = $input->getOption('pools');
        $verbose = $output->isVerbose();

        // Load pool endpoints
        $endpoints = PoolLoader::load($poolsFile, $output);
        if (empty($endpoints)) {
            $output->writeln('<error>No pools available. Cannot stamp.</error>');
            return Command::FAILURE;
        }

        try {
            if ($detached) {
                // Detached digest mode: input is hex
                $digest = hex2bin($file);
                if ($digest === false || strlen($digest) !== 32) {
                    $output->writeln('<error>Invalid hex digest for detached timestamp. Must be 32 bytes (SHA-256).</error>');
                    return Command::FAILURE;
                }
                $tsFile = TimestampFile::fromDigest($digest);
                $digestToSend = $digest;
            } else {
                // Normal file mode
                if (!file_exists($file)) {
                    $output->writeln("<error>File not found: $file</error>");
                    return Command::FAILURE;
                }

                $data = file_get_contents($file);
                $digest = hash('sha256', $data, true); // 32-byte SHA-256 digest
                $tsFile = TimestampFile::fromDigest($digest);
                $digestToSend = $digest;
            }

            // Stamp via calendar(s)
            $calendar = new CalendarClient($endpoints, 10.0, $verbose);
            $tsFile = $calendar->stamp($tsFile, $digestToSend);

            // Save .ots file
            $outputPath = $outFile ?? ($detached ? 'detached.ots' : $file . '.ots');
            file_put_contents($outputPath, $tsFile->serialize());

            $output->writeln("<info>File stamped and saved as $outputPath</info>");
            if ($verbose) {
                $output->writeln('[StampCommand] Operation completed successfully.');
            }

            return Command::SUCCESS;

        } catch (SerializationException $e) {
            $output->writeln('<error>Stamping failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>Unexpected error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
