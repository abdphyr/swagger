<?php

namespace Abdphyr\Swagger\Http\Controllers;

class SwaggerController
{
    public function swaggerUI()
    {
        return view('swagger.swagger');
    }

    public function swaggerDocument()
    {
        $document = config('swagger', []);
        $cache = require_once base_path('bootstrap/cache/swagger.php');
        $document["paths"] = is_array($cache) ? $cache : [];
        return $document;
    }
}
