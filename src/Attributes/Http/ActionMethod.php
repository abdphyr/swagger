<?php

namespace Abdphyr\Swagger\Attributes\Http;

#[\Attribute(\Attribute::TARGET_METHOD)]
class ActionMethod
{
    public function __construct(
        public string $uri,
        public string $method,
        public array $route = [],
        public array $query = [],
        public array $request = [],
        public array $cookies = [],
        public array $files = [],
        public array $server = [],
        public array $attributes = [],
        public array $headers = [],
        public $content = null,
        public array $tags = [],
        public string $description = '',
        public string $summary = '',
        public bool $hasAuth = true,
    ) {}
}
