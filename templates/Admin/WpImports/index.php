<?php
/**
 * @var \Cake\View\View $this
 * @var array $pendingJobs
 * @var array $historyJobs
 */
$this->BcAdmin->setTitle(__d('baser_core', 'WordPressインポート'));
$adminBase = '/baser/admin/bc-wp-import/wp_imports';
$csrfToken = $this->request->getAttribute('csrfToken');
?>

<link rel="stylesheet" href="/bc_wp_import/css/admin/wp_import.css">

<?php if (!empty($pendingJobs)): ?>
<section class="bca-section" data-bca-section-type="form-group" id="js-pending-section">
    <h2 class="bca-main__heading" data-bca-heading-size="lg"><?= __d('baser_core', '未完了のインポート') ?></h2>
    <div class="bc-wp-import__scroll-table">
    <table class="bca-table-listup" id="js-pending-table">
        <thead class="bca-table-listup__thead">
        <tr>
            <th class="bca-table-listup__thead-th" style="width:2.5rem;">
                <input type="checkbox" id="js-pending-check-all" title="<?= __d('baser_core', 'すべて選択') ?>">
            </th>
            <th class="bca-table-listup__thead-th"><?= __d('baser_core', '作成日時') ?></th>
            <th class="bca-table-listup__thead-th"><?= __d('baser_core', 'ファイル名') ?></th>
            <th class="bca-table-listup__thead-th"><?= __d('baser_core', '状態') ?></th>
            <th class="bca-table-listup__thead-th"><?= __d('baser_core', 'フェーズ') ?></th>
            <th class="bca-table-listup__thead-th"><?= __d('baser_core', '操作') ?></th>
        </tr>
        </thead>
        <tbody class="bca-table-listup__tbody" id="js-pending-tbody">
        <?php foreach ($pendingJobs as $job): ?>
            <tr data-job-token="<?= h($job->job_token) ?>">
                <td class="bca-table-listup__tbody-td">
                    <input type="checkbox" class="js-pending-check" value="<?= h($job->job_token) ?>">
                </td>
                <td class="bca-table-listup__tbody-td"><?= $job->created ? h($job->created->format('Y/m/d H:i')) : '-' ?></td>
                <td class="bca-table-listup__tbody-td"><?= h($job->source_filename) ?></td>
                <td class="bca-table-listup__tbody-td"><?= h($job->status) ?></td>
                <td class="bca-table-listup__tbody-td"><?= h($job->phase) ?></td>
                <td class="bca-table-listup__tbody-td bca-table-listup__tbody-td--actions">
                    <button class="bca-btn bca-actions__item js-delete-btn"
                            data-token="<?= h($job->job_token) ?>"
                            data-status="<?= h($job->status) ?>">
                        <?= __d('baser_core', '削除') ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="bca-actions" id="js-pending-bulk-actions">
        <div class="bca-actions__before"></div>
        <div class="bca-actions__main">
            <button type="button" id="js-pending-delete-all-btn" class="bca-btn bca-actions__item" data-bca-btn-type="delete" disabled>
                <?= __d('baser_core', '選択した未完了ジョブを削除') ?>
            </button>
        </div>
        <div class="bca-actions__sub"></div>
    </div>
</section>
<?php endif; ?>

