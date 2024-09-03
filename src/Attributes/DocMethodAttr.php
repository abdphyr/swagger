<?php

namespace Abd\Swagger\Attributes;


#[\Attribute(\Attribute::TARGET_METHOD)]
class DocMethodAttr
{
    public function __construct(public $method = 'GET') {}
}
