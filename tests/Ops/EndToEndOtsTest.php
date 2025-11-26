<?php

use PHPUnit\Framework\TestCase;
use OpenTimestamps\Ops\SHA256Op;
use OpenTimestamps\Ops\AppendOp;
use OpenTimestamps\Ops\CalendarCommitOp;
use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Calendar\CalendarClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

final class EndToEndOtsTest extends TestCase {

    public function testFullOtsWorkflow(): void {
        // Step 1: create TimestampFile with some ops
        $ops = [new SHA256Op(), new AppendOp('abc')];
        $tsFile = new TimestampFile($ops);

        $input = 'hello world';
        $expectedLeaf = hash('sha256', $input, true) . 'abc';
        $this->assertEquals($expectedLeaf, $tsFile->computeRoot($input));

        // Step 2: build Merkle tree
        $tree = $tsFile->buildMerkleTree($input);
        $root = $tree->getRoot();
        $this->assertIsString($root);
        $this->assertNotEmpty($root);

        // Step 3: mock CalendarClient submission
        $mock = new MockHandler([
            new Response(200, [], hex2bin('deadbeef'))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $calendarClient = new CalendarClient('https://mockedserver.example');
        $reflection = new \ReflectionClass($calendarClient);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($calendarClient, $client);

        $tsFile = $calendarClient->stamp($tsFile);

        // Step 4: ensure CalendarCommitOp is added
        $opsAfter = $tsFile->getOps();
        $this->assertCount(3, $opsAfter);
        $this->assertInstanceOf(CalendarCommitOp::class, $opsAfter[2]);
        $this->assertEquals(hex2bin('deadbeef'), $opsAfter[2]->serialize());

        // Step 5: serialize and deserialize TimestampFile
        $serialized = $tsFile->serialize();
        $deserialized = TimestampFile::deserialize($serialized);
        $this->assertCount(3, $deserialized->getOps());

        // Step 6: recompute root and verify consistency
        $this->assertEquals($tsFile->computeRoot($input), $deserialized->computeRoot($input));
    }
}
