# BcWpImport 詳細設計

## 目的

- WordPress から出力された WXR を baserCMS5 に取り込み、固定ページやブログ記事の移行を支援する。
- 取り込み前に内容を可視化し、どこへ入るかを管理画面で明示する。

## 想定ユースケース

- WordPress の投稿と固定ページを baserCMS に移したい。
- ブログ単位で WordPress の記事を取り込みたい。
- 事前に衝突を確認したうえで安全に移行したい。

## MVP 対応範囲

### 対象

- post を baserCMS のブログ記事として取り込む
- page を baserCMS の固定ページとして取り込む
- category をブログカテゴリーとして取り込む
- post_tag をブログタグとして取り込む
- author を投稿者マッピング候補として読み込む

### 初版では対象外または限定対応

- custom post type
- comment
- menu_item
- attachment の完全取込
- WooCommerce など特定プラグイン由来データ
- block editor 固有構造の完全再現

## 取込先マッピング方針

| WXR / WordPress | baserCMS 側 | 備考 |
|---|---|---|
| post | ブログ記事 | 取込先ブログの選択を必須にする |
| page | 固定ページ | 親ページ関係とコンテンツフォルダ配下は要調整 |
| category | ブログカテゴリー | 対象ブログ配下へ生成 |
| post_tag | ブログタグ | 対象ブログ配下へ生成 |
| author | baserCMS ユーザー | 既存ユーザーへ割当またはデフォルトユーザーへ統一 |

## 取り込み前の解析ステップ

- XML 構文チェック
- WXR バージョン確認
- アイテム件数集計
- 種別別件数集計
- 著者一覧抽出
- カテゴリー、タグ一覧抽出
- 取り込み不可要素の検出

## 事前確認画面で表示したい内容

- ファイル名
- WXR バージョン
- 総 item 数
- post / page / attachment / custom post type の件数
- 著者候補一覧
- 取り込み対象外件数
- 想定される警告

## 取込オプション案

- 取り込み対象
  - 全件
  - 投稿のみ
  - 固定ページのみ
- 取込先ブログ
- 固定ページの配置先コンテンツフォルダ
- 著者マッピング
  - 同名ユーザーへ割当
  - 指定ユーザーへ一括割当
- スラッグ重複時の扱い
  - 連番付与
  - 上書きしないでスキップ
  - 更新扱いで上書き
- 公開状態の扱い
  - 元の状態を維持
  - すべて下書きで取込
- 本文の URL 変換
  - WordPress URL を維持
  - 指定ドメインへ置換

## ジョブ処理方針

- ジョブ管理型にする。
- 解析フェーズと登録フェーズを分ける。
- 解析後の確認結果をジョブに保持し、ユーザーが問題なければ登録を開始する。
- 長時間処理を考慮して分割実行する。

## ディレクトリ構成案

```text
plugins/BcWpImport/
├── README.md
├── VERSION.txt
├── LICENSE.txt
├── composer.json
├── config.php
├── config/
│   ├── routes.php
│   ├── setting.php
│   └── Migrations/
│       └── YYYYMMDDHHMMSS_CreateBcWpImportJobs.php
├── src/
│   ├── BcWpImportPlugin.php
│   ├── Controller/
│   │   └── Admin/
│   │       └── WpImportsController.php
│   ├── Model/
│   │   ├── Entity/
│   │   │   └── BcWpImportJob.php
│   │   └── Table/
│   │       └── BcWpImportJobsTable.php
│   ├── Service/
│   │   ├── Admin/
│   │   │   └── WpImportAdminService.php
│   │   ├── WxrParserService.php
│   │   ├── WpImportService.php
│   │   ├── PageImporterService.php
│   │   └── BlogPostImporterService.php
│   └── Utility/
│       ├── WxrReader.php
│       └── WxrMapper.php
├── templates/
│   └── Admin/
│       └── WpImports/
│           └── index.php
├── webroot/
│   ├── css/
│   │   └── admin/
│   │       └── wp_import.css
│   └── js/
│       └── admin/
│           └── wp_import.js
└── tests/
    └── TestCase/
        ├── Controller/
        │   └── Admin/
        │       └── WpImportsControllerTest.php
        └── Service/
            ├── WxrParserServiceTest.php
            └── WpImportServiceTest.php
```

