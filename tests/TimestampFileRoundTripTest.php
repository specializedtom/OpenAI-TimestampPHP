<?php

use PHPUnit\Framework\TestCase;
use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Ops\{
    SHA1Op,
    SHA256Op,
    AppendOp,
    PrependOp,
    CalendarCommitOp
};

final class TimestampFileRoundTripTest extends TestCase
{
    /**
     * Helper: runs serialize → deserialize → serialize and asserts byte identity.
     */
    private function assertRoundTripBinaryIdentical(TimestampFile $ts)
    {
        $bytes1 = $ts->serialize();
        $ts2    = TimestampFile::deserialize($bytes1);
        $bytes2 = $ts2->serialize();

        $this->assertSame(
            bin2hex($bytes1),
            bin2hex($bytes2),
            "Round-trip serialization must be identical"
        );

        return $ts2;
    }

    /** --------------------------------------------------------------
     *  BASIC: Empty TimestampFile
     * --------------------------------------------------------------*/
    public function test_empty_timestampfile_roundtrip()
    {
        $ts = new TimestampFile([]);
        $this->assertRoundTripBinaryIdentical($ts);
    }

    /** --------------------------------------------------------------
     *  INDIVIDUAL OP TESTS
     * --------------------------------------------------------------*/

    public function test_sha1_op_roundtrip()
    {
        $op = new SHA1Op();
        $ts = new TimestampFile([$op]);

        $ts2 = $this->assertRoundTripBinaryIdentical($ts);
        $this->assertInstanceOf(SHA1Op::class, $ts2->getOps()[0]);
    }

    public function test_sha256_op_roundtrip()
    {
        $op = new SHA256Op();
        $ts = new TimestampFile([$op]);

        $ts2 = $this->assertRoundTripBinaryIdentical($ts);
        $this->assertInstanceOf(SHA256Op::class, $ts2->getOps()[0]);
    }

    public function test_append_op_roundtrip()
    {
        $op = new AppendOp("hello world");
        $ts = new TimestampFile([$op]);

        $ts2 = $this->assertRoundTripBinaryIdentical($ts);
        $this->assertSame("hello world", $ts2->getOps()[0]->getData());
    }

    public function test_prepend_op_roundtrip()
    {
        $op = new PrependOp("xyz");
        $ts = new TimestampFile([$op]);

        $ts2 = $this->assertRoundTripBinaryIdentical($ts);
        $this->assertSame("xyz", $ts2->getOps()[0]->getData());
    }

    public function test_calendarcommit_op_roundtrip()
    {
        $hash = random_bytes(32);
        $op = new CalendarCommitOp($hash);

        $ts = new TimestampFile([$op]);

        $ts2 = $this->assertRoundTripBinaryIdentical($ts);
        $this->assertSame($hash, $ts2->getOps()[0]->getHash());
    }

    /** --------------------------------------------------------------
     *  MIXED OPS IN A SEQUENCE
     * --------------------------------------------------------------*/
    public function test_mixed_ops_roundtrip()
    {
        $ts = new TimestampFile([
            new SHA256Op(),
            new PrependOp("abc"),
            new AppendOp("123"),
            new CalendarCommitOp(random_bytes(32)),
            new SHA1Op()
        ]);

        $ts2 = $this->assertRoundTripBinaryIdentical($ts);

        $ops2 = $ts2->getOps();
        $this->assertInstanceOf(SHA256Op::class, $ops2[0]);
        $this->assertInstanceOf(PrependOp::class,   $ops2[1]);
        $this->assertInstanceOf(AppendOp::class,    $ops2[2]);
        $this->assertInstanceOf(CalendarCommitOp::class, $ops2[3]);
        $this->assertInstanceOf(SHA1Op::class,      $ops2[4]);
    }

    /** --------------------------------------------------------------
     *  RANDOMISED FUZZ TESTING
     * --------------------------------------------------------------*/
    public function test_fuzz_roundtrip_random_ops()
    {
        $ops = [];
        $opsAvailable = [
            fn() => new SHA1Op(),
            fn() => new SHA256Op(),
            fn() => new AppendOp(random_bytes(random_int(1, 30))),
            fn() => new PrependOp(random_bytes(random_int(1, 30))),
            fn() => new CalendarCommitOp(random_bytes(32)),
        ];

        for ($i = 0; $i < 20; $i++) {
            $ops[] = $opsAvailable[array_rand($opsAvailable)]();
        }

        $ts = new TimestampFile($ops);

        // The main assertion:
        $this->assertRoundTripBinaryIdentical($ts);
    }

    /** --------------------------------------------------------------
     *  KNOWN-VECTOR TEST HOOK (KEEP EMPTY FOR NOW)
     * --------------------------------------------------------------*/
    public function test_known_js_vector_roundtrip()
    {
        // You can drop real .ots files here later.
        $this->markTestIncomplete("Add reference .ots fixtures from JS implementation.");
    }
}
