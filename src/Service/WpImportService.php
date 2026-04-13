<?php
declare(strict_types=1);

namespace BcWpImport\Service;

use BaserCore\Service\ContentFoldersService;
use BaserCore\Service\PagesService;
use BaserCore\Utility\BcUtil;
use BcBlog\Service\BlogCategoriesService;
use BcBlog\Service\BlogPostsService;
use BcBlog\Service\BlogTagsService;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

class WpImportService
{
    public function __construct(protected WxrParserService $parserService = new WxrParserService())
    {
    }

    public function createJob(UploadedFileInterface $uploadedFile)
    {
        $tmpDir = TMP . 'bc_wp_import' . DS;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $filename = (string) $uploadedFile->getClientFilename();
        $tmpPath = $tmpDir . uniqid('wxr_', true) . '.xml';
        $uploadedFile->moveTo($tmpPath);

        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $job = $jobsTable->newEntity(array_merge(
            $this->buildInitialJobData($filename, $tmpPath),
            [
                'job_token' => bin2hex(random_bytes(16)),
                'expires_at' => FrozenTime::now()->addDays((int) Configure::read('BcWpImport.jobExpireDays', 3)),
            ]
        ));

        return $jobsTable->saveOrFail($job);
    }

    public function buildInitialJobData(string $sourceFilename, string $wxrPath): array
    {
        return [
            'source_filename' => $sourceFilename,
            'wxr_path' => $wxrPath,
            'status' => 'pending',
            'phase' => 'upload',
            'mode' => 'strict',
            'import_target' => 'all',
        ];
    }

    public function analyze(string $filePath): array
    {
        return $this->parserService->analyze($filePath);
    }

    public function analyzeJob(string $token): array
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $job = $jobsTable->find()->where(['job_token' => $token])->firstOrFail();

        $analysis = $this->analyze((string) $job->wxr_path);
        $itemTotal = array_sum($analysis['item_counts'] ?? []);

        $job = $jobsTable->patchEntity($job, [
            'status' => 'waiting',
            'phase' => 'review',
            'parsed_summary' => json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'total_items' => $itemTotal,
            'analyzable_items' => $itemTotal,
            'importable_items' => ($analysis['item_counts']['post'] ?? 0) + ($analysis['item_counts']['page'] ?? 0),
            'processed' => $itemTotal,
            'warning_count' => count($analysis['unsupported_types'] ?? []),
            'unsupported_count' => count($analysis['unsupported_types'] ?? []),
            'started_at' => $job->started_at ?: FrozenTime::now(),
            'ended_at' => FrozenTime::now(),
        ]);
        $jobsTable->saveOrFail($job);

