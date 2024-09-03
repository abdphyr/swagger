<?php


namespace Abd\Swagger\Http\Controllers;

class SwaggerController
{
    public function doc()
    {
        return view('swagger::swagger');
    }
}