<section class="bca-section" data-bca-section-type="form-group" id="js-upload-section">
    <h2 class="bca-main__heading" data-bca-heading-size="lg"><?= __d('baser_core', '新規インポート') ?></h2>
    <table class="form-table bca-form-table" data-bca-table-type="type2">
        <tbody>
        <tr>
            <th class="col-head bca-form-table__label">
                <?= $this->BcAdminForm->label('wxr_file', __d('baser_core', 'WXRファイル') . ' <span class="bca-label" data-bca-label-type="required">' . __d('baser_core', '必須') . '</span>', ['escape' => false]) ?>
            </th>
            <td class="col-input bca-form-table__input">
                <input type="file" id="wxr-file" accept=".xml" class="bca-input__file">
                <p class="bca-form__note"><?= __d('baser_core', 'WordPress のエクスポート XML ファイルを選択してください。') ?></p>
            </td>
        </tr>
        </tbody>
    </table>

    <div id="js-analysis-summary" style="display:none;">
        <h3 class="bca-main__heading" data-bca-heading-size="md"><?= __d('baser_core', '解析結果') ?></h3>

        <p class="bc-wp-import__group-heading"><?= __d('baser_core', '固定ページ') ?></p>
        <table class="form-table bca-form-table" data-bca-table-type="type2">
            <tbody>
            <tr>
                <th class="col-head bca-form-table__label"><?= __d('baser_core', '固定ページ数') ?></th>
                <td class="col-input bca-form-table__input" id="js-sum-pages">-</td>
            </tr>
            </tbody>
        </table>

        <p class="bc-wp-import__group-heading"><?= __d('baser_core', 'ブログ') ?></p>
        <table class="form-table bca-form-table" data-bca-table-type="type2">
            <tbody>
            <tr>
                <th class="col-head bca-form-table__label"><?= __d('baser_core', '投稿数') ?></th>
                <td class="col-input bca-form-table__input" id="js-sum-posts">-</td>
            </tr>
            <tr>
                <th class="col-head bca-form-table__label"><?= __d('baser_core', 'カテゴリー数') ?></th>
                <td class="col-input bca-form-table__input" id="js-sum-categories">-</td>
            </tr>
            <tr>
                <th class="col-head bca-form-table__label"><?= __d('baser_core', 'タグ数') ?></th>
                <td class="col-input bca-form-table__input" id="js-sum-tags">-</td>
            </tr>
            </tbody>
        </table>

        <p class="bc-wp-import__group-heading"><?= __d('baser_core', 'ユーザー') ?></p>
        <table class="form-table bca-form-table" data-bca-table-type="type2">
            <tbody>
            <tr>
                <th class="col-head bca-form-table__label"><?= __d('baser_core', 'ユーザー数') ?></th>
                <td class="col-input bca-form-table__input" id="js-sum-authors">-</td>
            </tr>
            </tbody>
        </table>
    </div>

    <div id="js-import-settings-wrapper" style="display:none;">
        <div class="bca-collapse__action">
            <button type="button"
                    class="bca-collapse__btn"
                    data-bca-collapse="collapse"
                    data-bca-target="#js-import-settings-body"
                    aria-expanded="true"
                    aria-controls="js-import-settings-body">
                <?= __d('baser_core', 'インポート設定') ?>&nbsp;&nbsp;
                <i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
            </button>
        </div>
        <div class="bca-collapse" id="js-import-settings-body" data-bca-state="" style="display:none;">
            <table class="form-table bca-form-table" data-bca-table-type="type2">
                <tbody>
                <tr>
                    <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('import_target', __d('baser_core', '取込対象')) ?></th>
                    <td class="col-input bca-form-table__input">
                        <?= $this->BcAdminForm->control('import_target', [
                            'type' => 'select',
                            'id' => 'import-target',
                            'label' => false,
                            'options' => [
                                'all'   => __d('baser_core', '投稿と固定ページ'),
                                'posts' => __d('baser_core', '投稿のみ'),
                                'pages' => __d('baser_core', '固定ページのみ'),
                            ],
                            'empty' => false,
                        ]) ?>
                    </td>
                </tr>
                <tr id="js-blog-row">
                    <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('blog_content_id', __d('baser_core', '取込先ブログ')) ?></th>
                    <td class="col-input bca-form-table__input">
                        <?= $this->BcAdminForm->control('blog_content_id', [
                            'type' => 'select',
                            'id' => 'blog-content-id',
                            'label' => false,
                            'options' => $blogOptions,
                            'empty' => !empty($blogOptions) ? __d('baser_core', '-- ブログを選択 --') : __d('baser_core', '（ブログがありません）'),
                        ]) ?>
                    </td>
                </tr>
                <tr id="js-folder-row">
                    <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('content_folder_id', __d('baser_core', '固定ページ配置先フォルダ')) ?></th>
                    <td class="col-input bca-form-table__input">
                        <?= $this->BcAdminForm->control('content_folder_id', [
                            'type' => 'select',
                            'id' => 'content-folder-id',
                            'label' => false,
                            'options' => $contentFolderOptions,
                            'empty' => !empty($contentFolderOptions) ? __d('baser_core', '-- フォルダを選択 --') : __d('baser_core', '（フォルダがありません）'),
                        ]) ?>
                    </td>
                </tr>
                <tr>
                    <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('author_strategy', __d('baser_core', 'ユーザー割り当て')) ?></th>
                    <td class="col-input bca-form-table__input">
                        <?= $this->BcAdminForm->control('author_strategy', [
                            'type' => 'select',
                            'id' => 'author-strategy',
                            'label' => false,
                            'options' => [
                                'match'  => __d('baser_core', '同名ユーザーへ割り当て'),
                                'assign' => __d('baser_core', '指定ユーザーへ一括割り当て'),
                            ],
                            'empty' => false,
                        ]) ?>
                        <div id="js-author-assign-wrapper" style="display:none; margin-top:8px;">
                            <?= $this->BcAdminForm->control('author_assign_user_id', [
                                'type' => 'select',
                                'id' => 'author-assign-user-id',
                                'label' => false,
                                'options' => $userOptions,
                                'empty' => __d('baser_core', '-- ユーザーを選択 --'),
                            ]) ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('slug_strategy', __d('baser_core', 'スラッグ重複時')) ?></th>
                    <td class="col-input bca-form-table__input">
                        <?= $this->BcAdminForm->control('slug_strategy', [
                            'type' => 'select',
                            'id' => 'slug-strategy',
                            'label' => false,
                            'options' => [
                                'suffix'    => __d('baser_core', '連番付与'),
                                'skip'      => __d('baser_core', 'スキップ'),
                                'overwrite' => __d('baser_core', '上書き'),
                            ],
                            'empty' => false,
                        ]) ?>
                    </td>
                </tr>
                <tr>
                    <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('publish_strategy', __d('baser_core', '公開状態')) ?></th>
                    <td class="col-input bca-form-table__input">
                        <?= $this->BcAdminForm->control('publish_strategy', [
                            'type' => 'select',
                            'id' => 'publish-strategy',
                            'label' => false,
                            'options' => [
                                'keep'  => __d('baser_core', '元の状態を維持'),
                                'draft' => __d('baser_core', 'すべて下書き'),
                            ],
                            'empty' => false,
                        ]) ?>
                    </td>
                </tr>
                <tr>
                    <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('url_replace_mode', __d('baser_core', 'URL 置換')) ?></th>
                    <td class="col-input bca-form-table__input">
                        <?= $this->BcAdminForm->control('url_replace_mode', [
                            'type' => 'select',
                            'id' => 'url-replace-mode',
                            'label' => false,
                            'options' => [
                                'keep'    => __d('baser_core', '置換しない'),
                                'replace' => __d('baser_core', '指定ドメインへ置換'),
                            ],
                            'empty' => false,
                        ]) ?>
                        <div id="js-url-replace-wrapper" style="display:none; margin-top:8px;">
                            <span class="bca-textbox">
                                <input type="text" id="url-replace-from" class="bca-textbox__input" placeholder="<?= __d('baser_core', '置換元 URL') ?>">
                            </span>
                            <span class="bca-textbox" style="margin-top:8px;">
                                <input type="text" id="url-replace-to" class="bca-textbox__input" placeholder="<?= __d('baser_core', '置換先 URL') ?>">
                            </span>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <input type="hidden" id="js-review-token" value="">
    <div id="js-upload-error" class="bc-wp-import__upload-error" style="display:none;"></div>

    <div class="bca-actions">
        <div class="bca-actions__before"></div>
        <div class="bca-actions__main">
            <button id="js-upload-analyze-btn" class="bca-btn bca-actions__item" data-bca-btn-type="save" data-bca-btn-size="lg" data-bca-btn-width="lg">
                <?= __d('baser_core', 'アップロードして解析') ?>
            </button>
            <button id="js-start-import-btn" class="bca-btn bca-actions__item" data-bca-btn-type="save" data-bca-btn-size="lg" data-bca-btn-width="lg" style="display:none;">
                <?= __d('baser_core', 'インポート実行') ?>
            </button>
        </div>
        <div class="bca-actions__sub">
        </div>
    </div>
