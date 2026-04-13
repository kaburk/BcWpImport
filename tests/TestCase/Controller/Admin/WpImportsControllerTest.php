<?php
declare(strict_types=1);

namespace BcWpImport\Test\TestCase\Controller\Admin;

use BaserCore\Test\Scenario\InitAppScenario;
use BaserCore\Test\Scenario\RootContentScenario;
use BaserCore\TestSuite\BcTestCase;
use BcBlog\Test\Scenario\BlogContentScenario;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use CakephpFixtureFactories\Scenario\ScenarioAwareTrait;

class WpImportsControllerTest extends BcTestCase
{
    use IntegrationTestTrait;
    use ScenarioAwareTrait;

    private array $tempPaths = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->loadFixtureScenario(InitAppScenario::class);
        $this->loadFixtureScenario(RootContentScenario::class, 1, 1, null, 'root', '/');
        $this->loadFixtureScenario(BlogContentScenario::class, 101, 1, 1, 'wp-import-test-blog', '/wp-import-test-blog/');
        TableRegistry::getTableLocator()->get('BaserCore.Contents')->recover();
    }

    public function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function testIndex(): void
    {
        $job = $this->createJob('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', [
            'status' => 'waiting',
            'phase' => 'review',
        ]);

        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-import/wp_imports/index'));
        $this->get('/baser/admin/bc-wp-import/wp_imports/index');

        $this->assertResponseOk();
        $vars = $this->_controller->viewBuilder()->getVars();
        $this->assertArrayHasKey('pendingJobs', $vars);
        $this->assertArrayHasKey('historyJobs', $vars);
        $this->assertArrayHasKey('blogOptions', $vars);
        $this->assertArrayHasKey('contentFolderOptions', $vars);
        $this->assertArrayHasKey('userOptions', $vars);
        $this->assertNotEmpty($vars['pendingJobs']);
        $this->assertEquals($job->job_token, $vars['pendingJobs'][0]->job_token);
    }

    public function testGetLogReturnsLines(): void
    {
        $token = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $logPath = TMP . 'bc_wp_import' . DS . $token . '.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0777, true);
        }
        file_put_contents($logPath, "10:00:00 [INFO] line1\n10:00:01 [INFO] line2\n");
        $this->tempPaths[] = $logPath;

        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-import/wp_imports/get_log?token=' . $token));
        $this->get('/baser/admin/bc-wp-import/wp_imports/get_log?token=' . $token);

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $data = json_decode((string) $this->_response->getBody(), true);
        $this->assertSame(['10:00:00 [INFO] line1', '10:00:01 [INFO] line2'], $data['lines']);
    }

    public function testGetLogReturnsEmptyWhenTokenInvalid(): void
    {
        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-import/wp_imports/get_log?token=INVALID-TOKEN'));
        $this->get('/baser/admin/bc-wp-import/wp_imports/get_log?token=INVALID-TOKEN');

        $this->assertResponseOk();
        $data = json_decode((string) $this->_response->getBody(), true);
        $this->assertSame(['lines' => []], $data);
    }

    public function testCancel(): void
    {
        $token = 'cccccccccccccccccccccccccccccccc';
        $this->createJob($token, [
            'status' => 'processing',
            'phase' => 'import',
        ]);

        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-import/wp_imports/cancel'));
        $this->enableSecurityToken();
        $this->enableCsrfToken();
        $this->post('/baser/admin/bc-wp-import/wp_imports/cancel', ['token' => $token]);

        $this->assertResponseOk();
        $data = json_decode((string) $this->_response->getBody(), true);
        $this->assertSame('cancelled', $data['result']['status']);

        $saved = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs')
            ->find()->where(['job_token' => $token])->firstOrFail();
        $this->assertSame('cancelled', $saved->status);
        $this->assertNotNull($saved->ended_at);
    }

    public function testDeleteAll(): void
    {
        $token1 = 'dddddddddddddddddddddddddddddddd';
        $token2 = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
        $this->createJob($token1);
        $this->createJob($token2);

        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-import/wp_imports/delete_all'));
        $this->enableSecurityToken();
        $this->enableCsrfToken();
        $this->post('/baser/admin/bc-wp-import/wp_imports/delete_all', ['tokens' => [$token1, $token2]]);

        $this->assertResponseOk();
        $data = json_decode((string) $this->_response->getBody(), true);
        $this->assertTrue($data['success']);

        $count = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs')
            ->find()->where(['job_token IN' => [$token1, $token2]])->count();
        $this->assertSame(0, $count);
    }

    public function testDownloadReport(): void
    {
        $token = 'ffffffffffffffffffffffffffffffff';
        $reportPath = TMP . 'bc_wp_import' . DS . 'report_' . $token . '.csv';
        if (!is_dir(dirname($reportPath))) {
            mkdir(dirname($reportPath), 0777, true);
        }
        file_put_contents($reportPath, "col1,col2\nvalue1,value2\n");
        $this->tempPaths[] = $reportPath;

        $this->createJob($token, [
            'report_csv_path' => $reportPath,
            'status' => 'completed',
            'phase' => 'import',
        ]);

        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-import/wp_imports/download_report?token=' . $token));
        $this->get('/baser/admin/bc-wp-import/wp_imports/download_report?token=' . $token);

        $this->assertResponseOk();
        $this->assertHeaderContains('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertHeaderContains('Content-Disposition', 'attachment; filename="import-report.csv"');
        $this->assertStringContainsString('col1,col2', (string) $this->_response->getBody());
    }

    private function createJob(string $token, array $overrides = [])
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $wxrPath = TMP . 'bc_wp_import' . DS . $token . '.xml';
        if (!is_dir(dirname($wxrPath))) {
            mkdir(dirname($wxrPath), 0777, true);
        }
        file_put_contents($wxrPath, '<rss version="2.0"><channel /></rss>');
        $this->tempPaths[] = $wxrPath;

        $data = array_merge([
            'job_token' => $token,
            'source_filename' => $token . '.xml',
            'wxr_path' => $wxrPath,
            'status' => 'waiting',
            'phase' => 'review',
            'mode' => 'strict',
            'import_target' => 'all',
            'author_strategy' => 'match',
            'slug_strategy' => 'suffix',
            'publish_strategy' => 'keep',
            'url_replace_mode' => 'keep',
            'total_items' => 0,
            'analyzable_items' => 0,
            'importable_items' => 0,
            'processed' => 0,
            'success_count' => 0,
            'skip_count' => 0,
            'warning_count' => 0,
            'error_count' => 0,
            'unsupported_count' => 0,
            'expires_at' => FrozenTime::now()->addDays(1),
        ], $overrides);

        $job = $jobsTable->newEntity($data);
        return $jobsTable->saveOrFail($job);
    }
}
