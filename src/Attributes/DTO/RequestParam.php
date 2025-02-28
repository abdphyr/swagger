<?php

namespace Abdphyr\Swagger\Attributes\DTO;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class RequestParam
{
    public ?string $key;
    public mixed $value;
    
    public function __construct($key = null, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
}
