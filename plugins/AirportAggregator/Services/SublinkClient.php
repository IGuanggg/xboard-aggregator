<?php

namespace Plugin\AirportAggregator\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class SublinkClient
{
    private const FORWARDED_HEADERS = [
        'content-type',
        'content-disposition',
        'profile-update-interval',
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 15,
        private readonly int $cacheSeconds = 30,
        private readonly bool $verifyTls = true,
    ) {
    }

    public function subscription(string $shareToken, string $client, string $userAgent = ''): SublinkResponse
    {
        $baseUrl = $this->validatedBaseUrl();
        $cacheKey = 'airport_aggregator:' . hash('sha256', $baseUrl . '|' . $shareToken . '|' . $client);
        $fetch = fn (): array => $this->fetch($baseUrl, $shareToken, $client, $userAgent);

        $payload = $this->cacheSeconds > 0
            ? Cache::remember($cacheKey, $this->cacheSeconds, $fetch)
            : $fetch();

        return new SublinkResponse(
            body: (string) $payload['body'],
            status: (int) $payload['status'],
            headers: (array) $payload['headers'],
        );
    }

    private function fetch(string $baseUrl, string $shareToken, string $client, string $userAgent): array
    {
        $response = Http::timeout(max(1, min(60, $this->timeoutSeconds)))
            ->withOptions([
                'verify' => $this->verifyTls,
                'allow_redirects' => ['max' => 2, 'strict' => true],
            ])
            ->withHeaders([
                'Accept' => '*/*',
                'User-Agent' => $userAgent !== '' ? $userAgent : 'Xboard-AirportAggregator/0.1',
            ])
            ->get($baseUrl . '/c/', [
                'token' => $shareToken,
                'client' => $client,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException("SublinkPro returned HTTP {$response->status()}");
        }

        return [
            'body' => $response->body(),
            'status' => $response->status(),
            'headers' => $this->selectHeaders($response),
        ];
    }

    private function validatedBaseUrl(): string
    {
        $baseUrl = rtrim(trim($this->baseUrl), '/');
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException('SublinkPro 地址必须是有效的 HTTP 或 HTTPS URL');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('SublinkPro 地址不能包含凭据、查询参数或片段');
        }

        return $baseUrl;
    }

    private function selectHeaders(Response $response): array
    {
        $headers = [];
        foreach (self::FORWARDED_HEADERS as $name) {
            $value = $response->header($name);
            if (is_string($value) && $value !== '') {
                $headers[$name] = $value;
            }
        }

        $headers['content-type'] ??= 'text/plain; charset=utf-8';
        return $headers;
    }
}
