<?php
declare(strict_types=1);

namespace BcWpImport\Service\Admin;

use BaserCore\Service\ContentsService;
use Cake\ORM\TableRegistry;

class WpImportAdminService
{
    public function getViewVarsForIndex(): array
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $pendingJobs = $jobsTable->find()
            ->where(['status IN' => ['pending', 'processing', 'waiting', 'failed']])
            ->orderBy(['created' => 'DESC'])
            ->all()
            ->toList();
        $historyJobs = $jobsTable->find()
            ->where(['status IN' => ['completed', 'cancelled']])
            ->orderBy(['created' => 'DESC'])
            ->limit(20)
            ->all()
            ->toList();

        return [
            'pendingJobs'          => $pendingJobs,
            'historyJobs'          => $historyJobs,
            'blogOptions'          => $this->getBlogOptions(),
            'contentFolderOptions' => $this->getContentFolderOptions(),
            'userOptions'          => $this->getUserOptions(),
        ];
    }

    /**
     * ブログコンテンツの選択肢を「サイト名 - ブログ名」形式で返す
     */
    protected function getBlogOptions(): array
    {
        try {
            $table = TableRegistry::getTableLocator()->get('BcBlog.BlogContents');
            $blogContents = $table->find()->contain(['Contents' => ['Sites']])->all();
            $list = [];
            foreach ($blogContents as $bc) {
                $siteName  = $bc->content->site->display_name ?? $bc->content->site->name ?? '';
                $blogTitle = $bc->content->title ?? '';
                $label = $siteName ? $siteName . ' - ' . $blogTitle : $blogTitle;
                $list[$bc->id] = $label;
            }
            return $list;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * コンテンツフォルダの選択肢を階層付きで返す
     */
    protected function getContentFolderOptions(): array
    {
        try {
            $service = new ContentsService();
            $result = $service->getContentFolderList();
            return $result ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * ユーザー一覧の選択肢を返す
     */
    protected function getUserOptions(): array
    {
        try {
            $table = TableRegistry::getTableLocator()->get('BaserCore.Users');
            return $table->getUserList();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
