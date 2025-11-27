<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Calendar\CalendarClient;
use OpenTimestamps\CLI\PoolLoader;
use OpenTimestamps\Exception\SerializationException;

class SubmitCommand extends Command
{
    protected static $defaultName = 'submit';

    protected function configure(): void
    {
        $this
            ->setName('submit')
            ->setDescription('Submit a .ots file to configured OpenTimestamps calendars')
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to the .ots file (positional)')
            ->addOption('ots', null, InputOption::VALUE_REQUIRED, 'Path to the .ots file (alternative to positional argument)')
            ->addOption('pools', null, InputOption::VALUE_OPTIONAL, 'JSON file with calendar pools', 'default_pools.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Accept either positional "file" or --ots option (option overrides positional)
        $otsPath = $input->getOption('ots') ?? $input->getArgument('file');
        if (!$otsPath) {
            $output->writeln('<error>No OTS file provided.</error>');
            return Command::FAILURE;
        }

        $poolsFile = $input->getOption('pools') ?? 'default_pools.json';
        $verbose = $output->isVerbose();

        if (!file_exists($otsPath)) {
            $output->writeln("<error>OTS file not found: $otsPath</error>");
            return Command::FAILURE;
        }

        try {
            // Read and parse .ots
            $raw = file_get_contents($otsPath);
            $tsFile = TimestampFile::deserialize($raw);

            // Determine the 32-byte digest to submit:
            // - If initialDigest is present in .ots, use it.
            // - Otherwise compute the leaf (ops should produce a 32-byte digest).
            $initial = $tsFile->getInitialDigest();
            if ($initial === null) {
                // Try to compute leaf hash from ops (e.g. sha256-file op)
                $leaf = $tsFile->computeLeafHash(); // may throw if not 32 bytes
                // store it in the TimestampFile so serialization persists it
                $tsFile->setInitialDigest($leaf);
                $initial = $leaf;
            }

            // Load endpoints from pools JSON
            $endpoints = PoolLoader::load($poolsFile, $output);
            if (empty($endpoints)) {
                $output->writeln("<error>No calendar pools found in $poolsFile</error>");
                return Command::FAILURE;
            }

            $calendar = new CalendarClient($endpoints, 10.0, $verbose);

            // Submit — CalendarClient expects the TimestampFile and will POST the digest
            $tsFile = $calendar->stamp($tsFile, $initial);

            // Write updated .ots (calendar commits will be appended)
            file_put_contents($otsPath, $tsFile->serialize());

            $output->writeln("<info>Submitted and updated .ots file: $otsPath</info>");
            if ($verbose) {
                $output->writeln('[SubmitCommand] Operation completed successfully.');
            }

            return Command::SUCCESS;

        } catch (SerializationException $e) {
            $output->writeln('<error>Failed to process OTS file: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>Unexpected error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
