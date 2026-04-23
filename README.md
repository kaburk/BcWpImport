# BcWpImport plugin for baserCMS

BcWpImport は、WordPress からエクスポートした WXR（WordPress eXtended RSS）ファイルを baserCMS5 へ取り込むプラグインです。
管理画面から WXR ファイルをアップロードし、内容を確認したうえでブログ記事や固定ページを一括登録できます。

## 取り込める対象

| WordPress 側 | baserCMS 側 | 備考 |
|---|---|---|
| post（投稿） | ブログ記事 | 取込先ブログの指定が必要 |
| page（固定ページ） | 固定ページ | コンテンツ配置先は要調整 |
| category（カテゴリー） | ブログカテゴリー | 対象ブログ配下へ自動作成 |
| post_tag（タグ） | ブログタグ | 対象ブログ配下へ自動作成 |
| author（著者） | baserCMS ユーザー | 同名割当または一括割当 |

### 初版では対象外

- カスタム投稿タイプ
- コメント
- attachment の完全取込
- WooCommerce など特定プラグイン由来のデータ

## WXR ファイルについて

WXR（WordPress eXtended RSS）は WordPress が標準で書き出すエクスポート形式です。
拡張子は `.xml` で、内部は RSS 2.0 ベースの XML 構造になっています。

### WXR ファイルの入手方法

1. WordPress の管理画面にログインする
2. **ツール → エクスポート** を開く
3. エクスポート対象を選択する（「すべてのコンテンツ」を選ぶのが最初は簡単）
4. **エクスポートファイルをダウンロード** ボタンをクリックする

ダウンロードされる `.xml` ファイルがそのまま本プラグインへアップロードできます。

### テスト用 WXR ファイルの入手・作成

#### WordPress 公式テストデータ

WordPress テーマ開発や動作確認用に公式が配布しているテストデータセットがあります。
記事・固定ページ・カテゴリー・タグ・著者など多様なパターンが含まれており、本プラグインの動作確認に最適です。

| リポジトリ | 内容 |
|---|---|
| [wordpress/theme-test-data](https://github.com/WordPress/theme-test-data) | WordPress 公式テーマユニットテストデータ（英語） |
| [jawordpressorg/theme-test-data-ja](https://github.com/jawordpressorg/theme-test-data-ja) | 日本語版テーマユニットテストデータ |

どちらも `themeunittestdata.wordpress.xml`（または同等のファイル）をダウンロードして、そのまま本プラグインへアップロードできます。

#### 最小構成の手動作成

本番データを使わずにシンプルに動作を確認したい場合は、以下の最小構成の XML を手動で作成できます。

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:wp="http://wordpress.org/export/1.2/">
  <channel>
    <title>テストサイト</title>
    <language>ja</language>
    <wp:wxr_version>1.2</wp:wxr_version>
    <item>
      <title>サンプル投稿</title>
      <link>https://example.com/sample-post/</link>
      <pubDate>Thu, 01 Jan 2026 00:00:00 +0000</pubDate>
      <dc:creator>admin</dc:creator>
      <content:encoded><![CDATA[本文テキストをここに書く。]]></content:encoded>
      <wp:post_date>2026-01-01 00:00:00</wp:post_date>
      <wp:post_name>sample-post</wp:post_name>
      <wp:status>publish</wp:status>
      <wp:post_type>post</wp:post_type>
      <category domain="category" nicename="news"><![CDATA[News]]></category>
      <category domain="post_tag" nicename="release"><![CDATA[Release]]></category>
    </item>
    <item>
      <title>サンプルページ</title>
      <link>https://example.com/sample-page/</link>
      <dc:creator>admin</dc:creator>
      <content:encoded><![CDATA[固定ページの本文。]]></content:encoded>
      <wp:post_date>2026-01-01 00:00:00</wp:post_date>
      <wp:post_name>sample-page</wp:post_name>
      <wp:status>publish</wp:status>
      <wp:post_type>page</wp:post_type>
    </item>
  </channel>
</rss>
```

このファイルを `.xml` として保存し、管理画面からアップロードすると解析・取込の動作を確認できます。

## 主な機能

- WXR ファイルのアップロード
- 解析（アイテム件数・著者・カテゴリー・タグ一覧の抽出）
- 取込前レビュー設定（著者マッピング・スラッグ競合・URL置換・公開状態）
- ブログ記事・固定ページの一括登録
- 処理中ログのリアルタイム表示（ポーリング方式・ターミナル風ビューアー）
- インジケーター型プログレスバーと経過時間カウンター
- アイテム単位の取込結果を CSV でダウンロード
- ジョブ管理（未完了・完了 / 失敗 / キャンセル）
- 個別削除・チェックボックスによる一括削除（未完了・履歴の両テーブル）

## 取込オプション

| オプション | 選択肢 |
|---|---|
| 取込対象 | 全件 / 投稿のみ / 固定ページのみ |
| 著者マッピング | 同名ユーザーへ割当 / 指定ユーザーへ一括割当 |
| スラッグ重複時 | 連番付与（suffix）/ スキップ / 上書き |
| 公開状態 | 元の状態を維持 / すべて下書きで取込 |
| URL置換 | WordPress URL のまま維持 / 指定ドメインへ置換 |

## 管理画面

- メニュー名: **WordPressインポート**
- URL: `/baser/admin/bc-wp-import/wp_imports/index`

## 動作環境

- baserCMS 5.x
- PHP 8.1 以上
- CakePHP 5.x

## インストール

1. `plugins/BcWpImport/` に本プラグインを配置する
2. 管理画面の **プラグイン管理** から「WordPressインポート」を有効化する

## 設定

`config/setting.php` で以下を変更できます。

| キー | 初期値 | 説明 |
|---|---|---|
| `BcWpImport.jobExpireDays` | `3` | ジョブ（アップロードファイル含む）の保持日数 |
| `BcWpImport.batchSize` | `100` | 一度に処理するアイテム件数 |

## 進捗・残件

[docs/progress.md](docs/progress.md) を参照してください。

## ライセンス

MIT License. 詳細は `LICENSE.txt` を参照してください。
