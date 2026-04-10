<?php
declare(strict_types=1);

namespace BcWpImport\Controller\Admin;

use BaserCore\Controller\Admin\BcAdminAppController;
use BcWpImport\Service\Admin\WpImportAdminService;
use BcWpImport\Service\WpImportService;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Throwable;

class WpImportsController extends BcAdminAppController
{
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->FormProtection->setConfig('unlockedActions', [
            'upload',
            'analyze',
            'save_review_settings',
            'import',
            'status',
            'cancel',
            'delete',
            'delete_all',
            'get_log',
        ]);
    }

    public function index(): void
    {
        $service = new WpImportAdminService();
        $this->set($service->getViewVarsForIndex());
    }

    public function upload(): Response
    {
        $this->request->allowMethod('post');

        $uploadedFile = $this->request->getUploadedFile('wxr_file');
        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonResponse(['message' => __d('baser_core', 'WXRファイルをアップロードしてください。')], 400);
        }

        try {
            $service = new WpImportService();
            $job = $service->createJob($uploadedFile);
            return $this->jsonResponse([
                'job' => [
                    'token' => $job->job_token,
                    'status' => $job->status,
                    'phase' => $job->phase,
                    'source_filename' => $job->source_filename,
                ]
            ]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function analyze(): Response
    {
        $this->request->allowMethod('post');

        $token = (string) $this->request->getData('token');
        if (!$token) {
            return $this->jsonResponse(['message' => __d('baser_core', 'トークンが指定されていません。')], 400);
        }

        try {
            $service = new WpImportService();
            $result = $service->analyzeJob($token);
            return $this->jsonResponse(['result' => $result]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function save_review_settings(): Response
    {
        $this->request->allowMethod('post');

        $token = (string) $this->request->getData('token');
        if (!$token) {
            return $this->jsonResponse(['message' => __d('baser_core', 'トークンが指定されていません。')], 400);
        }

        try {
            $service = new WpImportService();
            $result = $service->saveReviewSettings($token, (array) $this->request->getData());
            return $this->jsonResponse(['result' => $result]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function import(): Response
    {
        $this->request->allowMethod('post');

        $token = (string) $this->request->getData('token');
        if (!$token) {
            return $this->jsonResponse(['message' => __d('baser_core', 'トークンが指定されていません。')], 400);
        }

        try {
            $service = new WpImportService();
            $result = $service->importJob($token);
            $result['log_lines'] = $service->getLogLines($token);
            return $this->jsonResponse(['result' => $result]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function status(): Response
    {
        $this->request->allowMethod('post');

        $token = (string) $this->request->getData('token');
        if (!$token) {
            return $this->jsonResponse(['message' => __d('baser_core', 'トークンが指定されていません。')], 400);
        }

        try {
            $service = new WpImportService();
            $result = $service->getJobStatus($token);
            return $this->jsonResponse(['result' => $result]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function cancel(): Response
    {
        $this->request->allowMethod('post');

        $token = (string) $this->request->getData('token');
        if (!$token) {
            return $this->jsonResponse(['message' => __d('baser_core', 'トークンが指定されていません。')], 400);
        }

        try {
            $service = new WpImportService();
            $result = $service->cancelJob($token);
            return $this->jsonResponse(['result' => $result]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function delete(string $token = ''): Response
    {
        $this->request->allowMethod('post');

        if (!$token) {
            $token = (string) $this->request->getData('token');
        }
        if (!$token) {
            return $this->jsonResponse(['message' => __d('baser_core', 'トークンが指定されていません。')], 400);
        }

        try {
            $jobsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
            $job = $jobsTable->find()->where(['job_token' => $token])->first();
            if ($job) {
                $jobsTable->delete($job);
            }
            return $this->jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function delete_all(): Response
    {
        $this->request->allowMethod('post');

        $tokens = (array) $this->request->getData('tokens');
        if (empty($tokens)) {
            return $this->jsonResponse(['message' => __d('baser_core', 'トークンが指定されていません。')], 400);
        }

        try {
            $jobsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
            $jobsTable->deleteAll(['job_token IN' => $tokens]);
            return $this->jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function get_log(): Response
    {
        $token = (string) ($this->request->getQuery('token') ?? '');
        if (!$token || !preg_match('/^[a-f0-9]+$/', $token)) {
            return $this->jsonResponse(['lines' => []]);
        }

        try {
            $service = new WpImportService();
            $lines = $service->getLogLines($token);
            return $this->jsonResponse(['lines' => $lines]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['lines' => []]);
        }
    }

    public function download_report(): Response
    {
        $token = (string) ($this->request->getQuery('token') ?? '');
        if (!$token) {
            return $this->jsonResponse(['message' => __d('baser_core', 'トークンが指定されていません。')], 400);
        }

        $service = new WpImportService();
        $path = $service->getReportCsvPath($token);
        if (!$path) {
            return $this->jsonResponse(['message' => __d('baser_core', 'レポートファイルが見つかりません。')], 404);
        }

        return $this->response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="import-report.csv"')
            ->withStringBody((string) file_get_contents($path));
    }

    private function jsonResponse(array $data, int $status = 200): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
