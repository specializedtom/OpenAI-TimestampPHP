<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenTimestamps\TimestampFile\TimestampFile;

class MergeCommand extends Command
{
    protected static $defaultName = 'merge';

    protected function configure(): void
    {
        $this
            ->setName('merge')
            ->setDescription('Merge multiple .ots timestamp files into one')
            ->addArgument('otsFiles', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Paths to .ots files to merge')
            ->addOption('out', null, InputOption::VALUE_OPTIONAL, 'Output merged .ots file path', 'merged.ots')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Verbose output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = $input->getArgument('otsFiles');
        $outFile = $input->getOption('out');
        $verbose = $input->getOption('verbose');

        if (empty($files)) {
            $output->writeln('<error>No .ots files provided.</error>');
            return Command::FAILURE;
        }

        $mergedTs = null;

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $output->writeln('<error>OTS file not found: ' . $file . '</error>');
                return Command::FAILURE;
            }

            $ts = TimestampFile::deserialize(file_get_contents($file));
            if (!$mergedTs) {
                $mergedTs = $ts;
            } else {
                foreach ($ts->getOps() as $op) {
                    $mergedTs->addOp($op);
                }
            }

            if ($verbose) {
                $output->writeln("[Merge] Loaded $file");
            }
        }

        file_put_contents($outFile, $mergedTs->serialize());
        $output->writeln('<info>Merged timestamp file saved as ' . $outFile . '</info>');

        return Command::SUCCESS;
    }
}
