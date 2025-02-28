<?php

namespace Abdphyr\Swagger\Attributes\DTO;

use Illuminate\Http\UploadedFile;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class FileParam
{
    public ?string $key;
    public UploadedFile $value;

    public function __construct($key = null, UploadedFile $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
}
