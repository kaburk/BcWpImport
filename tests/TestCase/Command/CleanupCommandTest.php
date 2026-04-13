<?php
declare(strict_types=1);

namespace BcWpImport\Test\TestCase\Command;

use BaserCore\TestSuite\BcTestCase;
use Cake\Command\Command;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;

class CleanupCommandTest extends BcTestCase
{
    use ConsoleIntegrationTestTrait;

    private array $tempPaths = [];

    public function tearDown(): void
    {
        TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs')
            ->deleteAll(['job_token LIKE' => 'cleanupcommandtest%']);

        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testExecuteReturnsWhenNoExpiredJobs(): void
    {
        $this->exec('BcWpImport.cleanup');

        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertOutputContains('削除対象のジョブはありませんでした。');
    }

    public function testExecuteDryRunDoesNotDeleteExpiredJobs(): void
    {
        $wxrPath = $this->createTempFile('cleanupcommandtest-dryrun.xml', '<rss/>');
        $job = $this->createJob('cleanupcommandtest-dryrun', [
            'expires_at' => FrozenTime::now()->addDays(-1),
            'wxr_path' => $wxrPath,
        ]);

        $this->exec('BcWpImport.cleanup --dry-run');

        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertOutputContains('[dry-run] 削除対象のジョブ: 1 件');
        $this->assertFileExists($wxrPath);
        $this->assertNotNull(TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs')->find()
            ->where(['id' => $job->id])->first());
    }

    public function testExecuteDeletesExpiredJobsAndFiles(): void
    {
        $wxrPath = $this->createTempFile('cleanupcommandtest-expired.xml', '<rss/>');
        $reportPath = $this->createTempFile('cleanupcommandtest-expired.csv', 'report');
        $warningPath = $this->createTempFile('cleanupcommandtest-expired-warning.log', 'warning');
        $errorPath = $this->createTempFile('cleanupcommandtest-expired-error.log', 'error');
        $pollingLogPath = $this->createPollingLogFile('cleanupcommandtest-expired');
        $futureWxrPath = $this->createTempFile('cleanupcommandtest-future.xml', '<rss/>');

        $expiredJob = $this->createJob('cleanupcommandtest-expired', [
            'expires_at' => FrozenTime::now()->addDays(-1),
            'wxr_path' => $wxrPath,
            'report_csv_path' => $reportPath,
            'warning_log_path' => $warningPath,
            'error_log_path' => $errorPath,
        ]);
        $futureJob = $this->createJob('cleanupcommandtest-future', [
            'expires_at' => FrozenTime::now()->addDays(1),
            'wxr_path' => $futureWxrPath,
        ]);

        $this->exec('BcWpImport.cleanup');

        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertOutputContains('クリーンアップ完了: ジョブ 1 件・ファイル 5 件を削除しました。');
        $this->assertFileDoesNotExist($wxrPath);
        $this->assertFileDoesNotExist($reportPath);
        $this->assertFileDoesNotExist($warningPath);
        $this->assertFileDoesNotExist($errorPath);
        $this->assertFileDoesNotExist($pollingLogPath);
        $this->assertFileExists($futureWxrPath);

        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $this->assertNull($jobsTable->find()->where(['id' => $expiredJob->id])->first());
        $this->assertNotNull($jobsTable->find()->where(['id' => $futureJob->id])->first());
    }

    private function createJob(string $token, array $overrides = [])
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $data = array_merge([
            'job_token' => $token,
            'status' => 'completed',
            'phase' => 'import',
            'mode' => 'strict',
            'import_target' => 'all',
            'author_strategy' => 'match',
            'slug_strategy' => 'suffix',
            'publish_strategy' => 'keep',
            'url_replace_mode' => 'keep',
            'source_filename' => $token . '.xml',
            'wxr_path' => $this->createTempFile($token . '-source.xml', '<rss/>'),
            'total_items' => 0,
            'analyzable_items' => 0,
            'importable_items' => 0,
            'processed' => 0,
            'success_count' => 0,
            'skip_count' => 0,
            'warning_count' => 0,
            'error_count' => 0,
            'unsupported_count' => 0,
            'expires_at' => FrozenTime::now()->addDays(-1),
        ], $overrides);

        return $jobsTable->saveOrFail($jobsTable->newEntity($data));
    }

    private function createTempFile(string $filename, string $contents): string
    {
        $dir = TMP . 'bc_wp_import' . DS;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . $filename;
        file_put_contents($path, $contents);
        $this->tempPaths[] = $path;

        return $path;
    }

    private function createPollingLogFile(string $token): string
    {
        return $this->createTempFile($token . '.log', 'polling log');
    }
}
