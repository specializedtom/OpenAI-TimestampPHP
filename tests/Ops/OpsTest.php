<?php

use PHPUnit\Framework\TestCase;
use OpenTimestamps\Ops\SHA256Op;
use OpenTimestamps\Ops\SHA1Op;
use OpenTimestamps\Ops\RIPEMD160Op;
use OpenTimestamps\Ops\AppendOp;
use OpenTimestamps\Ops\PrependOp;
use OpenTimestamps\Ops\CalendarCommitOp;

final class OpsTest extends TestCase {

    public function testSHA256Op(): void {
        $op = new SHA256Op();
        $input = 'hello world';
        $hash = $op->apply($input);
        $this->assertEquals(hash('sha256', $input, true), $hash);

        $serialized = $op->serialize();
        $deserialized = SHA256Op::fromData($serialized);
        $this->assertEquals($op->apply($input), $deserialized->apply($input));
    }

    public function testSHA1Op(): void {
        $op = new SHA1Op();
        $input = 'hello world';
        $hash = $op->apply($input);
        $this->assertEquals(hash('sha1', $input, true), $hash);

        $serialized = $op->serialize();
        $deserialized = SHA1Op::fromData($serialized);
        $this->assertEquals($op->apply($input), $deserialized->apply($input));
    }

    public function testRIPEMD160Op(): void {
        $op = new RIPEMD160Op();
        $input = 'hello world';
        $hash = $op->apply($input);
        $this->assertEquals(hash('ripemd160', $input, true), $hash);

        $serialized = $op->serialize();
        $deserialized = RIPEMD160Op::fromData($serialized);
        $this->assertEquals($op->apply($input), $deserialized->apply($input));
    }

    public function testAppendOp(): void {
        $data = 'abc';
        $op = new AppendOp($data);
        $input = '123';
        $this->assertEquals('123abc', $op->apply($input));

        $serialized = $op->serialize();
        $deserialized = AppendOp::fromData($serialized);
        $this->assertEquals($op->apply($input), $deserialized->apply($input));
    }

    public function testPrependOp(): void {
        $data = 'abc';
        $op = new PrependOp($data);
        $input = '123';
        $this->assertEquals('abc123', $op->apply($input));

        $serialized = $op->serialize();
        $deserialized = PrependOp::fromData($serialized);
        $this->assertEquals($op->apply($input), $deserialized->apply($input));
    }

    public function testCalendarCommitOp(): void {
        $attestation = hex2bin('deadbeef');
        $op = new CalendarCommitOp($attestation);
        $input = 'input';
        $this->assertEquals($input, $op->apply($input));

        $serialized = $op->serialize();
        $deserialized = CalendarCommitOp::fromData($serialized);
        $this->assertEquals($op->apply($input), $deserialized->apply($input));
    }
}
