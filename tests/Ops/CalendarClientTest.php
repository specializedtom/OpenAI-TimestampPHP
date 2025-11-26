<?php

use PHPUnit\Framework\TestCase;
use OpenTimestamps\Calendar\CalendarClient;
use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Ops\SHA256Op;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

final class CalendarClientTest extends TestCase {

    public function testStampAddsCalendarCommitOp(): void {
        $mock = new MockHandler([
            new Response(200, [], hex2bin('deadbeef'))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $calendarClient = new CalendarClient('https://mockedserver.example');

        // Inject the mocked HTTP client
        $reflection = new \ReflectionClass($calendarClient);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($calendarClient, $client);

        $tsFile = new TimestampFile([new SHA256Op()]);
        $this->assertCount(1, $tsFile->getOps());

        $updatedTsFile = $calendarClient->stamp($tsFile);
        $ops = $updatedTsFile->getOps();

        $this->assertCount(2, $ops);
        $this->assertInstanceOf(\OpenTimestamps\Ops\CalendarCommitOp::class, $ops[1]);
        $this->assertEquals(hex2bin('deadbeef'), $ops[1]->serialize());
    }
}