## 初期ファイル一覧

| ファイル | 必要度 | 役割 |
|---|---|---|
| README.md | 必須 | 概要、導入手順、制約を記載 |
| VERSION.txt | 必須 | プラグイン版管理 |
| LICENSE.txt | 必須 | ライセンス明示 |
| composer.json | 必須 | CakePHP プラグインとしての autoload 設定 |
| config.php | 必須 | プラグイン名、説明、作者情報 |
| config/routes.php | 任意 | 管理画面外の専用ルートが必要になったとき用。初期は空でもよい |
| config/setting.php | 必須 | 管理画面メニュー、デフォルト設定 |
| config/Migrations/CreateBcWpImportJobs.php | 必須 | インポートジョブ管理テーブル作成 |
| src/BcWpImportPlugin.php | 必須 | プラグイン本体 |
| src/Controller/Admin/WpImportsController.php | 必須 | index、upload、analyze、import、status、delete などの入口 |
| src/Model/Entity/BcWpImportJob.php | 必須 | ジョブエンティティ |
| src/Model/Table/BcWpImportJobsTable.php | 必須 | ジョブ保存、バリデーション、検索 |
| src/Service/Admin/WpImportAdminService.php | 推奨 | 一覧表示用の view 変数、サマリ生成 |
| src/Service/WxrParserService.php | 必須 | XML 構文解析、item 集計、著者・タクソノミー抽出 |
| src/Service/WpImportService.php | 必須 | ジョブ全体の進行管理 |
| src/Service/PageImporterService.php | 推奨 | page の取り込み責務を分離 |
| src/Service/BlogPostImporterService.php | 推奨 | post の取り込み責務を分離 |
| src/Utility/WxrReader.php | 推奨 | SimpleXML / XMLReader の低レイヤ読み込み共通化 |
| src/Utility/WxrMapper.php | 推奨 | WXR item を baserCMS 用配列へ変換 |
| templates/Admin/WpImports/index.php | 必須 | アップロード、解析結果、ジョブ一覧、履歴表示 |
| webroot/css/admin/wp_import.css | 任意 | 参考 UI の差分スタイル |
| webroot/js/admin/wp_import.js | 推奨 | 解析開始、進捗更新、再開処理 |

## 最小スタート構成

- README.md
- VERSION.txt
- LICENSE.txt
- composer.json
- config.php
- config/setting.php
- config/Migrations/CreateBcWpImportJobs.php
- src/BcWpImportPlugin.php
- src/Controller/Admin/WpImportsController.php
- src/Model/Entity/BcWpImportJob.php
- src/Model/Table/BcWpImportJobsTable.php
- src/Service/WxrParserService.php
- src/Service/WpImportService.php
- templates/Admin/WpImports/index.php
- webroot/js/admin/wp_import.js
- tests/TestCase/Service/WxrParserServiceTest.php

## ジョブテーブル詳細設計

### 想定テーブル名

- bc_wp_import_jobs

### 用途

- WXR アップロード後の解析結果を保持する。
- 解析確認後の取り込みジョブを再開できるようにする。
- エラー、警告、スキップの内容をダウンロードできるようにする。

### カラム案

