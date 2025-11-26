<?php

use PHPUnit\Framework\TestCase;
use OpenTimestamps\Ops\{
    OpReturnOp,
    BitcoinBlockHeaderOp,
    OpFactory
};
use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;
use OpenTimestamps\Exception\SerializationException;

final class BitcoinOpsTest extends TestCase
{
    /** --------------------------------------------------------------
     *  OP_RETURN ROUND-TRIP
     * --------------------------------------------------------------*/
    public function test_opreturn_roundtrip()
    {
        $commitment = random_bytes(32);
        $op = new OpReturnOp($commitment);

        $writer = new BinaryWriter();
        $op->serialize($writer);
        $serialized = $writer->getData();

        // Deserialize via OpFactory
        $op2 = OpFactory::deserialize(new BinaryReader($serialized));

        $this->assertInstanceOf(OpReturnOp::class, $op2);
        $this->assertSame($commitment, $op2->getCommitment());

        // Round-trip check
        $writer2 = new BinaryWriter();
        $op2->serialize($writer2);
        $this->assertSame(bin2hex($serialized), bin2hex($writer2->getData()));
    }

    /** --------------------------------------------------------------
     *  OP_RETURN invalid length throws
     * --------------------------------------------------------------*/
    public function test_opreturn_invalid_length()
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpReturnOp(random_bytes(31)); // must be 32
    }

    /** --------------------------------------------------------------
     *  BLOCK HEADER ROUND-TRIP
     * --------------------------------------------------------------*/
    public function test_blockheader_roundtrip()
    {
        $header = random_bytes(80);
        $txHash = random_bytes(32);

        $op = new BitcoinBlockHeaderOp($header, $txHash);

        $writer = new BinaryWriter();
        $op->serialize($writer);
        $serialized = $writer->getData();

        // Deserialize via OpFactory
        $op2 = OpFactory::deserialize(new BinaryReader($serialized));

        $this->assertInstanceOf(BitcoinBlockHeaderOp::class, $op2);
        $this->assertSame($header, $op2->getBlockHeader());
        $this->assertSame($txHash, $op2->getTxHash());

        // Round-trip check
        $writer2 = new BinaryWriter();
        $op2->serialize($writer2);
        $this->assertSame(bin2hex($serialized), bin2hex($writer2->getData()));
    }

    /** --------------------------------------------------------------
     *  BLOCK HEADER invalid lengths
     * --------------------------------------------------------------*/
    public function test_blockheader_invalid_lengths()
    {
        // Invalid header
        $this->expectException(\InvalidArgumentException::class);
        new BitcoinBlockHeaderOp(random_bytes(79), random_bytes(32));

        // Invalid txHash
        $this->expectException(\InvalidArgumentException::class);
        new BitcoinBlockHeaderOp(random_bytes(80), random_bytes(31));
    }

    /** --------------------------------------------------------------
     *  ROUND-TRIP VIA TimestampFile
     * --------------------------------------------------------------*/
    public function test_bitcoin_ops_in_timestampfile()
    {
        $ops = [
            new OpReturnOp(random_bytes(32)),
            new BitcoinBlockHeaderOp(random_bytes(80), random_bytes(32)),
        ];

        $ts = new \OpenTimestamps\TimestampFile\TimestampFile($ops);

        $bytes = $ts->serialize();
        $ts2 = \OpenTimestamps\TimestampFile\TimestampFile::deserialize($bytes);
        $bytes2 = $ts2->serialize();

        $this->assertSame(bin2hex($bytes), bin2hex($bytes2));

        $ops2 = $ts2->getOps();
        $this->assertInstanceOf(OpReturnOp::class, $ops2[0]);
        $this->assertInstanceOf(BitcoinBlockHeaderOp::class, $ops2[1]);
    }
}
