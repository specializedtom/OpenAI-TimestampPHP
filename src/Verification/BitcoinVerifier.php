<?php

namespace OpenTimestamps\Verification;

use OpenTimestamps\Ops\{
    OpReturnOp,
    BitcoinBlockHeaderOp
};
use OpenTimestamps\Serialization\Merkle\TreeBuilder;
use OpenTimestamps\Exception\SerializationException;

class BitcoinVerifier
{
    /**
     * Verify that a given leaf hash was committed in the Bitcoin blockchain.
     *
     * @param string $leafHash  The leaf digest from TimestampFile
     * @param OpReturnOp $opReturn  The OP_RETURN commitment
     * @param BitcoinBlockHeaderOp $blockOp  The Bitcoin block header containing the tx
     * @param array<int, string> $merkleProof  Optional: Merkle proof (array of sibling hashes)
     * @return bool True if verification succeeds
     * @throws SerializationException
     */
    public static function verifyCommitment(
        string $leafHash,
        OpReturnOp $opReturn,
        BitcoinBlockHeaderOp $blockOp,
        array $merkleProof = []
    ): bool
    {
        // 1. Check commitment matches leaf hash (SHA256 of leaf)
        $commitmentHash = $opReturn->getCommitment();

        if (hash('sha256', $leafHash, true) !== $commitmentHash) {
            return false;
        }

        // 2. Compute transaction Merkle root from leaf and proof
        $computedRoot = $commitmentHash;

        foreach ($merkleProof as $sibling) {
            // For simplicity, assume left concatenation: hash(left + right)
            $computedRoot = hash('sha256', $computedRoot . $sibling, true);
        }

        // 3. Compare against block header Merkle root
        $blockHeader = $blockOp->getBlockHeader();

        if (strlen($blockHeader) !== 80) {
            throw new SerializationException('Invalid block header length');
        }

        // Bitcoin block header: bytes 36..68 (32 bytes) = Merkle root (big-endian)
        $merkleRootBytes = substr($blockHeader, 36, 32);

        return $computedRoot === $merkleRootBytes;
    }
}