| カラム | 型 | 必須 | 説明 |
|---|---|---|---|
| id | int PK AUTO | ○ | 主キー |
| job_token | varchar(255) | ○ | ジョブ識別子。ユニーク |
| status | varchar(30) | ○ | pending / processing / waiting / completed / failed / cancelled |
| phase | varchar(30) | ○ | upload / analyze / review / import |
| mode | varchar(20) | ○ | strict / lenient。初期は strict を基本想定 |
| import_target | varchar(30) | ○ | all / posts / pages |
| blog_content_id | int | - | 投稿の取込先ブログID。posts を含む場合に利用 |
| content_folder_id | int | - | 固定ページの配置先コンテンツフォルダID |
| author_strategy | varchar(30) | ○ | match / assign |
| author_assign_user_id | int | - | assign のときの一括割当先ユーザーID |
| slug_strategy | varchar(30) | ○ | suffix / skip / overwrite |
| publish_strategy | varchar(30) | ○ | keep / draft |
| url_replace_mode | varchar(30) | ○ | keep / replace |
| url_replace_from | varchar(255) | - | 置換元ドメイン |
| url_replace_to | varchar(255) | - | 置換先ドメイン |
| source_filename | varchar(255) | ○ | 元アップロードファイル名 |
| wxr_path | varchar(255) | ○ | 一時保存した WXR ファイルパス |
| parsed_summary | text | - | 解析結果サマリ JSON |
| import_settings | text | - | 取込設定 JSON。画面入力のスナップショット |
| analysis_position | bigint | - | 解析再開用の読込位置 |
| import_position | bigint | - | item 取り込み再開用の読込位置 |
| total_items | int | ○ | 総 item 数 |
| analyzable_items | int | ○ | 解析可能 item 数 |
| importable_items | int | ○ | 取り込み対象 item 数 |
| processed | int | ○ | 現フェーズの処理済件数 |
| success_count | int | ○ | 取込成功件数 |
| skip_count | int | ○ | スキップ件数 |
| warning_count | int | ○ | 警告件数 |
| error_count | int | ○ | エラー件数 |
| unsupported_count | int | ○ | 未対応 item 件数 |
| error_log_path | varchar(255) | - | エラーログ JSON Lines |
| warning_log_path | varchar(255) | - | 警告ログ JSON Lines |
| report_csv_path | varchar(255) | - | エラー・警告ダウンロード用CSV |
| expires_at | datetime | ○ | 一時ファイル削除期限 |
| started_at | datetime | - | ジョブ開始日時 |
| ended_at | datetime | - | ジョブ終了日時 |
| created | datetime | ○ | 作成日時 |
| modified | datetime | ○ | 更新日時 |

### parsed_summary に持たせる内容

```json
{
  "wxr_version": "1.2",
  "channel_title": "WordPress Site",
  "language": "ja",
  "item_counts": {
    "post": 120,
    "page": 24,
    "attachment": 58,
    "nav_menu_item": 12,
    "custom_post_type": 3
  },
  "authors": [
    {"login": "admin", "email": "admin@example.com"}
  ],
  "categories": ["news", "topics"],
  "tags": ["release", "event"],
  "unsupported_types": ["attachment", "nav_menu_item"],
  "warnings_preview": [
    "attachment は初期版では取り込み対象外です"
  ]
}
```

### import_settings に持たせる内容

```json
{
  "import_target": "all",
  "blog_content_id": 3,
  "content_folder_id": 12,
  "author_strategy": "assign",
  "author_assign_user_id": 1,
  "slug_strategy": "suffix",
  "publish_strategy": "draft",
  "url_replace_mode": "replace",
  "url_replace_from": "https://old.example.com",
  "url_replace_to": "https://new.example.com"
}
```

### 状態遷移

| status | phase | 意味 |
|---|---|---|
| pending | upload | ファイル受領直後 |
| processing | analyze | XML 解析中 |
| waiting | review | 解析完了、ユーザー確認待ち |
| processing | import | 取り込み中 |
| completed | import | 取り込み完了 |
| failed | analyze/import | 解析または取込に失敗 |
| cancelled | analyze/import | ユーザー中断 |

### インデックス案

- UNIQUE job_token
- INDEX status
- INDEX phase
- INDEX expires_at
- INDEX blog_content_id
- INDEX created

### 実装メモ

- review フェーズを持たせることで、解析結果確認後に import 開始する二段階 UI を作りやすい。
- parsed_summary は text + JSON とし、初期は DB JSON 型に依存しない。
- import_position は XMLReader での byte offset または item index のどちらかに統一する。
- WordPress 元 ID と既存レコードの対応は、ジョブテーブルではなく取り込み先メタテーブルで管理した方がよい。

## migration たたき台

### 方針

