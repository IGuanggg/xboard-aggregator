<?php

namespace Plugin\AirportAggregator\Services;

use InvalidArgumentException;
use JsonException;

class GroupRouteResolver
{
    public function resolve(array|string|null $routes, int $groupId): ?string
    {
        if (is_string($routes)) {
            $routes = trim($routes);
            if ($routes === '') {
                return null;
            }

            try {
                $routes = json_decode($routes, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('权限组映射不是有效的 JSON', previous: $exception);
            }
        }

        if (!is_array($routes)) {
            throw new InvalidArgumentException('权限组映射必须是 JSON 对象');
        }

        $token = $routes[(string) $groupId] ?? $routes[$groupId] ?? null;
        if ($token === null || trim((string) $token) === '') {
            return null;
        }

        $token = trim((string) $token);
        if (!preg_match('/^[A-Za-z0-9_-]{8,256}$/', $token)) {
            throw new InvalidArgumentException("权限组 {$groupId} 的分享 Token 格式无效");
        }

        return $token;
    }
}
