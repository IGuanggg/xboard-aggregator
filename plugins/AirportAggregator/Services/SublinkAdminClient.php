<?php

namespace Plugin\AirportAggregator\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SublinkAdminClient
{
    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 15,
        private readonly bool $verifyTls = true,
    ) {
        $this->baseUrl = rtrim($this->validateBaseUrl($baseUrl), '/');
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('请先在插件设置中填写 SublinkPro API Key');
        }
    }

    public function list(array $query): array
    {
        return $this->data($this->request()->get($this->url('/api/v1/airports'), $query));
    }

    public function get(int $id): array
    {
        return $this->data($this->request()->get($this->url("/api/v1/airports/{$id}")));
    }

    public function create(array $payload): void
    {
        $this->data($this->request()->post($this->url('/api/v1/airports'), $payload));
    }

    public function update(int $id, array $payload): void
    {
        $this->data($this->request()->put($this->url("/api/v1/airports/{$id}"), $payload));
    }

    public function delete(int $id, bool $deleteNodes = true): void
    {
        $deleteNodesQuery = $deleteNodes ? 'true' : 'false';
        $this->data($this->request()->delete(
            $this->url("/api/v1/airports/{$id}?deleteNodes={$deleteNodesQuery}")
        ));
    }

    public function pull(int $id): void
    {
        $this->data($this->request()->post($this->url("/api/v1/airports/{$id}/pull")));
    }

    public function pullAll(array $ids = []): array
    {
        return $this->data($this->request()->post(
            $this->url('/api/v1/airports/pull-all'),
            ['ids' => array_values(array_map('intval', $ids))]
        ));
    }

    public function refreshUsage(int $id): array
    {
        return $this->data($this->request()->post(
            $this->url("/api/v1/airports/{$id}/refresh-usage")
        ));
    }

    public function listNodes(array $query = []): array
    {
        return $this->data($this->request()->get($this->url('/api/v1/nodes/get'), $query));
    }

    public function updateNode(array $payload): void
    {
        $this->data($this->request()->asMultipart()->post(
            $this->url('/api/v1/nodes/update'),
            $payload
        ));
    }

    public function deleteNode(int $id): void
    {
        $this->data($this->request()->delete(
            $this->url('/api/v1/nodes/delete'),
            ['id' => $id]
        ));
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['X-API-Key' => trim($this->apiKey)])
            ->timeout(max(1, $this->timeoutSeconds))
            ->withOptions(['verify' => $this->verifyTls]);
    }

    private function data(Response $response): array
    {
        if (!$response->successful()) {
            throw new RuntimeException("SublinkPro 请求失败（HTTP {$response->status()}）");
        }

        $body = $response->json();
        if (!is_array($body) || (int) ($body['code'] ?? 0) !== 200) {
            throw new RuntimeException((string) ($body['msg'] ?? 'SublinkPro 返回了无效数据'));
        }

        $data = $body['data'] ?? [];
        return is_array($data) ? $data : [];
    }

    private function url(string $path): string
    {
        return $this->baseUrl . $path;
    }

    private function validateBaseUrl(string $baseUrl): string
    {
        $parts = parse_url(trim($baseUrl));
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            throw new RuntimeException('SublinkPro 内网地址必须是有效的 HTTP/HTTPS URL');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('SublinkPro 内网地址不能包含账号、查询参数或片段');
        }

        return trim($baseUrl);
    }
}