- 初期 migration はジョブテーブル作成のみに絞る。
- item 単位の明細テーブルは初版では作らない。
- JSON 保持カラムは text にして、DB 方言依存を避ける。
- 既存の BcCsvImportCore と同様に、1 migration 1 テーブルで開始する。

### ファイル名案

- config/Migrations/YYYYMMDDHHMMSS_CreateBcWpImportJobs.php

### 実装イメージ

```php
<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class CreateBcWpImportJobs extends BcMigration
{
    public function up()
    {
        $this->table('bc_wp_import_jobs', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('job_token', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'pending'])
            ->addColumn('phase', 'string', ['limit' => 30, 'null' => false, 'default' => 'upload'])
            ->addColumn('mode', 'string', ['limit' => 20, 'null' => false, 'default' => 'strict'])
            ->addColumn('import_target', 'string', ['limit' => 30, 'null' => false, 'default' => 'all'])
            ->addColumn('blog_content_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('content_folder_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('author_strategy', 'string', ['limit' => 30, 'null' => false, 'default' => 'match'])
            ->addColumn('author_assign_user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('slug_strategy', 'string', ['limit' => 30, 'null' => false, 'default' => 'suffix'])
            ->addColumn('publish_strategy', 'string', ['limit' => 30, 'null' => false, 'default' => 'keep'])
            ->addColumn('url_replace_mode', 'string', ['limit' => 30, 'null' => false, 'default' => 'keep'])
            ->addColumn('url_replace_from', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('url_replace_to', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('source_filename', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('wxr_path', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('parsed_summary', 'text', ['null' => true, 'default' => null])
            ->addColumn('import_settings', 'text', ['null' => true, 'default' => null])
            ->addColumn('analysis_position', 'biginteger', ['null' => true, 'default' => 0])
            ->addColumn('import_position', 'biginteger', ['null' => true, 'default' => 0])
            ->addColumn('total_items', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('analyzable_items', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('importable_items', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('processed', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('success_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('skip_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('warning_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('error_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('unsupported_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('error_log_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('warning_log_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('report_csv_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('expires_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('started_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('ended_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['job_token'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['phase'])
            ->addIndex(['expires_at'])
            ->addIndex(['blog_content_id'])
            ->addIndex(['created'])
            ->create();
    }

    public function down()
    {
        $this->table('bc_wp_import_jobs')->drop()->save();
    }
}
```

### 補足

- source_filename と wxr_path は初期から必須にする。
- parsed_summary と import_settings は null 許容で開始し、解析後・確認後に埋める。
- expires_at はジョブ作成時に設定する運用でよい。

### migration 作成時の注意点

- enum は使わず string で表現する。
- 将来の値追加に備え、status や phase を DB 制約で固めすぎない。
- bigint の position 系は 0 初期値で揃える。
- addIndex() は一覧検索と期限削除で使うものだけに留める。

## 画面遷移とアクション責務

### 画面構成の考え方

- 1 画面完結を基本にする。
- ただし UI 上は、アップロードと解析、確認、取り込み、履歴の 4 状態に分けて見せる。
- BcCsvImportCore の index 画面をベースに、解析結果サマリ領域を追加する。

### 画面状態

| 状態 | 表示内容 | 主な操作 |
|---|---|---|
| 初期表示 | アップロードフォーム、未完了ジョブ、履歴 | WXR選択、解析開始 |
| 解析中 | 進捗バー、解析件数、キャンセル | 状態確認、キャンセル |
| 確認待ち | 解析結果サマリ、取込オプション、インポート開始 | 設定変更、取込開始 |
| 取込中 | 進捗バー、成功、スキップ、エラー件数 | 状態確認、キャンセル |
| 完了/失敗 | 結果サマリ、レポートDL | エラーレポート、削除 |

### 主要アクション案

#### index

- 役割
  - 初期画面表示
  - 未完了ジョブ一覧の表示
  - 履歴一覧の表示
  - 必要な選択肢データの表示
- 返すもの
  - pendingJobs
  - historyJobs
  - blogOptions
  - contentFolderOptions
  - userOptions

#### upload

