<?php
declare(strict_types=1);

namespace BcWpImport\Test\TestCase\Service;

use BaserCore\Test\Scenario\InitAppScenario;
use BaserCore\Test\Scenario\RootContentScenario;
use BaserCore\TestSuite\BcTestCase;
use BcBlog\Test\Scenario\BlogContentScenario;
use BcWpImport\Service\WpImportService;
use BcWpImport\Service\WxrParserService;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;
use CakephpFixtureFactories\Scenario\ScenarioAwareTrait;

class WpImportServiceTest extends BcTestCase
{
    use ScenarioAwareTrait;

    private WpImportService $service;

    private array $tempPaths = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->loadFixtureScenario(InitAppScenario::class);
        $this->loadFixtureScenario(RootContentScenario::class, 1, 1, null, 'root', '/');
        $this->loadFixtureScenario(BlogContentScenario::class, 101, 1, 1, 'wp-import-test-blog', '/wp-import-test-blog/');

        TableRegistry::getTableLocator()->get('BaserCore.Contents')->recover();

        $this->loginAdmin($this->getRequest('/'));
        $this->service = new WpImportService(new WxrParserService());
    }

    public function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        unset($this->service);

        parent::tearDown();
    }

    public function testImportJobCreatesPagesPostsAndReport(): void
    {
        $wxrPath = TMP . 'bc_wp_import' . DS . 'wp_import_service_test.xml';
        if (!is_dir(dirname($wxrPath))) {
            mkdir(dirname($wxrPath), 0777, true);
        }
        $this->tempPaths[] = $wxrPath;

        file_put_contents($wxrPath, <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <title>Import Test</title>
        <language>ja</language>
        <wp:wxr_version>1.2</wp:wxr_version>
        <item>
            <title>Company</title>
            <content:encoded><![CDATA[<p><a href="https://old.example.com/company">Company</a></p>]]></content:encoded>
            <excerpt:encoded><![CDATA[]]></excerpt:encoded>
            <wp:post_type>page</wp:post_type>
            <wp:status>publish</wp:status>
            <wp:post_name>company</wp:post_name>
            <wp:post_date>2026-04-01 10:00:00</wp:post_date>
            <wp:post_author>admin</wp:post_author>
            <wp:post_id>100</wp:post_id>
            <wp:post_parent>0</wp:post_parent>
        </item>
        <item>
            <title>History</title>
            <content:encoded><![CDATA[<p>History body</p>]]></content:encoded>
            <excerpt:encoded><![CDATA[]]></excerpt:encoded>
            <wp:post_type>page</wp:post_type>
            <wp:status>publish</wp:status>
            <wp:post_name>history</wp:post_name>
            <wp:post_date>2026-04-01 11:00:00</wp:post_date>
            <wp:post_author>admin</wp:post_author>
            <wp:post_id>101</wp:post_id>
            <wp:post_parent>100</wp:post_parent>
        </item>
        <item>
            <title>Launch News</title>
            <content:encoded><![CDATA[<p><img src="https://old.example.com/files/news.jpg"></p>]]></content:encoded>
            <excerpt:encoded><![CDATA[https://old.example.com/news-excerpt]]></excerpt:encoded>
            <wp:post_type>post</wp:post_type>
            <wp:status>publish</wp:status>
            <wp:post_name>launch-news</wp:post_name>
            <wp:post_date>2026-04-02 09:00:00</wp:post_date>
            <wp:post_author>admin</wp:post_author>
            <wp:post_id>102</wp:post_id>
            <wp:post_parent>0</wp:post_parent>
            <category domain="category" nicename="news"><![CDATA[News]]></category>
            <category domain="post_tag" nicename="release"><![CDATA[Release]]></category>
        </item>
    </channel>
</rss>
XML);

        $analysis = (new WxrParserService())->analyze($wxrPath);
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $job = $jobsTable->newEntity([
            'job_token' => 'wpimporttesttoken0000000000000001',
            'source_filename' => 'wp_import_service_test.xml',
            'wxr_path' => $wxrPath,
            'status' => 'waiting',
            'phase' => 'review',
            'mode' => 'strict',
            'import_target' => 'all',
            'blog_content_id' => 101,
            'content_folder_id' => 1,
            'author_strategy' => 'assign',
            'author_assign_user_id' => 1,
            'slug_strategy' => 'suffix',
            'publish_strategy' => 'keep',
            'url_replace_mode' => 'replace',
            'url_replace_from' => 'https://old.example.com',
            'url_replace_to' => 'https://new.example.com',
            'parsed_summary' => json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'import_settings' => json_encode([
                'mode' => 'strict',
                'import_target' => 'all',
                'blog_content_id' => 101,
                'content_folder_id' => 1,
                'author_strategy' => 'assign',
                'author_assign_user_id' => 1,
                'slug_strategy' => 'suffix',
                'publish_strategy' => 'keep',
                'url_replace_mode' => 'replace',
                'url_replace_from' => 'https://old.example.com',
                'url_replace_to' => 'https://new.example.com',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'total_items' => 3,
            'analyzable_items' => 3,
            'importable_items' => 3,
            'expires_at' => FrozenTime::now()->addDays(1),
        ]);
        $jobsTable->saveOrFail($job);

        $result = $this->service->importJob('wpimporttesttoken0000000000000001');

        $this->assertEquals('completed', $result['status'], json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertEquals('import', $result['phase']);
        $this->assertEquals(3, $result['processed']);
        $this->assertEquals(3, $result['success_count']);
        $this->assertEquals(0, $result['skip_count']);
        $this->assertEquals(0, $result['error_count']);
        $this->assertTrue($result['has_report']);

        $contentsTable = TableRegistry::getTableLocator()->get('BaserCore.Contents');
        $pagesTable = TableRegistry::getTableLocator()->get('BaserCore.Pages');
        $postsTable = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        $categoriesTable = TableRegistry::getTableLocator()->get('BcBlog.BlogCategories');
        $tagsTable = TableRegistry::getTableLocator()->get('BcBlog.BlogTags');

        $folderContent = $contentsTable->find()->where([
            'type' => 'ContentFolder',
            'parent_id' => 1,
            'name' => 'company',
        ])->first();
        $this->assertNotNull($folderContent);

        $indexContent = $contentsTable->find()->where([
            'type' => 'Page',
            'parent_id' => $folderContent->id,
            'name' => 'index',
        ])->first();
        $this->assertNotNull($indexContent);
        $indexPage = $pagesTable->get((int) $indexContent->entity_id);
        $this->assertStringContainsString('https://new.example.com/company', (string) $indexPage->contents);

        $childContent = $contentsTable->find()->where([
            'type' => 'Page',
            'parent_id' => $folderContent->id,
            'name' => 'history',
        ])->first();
        $this->assertNotNull($childContent);
        $childPage = $pagesTable->get((int) $childContent->entity_id);
        $this->assertStringContainsString('History body', (string) $childPage->contents);

        $post = $postsTable->find()
            ->contain(['BlogTags'])
            ->where([
                'blog_content_id' => 101,
                'name' => 'launch-news',
            ])
            ->first();
        $this->assertNotNull($post);
        $this->assertStringContainsString('https://new.example.com/files/news.jpg', (string) $post->detail);
        $this->assertStringContainsString('https://new.example.com/news-excerpt', (string) $post->content);
        $this->assertCount(1, $post->blog_tags);
        $this->assertEquals('Release', $post->blog_tags[0]->name);

        $category = $categoriesTable->find()->where([
            'blog_content_id' => 101,
            'name' => 'news',
        ])->first();
        $this->assertNotNull($category);
        $this->assertEquals('News', $category->title);

        $tag = $tagsTable->find()->where(['name' => 'Release'])->first();
        $this->assertNotNull($tag);

        $savedJob = $jobsTable->find()->where(['job_token' => 'wpimporttesttoken0000000000000001'])->firstOrFail();
        $this->assertNotEmpty($savedJob->report_csv_path);
        $this->assertFileExists((string) $savedJob->report_csv_path);
        $this->tempPaths[] = (string) $savedJob->report_csv_path;

        $logPath = TMP . 'bc_wp_import' . DS . 'wpimporttesttoken0000000000000001.log';
        $this->assertFileExists($logPath);
        $this->tempPaths[] = $logPath;

        $logs = $this->service->getLogLines('wpimporttesttoken0000000000000001');
        $this->assertNotEmpty($logs);
        $this->assertStringContainsString('インポート完了', end($logs));
    }
}
