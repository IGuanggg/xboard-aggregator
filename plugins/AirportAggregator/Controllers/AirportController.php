<?php

namespace Plugin\AirportAggregator\Controllers;

use App\Http\Controllers\PluginController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugin\AirportAggregator\Services\SublinkAdminClient;
use Throwable;

class AirportController extends PluginController
{
    public function fetch(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query('current', 1));
            $pageSize = min(100, max(1, (int) $request->query('pageSize', 20)));
            $data = $this->client()->list(array_filter([
                'page' => $page,
                'pageSize' => $pageSize,
                'keyword' => trim((string) $request->query('search', '')),
                'group' => trim((string) $request->query('group', '')),
                'enabled' => $request->query('enabled'),
            ], fn ($value) => $value !== '' && $value !== null));

            $items = $data['items'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            $sortField = (string) $request->query('sort_field', '');
            if (in_array($sortField, ['id', 'nodeCount', 'lastRunTime', 'nextRunTime'], true)) {
                $descending = $request->query('sort_order') === 'desc';
                usort($items, function (array $left, array $right) use ($sortField, $descending): int {
                    $result = ($left[$sortField] ?? null) <=> ($right[$sortField] ?? null);
                    return $descending ? -$result : $result;
                });
            }

            return response()->json([
                'data' => array_values($items),
                'total' => (int) ($data['total'] ?? count($items)),
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:4096'],
            'group' => ['nullable', 'string', 'max:255'],
            'cronExpr' => ['required', 'string', 'max:100'],
            'enabled' => ['required', 'boolean'],
            'userAgent' => ['nullable', 'string', 'max:512'],
            'fetchUsageInfo' => ['required', 'boolean'],
            'pullNow' => ['required', 'boolean'],
            'skipTLSVerify' => ['required', 'boolean'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $client = $this->client();
            $id = isset($validated['id']) ? (int) $validated['id'] : null;
            $pullNow = (bool) $validated['pullNow'];
            unset($validated['pullNow']);
            $payload = $id ? array_merge($client->get($id), $validated) : array_merge(
                $this->defaults(),
                $validated
            );
            unset($payload['id'], $payload['nodeStats'], $payload['nodeCount'], $payload['createdAt'], $payload['updatedAt']);

            $id ? $client->update($id, $payload) : $client->create($payload);
            if ($id && $pullNow) {
                $client->pull($id);
            }

            return response()->json(['message' => $id ? '机场已更新' : '机场已添加']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        $validated = $request->validate(['id' => ['required', 'integer', 'min:1']]);

        try {
            $this->client()->delete((int) $validated['id']);
            return response()->json(['message' => '机场及其聚合节点已删除']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate(['id' => ['required', 'integer', 'min:1']]);

        try {
            $this->client()->pull((int) $validated['id']);
            return response()->json(['message' => '拉取任务已提交']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function pullAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        try {
            $data = $this->client()->pullAll($validated['ids'] ?? []);
            return response()->json([
                'message' => '批量拉取任务已提交',
                'data' => $data,
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function refreshUsage(Request $request): JsonResponse
    {
        $validated = $request->validate(['id' => ['required', 'integer', 'min:1']]);

        try {
            $data = $this->client()->refreshUsage((int) $validated['id']);
            return response()->json([
                'message' => '上游用量已刷新',
                'data' => $data,
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function nodes(Request $request): JsonResponse
    {
        try {
            $data = $this->client()->listNodes(array_filter([
                'page' => max(1, (int) $request->query('page', 1)),
                'pageSize' => min(1000, max(1, (int) $request->query('pageSize', 1000))),
                'search' => trim((string) $request->query('search', '')),
                'protocol' => trim((string) $request->query('protocol', '')),
                'source' => trim((string) $request->query('source', '')),
            ], fn ($value) => $value !== '' && $value !== null));

            $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : $data;

            return response()->json([
                'data' => array_values($items),
                'total' => (int) ($data['total'] ?? count($items)),
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function updateNode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'oldname' => ['required', 'string', 'max:1024'],
            'oldlink' => ['required', 'string', 'max:8192'],
            'link' => ['required', 'string', 'max:8192'],
            'name' => ['nullable', 'string', 'max:1024'],
            'nameMode' => ['required', 'in:link,remark'],
            'dialerProxyName' => ['nullable', 'string', 'max:1024'],
            'group' => ['nullable', 'string', 'max:1024'],
            'tags' => ['nullable', 'string', 'max:4096'],
        ]);

        try {
            unset($validated['id']);
            $this->client()->updateNode($validated);
            return response()->json(['message' => '聚合节点已更新']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function deleteNode(Request $request): JsonResponse
    {
        $validated = $request->validate(['id' => ['required', 'integer', 'min:1']]);

        try {
            $this->client()->deleteNode((int) $validated['id']);
            return response()->json(['message' => '聚合节点已删除']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function client(): SublinkAdminClient
    {
        return new SublinkAdminClient(
            baseUrl: (string) $this->getConfig('sublink_base_url', 'http://sublinkpro:8000'),
            apiKey: (string) $this->getConfig('sublink_api_key', ''),
            timeoutSeconds: (int) $this->getConfig('timeout_seconds', 15),
            verifyTls: (bool) $this->getConfig('verify_tls', true),
        );
    }

    private function defaults(): array
    {
        return [
            'downloadWithProxy' => false,
            'proxyLink' => '',
            'requestHeaders' => [],
            'updateAfterDetect' => false,
            'updateAfterDetectProfileId' => 0,
            'updateAfterDetectChangedOnly' => false,
            'logo' => '',
            'nodeNameWhitelist' => '',
            'nodeNameBlacklist' => '',
            'protocolWhitelist' => '',
            'protocolBlacklist' => '',
            'nodeNamePreprocess' => '',
            'deduplicationRule' => '',
            'nodeNameUniquify' => false,
            'nodeNamePrefix' => '',
            'nodeNameIntraUniquify' => false,
            'autoFillCountry' => false,
            'backfillExistingCountry' => false,
        ];
    }

    private function failure(Throwable $exception): JsonResponse
    {
        report($exception);
        return response()->json(['message' => $exception->getMessage()], 502);
    }
}
