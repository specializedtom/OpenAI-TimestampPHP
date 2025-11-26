<?php

namespace OpenTimestamps\Calendar;

use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Ops\CalendarCommitOp;
use OpenTimestamps\Exception\SerializationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class CalendarClient
{
    /** @var string[] */
    private array $endpoints;

    private float $timeout;
    private bool $verbose;

    public function __construct(
        array|string $endpoints = 'https://a.pool.opentimestamps.org',
        float $timeout = 10.0,
        bool $verbose = false
    ) {
        $this->endpoints = is_array($endpoints) ? array_map(fn($e) => rtrim($e, '/'), $endpoints) : [rtrim($endpoints, '/')];
        $this->timeout = $timeout;
        $this->verbose = $verbose;
    }

    /**
     * Stamp a TimestampFile: send leaf hashes to calendar and merge returned attestation.
     */
    public function stamp(TimestampFile $tsFile, string $fileDigest = ''): TimestampFile
    {
        $digest = $this->computeDigest($tsFile, $fileDigest);

        $lastException = null;
        foreach ($this->endpoints as $endpoint) {
            $client = new Client(['base_uri' => $endpoint, 'timeout' => $this->timeout]);
            try {
                if ($this->verbose) {
                    echo "[CalendarClient] Stamping via $endpoint...\n";
                }

                $response = $client->post('/add', [
                    'body' => $digest,
                    'headers' => ['Content-Type' => 'application/octet-stream'],
                ]);

                $attestationData = (string) $response->getBody();
                $this->mergeAttestation($tsFile, $attestationData);

                if ($this->verbose) {
                    echo "[CalendarClient] Stamp successful.\n";
                }

                return $tsFile;
            } catch (GuzzleException $e) {
                $lastException = $e;
                if ($this->verbose) {
                    echo "[CalendarClient] Failed to stamp via $endpoint: {$e->getMessage()}\n";
                }
                // Try next endpoint
            }
        }

        throw new SerializationException('Calendar request failed on all endpoints: ' . ($lastException?->getMessage() ?? 'unknown error'));
    }

    /**
     * Compute digest of TimestampFile leaves (Merkle root of all ops applied to file hash)
     */
    private function computeDigest(TimestampFile $tsFile, string $fileDigest = ''): string
    {
        return $tsFile->getMerkleRoot($fileDigest);
    }

    /**
     * Merge attestation data returned from calendar into the TimestampFile.
     */
    private function mergeAttestation(TimestampFile $tsFile, string $attestationData): void
    {
        $tsFile->addOp(new CalendarCommitOp($attestationData));
    }
}
