<?php

namespace OpenTimestamps\Verification;

use OpenTimestamps\Ops\OpReturnOp;
use OpenTimestamps\Ops\BitcoinBlockHeaderOp;
use OpenTimestamps\Exception\VerificationException;
use GuzzleHttp\Client;

class BitcoinSPVVerifier
{
    private Client $http;
    private string $cacheDir;

    public function __construct(string $cacheDir = __DIR__ . '/../../cache/blocks')
    {
        $this->http = new Client(['timeout' => 10.0, 'base_uri' => 'https://blockstream.info/api/']);
        $this->cacheDir = $cacheDir;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Automatically fetch the block header and verify Bitcoin OP_RETURN commitment
     */
    public function verify(string $txid, OpReturnOp $opReturn, ?BitcoinBlockHeaderOp $blockOp = null): bool
    {
        try {
            if (!$blockOp) {
                $blockOp = $this->getBlockHeaderForTx($txid);
            }
        } catch (\Exception $e) {
            throw new VerificationException('Failed to fetch block header: ' . $e->getMessage());
        }

        return BitcoinVerifier::verifyCommitment($opReturn->getLeafHash(), $opReturn, $blockOp, []);
    }

    /**
     * Fetch block header from cache or API
     */
    private function getBlockHeaderForTx(string $txid): BitcoinBlockHeaderOp
    {
        // Fetch transaction details
        $txData = $this->fetchJson("tx/$txid");

        if (!isset($txData['status']['block_hash'])) {
            throw new \RuntimeException('Transaction not yet confirmed on chain.');
        }

        $blockHash = $txData['status']['block_hash'];
        $cachedFile = $this->cacheDir . '/' . $blockHash . '.bin';

        if (file_exists($cachedFile)) {
            $header = file_get_contents($cachedFile);
        } else {
            // Fetch block header (80 bytes)
            $headerHex = trim($this->http->get("block/$blockHash/header")->getBody());
            $header = hex2bin($headerHex);
            if (strlen($header) !== 80) {
                throw new \RuntimeException('Invalid block header length.');
            }
            file_put_contents($cachedFile, $header);
        }

        return new BitcoinBlockHeaderOp($header);
    }

    /**
     * Helper: fetch JSON from API
     */
    private function fetchJson(string $endpoint): array
    {
        $response = $this->http->get($endpoint);
        return json_decode((string)$response->getBody(), true);
    }
}

