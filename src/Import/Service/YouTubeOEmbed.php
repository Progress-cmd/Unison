<?php

namespace App\Import\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class YouTubeOEmbed
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function fetchTitle(string $url): ?string
    {
        try {
            $response = $this->httpClient->request(
                'GET', 'https://www.youtube.com/oembed',
                ['query' => ['format' => 'json', 'url' => $url],
                'timeout' => 2,
                'max_duration' => 2]
            );
            return $response->toArray()['title'] ?? null;
        } catch (ExceptionInterface) {
            return null;
        }
    }
}