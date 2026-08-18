<?php

namespace Plugin\AirportAggregator\Services;

use Illuminate\Http\Request;
use JsonException;

class ClientResolver
{
    private const SAFE_CLIENT = '/^[a-z0-9_-]{1,32}$/';

    public function resolve(Request $request, array|string|null $configuredMap): string
    {
        $map = $this->parseMap($configuredMap);

        $explicit = strtolower(trim((string) $request->query('client', '')));
        if ($explicit !== '' && preg_match(self::SAFE_CLIENT, $explicit)) {
            return $map[$explicit] ?? $explicit;
        }

        $haystack = strtolower(implode(' ', [
            (string) $request->query('flag', ''),
            (string) $request->userAgent(),
        ]));

        foreach ($map as $needle => $client) {
            if ($needle === 'default') {
                continue;
            }
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return $client;
            }
        }

        return $map['default'] ?? 'v2ray';
    }

    private function parseMap(array|string|null $configuredMap): array
    {
        if (is_string($configuredMap)) {
            try {
                $configuredMap = json_decode($configuredMap, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $configuredMap = [];
            }
        }

        $defaults = [
            'clash' => 'mihomo',
            'mihomo' => 'mihomo',
            'surge' => 'surge',
            'sing-box' => 'singbox',
            'singbox' => 'singbox',
            'default' => 'v2ray',
        ];

        if (!is_array($configuredMap)) {
            return $defaults;
        }

        $result = [];
        foreach ($configuredMap as $needle => $client) {
            $needle = strtolower(trim((string) $needle));
            $client = strtolower(trim((string) $client));
            if ($needle !== '' && preg_match(self::SAFE_CLIENT, $client)) {
                $result[$needle] = $client;
            }
        }

        return array_replace($defaults, $result);
    }
}
