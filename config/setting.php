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
        // インポートジョブの有効期限（日数）
        // ジョブ作成時に expires_at へ記録される。クリーンアップコマンドは未実装
        'jobExpireDays' => 14,

        // 進捗ログを N 件ごとに出力する間隔
        // 数値を小さくするほどリアルタイム表示の更新が細かくなるが、ディスク書き込みが増える
        'batchSize' => 10,

        // 画面に表皮する（ポーリングで返す）ログの最大行数
        // 大量ログ時にレスポンスが肥大化しないよう末尾 N 行に絞る
        'logLineLimit' => 200,

        // スラッグの最大文字数（URL に使用する name フィールドの上限）
        // baserCMS の Contents.name カラムの最大長に合わせて調整する
        'slugMaxLength' => 230,
    ],
];
