# WXR移行プラグイン 進捗・残件メモ

更新日: 2026-04-11（ログ機能・UI改善 反映）

## 対象プラグイン

| プラグイン | 方向 | 状態 |
|---|---|---|
| BcWpImport | WordPress WXR → baserCMS 取り込み | コア機能実装済み・テスト未実施 |
| BcWpExport | baserCMS → WordPress WXR 書き出し | コア機能実装済み・テスト骨格あり・実行テスト未実施 |

---

## BcWpImport

### 実装済み

#### プラグイン基盤
- Plugin.php / config.php / setting.php / routes.php
- Migration: `CreateBcWpImportJobs`

#### コントローラ（`WpImportsController`）
※ URL は InflectedRoute によって camelCase 変換されないため、全アクションを snake_case で定義

- `index` — ジョブ一覧画面表示
- `upload` — WXRファイルのアップロード
- `analyze` — WXR解析実行
- `save_review_settings` — 取込前レビュー設定の保存
- `import` — 取込実行
- `status` — ジョブ状態取得
- `cancel` — ジョブキャンセル
- `delete` — 個別ジョブ削除（ファイル＋DBレコード）
- `delete_all` — 複数ジョブの一括削除
- `get_log` — 処理中ログの最新行取得（ポーリング用）
- `download_report` — 取込結果CSVダウンロード

#### サービス
- `WxrParserService` — XML構文解析・item集計・著者/タクソノミー抽出
- `WpImportService`
  - `createJob` — ジョブ作成・ファイル保存
  - `analyzeJob` — 解析実行・サマリ生成
  - `saveReviewSettings` — 著者マッピング・オプション保存
  - `importJob` — 固定ページ・ブログ記事の取り込み
  - `getJobStatus` / `cancelJob` / `getReportCsvPath`
  - `getLogLines(token, limit)` — ログファイル（`tmp/bc_wp_import/{token}.log`）の最新N行を返す
  - `appendLog(path, message, overwrite)` — private。ログファイルへ1行追記（初回は上書き）
- `WpImportAdminService` — 一覧表示用 view 変数生成（ブログ・コンテンツフォルダ・ユーザーのプルダウン選択肢を実DBから取得）

#### モデル
- `BcWpImportJobsTable` — `Timestamp` Behavior を追加し `created` / `modified` を自動セット

#### 取込ロジック（`importJob` 内）
- 固定ページ（page）の取り込み
- ブログ記事（post）の取り込み
- カテゴリ自動作成
- タグ自動作成
- 著者解決（同名割当 / 一括割当）
- スラッグ競合処理（suffix / skip / overwrite）
- URL置換（WordPress URL → 指定ドメイン）
- アイテム単位の結果収集
- 取込結果CSVの生成
  - ログファイルへの書き出し（開始・100件ごと・スキップ/エラー発生時・完了）

#### フロントエンド
- `templates/Admin/WpImports/index.php`
  - 解析結果：件数・著者・カテゴリ・タグを動的表示
  - レビュー設定：ブログ・コンテンツフォルダ・著者をDBから取得したプルダウンで表示
  - インポート実行前に必須項目（ブログ/フォルダ/著者/URL）をJSバリデーション
  - 処理中セクション：スピナー + インジケーター型プログレスバー + 経過時間カウンター + ログビューアー（黒背景 `<pre>`）
  - 未完了・完了履歴の両テーブルにチェックボックス・全選択・一括削除ボタン
  - 解析成功後に「アップロードして解析」ボタンを非表示化
- `webroot/js/admin/wp_import.js`
  - `showSection` でセクション切替時にポーリング・経過時間タイマーを開始/停止
  - `_logPollTimer`: 2秒ごとに `get_log?token=xxx` を fetch してログビューアーを更新（自動スクロール）
  - `_elapsedTimer`: 1秒ごとに経過時間を更新
  - `updateBulkDeleteButton`: pending / history 両テーブル対応
  - JSバリデーション：インポート実行前に必須項目を検証してエラー表示
- `webroot/css/admin/wp_import.css`
  - `.bc-wp-import__progress-track` / `.bc-wp-import__progress-bar-indeterminate` — アニメーション付きインジケーターバー
  - `.bc-wp-import__log-wrap` / `.bc-wp-import__log-viewer` — 黒背景等幅フォントのログ表示エリア
  - `.bc-wp-import__upload-error` — バリデーションエラー用スタイル
#### テスト
- `tests/TestCase/Service/WxrParserServiceTest.php`（骨格のみ）

---

### 残件

#### v1.0 向け（必須）
- [ ] Docker コンテナ内でユニットテストを実行して動作確認
- [ ] `WxrParserServiceTest` にテストケースを追加（正常系・異常系・マルチ著者等）
- [ ] 実DBを使った `WpImportService` の結合テスト（実際にWXRを取り込んで記事・カテゴリ・タグが正しく登録されるか確認）

