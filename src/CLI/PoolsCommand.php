<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../../vendor/autoload.php';

use OpenTimestamps\CLI\PoolLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PoolsCommand extends Command
{
    protected static $defaultName = 'pools';

    protected function configure(): void
    {
        $this
	    ->setName('pools')
            ->setDescription('List configured OpenTimestamps calendar pools')
            ->addOption('pools', null, InputOption::VALUE_OPTIONAL, 'JSON file with default pools', 'default_pools.json');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $poolsFile = $input->getOption('pools');
	$endpoints = PoolLoader::load($poolsFile, $output);

        if (empty($endpoints)) {
            $output->writeln('<comment>No pools configured.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln("<info>Configured pools:</info>");
        foreach ($endpoints as $ep) {
            $output->writeln(" - $ep");
        }

        return Command::SUCCESS;
    }

}
