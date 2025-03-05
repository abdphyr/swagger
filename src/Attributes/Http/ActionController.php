<?php

namespace Abdphyr\Swagger\Attributes\Http;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ActionController
{
    public function __construct(public string $uri, public array $pages = ['default']) {}
}
