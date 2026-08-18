<?php

namespace Plugin\AirportAggregator\Services;

final readonly class SublinkResponse
{
    public function __construct(
        public string $body,
        public int $status,
        public array $headers,
    ) {
    }
}
