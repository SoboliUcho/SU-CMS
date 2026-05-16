<?php

// Dynamické routy načítané při startu aplikace.
// Lze upravovat bez zásahu do Bootstrapu; admin může zapisovat do DB, toto slouží jako fallback.
return [
    "api" =>[
        "HealthController" => [
            "index"=>[
                'method' => 'GET',
                'uri'    => '/api/health',
                'action' => 'App\\Controllers\\Api\\HealthController',
                'enabled'=> true,
            ],
        ],
        "ModuleInstallController" => [
            "installAll"=>[
                'method' => 'POST',
                'uri'    => '/api/modules/install-all',
                'action' => 'App\\Controllers\\Api\\ModuleInstallController',
                'enabled'=> true,
            ],
        ],
    ],
    "web" =>[
        "HomeController" => [
            "index"=>[
                'method' => 'GET',
                'uri'    => '/',
                'action' => 'App\\Controllers\\Web\\IndexController',
                'enabled'=> true,
            ],
        ],
    ]
];