#### v1.1以降（テスト拡充）
- [ ] `WpImportServiceTest` の作成（appendLog / getLogLines のユニットテスト含む）
- [ ] `WpImportsControllerTest` の作成（get_log レスポンス・delete_all 等）

#### 将来対応（大量データ・非同期処理）
- [ ] Chunked / resumable import（大量データ対応・分割実行）
- [ ] `warning_log_path` / `error_log_path` の活用（警告・エラーの詳細ダウンロード）

#### サービス分離（設計推奨・低優先度）
- [ ] `PageImporterService` の分離（現在 `WpImportService` に内包）
- [ ] `BlogPostImporterService` の分離（現在 `WpImportService` に内包）
- [ ] `WxrReader` utility（SimpleXML/XMLReader 低レイヤ共通化）
- [ ] `WxrMapper` utility（WXR item → baserCMS 用配列変換の切り出し）

---

## BcWpExport

詳細は [BcWpExport/docs/progress.md](../../BcWpExport/docs/progress.md) を参照。

### 実装済み（概要）

#### プラグイン基盤
- `BcWpExportPlugin.php` / `config.php` / `setting.php` / `routes.php`
- Migration: `CreateBcWpExportJobs`
- Entity: `BcWpExportJob` / Table: `BcWpExportJobsTable`

#### コントローラ（`WpExportsController`）
- `index` / `create` / `download` / `delete` / `delete_all` — 全アクション実装済み

#### サービス
- `WxrWriterService` — DOMDocument を使ったWXR XML生成（名前空間・CDATA・整形・postmeta対応）
- `WpExportService` — 設定正規化・固定ページ/記事収集・item変換・アタッチメント出力・URL絶対化
- `WpExportAdminService` — 一覧表示用 view 変数生成・ジョブ削除

#### フロントエンド
- `templates/Admin/WpExports/index.php`（フィルタ・オプション・結果表示・履歴管理）
- `webroot/js/admin/wp_export.js`（AJAX通信・DOM更新・チェックボックス管理）
- `webroot/css/admin/wp_export.css`

#### テスト
- `tests/TestCase/Service/WxrWriterServiceTest.php`（2件のテストケース実装済み）

### 残件（概要）

#### v1.0 向け（必須）
- [ ] Docker コンテナ内でユニットテストを実行（`WxrWriterServiceTest` の実行確認）
- [ ] `WxrWriterServiceTest` にケースを追加（ページ親子関係・アタッチメント・URLエッジケース）

#### 将来対応
- [ ] `WpExportServiceTest` / `WpExportsControllerTest` の作成
- [ ] `status` / `cancel` アクション（非同期化対応時）
- [ ] Chunked / resumable export

---

## 実装上の共通メモ

- `WpImportService` は解析済みジョブを読み込み、post/page ごとに実データへ保存する構成（同期処理）。
- `WpImportService` は import 実行時に各アイテムの action と message をCSVへ書き出す。
- ログファイルは `TMP . 'bc_wp_import' . DS . $token . '.log'` に書き出す。エラー・スキップは毎回、成功は100件ごとに出力（大量データのパフォーマンス考慮）。
- `get_log` アクションはトークン形式（`/^[a-f0-9]+$/`）で検証し、不正なトークンは空配列を返す。
- JS の `showSection('js-progress-section')` 呼び出し時にポーリング開始、他セクションへの切替時に停止。
- InflectedRoute は URL の camelCase 変換を行わないため、全アクションは snake_case で定義する必要がある。
- `WpExportService` は DB から固定ページとブログ記事を収集して WXR を組み立てる。同期一括処理のため、ジョブは即 `completed` になる。
- `WxrWriterService` の XML 生成は DOMDocument + namespaced element を使用。
- `include_media_urls` が有効な場合はアイキャッチを `attachment` post_type として items に追加し、`_thumbnail_id` postmeta を付与する。
- `source_summary` のキーは `pages` / `posts` / `categories` / `tags` / `authors` / `total_items`。
- 履歴テーブルの日時は `Timestamp` Behavior が `created` / `modified` を自動セット。テンプレート側で `$job->created->format('Y/m/d H:i')` にて表示。
- BcWpImportJobsTable に `addBehavior('Timestamp')` を追加済み（欠落が原因で created が null になっていた問題を修正済み）。
- JS の IIFE パターン（`(function(){'use strict';...})();`）はファイル内に1つのみ。大規模置換後は `grep -c "^})();"` で確認すること。
- エディタ上の静的解析エラーなし。Docker コンテナ内での実行テストは未実施。
