<?php

namespace OpenTimestamps\CLI;

use Symfony\Component\Console\Output\OutputInterface;

class PoolLoader
{
    /**
     * Load pool endpoints from JSON file.
     *
     * @param string $jsonFile
     * @param OutputInterface $output
     * @return string[]
     */
    public static function load(string $jsonFile, OutputInterface $output): array
    {
        if (!file_exists($jsonFile)) {
            $output->writeln("<error>Pools file not found: $jsonFile</error>");
            return [];
        }

        $data = json_decode(file_get_contents($jsonFile), true);
        if (!isset($data['default_pools']) || !is_array($data['default_pools'])) {
            $output->writeln("<error>No default_pools array in JSON</error>");
            return [];
        }

        return array_map(fn($url) => rtrim($url, '/'), $data['default_pools']);
    }
}
