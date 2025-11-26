<?php

use PHPUnit\Framework\TestCase;
use OpenTimestamps\Ops\OpReturnOp;
use OpenTimestamps\Ops\BitcoinBlockHeaderOp;
use OpenTimestamps\Verification\BitcoinVerifier;
use OpenTimestamps\Exception\SerializationException;

final class BitcoinVerifierTest extends TestCase
{
    /**
     * Simulate a simple Bitcoin Merkle tree with one transaction (no siblings)
     */
    public function test_verify_success_single_tx()
    {
        $leafHash = random_bytes(32); // leaf digest

        // OP_RETURN commitment = SHA256(leafHash)
        $commitment = hash('sha256', $leafHash, true);
        $opReturn = new OpReturnOp($commitment);

        // Simulated block header with Merkle root = commitment
        $blockHeader = str_repeat("\0", 36) . $commitment . str_repeat("\0", 12); // 80 bytes total
        $blockOp = new BitcoinBlockHeaderOp($blockHeader);

        $result = BitcoinVerifier::verifyCommitment($leafHash, $opReturn, $blockOp, []);
        $this->assertTrue($result);
    }

    /**
     * Fail verification if leaf hash does not match OP_RETURN commitment
     */
    public function test_verify_fail_commitment_mismatch()
    {
        $leafHash = random_bytes(32);
        $wrongLeaf = random_bytes(32);

        $commitment = hash('sha256', $wrongLeaf, true);
        $opReturn = new OpReturnOp($commitment);

        $blockHeader = str_repeat("\0", 36) . $commitment . str_repeat("\0", 12);
        $blockOp = new BitcoinBlockHeaderOp($blockHeader);

        $result = BitcoinVerifier::verifyCommitment($leafHash, $opReturn, $blockOp, []);
        $this->assertFalse($result);
    }

    /**
     * Fail verification if Merkle root in block header does not match computed root
     */
    public function test_verify_fail_merkle_root_mismatch()
    {
        $leafHash = random_bytes(32);
        $commitment = hash('sha256', $leafHash, true);
        $opReturn = new OpReturnOp($commitment);

        // Merkle root in header is different
        $wrongMerkleRoot = random_bytes(32);
        $blockHeader = str_repeat("\0", 36) . $wrongMerkleRoot . str_repeat("\0", 12);
        $blockOp = new BitcoinBlockHeaderOp($blockHeader);

        $result = BitcoinVerifier::verifyCommitment($leafHash, $opReturn, $blockOp, []);
        $this->assertFalse($result);
    }

    /**
     * Verify with a Merkle proof of multiple siblings
     */
    public function test_verify_with_merkle_proof()
    {
        $leafHash = random_bytes(32);
        $commitment = hash('sha256', $leafHash, true);
        $opReturn = new OpReturnOp($commitment);

        // Simulated Merkle proof: 2 sibling hashes
        $sibling1 = random_bytes(32);
        $sibling2 = random_bytes(32);

        // Compute expected Merkle root manually
        $computedRoot = hash('sha256', $commitment . $sibling1, true);
        $computedRoot = hash('sha256', $computedRoot . $sibling2, true);

        // Place computed root in block header
        $blockHeader = str_repeat("\0", 36) . $computedRoot . str_repeat("\0", 12);
        $blockOp = new BitcoinBlockHeaderOp($blockHeader);

        $result = BitcoinVerifier::verifyCommitment(
            $leafHash,
            $opReturn,
            $blockOp,
            [$sibling1, $sibling2]
        );
        $this->assertTrue($result);
    }

    /**
     * Invalid block header length should throw
     */
    public function test_verify_invalid_block_header_length()
    {
        $leafHash = random_bytes(32);
        $commitment = hash('sha256', $leafHash, true);
        $opReturn = new OpReturnOp($commitment);

        $this->expectException(SerializationException::class);
        $blockOp = new BitcoinBlockHeaderOp(random_bytes(79)); // invalid
        BitcoinVerifier::verifyCommitment($leafHash, $opReturn, $blockOp, []);
    }
}
