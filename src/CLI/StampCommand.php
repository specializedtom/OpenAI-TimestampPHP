<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestamps\Serialization\TimestampFile;
use OpenTimestamps\Calendar\CalendarClient;
use OpenTimestamps\Ops\SHA256Op;
use OpenTimestamps\Ops\AppendOp;
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
            ->setDescription('Stamp a file or digest to the OTS calendar')
            ->addArgument('file', InputArgument::REQUIRED, 'File path or detached digest (hex)')
            ->addOption('detached', null, InputOption::VALUE_NONE, 'Use detached timestamp mode')
            ->addOption('out', null, InputOption::VALUE_OPTIONAL, 'Output .ots file path')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Verbose output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $detached = $input->getOption('detached');
        $verbose = $input->getOption('verbose');
        $outFile = $input->getOption('out');

        if ($detached) {
            $digest = hex2bin($file);
            if ($digest === false || strlen($digest) !== 32) {
                $output->writeln('<error>Invalid hex digest for detached timestamp.</error>');
                return Command::FAILURE;
            }
            $tsFile = TimestampFile::fromDigest($digest);
        } else {
            if (!file_exists($file)) {
                $output->writeln('<error>File not found: ' . $file . '</error>');
                return Command::FAILURE;
            }
            $data = file_get_contents($file);
            $tsFile = new TimestampFile();
            $tsFile->addOp(new SHA256Op());
            $tsFile->addOp(new AppendOp($data));
        }

        $calendar = new CalendarClient(
            ['https://a.pool.opentimestamps.org', 'https://b.pool.opentimestamps.org'],
            10.0,
            $verbose
        );

        $tsFile = $calendar->stamp($tsFile, $detached ? $digest : '');

        $outputPath = $outFile ?? ($detached ? 'detached.ots' : $file . '.ots');
        file_put_contents($outputPath, $tsFile->serialize());

        $output->writeln('<info>File stamped and saved as ' . $outputPath . '</info>');
        return Command::SUCCESS;
    }
}
