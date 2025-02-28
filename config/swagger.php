<?php

return [
    "openapi" => "3.0.3",
    "info" => [
        "title" => "API doc",
        "description" => "Documentation",
        "contact" => [
            "email" => "abdphyr@gmail.com"
        ],
        "version" => "1.0.0"
    ],
    "servers" => [
        [
            "url" => "http://127.0.0.1:8000/api"
        ]
    ],
    "tags" => [
        [
            "name" => "auth",
            "description" => "Authentication"
        ]
    ],
    "components" => [
        "securitySchemes" => [
            "bearerAuth" => [
                "type" =>  "http",
                "scheme" =>  "bearer",
                "bearerFormat" =>  "JWT"
            ]
        ]
    ]
];
