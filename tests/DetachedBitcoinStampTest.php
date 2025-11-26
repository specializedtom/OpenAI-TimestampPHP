<?php

use PHPUnit\Framework\TestCase;
use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Calendar\CalendarClient;
use OpenTimestamps\Ops\OpReturnOp;
use OpenTimestamps\Ops\BitcoinBlockHeaderOp;
use OpenTimestamps\Verification\BitcoinVerifier;

final class DetachedBitcoinStampTest extends TestCase
{
    public function test_detached_timestamp_with_bitcoin()
    {
        $digest = random_bytes(32);
        $tsFile = TimestampFile::fromDigest($digest);

        // Mock calendar attestation
        $tsFile->addOp(new \OpenTimestamps\Ops\CalendarCommitOp('FAKE_CAL_ATTESTATION'));

        // Add OP_RETURN
        $leafHash = $tsFile->computeLeafHash();
        $opReturn = new OpReturnOp(hash('sha256', $leafHash, true));
        $tsFile->addOp($opReturn);

        // Simulate block header
        $blockHeader = str_repeat("\0", 36) . hash('sha256', $leafHash, true) . str_repeat("\0", 12);
        $blockOp = new BitcoinBlockHeaderOp($blockHeader);
        $tsFile->addOp($blockOp);

        $isValid = BitcoinVerifier::verifyCommitment($leafHash, $opReturn, $blockOp, []);
        $this->assertTrue($isValid);
    }
}
