<?php

return [
    'default' => [
        'title' => 'Default API documentation',
        'document' => [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'API doc',
                'description' => 'Documentation',
                'contact' => ['email' => 'abdphyr@gmail.com'],
                'version' => '1.0.0'
            ],
            'servers' => [
                [
                    'url' => 'http://127.0.0.1:8000/api'
                ]
            ],
            'tags' => [],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' =>  'http',
                        'scheme' =>  'bearer',
                        'bearerFormat' =>  'JWT'
                    ]
                ]
            ]
        ]
    ],
    'admin' => [
        'title' => 'Admin API documentation',
        'document' => [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'API doc',
                'description' => 'Documentation',
                'contact' => ['email' => 'abdphyr@gmail.com'],
                'version' => '1.0.0'
            ],
            'servers' => [
                [
                    'url' => 'http://127.0.0.1:8000/api'
                ]
            ],
            'tags' => [],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' =>  'http',
                        'scheme' =>  'bearer',
                        'bearerFormat' =>  'JWT'
                    ]
                ]
            ]
        ]
    ]
];
