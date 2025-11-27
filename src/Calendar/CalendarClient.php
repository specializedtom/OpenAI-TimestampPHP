<?php

namespace OpenTimestamps\Calendar;

use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Op\CalendarCommitOp;
use OpenTimestamps\Op\AppendOp;
use OpenTimestamps\Exception\SerializationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

class CalendarClient
{
    /** @var string[] */
    private array $endpoints;

    private float $timeout;
    private bool $verbose;

    public function __construct(array|string $endpoints = 'https://a.pool.opentimestamps.org', float $timeout = 10.0, bool $verbose = false)
    {
        $this->endpoints = is_array($endpoints)
            ? array_map(fn($e) => rtrim($e, '/'), $endpoints)
            : [rtrim($endpoints, '/')];

        $this->timeout = $timeout;
        $this->verbose = $verbose;
    }


    /**
     * Stamp a TimestampFile by sending its digest to each endpoint until successful.
     *
     * @param TimestampFile $tsFile
     * @param string $fileDigest 32-byte digest (or empty string to compute)
     * @return TimestampFile
     * @throws SerializationException
     */
    public function stamp(TimestampFile $tsFile, string $fileDigest = ''): TimestampFile
    {
        // Compute digest if not provided
        $digest = $fileDigest ?: $tsFile->getMerkleRoot($tsFile->initialDigest ?? '');
        if (strlen($digest) !== 32) {
            throw new SerializationException('Digest must be 32 bytes');
        }

        $lastException = null;

        foreach ($this->endpoints as $endpoint) {
            $client = new Client(['base_uri' => $endpoint, 'timeout' => $this->timeout]);
            try {
                if ($this->verbose) {
                    echo "[CalendarClient] Trying endpoint: $endpoint\n";
                }

                // POST digest to /digest endpoint
                $response = $client->post('/digest', [
                    'body' => $digest,
                    'headers' => ['Content-Type' => 'application/octet-stream'],
                ]);

                $attestationData = (string)$response->getBody();
                $tsFile->addOp(new CalendarCommitOp($attestationData));

                if ($this->verbose) {
                    echo "[CalendarClient] Stamp successful via $endpoint\n";
                }

                return $tsFile;

            } catch (RequestException | ConnectException $e) {
                $lastException = $e;
                if ($this->verbose) {
                    $msg = $e->getMessage();
                    echo "[CalendarClient] Failed on $endpoint: $msg\n";
                }
            }
        }

        throw new SerializationException(
            'Stamping failed on all endpoints: ' . ($lastException?->getMessage() ?? 'unknown error')
        );
    }


    /**
     * Compute 32-byte SHA256 digest of original data in TimestampFile.
     */
    private function computeDigest(TimestampFile $tsFile): string
    {
        // Get Merkle root
        $merkleRoot = $tsFile->getMerkleRoot('');

        // If it returns hex string, convert to binary
        if (strlen($merkleRoot) === 64 && ctype_xdigit($merkleRoot)) {
            return hex2bin($merkleRoot);
        }

        // If already binary 32 bytes
        if (strlen($merkleRoot) === 32) {
            return $merkleRoot;
        }

        throw new \RuntimeException(
            'Computed digest is invalid length: ' . strlen($merkleRoot)
        );

    }

    /**
     * Merge attestation data into TimestampFile.
     */
    private function mergeAttestation(TimestampFile $tsFile, string $attestationData): void
    {
        $tsFile->addOp(new CalendarCommitOp($attestationData));
    }
}
