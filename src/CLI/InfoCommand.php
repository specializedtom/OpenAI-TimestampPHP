<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Exception\SerializationException;

class InfoCommand extends Command
{
    protected static $defaultName = 'info';

    protected function configure(): void
    {
        $this
            ->setName('info')
            ->setDescription('Show info about a .ots file')
            ->addArgument('otsfile', InputArgument::REQUIRED, 'Path to the .ots file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $otsFilePath = $input->getArgument('otsfile');

        if (!file_exists($otsFilePath)) {
            $output->writeln('<error>OTS file not found: ' . $otsFilePath . '</error>');
            return Command::FAILURE;
        }

        try {
            $tsFile = TimestampFile::deserialize(file_get_contents($otsFilePath));
        } catch (SerializationException $e) {
            $output->writeln('<error>Failed to deserialize OTS file: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $ops = $tsFile->getOps();
        $output->writeln('<info>Number of ops: ' . count($ops) . '</info>');

        try {
            $leafHash = $tsFile->computeLeafHash();
            $output->writeln('<info>Leaf hash: ' . bin2hex($leafHash) . '</info>');

            try {
                $merkleRoot = $tsFile->getMerkleRoot($leafHash);
                $output->writeln('<info>Merkle root: ' . bin2hex($merkleRoot) . '</info>');
            } catch (\Exception $e) {
                $output->writeln('<comment>Merkle root not yet available: ' . $e->getMessage() . '</comment>');
            }

        } catch (\Exception $e) {
            $output->writeln('<comment>Leaf hash not yet computable: ' . $e->getMessage() . '</comment>');
        }

        return Command::SUCCESS;
    }
}
