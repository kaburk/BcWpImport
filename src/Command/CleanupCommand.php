<?php
declare(strict_types=1);

namespace BcWpImport\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * 期限切れの WordPress インポートジョブと関連ファイルを削除する
 *
 * 実行例:
 *   bin/cake BcWpImport.cleanup
 *   bin/cake BcWpImport.cleanup --dry-run
 */
class CleanupCommand extends Command
{
    public static function defaultName(): string
    {
        return 'BcWpImport.cleanup';
    }

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('期限切れのWordPressインポートジョブと関連ファイルを削除する。');
        $parser->addOption('dry-run', [
            'help' => '実際の削除を行わず、対象件数のみ表示する。',
            'boolean' => true,
            'default' => false,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dryRun = (bool) $args->getOption('dry-run');

        /** @var \BcWpImport\Model\Table\BcWpImportJobsTable $jobs */
        $jobs = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $expiredJobs = $jobs->find()
            ->where(['expires_at <' => new DateTime()])
            ->all();

        $total = $expiredJobs->count();
        if ($total === 0) {
            $io->out('削除対象のジョブはありませんでした。');
            return self::CODE_SUCCESS;
        }

        if ($dryRun) {
            $io->out(sprintf('[dry-run] 削除対象のジョブ: %d 件', $total));
            return self::CODE_SUCCESS;
        }

        $deletedJobs = 0;
        $deletedFiles = 0;

        foreach ($expiredJobs as $job) {
            $deletedFiles += $this->deleteFileIfExists($job->wxr_path ?? null);
            $deletedFiles += $this->deleteFileIfExists($job->report_csv_path ?? null);
            $deletedFiles += $this->deleteFileIfExists($job->warning_log_path ?? null);
            $deletedFiles += $this->deleteFileIfExists($job->error_log_path ?? null);
            $deletedFiles += $this->deleteFileIfExists($this->buildPollingLogPath((string) $job->job_token));

            if ($jobs->delete($job)) {
                $deletedJobs++;
            }
        }

        $io->out(sprintf(
            'クリーンアップ完了: ジョブ %d 件・ファイル %d 件を削除しました。',
            $deletedJobs,
            $deletedFiles
        ));

        return self::CODE_SUCCESS;
    }

    protected function buildPollingLogPath(string $token): string
    {
        return TMP . 'bc_wp_import' . DS . $token . '.log';
    }

    protected function deleteFileIfExists(?string $path): int
    {
        if (!$path || !file_exists($path)) {
            return 0;
        }
        unlink($path);
        return 1;
    }
}
