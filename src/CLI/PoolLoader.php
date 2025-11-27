<?php

namespace OpenTimestamps\CLI;

use Symfony\Component\Console\Output\OutputInterface;

class PoolLoader
{
    /**
     * Load pool endpoints from JSON file.
     *
     * @param OutputInterface $output
     * @param string|null $jsonFile Optional path to JSON file
     * @return string[] List of calendar URLs
     */
    public static function load(OutputInterface $output, ?string $jsonFile = null): array
    {
        if (!$jsonFile) {
            // Determine project root (assuming composer.json is in root)
            $root = dirname(__DIR__, 2);

            $composerJsonPath = $root . '/composer.json';
            if (!file_exists($composerJsonPath)) {
                $output->writeln("<error>composer.json not found at project root: $root</error>");
                return [];
            }

            $composerJson = json_decode(file_get_contents($composerJsonPath), true);
            $jsonFile = $composerJson['extra']['ots']['pools-file'] ?? ($root . '/default_pools.json');
        }

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
