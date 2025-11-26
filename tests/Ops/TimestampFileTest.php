<?php

use PHPUnit\Framework\TestCase;
use OpenTimestamps\Ops\SHA256Op;
use OpenTimestamps\Ops\AppendOp;
use OpenTimestamps\Ops\CalendarCommitOp;
use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Serialization\Merkle\TreeBuilder;

final class TimestampFileTest extends TestCase {

    public function testSerializeDeserialize(): void {
        $ops = [new SHA256Op(), new AppendOp('abc')];
        $tsFile = new TimestampFile($ops);

        $serialized = $tsFile->serialize();
        $deserialized = TimestampFile::deserialize($serialized);

        $this->assertCount(2, $deserialized->getOps());
        $this->assertEquals($tsFile->computeRoot('input'), $deserialized->computeRoot('input'));
    }

    public function testComputeRoot(): void {
        $ops = [new SHA256Op(), new AppendOp('abc')];
        $tsFile = new TimestampFile($ops);

        $input = 'hello';
        $expected = hash('sha256', $input, true);
        $expected = $expected . 'abc';
        $this->assertEquals($expected, $tsFile->computeRoot($input));
    }

    public function testBuildMerkleTree(): void {
        $ops = [new SHA256Op(), new CalendarCommitOp(hex2bin('deadbeef'))];
        $tsFile = new TimestampFile($ops);

        $input = 'hello';
        $tree = $tsFile->buildMerkleTree($input);
        $root = $tree->getRoot();

        $this->assertIsString($root);
        $this->assertNotEmpty($root);

        // Check proof for first leaf
        $proof = $tree->getProof(0);
        $this->assertIsArray($proof);
        $this->assertNotEmpty($proof);
    }
}
