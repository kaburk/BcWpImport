<?php

return [
    'BcApp' => [
        'adminNavigation' => [
            'Contents' => [
                'BcWpImport' => [
                    'title' => __d('baser_core', 'WordPressインポート'),
                    'url' => [
                        'Admin' => true,
                        'plugin' => 'BcWpImport',
                        'controller' => 'wp_imports',
                        'action' => 'index',
                    ],
                ],
            ],
        ],
    ],
    'BcWpImport' => [
        'jobExpireDays' => 3,
        'batchSize' => 100,
    ],
];
