<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestamps\Serialization\TimestampFile;
use OpenTimestamps\Calendar\CalendarClient;
use OpenTimestamps\Ops\SHA256Op;
use OpenTimestamps\Ops\AppendOp;
use OpenTimestamps\Ops\PrependOp;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InfoCommand extends Command {
    protected static $defaultName = 'info';

    protected function configure(): void {
        $this->setDescription('Show info about a .ots file')
            ->addArgument('otsfile', InputArgument::REQUIRED, 'Path to the .ots file');
    }

    protected function execute($input, $output): int {
        $otsFilePath = $input->getArgument('otsfile');
        if (!file_exists($otsFilePath)) {
            $output->writeln('<error>OTS file not found</error>');
            return Command::FAILURE;
        }

        $data = file_get_contents($otsFilePath);
        try {
            $tsFile = TimestampFile::deserialize($data);
            $output->writeln('<info>Number of ops: ' . count($tsFile->ops) . '</info>');
            $root = $tsFile->computeMerkleRoot();
            $output->writeln('<info>Merkle root: ' . bin2hex($root) . '</info>');
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to read OTS file: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}