        return [
            'token' => $job->job_token,
            'status' => $job->status,
            'phase' => $job->phase,
            'processed' => $job->processed,
            'total' => $job->total_items,
            'summary' => $analysis,
        ];
    }

    public function saveReviewSettings(string $token, array $data): array
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $job = $jobsTable->find()->where(['job_token' => $token])->firstOrFail();

        if (!$job->parsed_summary) {
            throw new InvalidArgumentException(__d('baser_core', '先に WXR 解析を完了してください。'));
        }

        $settings = $this->normalizeReviewSettings($data);
        $job = $jobsTable->patchEntity($job, array_merge($settings, [
            'status' => 'waiting',
            'phase' => 'review',
            'import_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]));
        $jobsTable->saveOrFail($job);

        return [
            'token' => $job->job_token,
            'status' => $job->status,
            'phase' => $job->phase,
            'settings' => $settings,
        ];
    }

    protected function normalizeReviewSettings(array $data): array
    {
        $importTarget = (string) ($data['import_target'] ?? 'all');
        $authorStrategy = (string) ($data['author_strategy'] ?? 'match');
        $urlReplaceMode = (string) ($data['url_replace_mode'] ?? 'keep');

        $settings = [
            'mode' => (string) ($data['mode'] ?? 'strict'),
            'import_target' => in_array($importTarget, ['all', 'posts', 'pages'], true) ? $importTarget : 'all',
            'blog_content_id' => $this->toNullableInt($data['blog_content_id'] ?? null),
            'content_folder_id' => $this->toNullableInt($data['content_folder_id'] ?? null),
            'author_strategy' => in_array($authorStrategy, ['match', 'assign'], true) ? $authorStrategy : 'match',
            'author_assign_user_id' => $this->toNullableInt($data['author_assign_user_id'] ?? null),
            'slug_strategy' => (string) ($data['slug_strategy'] ?? 'suffix'),
            'publish_strategy' => (string) ($data['publish_strategy'] ?? 'keep'),
            'url_replace_mode' => in_array($urlReplaceMode, ['keep', 'replace'], true) ? $urlReplaceMode : 'keep',
            'url_replace_from' => $this->toNullableString($data['url_replace_from'] ?? null),
            'url_replace_to' => $this->toNullableString($data['url_replace_to'] ?? null),
        ];

        if ($settings['import_target'] === 'pages') {
            $settings['blog_content_id'] = null;
        }
        if ($settings['import_target'] === 'posts') {
            $settings['content_folder_id'] = null;
        }
        if ($settings['author_strategy'] !== 'assign') {
            $settings['author_assign_user_id'] = null;
        }
        if ($settings['url_replace_mode'] !== 'replace') {
            $settings['url_replace_from'] = null;
            $settings['url_replace_to'] = null;
        }

        return $settings;
    }

    protected function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    protected function toNullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? null : $value;
    }

    public function importJob(string $token): array
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $job = $jobsTable->find()->where(['job_token' => $token])->firstOrFail();
        if (!$job->parsed_summary) {
            throw new InvalidArgumentException(__d('baser_core', '先に WXR 解析を完了してください。'));
        }

        $settings = $job->import_settings ? (json_decode((string) $job->import_settings, true) ?: []) : [];
        $items = $this->parserService->parseItems((string) $job->wxr_path);
        $defaultUserId = $this->resolveDefaultUserId($settings);

        $job = $jobsTable->patchEntity($job, [
            'status' => 'processing',
            'phase' => 'import',
            'processed' => 0,
            'success_count' => 0,
            'skip_count' => 0,
            'error_count' => 0,
            'started_at' => $job->started_at ?: FrozenTime::now(),
            'ended_at' => null,
        ]);
        $jobsTable->saveOrFail($job);

        $successCount = 0;
        $skipCount = 0;
        $errorCount = 0;
        $processed = 0;
        $messages = [];
        $reportRows = [['post_type', 'title', 'post_name', 'action', 'message']];

        $logPath = TMP . 'bc_wp_import' . DS . $job->job_token . '.log';
        $this->appendLog($logPath, '[INFO] インポートを開始します。対象件数: ' . count($items) . ' 件', true);

        // 固定ページの親子関係を解決するための事前スキャン
        // wp_post_id が他ページの wp_post_parent として使われているものは「フォルダ」として扱う
        $parentWpIds = [];
        foreach ($items as $item) {
            if ((string) ($item['post_type'] ?? '') === 'page') {
                $wpParent = (int) ($item['wp_post_parent'] ?? 0);
                if ($wpParent > 0) {
                    $parentWpIds[$wpParent] = true;
                }
            }
        }
        // wp_post_id → 作成した ContentFolder の content_id マップ
        $wpIdToFolderContentId = [];

        foreach ($items as $item) {
            $postType = (string) ($item['post_type'] ?? '');
            $title = (string) ($item['title'] ?? '');
            $postName = (string) ($item['post_name'] ?? '');

            if (!$this->isImportTarget($postType, (string) $job->import_target)) {
                $reportRows[] = [$postType, $title, $postName, 'skip', 'Non-target type'];
                continue;
            }
            $processed++;
            try {
                if ($postType === 'page') {
                    $wpPostId   = (int) ($item['wp_post_id'] ?? 0);
                    $wpParentId = (int) ($item['wp_post_parent'] ?? 0);
                    $isFolder   = isset($parentWpIds[$wpPostId]);

                    // 親フォルダの解決: wp_post_parent がマップにあればそれを使い、なければ設定値を使う
                    $rootFolderId      = (int) ($settings['content_folder_id'] ?? 1 ?: 1);
                    $resolvedParentId  = ($wpParentId > 0 && isset($wpIdToFolderContentId[$wpParentId]))
                        ? $wpIdToFolderContentId[$wpParentId]
                        : $rootFolderId;

                    $result = $this->importPage($item, $settings, $defaultUserId, $resolvedParentId, $isFolder);

                    // フォルダとして作成した場合、後続ページの親解決のためマップに登録する
                    if ($isFolder && $wpPostId > 0 && isset($result['folder_content_id'])) {
                        $wpIdToFolderContentId[$wpPostId] = $result['folder_content_id'];
                    }
                } elseif ($postType === 'post') {
                    $result = $this->importPost($item, $settings, $defaultUserId);
                } else {
                    $skipCount++;
                    $msg = sprintf('Unsupported type skipped: %s', $postType);
                    $messages[] = $msg;
                    $reportRows[] = [$postType, $title, $postName, 'skip', $msg];
                    $this->appendLog($logPath, '[SKIP] ' . $postType . ': ' . $title . ' — ' . $msg);
                    continue;
                }

                if ($result['action'] === 'skipped') {
                    $skipCount++;
                    $msg = (string) ($result['message'] ?? '');
                    $messages[] = $msg;
                    $reportRows[] = [$postType, $title, $postName, 'skip', $msg];
                    $this->appendLog($logPath, '[SKIP] ' . $postType . ': ' . $title . ' — ' . $msg);
                } else {
                    $successCount++;
                    $reportRows[] = [$postType, $title, $postName, $result['action'], ''];
                    $batchSize = (int) Configure::read('BcWpImport.batchSize', 10);
                    if ($processed % $batchSize === 0) {
                        $this->appendLog($logPath, '[INFO] 処理中... ' . $processed . ' 件完了（成功:' . $successCount . ' スキップ:' . $skipCount . ' エラー:' . $errorCount . '）');
                    }
                }
            } catch (\Throwable $e) {
                $errorCount++;
                $msg = $e->getMessage();
                $messages[] = $msg;
                $reportRows[] = [$postType, $title, $postName, 'error', $msg];
                $this->appendLog($logPath, '[ERROR] ' . $postType . ': ' . $title . ' — ' . $msg);
            }
        }

        $reportCsvPath = $this->writeReportCsv((string) $job->job_token, $reportRows);

        $this->appendLog($logPath, '[INFO] インポート完了。成功:' . $successCount . ' スキップ:' . $skipCount . ' エラー:' . $errorCount . ' 件');

        $job = $jobsTable->patchEntity($job, [
            'status' => $errorCount > 0 ? 'failed' : 'completed',
            'phase' => 'import',
            'processed' => $processed,
            'success_count' => $successCount,
            'skip_count' => $skipCount,
            'error_count' => $errorCount,
            'report_csv_path' => $reportCsvPath,
            'ended_at' => FrozenTime::now(),
        ]);
        $jobsTable->saveOrFail($job);

        return [
            'token' => $job->job_token,
            'status' => $job->status,
            'phase' => $job->phase,
            'processed' => $job->processed,
            'success_count' => $job->success_count,
            'skip_count' => $job->skip_count,
            'error_count' => $job->error_count,
            'has_report' => true,
            'messages' => $messages,
        ];
    }

    public function getJobStatus(string $token): array
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $job = $jobsTable->find()->where(['job_token' => $token])->firstOrFail();

        return [
            'token' => $job->job_token,
            'status' => $job->status,
            'phase' => $job->phase,
            'processed' => (int) $job->processed,
            'total_items' => (int) $job->total_items,
            'success_count' => (int) $job->success_count,
            'skip_count' => (int) $job->skip_count,
            'warning_count' => (int) $job->warning_count,
            'error_count' => (int) $job->error_count,
            'has_report' => !empty($job->report_csv_path) && file_exists((string) $job->report_csv_path),
        ];
    }

    public function cancelJob(string $token): array
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $job = $jobsTable->find()->where(['job_token' => $token])->firstOrFail();
        $job = $jobsTable->patchEntity($job, [
            'status' => 'cancelled',
            'ended_at' => FrozenTime::now(),
        ]);
        $jobsTable->saveOrFail($job);

        return [
            'token' => $job->job_token,
            'status' => $job->status,
            'phase' => $job->phase,
        ];
    }

    /**
     * WXR の page アイテムを baserCMS に取り込む
     *
     * $asFolder が true のとき（このページが他ページの wp_post_parent として使われている場合）:
     *   → ContentFolder を作成し、コンテンツがあれば index ページも作成する
     *   → 戻り値に folder_content_id を含める（後続ページの親解決用）
     *
     * $asFolder が false のとき:
     *   → 通常の固定ページとして $resolvedParentId の下に作成する
     */
    protected function importPage(array $item, array $settings, int $defaultUserId, int $resolvedParentId = 0, bool $asFolder = false): array
    {
        if ($resolvedParentId === 0) {
            $resolvedParentId = (int) ($settings['content_folder_id'] ?? 1 ?: 1);
        }

        $slugStrategy = (string) ($settings['slug_strategy'] ?? 'suffix');
        $baseSlug = $this->normalizeSlug((string) ($item['post_name'] ?: $item['title']));
        $status = $this->resolvePublishStatus((string) ($item['post_status'] ?? 'publish'), (string) ($settings['publish_strategy'] ?? 'keep'));
        $authorId = $this->resolveAuthorId((string) $item['post_author'], $settings, $defaultUserId);
        $title = (string) ($item['title'] ?: $baseSlug);

        if ($asFolder) {
            return $this->importPageAsFolder($item, $settings, $resolvedParentId, $slugStrategy, $baseSlug, $title, $status, $authorId);
        }

        // 通常ページとして取り込む
        $pagesService = new PagesService();
        $existingPage = $this->findExistingPage($resolvedParentId, $baseSlug);
        $slug = $this->resolvePageSlug((string) ($item['post_name'] ?: $item['title']), $resolvedParentId, $slugStrategy);
        if ($slug === null) {
            return ['action' => 'skipped', 'message' => sprintf('Page skipped: %s', $item['title'])];
        }

        $pageData = [
            'contents' => $this->replaceUrls((string) ($item['post_content'] ?: $item['post_excerpt']), $settings),
            'draft' => $this->replaceUrls((string) ($item['post_content'] ?: $item['post_excerpt']), $settings),
            'page_template' => 'default',
            'content' => [
                'parent_id' => $resolvedParentId,
                'title' => $title,
                'name' => $slug,
                'plugin' => 'BaserCore',
                'type' => 'Page',
                'site_id' => 1,
                'alias_id' => null,
                'entity_id' => null,
                'author_id' => $authorId,
                'self_status' => $status,
            ],
        ];

        if ($slugStrategy === 'overwrite' && $existingPage) {
            $pagesService->update($existingPage, $pageData, ['associated' => ['Contents']]);
            return ['action' => 'updated'];
        }

        $pagesService->create($pageData, ['associated' => ['Contents']]);
        return ['action' => 'created'];
    }

    /**
     * 子ページが存在する WordPress ページを ContentFolder として取り込む
     * コンテンツがある場合は folder/index ページも合わせて作成する
     *
     * @return array action / folder_content_id（後続ページの親解決用）
     */
    protected function importPageAsFolder(array $item, array $settings, int $parentId, string $slugStrategy, string $baseSlug, string $title, bool $status, int $authorId): array
    {
        $foldersService = new ContentFoldersService();
        $contentsTable = TableRegistry::getTableLocator()->get('BaserCore.Contents');

        // 既存フォルダを確認
        $existingFolder = $contentsTable->find()
            ->where([
                'Contents.type' => 'ContentFolder',
                'Contents.parent_id' => $parentId,
                'Contents.name' => $baseSlug,
                'Contents.deleted_date IS' => null,
            ])
            ->first();

        $folderSlug = $existingFolder
            ? $baseSlug
            : ($slugStrategy === 'overwrite'
                ? $baseSlug
                : $this->suffixSlug($baseSlug, function (string $candidate) use ($contentsTable, $parentId): bool {
                    return !$contentsTable->exists([
                        'Contents.parent_id' => $parentId,
                        'Contents.name' => $candidate,
                        'Contents.deleted_date IS' => null,
                    ]);
                }));

        $folderData = [
            'content' => [
                'parent_id' => $parentId,
                'title' => $title,
                'name' => $folderSlug,
                'plugin' => 'BaserCore',
                'type' => 'ContentFolder',
                'site_id' => 1,
                'alias_id' => null,
                'entity_id' => null,
                'self_status' => true,
            ],
        ];

        if ($existingFolder && $slugStrategy === 'overwrite') {
            $folder = $foldersService->get((int) $existingFolder->entity_id);
            $foldersService->update($folder, $folderData, ['associated' => ['Contents']]);
            $folderContentId = (int) $existingFolder->id;
            $action = 'updated';
        } else {
            $folder = $foldersService->create($folderData, ['associated' => ['Contents']]);
            $folderContent = $contentsTable->find()
                ->where([
                    'Contents.type' => 'ContentFolder',
                    'Contents.parent_id' => $parentId,
                    'Contents.name' => $folderSlug,
                    'Contents.deleted_date IS' => null,
                ])
                ->first();
            $folderContentId = $folderContent ? (int) $folderContent->id : 0;
            $action = 'created';
        }

        // コンテンツがある場合は folder/index ページも作成する
        $rawContent = trim((string) ($item['post_content'] ?: $item['post_excerpt']));
        if ($rawContent !== '' && $folderContentId > 0) {
            $pagesService = new PagesService();
            $indexSlug = 'index';
            $existingIndex = $this->findExistingPage($folderContentId, $indexSlug);
            $indexData = [
                'contents' => $this->replaceUrls($rawContent, $settings),
                'draft' => $this->replaceUrls($rawContent, $settings),
                'page_template' => 'default',
                'content' => [
                    'parent_id' => $folderContentId,
                    'title' => $title,
                    'name' => $indexSlug,
                    'plugin' => 'BaserCore',
                    'type' => 'Page',
                    'site_id' => 1,
                    'alias_id' => null,
                    'entity_id' => null,
                    'author_id' => $authorId,
                    'self_status' => $status,
                ],
            ];
            if ($slugStrategy === 'overwrite' && $existingIndex) {
                $pagesService->update($existingIndex, $indexData, ['associated' => ['Contents']]);
            } else {
                $pagesService->create($indexData, ['associated' => ['Contents']]);
            }
        }

        return ['action' => $action, 'folder_content_id' => $folderContentId];
    }

    protected function importPost(array $item, array $settings, int $defaultUserId): array
    {
        $blogContentId = (int) ($settings['blog_content_id'] ?? 0);
        if (!$blogContentId) {
            throw new InvalidArgumentException(__d('baser_core', '投稿を取り込むにはブログIDが必要です。'));
        }

        $blogPostsService = new BlogPostsService();
        $baseSlug = $this->normalizeSlug((string) ($item['post_name'] ?: $item['title']));
        $existingPost = $this->findExistingPost($blogContentId, $baseSlug);
        $slugStrategy = (string) ($settings['slug_strategy'] ?? 'suffix');
        $slug = $this->resolvePostSlug((string) ($item['post_name'] ?: $item['title']), $blogContentId, $slugStrategy);
        if ($slug === null) {
            return ['action' => 'skipped', 'message' => sprintf('Post skipped: %s', $item['title'])];
        }

        $categoryId = $this->resolveCategoryId($blogContentId, $item['categories'][0] ?? null);
        $tagIds = $this->resolveTagIds($item['tags'] ?? []);
        $status = $this->resolvePublishStatus((string) ($item['post_status'] ?? 'publish'), (string) ($settings['publish_strategy'] ?? 'keep'));
        $body = $this->replaceUrls((string) ($item['post_content'] ?: ''), $settings);
        $excerpt = $this->replaceUrls((string) ($item['post_excerpt'] ?: ''), $settings);

        $postData = [
            'blog_content_id' => $blogContentId,
            'title' => (string) ($item['title'] ?: $slug),
            'name' => $slug,
            'content' => $excerpt,
            'detail' => $body,
            'blog_category_id' => $categoryId,
            'user_id' => $this->resolveAuthorId((string) $item['post_author'], $settings, $defaultUserId),
            'posted' => $this->resolvePostedAt($item),
            'status' => $status,
        ];
        if ($tagIds) {
            $postData['blog_tags'] = ['_ids' => $tagIds];
        }

        if ($slugStrategy === 'overwrite' && $existingPost) {
            $blogPostsService->update($existingPost, $postData);
            return ['action' => 'updated'];
        }

        $blogPostsService->create($postData);
        return ['action' => 'created'];
    }

    protected function isImportTarget(string $postType, string $importTarget): bool
    {
        return match ($importTarget) {
            'posts' => $postType === 'post',
            'pages' => $postType === 'page',
            default => in_array($postType, ['post', 'page'], true),
        };
    }

    protected function resolveAuthorId(string $authorName, array $settings, int $defaultUserId): int
    {
        if (($settings['author_strategy'] ?? 'match') === 'assign' && !empty($settings['author_assign_user_id'])) {
            return (int) $settings['author_assign_user_id'];
        }

        if ($authorName !== '') {
            $usersTable = TableRegistry::getTableLocator()->get('BaserCore.Users');
            $user = $usersTable->find()->where(['name' => $authorName])->first();
            if ($user) {
                return (int) $user->id;
            }
        }

        return $defaultUserId;
    }

    protected function resolveDefaultUserId(array $settings): int
    {
        if (!empty($settings['author_assign_user_id'])) {
            return (int) $settings['author_assign_user_id'];
        }

        $usersTable = TableRegistry::getTableLocator()->get('BaserCore.Users');
        $user = $usersTable->find()->where(['status' => true])->orderBy(['id' => 'ASC'])->first();
        if ($user) {
            return (int) $user->id;
        }

        return 1;
    }

    protected function resolveCategoryId(int $blogContentId, ?array $category): ?int
    {
        if (!$category || empty($category['label'])) {
            return null;
        }

        $categoriesTable = TableRegistry::getTableLocator()->get('BcBlog.BlogCategories');
        $existing = $categoriesTable->find()->where([
            'blog_content_id' => $blogContentId,
            'name' => $category['slug'] ?: BcUtil::urlencode($category['label']),
        ])->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $service = new BlogCategoriesService();
        $created = $service->create($blogContentId, [
            'name' => $category['slug'] ?: BcUtil::urlencode($category['label']),
            'title' => $category['label'],
            'status' => true,
        ]);
        return (int) $created->id;
    }

    protected function resolveTagIds(array $tags): array
    {
        if (!$tags) {
            return [];
        }

        $service = new BlogTagsService();
        $tagsTable = TableRegistry::getTableLocator()->get('BcBlog.BlogTags');
        $ids = [];
        foreach ($tags as $tag) {
            $name = trim((string) ($tag['label'] ?? ''));
            if ($name === '') {
                continue;
            }
            $existing = $tagsTable->find()->where(['name' => $name])->first();
            if ($existing) {
                $ids[] = (int) $existing->id;
                continue;
            }
            $created = $service->create(['name' => $name]);
            $ids[] = (int) $created->id;
        }
        return array_values(array_unique($ids));
    }

    protected function resolvePageSlug(string $rawSlug, int $parentId, string $strategy): ?string
    {
        $contentsTable = TableRegistry::getTableLocator()->get('BaserCore.Contents');
        $slug = $this->normalizeSlug($rawSlug);
        $existing = $contentsTable->find()->where([
            'plugin' => 'BaserCore',
            'type' => 'Page',
            'parent_id' => $parentId,
            'name' => $slug,
        ])->first();
        if (!$existing) {
            return $slug;
        }
        if ($strategy === 'skip') {
            return null;
        }
        return $strategy === 'overwrite' ? $slug : $this->suffixSlug($slug, function (string $candidate) use ($contentsTable, $parentId): bool {
            return !$contentsTable->exists([
                'plugin' => 'BaserCore',
                'type' => 'Page',
                'parent_id' => $parentId,
                'name' => $candidate,
            ]);
        });
    }

    protected function resolvePostSlug(string $rawSlug, int $blogContentId, string $strategy): ?string
    {
        $postsTable = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        $slug = $this->normalizeSlug($rawSlug);
        $existing = $postsTable->find()->where([
            'blog_content_id' => $blogContentId,
            'name' => $slug,
        ])->first();
        if (!$existing) {
            return $slug;
        }
        if ($strategy === 'skip') {
            return null;
        }
        return $strategy === 'overwrite' ? $slug : $this->suffixSlug($slug, function (string $candidate) use ($postsTable, $blogContentId): bool {
            return !$postsTable->exists([
                'blog_content_id' => $blogContentId,
                'name' => $candidate,
            ]);
        });
    }

    protected function suffixSlug(string $baseSlug, callable $isAvailable): string
    {
        $index = 2;
        $candidate = $baseSlug;
        while (!$isAvailable($candidate)) {
            $candidate = $baseSlug . '-' . $index;
            $index++;
        }
        return $candidate;
    }

    protected function normalizeSlug(string $rawSlug): string
    {
        $rawSlug = trim($rawSlug) !== '' ? trim($rawSlug) : 'imported-item';
        $maxLength = (int) Configure::read('BcWpImport.slugMaxLength', 230);
        return BcUtil::urlencode(mb_substr($rawSlug, 0, $maxLength, 'UTF-8'));
    }

    protected function resolvePublishStatus(string $wpStatus, string $publishStrategy): bool
    {
        if ($publishStrategy === 'draft') {
            return false;
        }
        return in_array($wpStatus, ['publish', 'future'], true);
    }

    protected function replaceUrls(string $body, array $settings): string
    {
        if (($settings['url_replace_mode'] ?? 'keep') !== 'replace') {
            return $body;
        }
        $from = (string) ($settings['url_replace_from'] ?? '');
        $to = (string) ($settings['url_replace_to'] ?? '');
        if ($from === '' || $to === '') {
            return $body;
        }
        return str_replace($from, $to, $body);
    }

    protected function resolvePostedAt(array $item): string
    {
        return (string) ($item['post_date'] ?: $item['post_date_gmt'] ?: FrozenTime::now()->format('Y-m-d H:i:s'));
    }

    protected function findExistingPage(int $parentId, string $slug): mixed
    {
        $contentsTable = TableRegistry::getTableLocator()->get('BaserCore.Contents');
        $content = $contentsTable->find()->where([
            'plugin' => 'BaserCore',
            'type' => 'Page',
            'parent_id' => $parentId,
            'name' => $slug,
        ])->first();
        if (!$content) {
            return null;
        }
        $pagesService = new PagesService();
        return $pagesService->get((int) $content->entity_id);
    }

    protected function findExistingPost(int $blogContentId, string $slug): mixed
    {
        $postsTable = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        return $postsTable->find()->where([
            'blog_content_id' => $blogContentId,
            'name' => $slug,
        ])->first();
    }

    protected function writeReportCsv(string $token, array $rows): string
    {
        $dir = TMP . 'bc_wp_import' . DS;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . 'report_' . preg_replace('/[^a-f0-9]/i', '', $token) . '.csv';
        $fp = fopen($path, 'w');
        if ($fp === false) {
            return '';
        }
        fputs($fp, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fp, array_map('strval', $row));
        }
        fclose($fp);
        return $path;
    }

    public function getReportCsvPath(string $token): ?string
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpImport.BcWpImportJobs');
        $job = $jobsTable->find()->where(['job_token' => $token])->first();
        if (!$job || !$job->report_csv_path) {
            return null;
        }
        $path = (string) $job->report_csv_path;
        return file_exists($path) ? $path : null;
    }

    /**
     * ジョブのログファイルから最新 N 行を返す
     */
    public function getLogLines(string $token, int $limit = 0): array
    {
        if ($limit === 0) {
            $limit = (int) Configure::read('BcWpImport.logLineLimit', 200);
        }
        $logPath = TMP . 'bc_wp_import' . DS . $token . '.log';
        if (!file_exists($logPath)) {
            return [];
        }
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return array_slice($lines, -$limit);
    }

    /**
     * ログファイルへ1行追記する（$overwrite=true で上書き作成）
     */
    private function appendLog(string $logPath, string $message, bool $overwrite = false): void
    {
        $line = date('H:i:s') . ' ' . $message . "\n";
        file_put_contents($logPath, $line, $overwrite ? LOCK_EX : FILE_APPEND | LOCK_EX);

        $token = basename($logPath, '.log');
        $appLine = date('Y-m-d H:i:s') . ' [token:' . $token . '] ' . $message . "\n";
        file_put_contents(LOGS . 'wp_import.log', $appLine, FILE_APPEND | LOCK_EX);

        if (str_starts_with($message, '[ERROR]')) {
            Log::error('[BcWpImport] [token:' . $token . '] ' . $message, 'wp_import');
        } elseif (str_starts_with($message, '[SKIP]')) {
            Log::warning('[BcWpImport] [token:' . $token . '] ' . $message, 'wp_import');
        } else {
            Log::info('[BcWpImport] [token:' . $token . '] ' . $message, 'wp_import');
        }
    }
}
