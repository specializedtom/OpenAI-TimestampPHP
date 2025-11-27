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
use OpenTimestamps\Exception\SerializationException;

class UpgradeCommand extends Command
{
    protected static $defaultName = 'upgrade';

    protected function configure(): void
    {
        $this
            ->setName('upgrade')
            ->setDescription('Upgrade a .ots timestamp file by fetching missing calendar attestations')
            ->addArgument('ots', InputArgument::REQUIRED, 'Path to .ots file to upgrade')
            ->addOption('out', null, InputOption::VALUE_OPTIONAL, 'Output upgraded .ots file path')
            ->addOption('pools', null, InputOption::VALUE_OPTIONAL, 'Path to JSON file containing calendar pools');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $otsFile = $input->getArgument('ots');
        $outFile = $input->getOption('out') ?? $otsFile;

        if (!file_exists($otsFile)) {
            $output->writeln('<error>OTS file not found: ' . $otsFile . '</error>');
            return Command::FAILURE;
        }

        // Load calendar endpoints from JSON (Composer extra or CLI)
        $poolsFile = $input->getOption('pools') ?? null;
        $calendarUrls = PoolLoader::load($output, $poolsFile);

        $calendar = new CalendarClient($calendarUrls);

        try {
            $tsFile = TimestampFile::deserialize(file_get_contents($otsFile));
        } catch (SerializationException $e) {
            $output->writeln('<error>Failed to deserialize OTS file: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        try {
            $tsFile = $calendar->stamp($tsFile); // fetch missing attestations
        } catch (\Exception $e) {
            $output->writeln('<comment>Warning: unable to fetch attestations: ' . $e->getMessage() . '</comment>');
        }

        file_put_contents($outFile, $tsFile->serialize());
        $output->writeln('<info>Upgraded timestamp file saved as ' . $outFile . '</info>');

        return Command::SUCCESS;
    }
}
