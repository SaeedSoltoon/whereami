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

namespace Whereami\Provider;

use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\RequestFactory;
use Whereami\HttpClientFactory;
use Whereami\Provider;

final class GoogleProvider implements Provider
{
    private $endpoint = "https://www.googleapis.com/geolocation/v1/geolocate";
    private $apikey;
    private $httpClient;
    private $requestFactory;

    public function __construct(
        $apikey,
        array $options = null,
        HttpClient $httpClient = null,
        RequestFactory $requestFactory = null
    ) {
        $this->apikey = $apikey;
        $this->options = $options;
        $this->httpClient = $httpClient ?: (new HttpClientFactory)->create();
        $this->requestFactory = $requestFactory ?: MessageFactoryDiscovery::find();
    }

    public function process(array $data = [])
    {
        $endpoint = $this->endpoint();
        $headers = [];
        $body = $this->transform($data);
        $request = $this->requestFactory->createRequest("POST", $endpoint, $headers, $body);
        $response = $this->httpClient->sendRequest($request);
        return $this->parse((string) $response->getBody());
    }

    private function endpoint()
    {
        return $this->endpoint .= "?" . http_build_query(["key" => $this->apikey]);
    }

    private function transform($data)
    {
        /* Anything truthy means enable ip fallback. */
        $json["considerIp"] = !empty($this->options["ip"]);
        $json["wifiAccessPoints"] = array_map(function ($entry) {
            return [
                "ssid" => $entry["name"],
                "macAddress" => $entry["address"],
                "signalStrength" => $entry["signal"],
                "channel" => $entry["channel"],
            ];
        }, $data);

        return json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function parse($json)
    {
        $data = json_decode($json, true);

        return [
            "latitude" => (float) $data["location"]["lat"],
            "longitude" => (float) $data["location"]["lng"],
            "accuracy" => (integer) $data["accuracy"],
        ];
    }
}
