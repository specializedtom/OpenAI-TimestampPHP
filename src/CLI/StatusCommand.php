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
use OpenTimestamps\Ops\Op;
use OpenTimestamps\Ops\TimestampOp;
use OpenTimestamps\Attestation\BitcoinAttestation;

class StatusCommand extends Command
{
    protected static $defaultName = 'status';

    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Monitor the status of a .ots file')
            ->addArgument('ots', InputArgument::REQUIRED, 'Path to .ots file to monitor')
            ->addOption('interval', null, InputOption::VALUE_OPTIONAL, 'Polling interval in seconds', 30)
            ->addOption('pools', null, InputOption::VALUE_OPTIONAL, 'Path to JSON file containing calendar pools');
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

        // Load calendar URLs from JSON (centralized)
        $poolsFile = $input->getOption('pools') ?? null; // will be handled by PoolLoader
        $calendarUrls = PoolLoader::load($output, $poolsFile);

        if (empty($calendarUrls)) {
            $output->writeln('<error>No calendar endpoints found in pools JSON.</error>');
            return Command::FAILURE;
        }

        $output->writeln("Monitoring OTS status for $otsFile");

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
                    $calendar->stamp($tsFile);
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

            [$confirmed, $timeSince, $explorerLink] = $this->getBitcoinInfo($tsFile);

            $output->writeln("+----------+-----------------------------------+----------+-----------+----------------+--------------------------+");
            $output->writeln("| Calendar | URL                               | Stamped? | Leaf Hash | Confirmed?     | Block Link               |");
            $output->writeln("+----------+-----------------------------------+----------+-----------+----------------+--------------------------+");
            foreach ($results as $res) {
                $output->writeln(sprintf(
                    "| %-8s | %-33s | %-8s | %-9s | %-14s | %-24s |",
                    $res['idx'],
                    $res['url'],
                    $res['stamped'] ? '✔' : '❌',
                    $leafHash ? substr($leafHex, 0, 8) . '...' : 'N/A',
                    $confirmed ? $timeSince : 'N/A',
                    $explorerLink ?? 'N/A'
                ));
            }
            $output->writeln("+----------+-----------------------------------+----------+-----------+----------------+--------------------------+");

            sleep($interval);
        }

        return Command::SUCCESS;
    }

    private function getBitcoinInfo(TimestampFile $tsFile): array
    {
        $stack = $tsFile->getOps();
        while (!empty($stack)) {
            $current = array_pop($stack);
            if ($current instanceof TimestampOp) {
                foreach ($current->attestations as $att) {
                    if ($att instanceof BitcoinAttestation) {
                        $timeSince = isset($att->blockTime) ? self::formatTimeDelta(time() - $att->blockTime) : 'N/A';
                        $explorerLink = $att->blockHash ? 'https://mempool.space/block/' . $att->blockHash : null;
                        return [true, $timeSince, $explorerLink];
                    }
                }
            }
            if (property_exists($current, 'ops') && is_array($current->ops)) {
                foreach ($current->ops as $child) {
                    $stack[] = $child;
                }
            }
        }

        return [false, 'N/A', null];
    }

    private static function formatTimeDelta(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf('%02dh %02dm %02ds', $hours, $minutes, $secs);
    }
}
