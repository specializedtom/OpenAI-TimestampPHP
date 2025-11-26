<?php
namespace OpenTimestamps\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class SPVMock
{
    public static function getMockedClient(): Client
    {
        $mock = new MockHandler([
            // Mock transaction response (confirmed)
            new Response(200, [], json_encode([
                'status' => ['block_hash' => '0000000000000000000mockblockhash']
            ])),
            // Mock block header response (80-byte header)
            new Response(200, [], str_repeat('00', 80))
        ]);

        $handler = HandlerStack::create($mock);
        return new Client(['handler' => $handler, 'base_uri' => 'https://mockapi/']);
    }
}
