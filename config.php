<?php
return [
    'type' => 'Plugin',
    'title' => __d('baser_core', 'WordPressインポート'),
    'description' => __d('baser_core', 'WordPress の WXR ファイルを baserCMS に取り込むプラグインです。'),
    'author' => 'kaburk',
    'url' => 'https://blog.kaburk.com/',
    'adminLink' => [
        'prefix' => 'Admin',
        'plugin' => 'BcWpImport',
        'controller' => 'WpImports',
        'action' => 'index',
    ],
];
