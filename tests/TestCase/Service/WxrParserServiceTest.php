<?php
declare(strict_types=1);

namespace BcWpImport\Test\TestCase\Service;

use BaserCore\TestSuite\BcTestCase;
use BcWpImport\Service\WxrParserService;

class WxrParserServiceTest extends BcTestCase
{
    public function testAnalyzeThrowsWhenFileNotFound(): void
    {
        $service = new WxrParserService();

        $this->expectException(\InvalidArgumentException::class);
        $service->analyze('/tmp/not-found-file.xml');
    }

    public function testAnalyze(): void
    {
        $service = new WxrParserService();
        $filePath = TMP . 'bc_wp_import_test.xml';
        file_put_contents($filePath, <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
        xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
        xmlns:content="http://purl.org/rss/1.0/modules/content/"
        xmlns:wfw="http://wellformedweb.org/CommentAPI/"
        xmlns:dc="http://purl.org/dc/elements/1.1/"
        xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <title>Example</title>
        <language>ja</language>
        <wp:wxr_version>1.2</wp:wxr_version>
        <item>
            <title>Post A</title>
            <wp:post_type>post</wp:post_type>
            <wp:post_author>admin</wp:post_author>
            <category domain="category" nicename="news"><![CDATA[News]]></category>
            <category domain="post_tag" nicename="release"><![CDATA[Release]]></category>
        </item>
        <item>
            <title>Page A</title>
            <wp:post_type>page</wp:post_type>
            <wp:post_author>editor</wp:post_author>
        </item>
        <item>
            <title>Attachment A</title>
            <wp:post_type>attachment</wp:post_type>
            <wp:post_author>admin</wp:post_author>
        </item>
    </channel>
</rss>
XML);

        $result = $service->analyze($filePath);

        unlink($filePath);

        $this->assertEquals('1.2', $result['wxr_version']);
        $this->assertEquals('Example', $result['channel_title']);
        $this->assertEquals('ja', $result['language']);
        $this->assertEquals(1, $result['item_counts']['post']);
        $this->assertEquals(1, $result['item_counts']['page']);
        $this->assertEquals(1, $result['item_counts']['attachment']);
        $this->assertEquals(['admin', 'editor'], $result['authors']);
        $this->assertEquals(['News'], $result['categories']);
        $this->assertEquals(['Release'], $result['tags']);
        $this->assertEquals(['attachment'], $result['unsupported_types']);
    }

    public function testParseItems(): void
    {
        $service = new WxrParserService();
        $filePath = TMP . 'bc_wp_import_items_test.xml';
        file_put_contents($filePath, <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
            xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
            xmlns:content="http://purl.org/rss/1.0/modules/content/"
            xmlns:dc="http://purl.org/dc/elements/1.1/"
            xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
            <item>
                <title>Post A</title>
                <content:encoded><![CDATA[<p>Body</p>]]></content:encoded>
                <excerpt:encoded><![CDATA[Summary]]></excerpt:encoded>
                <wp:post_type>post</wp:post_type>
                <wp:status>publish</wp:status>
                <wp:post_name>post-a</wp:post_name>
                <wp:post_date>2026-04-08 10:00:00</wp:post_date>
                <wp:post_author>admin</wp:post_author>
                <category domain="category" nicename="news"><![CDATA[News]]></category>
                <category domain="post_tag" nicename="release"><![CDATA[Release]]></category>
            </item>
    </channel>
</rss>
XML);

        $items = $service->parseItems($filePath);
        unlink($filePath);

        $this->assertCount(1, $items);
        $this->assertEquals('post', $items[0]['post_type']);
        $this->assertEquals('post-a', $items[0]['post_name']);
        $this->assertEquals('<p>Body</p>', $items[0]['post_content']);
        $this->assertEquals('Summary', $items[0]['post_excerpt']);
        $this->assertEquals('News', $items[0]['categories'][0]['label']);
        $this->assertEquals('Release', $items[0]['tags'][0]['label']);
    }

    public function testAnalyzeThrowsOnInvalidXml(): void
    {
        $service = new WxrParserService();
        $filePath = TMP . 'bc_wp_import_invalid.xml';
        file_put_contents($filePath, 'THIS IS NOT XML <<<>>>');

        try {
            $service->analyze($filePath);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        } finally {
            unlink($filePath);
        }
    }

    public function testAnalyzeMultipleAuthorsSorted(): void
    {
        $service = new WxrParserService();
        $filePath = TMP . 'bc_wp_import_authors.xml';
        file_put_contents($filePath, <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
        xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <wp:wxr_version>1.2</wp:wxr_version>
        <item>
            <wp:post_type>post</wp:post_type>
            <wp:post_author>charlie</wp:post_author>
        </item>
        <item>
            <wp:post_type>post</wp:post_type>
            <wp:post_author>alice</wp:post_author>
        </item>
        <item>
            <wp:post_type>post</wp:post_type>
            <wp:post_author>bob</wp:post_author>
        </item>
        <item>
            <wp:post_type>post</wp:post_type>
            <wp:post_author>alice</wp:post_author>
        </item>
    </channel>
</rss>
XML);

        $result = $service->analyze($filePath);
        unlink($filePath);

        // 重複なし・アルファベット順
        $this->assertEquals(['alice', 'bob', 'charlie'], $result['authors']);
    }

    public function testParseItemsWithHierarchy(): void
    {
        $service = new WxrParserService();
        $filePath = TMP . 'bc_wp_import_hierarchy.xml';
        file_put_contents($filePath, <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
        xmlns:content="http://purl.org/rss/1.0/modules/content/"
        xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
        xmlns:dc="http://purl.org/dc/elements/1.1/"
        xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Parent Page</title>
            <wp:post_type>page</wp:post_type>
            <wp:post_id>10</wp:post_id>
            <wp:post_parent>0</wp:post_parent>
            <wp:post_name>parent</wp:post_name>
            <wp:status>publish</wp:status>
        </item>
        <item>
            <title>Child Page</title>
            <wp:post_type>page</wp:post_type>
            <wp:post_id>20</wp:post_id>
            <wp:post_parent>10</wp:post_parent>
            <wp:post_name>child</wp:post_name>
            <wp:status>publish</wp:status>
        </item>
    </channel>
</rss>
XML);

        $items = $service->parseItems($filePath);
        unlink($filePath);

        $this->assertCount(2, $items);

        $parent = $items[0];
        $this->assertEquals(10, $parent['wp_post_id']);
        $this->assertEquals(0, $parent['wp_post_parent']);
        $this->assertEquals('parent', $parent['post_name']);

        $child = $items[1];
        $this->assertEquals(20, $child['wp_post_id']);
        $this->assertEquals(10, $child['wp_post_parent']);
        $this->assertEquals('child', $child['post_name']);
    }
}
