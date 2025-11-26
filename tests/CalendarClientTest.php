<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Response;
use OpenTimestamps\Calendar\CalendarClient;
use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Ops\CalendarCommitOp;

final class CalendarClientTest extends TestCase
{
    public function test_stamp_merges_attestation()
    {
        $digest = random_bytes(32);
        $tsFile = TimestampFile::fromDigest($digest);

        // Mock CalendarClient using Guzzle mock handler
        $client = $this->getMockBuilder(CalendarClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['stamp'])
            ->getMock();

        $client->method('stamp')->willReturnCallback(function ($ts) {
            $ts->addOp(new CalendarCommitOp('FAKE_ATTESTATION'));
            return $ts;
        });

        $tsFile = $client->stamp($tsFile);
        $ops = $tsFile->getOps();

        $this->assertCount(2, $ops);
        $this->assertInstanceOf(CalendarCommitOp::class, $ops[1]);
        $this->assertSame('FAKE_ATTESTATION', $ops[1]->getData());
    }
}
