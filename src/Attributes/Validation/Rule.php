<?php

namespace Abdphyr\Swagger\Attributes\Validation;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Rule
{
    public function __construct(public array|string $rule) {}
}
