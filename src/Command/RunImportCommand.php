<?php
declare(strict_types=1);

namespace BcWpImport\Command;

use BcWpImport\Service\WpImportService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Log\Log;

class RunImportCommand extends Command
{
    public static function defaultName(): string
    {
        return 'bc_wp_import.run_import';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('BcWpImport のインポートジョブをバックグラウンド実行します。')
            ->addArgument('token', [
                'help' => '実行対象ジョブのトークン',
                'required' => true,
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $token = (string) $args->getArgument('token');

        try {
            @set_time_limit(0);
            Log::info(sprintf('[BcWpImport] background_job_started token=%s', $token), 'wp_import');
            $service = new WpImportService();
            $service->importJob($token);
            Log::info(sprintf('[BcWpImport] background_job_finished token=%s', $token), 'wp_import');
            return self::CODE_SUCCESS;
        } catch (\Throwable $e) {
            try {
                $service = new WpImportService();
                $service->markJobFailed($token, $e->getMessage());
            } catch (\Throwable) {
            }
            Log::error(sprintf('[BcWpImport] background_job_error token=%s %s', $token, $e->getMessage()), 'wp_import');
            $io->err($e->getMessage());
            return self::CODE_ERROR;
        }
    }
}
