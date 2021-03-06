<?php

/*
 * This file is part of whereami package
 *
 * Copyright (c) 2017 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/whereami
 *
 */

namespace Whereami;

use Http\Mock\Client as MockClient;
use PHPUnit\Framework\TestCase;
use Whereami\Exception\NotFoundException;
use Whereami\Factory\HttpClientFactory;
use Whereami\Provider\GoogleProvider;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class GoogleProviderTest extends TestCase
{
    public function testShouldBeTrue()
    {
        $this->assertTrue(true);
    }

    public function testShouldConstruct()
    {
        $provider = new GoogleProvider("fakekey");
        $this->assertInstanceOf(GoogleProvider::class, $provider);
    }

    public function testShouldProcess()
    {
        $stream = new Stream("php://memory", "rb+");
        $stream->write('{"location": {"lat": 1.358496,"lng": 103.98983469999999},"accuracy": 22705.0}');
        $response = new Response($stream);

        $mockClient = new MockCLient;
        $mockClient->addResponse($response);
        $httpClient = (new HttpClientFactory($mockClient))->create();

        $networks = file_get_contents(__DIR__ . "/changi.json");
        $networks = json_decode($networks, true);

        $location = (new GoogleProvider("fakekey", $httpClient))->process($networks);

        $this->assertEquals(1.358496, $location["latitude"]);
        $this->assertEquals(103.98983469999999, $location["longitude"]);
        $this->assertEquals(22705, $location["accuracy"]);
    }

    public function testShouldHaveApplicationJsonHeaders()
    {
        $mockClient = new MockCLient;
        $httpClient = (new HttpClientFactory($mockClient))->create();

        $networks = file_get_contents(__DIR__ . "/changi.json");
        $networks = json_decode($networks, true);

        $location = (new GoogleProvider("fakekey", $httpClient))->process($networks);

        $request = $mockClient->getRequests()[0];

        $this->assertEquals(
            "application/json",
            $request->getHeaderLine("Accept")
        );
        $this->assertEquals(
            "application/json; charset=utf-8",
            $request->getHeaderLine("Content-Type")
        );
    }

    public function testShouldThrowNotFoundException()
    {
        $this->expectException(NotFoundException::class);

        $stream = new Stream("php://memory", "rb+");
        $stream->write('{"error": {"errors": [{"domain": "geolocation","reason": "notFound",');
        $stream->write('"message": "Not Found"}],"code": 404,"message": "Not Found"}}');
        $response = new Response($stream, 404);

        $mockClient = new MockCLient;
        $mockClient->addResponse($response);
        $httpClient = (new HttpClientFactory($mockClient))->create();

        $networks = file_get_contents(__DIR__ . "/changi.json");
        $networks = json_decode($networks, true);

        $location = (new GoogleProvider("fakekey", $httpClient))->process($networks);

        $this->assertEquals(1.358496, $location["latitude"]);
        $this->assertEquals(103.98983469999999, $location["longitude"]);
        $this->assertEquals(22705, $location["accuracy"]);
    }
}