</section>

<!-- 処理中エリア -->
<section class="bca-section" data-bca-section-type="form-group" id="js-progress-section" style="display:none;">
    <h2 class="bca-main__heading" data-bca-heading-size="lg"><?= __d('baser_core', '処理中...') ?></h2>
    <div class="bc-wp-import__spinner-wrap">
        <span class="bc-wp-import__spinner"></span>
        <span class="bca-form__note"><?= __d('baser_core', 'インポートを実行しています。しばらくお待ちください。') ?></span>
    </div>
    <p class="bca-form__note" style="margin-top:8px;"><?= __d('baser_core', '経過時間：') ?><span id="js-elapsed-time">0</span> <?= __d('baser_core', '秒') ?></p>
    <div class="bc-wp-import__log-wrap" style="margin-top:12px;">
        <pre id="js-import-log" class="bc-wp-import__log-viewer"><?= __d('baser_core', 'ログを待機中...') ?></pre>
    </div>
</section>

<!-- 結果エリア -->
<section class="bca-section" data-bca-section-type="form-group" id="js-result-section" style="display:none;">
    <h2 class="bca-main__heading" data-bca-heading-size="lg"><?= __d('baser_core', '処理結果') ?></h2>
    <table class="form-table bca-form-table" data-bca-table-type="type2">
        <tbody>
        <tr><th class="col-head bca-form-table__label"><?= __d('baser_core', '処理件数') ?></th><td class="col-input bca-form-table__input" id="js-res-total">-</td></tr>
        <tr><th class="col-head bca-form-table__label"><?= __d('baser_core', '成功件数') ?></th><td class="col-input bca-form-table__input" id="js-res-success">-</td></tr>
        <tr id="js-skip-row" style="display:none;"><th class="col-head bca-form-table__label"><?= __d('baser_core', 'スキップ件数') ?></th><td class="col-input bca-form-table__input" id="js-res-skip">-</td></tr>
        <tr id="js-error-count-row" style="display:none;"><th class="col-head bca-form-table__label" style="color:#c33;"><?= __d('baser_core', 'エラー件数') ?></th><td class="col-input bca-form-table__input" id="js-res-error" style="color:#c33;">-</td></tr>
        </tbody>
    </table>
    <div id="js-result-log-wrapper" style="display:none; margin-top:12px;">
        <h3 class="bca-main__heading" data-bca-heading-size="md"><?= __d('baser_core', 'インポートログ') ?></h3>
        <div class="bc-wp-import__log-wrap">
            <pre id="js-result-log" class="bc-wp-import__log-viewer"></pre>
        </div>
    </div>
    <div id="js-report-download" style="display:none; margin-top:8px;">
        <a id="js-report-link" href="#" class="bca-btn bca-actions__item" data-bca-btn-type="download">
            <?= __d('baser_core', 'レポートCSVをダウンロード') ?>
        </a>
    </div>
    <div class="bca-actions">
        <div class="bca-actions__before"></div>
        <div class="bca-actions__main">
            <button id="js-restart-btn" class="bca-btn bca-actions__item"><?= __d('baser_core', '新しいインポートを開始') ?></button>
        </div>
        <div class="bca-actions__sub"></div>
    </div>
