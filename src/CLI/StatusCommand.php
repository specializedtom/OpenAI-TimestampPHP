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

class StatusCommand extends Command
{
    protected static $defaultName = 'status';

    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Monitor the status of a .ots file')
            ->addArgument('ots', InputArgument::REQUIRED, 'Path to .ots file to monitor')
            ->addOption('interval', null, InputOption::VALUE_OPTIONAL, 'Polling interval in seconds', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $otsFile = $input->getArgument('ots');
        $interval = (int)$input->getOption('interval');
        $verbose = $output->isVerbose();

        if (!file_exists($otsFile)) {
            $output->writeln('<error>OTS file not found: ' . $otsFile . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("Monitoring OTS status for $otsFile");

        $calendarUrls = [
            'https://a.pool.opentimestamps.org',
            'https://b.pool.opentimestamps.org',
            'https://a.pool.eternitywall.com',
            'https://ots.btc.catallaxy.com',
        ];

        while (true) {
            $data = file_get_contents($otsFile);
            try {
                $tsFile = TimestampFile::deserialize($data);
            } catch (\Exception $e) {
                $output->writeln('<error>Failed to read OTS file: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            try {
                $leafHash = $tsFile->computeLeafHash();
                $leafHex = bin2hex($leafHash);
            } catch (\OpenTimestamps\Exception\SerializationException $e) {
                $leafHash = null;
                $leafHex = 'N/A';
                if ($verbose) {
                    $output->writeln("[Status] Leaf hash not yet computable: " . $e->getMessage());
                }
            }

            $results = [];
            foreach ($calendarUrls as $idx => $url) {
                $stamped = false;
                try {
                    $calendar = new CalendarClient($url);
                    $calendar->stamp($tsFile); // Attempts to fetch attestation
                    $stamped = true;
                } catch (\Exception $e) {
                    if ($verbose) {
                        $output->writeln("[Calendar $idx] $url not yet stamped: " . $e->getMessage());
                    }
                }
                $results[] = [
                    'idx' => $idx,
                    'url' => $url,
                    'stamped' => $stamped,
                ];
            }

            $output->writeln("+----------+-----------------------------------+----------+-----------+");
            $output->writeln("| Calendar | URL                               | Stamped? | Leaf Hash |");
            $output->writeln("+----------+-----------------------------------+----------+-----------+");
            foreach ($results as $res) {
                $output->writeln(sprintf(
                    "| %-8s | %-33s | %-8s | %-9s |",
                    $res['idx'],
                    $res['url'],
                    $res['stamped'] ? '✔' : '❌',
                    $leafHash ? substr($leafHex, 0, 8) . '...' : 'N/A'
                ));
            }
            $output->writeln("+----------+-----------------------------------+----------+-----------+");

            sleep($interval);
        }

        return Command::SUCCESS;

    }
}