- 役割
  - WXR ファイルの受け取り
  - 一時ファイル保存
  - ジョブレコード新規作成
  - 形式不正なら即時エラー返却
- 入力
  - wxr_file
- 出力
  - token
  - source_filename
  - status=pending
  - phase=upload

#### analyze

- 役割
  - WXR の構文解析
  - WXR バージョン、item 件数、著者、分類情報の抽出
  - parsed_summary の保存
  - waiting / review 状態への遷移
- 入力
  - token
  - offset または cursor
  - limit
- 出力
  - processed
  - total
  - warnings
  - completed
  - next_phase=review

#### save_review_settings

- 役割
  - 確認画面で指定した取込設定を保存
  - import_settings の更新
  - 設定バリデーション
- 入力
  - token
  - import_target
  - blog_content_id
  - content_folder_id
  - author_strategy
  - author_assign_user_id
  - slug_strategy
  - publish_strategy
  - url_replace_mode
  - url_replace_from
  - url_replace_to
- 出力
  - 保存済み設定
  - 実行可否

#### import

- 役割
  - review 状態のジョブを import フェーズへ進める
  - item ごとの変換と保存
  - 進捗、成功、スキップ、エラーを加算
- 入力
  - token
  - offset または cursor
  - limit
- 出力
  - processed
  - total
  - success_count
  - skip_count
  - warning_count
  - error_count
  - completed

#### status

- 役割
  - 現在の status / phase / processed を返す
  - フロントのポーリングに使う
- 出力
  - token
  - status
  - phase
  - processed
  - total_items
  - success_count
  - skip_count
  - warning_count
  - error_count

#### preview

- 役割
  - parsed_summary や import_settings を再表示する
  - review 状態ジョブの詳細確認に使う
- 出力
  - parsed_summary
  - import_settings
  - warnings_preview

#### download_report

- 役割
  - エラー、警告、スキップ一覧の CSV を返す
- 入力
  - token
  - type=error|warning|all

#### cancel

- 役割
  - analyze/import 中ジョブの停止
  - cancelled 状態への遷移

#### delete

- 役割
  - ジョブレコードと一時ファイル削除
  - WXR ファイル、レポート、ログの掃除

### 画面遷移フロー

#### 新規取り込み

1. index 表示
2. WXR ファイル選択
3. upload 実行
4. analyze 実行
5. review 状態に遷移
6. save_review_settings 実行
7. import 実行
8. completed または failed

#### 再開

1. index の未完了ジョブ一覧から再開
2. status で phase を確認
3. analyze 中なら analyze を再開
4. review なら preview を表示
5. import 中なら import を再開

### 画面上の主要ブロック

#### 新規インポートブロック

- WXR ファイル選択
- 解析開始ボタン
- オプションアコーディオン

#### 解析結果サマリブロック

- ファイル名
- WXR バージョン
- 種別別件数
- 著者一覧
- 未対応要素一覧
- 警告プレビュー
- 取込設定フォーム
- インポート開始ボタン

#### 未完了ジョブ一覧ブロック

- 作成日時
- 状態
- フェーズ
- 進捗
- 概要
- 操作

#### 履歴ブロック

- 完了日時
- 結果サマリ
- レポートダウンロード
- 削除

### JavaScript 側の責務

- upload から analyze から review 表示の状態切り替え
- status ポーリング
- analyze/import のバッチ呼び出し
- レポートダウンロード導線
- 再開時の phase 判定

### Service 層の責務分担

#### WxrParserService

- XML 構文確認
- channel 情報取得
- item 種別集計
- 著者、カテゴリー、タグ一覧抽出

#### WpImportService

- ジョブ作成
- ジョブ状態更新
- analyze/import フェーズ進行
- ログ・レポート出力

#### PageImporterService

- page を baserCMS 固定ページへ変換
- 親子関係、スラッグ、公開状態の調整

#### BlogPostImporterService

- post をブログ記事へ変換
- カテゴリー、タグ、著者割当の調整

### 初期実装で省くもの

- 複数画面の wizard 化
- attachment 個別確認画面
- item 単位の手動マッピング UI
- リアルタイムプレビューの凝った UI