</section>

<?php if (!empty($historyJobs)): ?>
<div class="bca-collapse__action">
    <button type="button"
            class="bca-collapse__btn"
            data-bca-collapse="collapse"
            data-bca-target="#js-history-body"
            aria-expanded="false"
            aria-controls="js-history-body">
        <?= __d('baser_core', '最近の履歴') ?>&nbsp;&nbsp;
        <i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
    </button>
</div>
<div class="bca-collapse" id="js-history-body" data-bca-state="" style="display:none;">
    <section class="bca-section" data-bca-section-type="form-group" id="js-history-section">
        <div class="bc-wp-import__scroll-table">
        <table class="bca-table-listup" id="js-history-table">
            <thead class="bca-table-listup__thead">
            <tr>
                <th class="bca-table-listup__thead-th" style="width:2.5rem;">
                    <input type="checkbox" id="js-history-check-all" title="<?= __d('baser_core', 'すべて選択') ?>">
                </th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', '作成日時') ?></th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', 'ファイル名') ?></th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', '状態') ?></th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', '成功件数') ?></th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', '操作') ?></th>
            </tr>
            </thead>
            <tbody class="bca-table-listup__tbody" id="js-history-tbody">
            <?php foreach ($historyJobs as $job): ?>
                <tr data-job-token="<?= h($job->job_token) ?>">
                    <td class="bca-table-listup__tbody-td">
                        <input type="checkbox" class="js-history-check" value="<?= h($job->job_token) ?>">
                    </td>
                    <td class="bca-table-listup__tbody-td"><?= $job->created ? h($job->created->format('Y/m/d H:i')) : '-' ?></td>
                    <td class="bca-table-listup__tbody-td"><?= h($job->source_filename) ?></td>
                    <td class="bca-table-listup__tbody-td"><?= h($job->status) ?></td>
                    <td class="bca-table-listup__tbody-td"><?= number_format((int)$job->success_count) ?> 件</td>
                    <td class="bca-table-listup__tbody-td bca-table-listup__tbody-td--actions">
                        <?php if (!empty($job->report_csv_path) && file_exists((string)$job->report_csv_path)): ?>
                            <a href="<?= h($adminBase) ?>/download_report?token=<?= h($job->job_token) ?>" class="bca-btn bca-actions__item" data-bca-btn-type="download"><?= __d('baser_core', 'レポートCSV') ?></a>
                        <?php endif; ?>
                        <button class="bca-btn bca-actions__item js-delete-btn"
                                data-token="<?= h($job->job_token) ?>"
                                data-status="<?= h($job->status) ?>">
                            <?= __d('baser_core', '削除') ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="bca-actions" id="js-history-bulk-actions">
            <div class="bca-actions__before"></div>
            <div class="bca-actions__main">
                <button type="button" id="js-history-delete-all-btn" class="bca-btn bca-actions__item" data-bca-btn-type="delete" disabled>
                    <?= __d('baser_core', '選択した履歴を削除') ?>
                </button>
            </div>
            <div class="bca-actions__sub"></div>
        </div>
    </section>
</div>
<?php endif; ?>

<script>
    window.bcWpImportConfig = {
        adminBase: '<?= h($adminBase) ?>',
        csrfToken: '<?= h($csrfToken) ?>'
    };
</script>
<script src="/bc_wp_import/js/admin/wp_import.js"></script>
