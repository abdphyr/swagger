<?php

namespace Abdphyr\Swagger\Http\Controllers;

class SwaggerController
{

    public function __call($name, $arguments)
    {
        list($page, $option) = explode('-', $name);
        if ($option == 'ui') {
            try {
                return $this->resolveView($page);
            } catch (\Throwable $th) {
                return '<div>' .  $th->getMessage() . '</div>';
            }
        } else if ($option == 'data') {
            try {
                $document = config("swagger.$page.document", []);
                $cache = require_once base_path("bootstrap/cache/swagger/$page.php");
                $document["paths"] = is_array($cache) ? $cache : [];
                return $document;
            } catch (\Throwable $th) {
                return response()->json(['message' => $th->getMessage()], 500);
            }
        }
    }

    protected function resolveView($page)
    {
        $lang = config("swagger.$page.lang", 'en');
        $title = config("swagger.$page.title", 'Default API documentation');
        $url = config("swagger.$page.data_endpoint", "/swagger/$page-data");

        return "<!DOCTYPE html>
                <html lang='$lang'>

                <head>
                    <title>$title</title>
                    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/4.5.0/swagger-ui.min.css'>
                </head>

                <body>
                    <div id='swagger-$page-ui'></div>
                    <script src='https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/4.5.0/swagger-ui-bundle.min.js'></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            SwaggerUIBundle({
                                dom_id: '#swagger-$page-ui',
                                url: '$url',
                                presets: [SwaggerUIBundle.presets.apis],
                            });
                        });
                    </script>
                </body>

                </html>
                ";
    }
}
