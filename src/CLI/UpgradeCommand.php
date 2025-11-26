<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenTimestamps\Serialization\TimestampFile;
use OpenTimestamps\Calendar\CalendarClient;

class UpgradeCommand extends Command
{
    protected static $defaultName = 'upgrade';

    protected function configure(): void
    {
        $this
            ->setDescription('Upgrade a .ots timestamp file by fetching missing calendar attestations')
            ->addArgument('ots', InputArgument::REQUIRED, 'Path to .ots file to upgrade')
            ->addOption('out', null, InputOption::VALUE_OPTIONAL, 'Output upgraded .ots file path')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Verbose output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $otsFile = $input->getArgument('ots');
        $outFile = $input->getOption('out') ?? $otsFile;
        $verbose = $input->getOption('verbose');

        if (!file_exists($otsFile)) {
            $output->writeln('<error>OTS file not found: ' . $otsFile . '</error>');
            return Command::FAILURE;
        }

        $tsFile = TimestampFile::deserialize(file_get_contents($otsFile));

        $calendar = new CalendarClient();

        // Upgrade each leaf by fetching missing attestations
        foreach ($tsFile->getOps() as $op) {
            if (method_exists($op, 'needsUpgrade') && $op->needsUpgrade()) {
                $digest = $tsFile->computeLeafHash();
                $calendar->stamp($tsFile); // Fetch missing attestation
                if ($verbose) {
                    $output->writeln("[Upgrade] Fetched attestation for leaf: $digest");
                }
            }
        }

        file_put_contents($outFile, $tsFile->serialize());
        $output->writeln('<info>Upgraded timestamp file saved as ' . $outFile . '</info>');

        return Command::SUCCESS;
    }
}
