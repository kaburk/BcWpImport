(function () {
    'use strict';

    async function apiPost(url, formData, csrfToken) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData,
        });
        const text = await response.text();
        let data = {};
        try {
            data = JSON.parse(text);
        } catch (_) {
            throw new Error('Server error (' + response.status + '): ' + text.slice(0, 300));
        }
        if (!response.ok) {
            throw new Error(data.message || 'Server error (' + response.status + ')');
        }
        return data;
    }

    function showError(message) {
        const el = document.getElementById('js-upload-error');
        if (!el) return;
        el.textContent = message;
        el.style.display = message ? '' : 'none';
        if (message) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    let _elapsedTimer = null;

    function showSection(id) {
        document.body.classList.toggle('bc-wp-import--processing', id === 'js-progress-section');
        ['js-upload-section', 'js-progress-section', 'js-result-section'].forEach(function (sId) {
            const el = document.getElementById(sId);
            if (el) el.style.display = sId === id ? '' : 'none';
        });

        // タイマーを停止（セクション離脱時）
        if (_elapsedTimer) { clearInterval(_elapsedTimer); _elapsedTimer = null; }

        if (id === 'js-progress-section') {
            // 経過時間カウンター
            const elapsedEl = document.getElementById('js-elapsed-time');
            let seconds = 0;
            if (elapsedEl) elapsedEl.textContent = '0';
            _elapsedTimer = setInterval(function () {
                seconds++;
                if (elapsedEl) elapsedEl.textContent = String(seconds);
            }, 1000);

            // 処理中メッセージ
            const logEl = document.getElementById('js-import-log');
            if (logEl) {
                logEl.classList.add('bc-wp-import__log-viewer--placeholder');
                logEl.style.color = '#000';
                logEl.style.background = '#fff';
                logEl.style.border = '1px solid #ddd';
                logEl.style.fontFamily = 'inherit';
                logEl.textContent = 'インポートを実行中です。完了後にログが表示されます...';
            }
        }
    }

    function showAnalysisSummary(result) {
        const wrapper = document.getElementById('js-analysis-summary');
        if (!wrapper) return;
        const setCount = function (id, val) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = (val !== undefined && val !== null)
                ? Number(val).toLocaleString('ja-JP') + ' 件'
                : '-';
        };
        // analyzeJob が返す構造: result.summary.item_counts.post / .page, result.summary.authors 等
        const ic  = result.summary?.item_counts ?? {};
        const auth = result.summary?.authors    ?? [];
        const cats = result.summary?.categories ?? [];
        const tags = result.summary?.tags       ?? [];
        setCount('js-sum-posts',      ic['post']  ?? null);
        setCount('js-sum-pages',      ic['page']  ?? null);
        setCount('js-sum-categories', cats.length);
        setCount('js-sum-tags',       tags.length);
        setCount('js-sum-authors',    auth.length);
        wrapper.style.display = '';
    }

    function showImportSettings() {
        const wrapper = document.getElementById('js-import-settings-wrapper');
        if (wrapper) wrapper.style.display = '';
    }

    function showResult(result) {
        const config = window.bcWpImportConfig || {};
        const setField = function (id, val) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = (val !== undefined && val !== null)
                ? Number(val).toLocaleString('ja-JP') + ' 件'
                : '-';
        };

        const total = (result.success_count || 0) + (result.skip_count || 0) + (result.error_count || 0);
        setField('js-res-total', total);
        setField('js-res-success', result.success_count);

        const skipRow = document.getElementById('js-skip-row');
        if (skipRow) {
            if ((result.skip_count || 0) > 0) {
                setField('js-res-skip', result.skip_count);
                skipRow.style.display = '';
            } else {
                skipRow.style.display = 'none';
            }
        }

        const errorRow = document.getElementById('js-error-count-row');
        if (errorRow) {
            if ((result.error_count || 0) > 0) {
                setField('js-res-error', result.error_count);
                errorRow.style.display = '';
            } else {
                errorRow.style.display = 'none';
            }
        }

        const reportDownload = document.getElementById('js-report-download');
        const reportLink = document.getElementById('js-report-link');
        if (reportDownload && reportLink) {
            const token = result.token || document.getElementById('js-review-token')?.value || '';
            if (token && result.has_report) {
                reportLink.href = config.adminBase + '/download_report?token=' + encodeURIComponent(token);
                reportDownload.style.display = '';
            } else {
                reportDownload.style.display = 'none';
            }
        }

        // ログ表示
        const resultLogEl = document.getElementById('js-result-log');
        if (resultLogEl && Array.isArray(result.log_lines) && result.log_lines.length > 0) {
            resultLogEl.classList.remove('bc-wp-import__log-viewer--placeholder');
            resultLogEl.textContent = result.log_lines.join('\n');
            resultLogEl.scrollTop = resultLogEl.scrollHeight;
            const logWrapper = document.getElementById('js-result-log-wrapper');
            if (logWrapper) logWrapper.style.display = '';
        }

        showSection('js-result-section');
    }

    function collectReviewFormData() {
        const formData = new FormData();
        const get = function (id) { return document.getElementById(id)?.value ?? ''; };
        formData.append('token',                get('js-review-token'));
        formData.append('import_target',        get('import-target'));
        formData.append('blog_content_id',      get('blog-content-id'));
        formData.append('content_folder_id',    get('content-folder-id'));
        formData.append('author_strategy',      get('author-strategy'));
        formData.append('author_assign_user_id', get('author-assign-user-id'));
        formData.append('slug_strategy',        get('slug-strategy'));
        formData.append('publish_strategy',     get('publish-strategy'));
        formData.append('url_replace_mode',     get('url-replace-mode'));
        formData.append('url_replace_from',     get('url-replace-from'));
        formData.append('url_replace_to',       get('url-replace-to'));
        return formData;
    }

    async function deleteJob(token, row) {
        const config = window.bcWpImportConfig || {};
        if (!window.confirm('このジョブを削除します。よろしいですか？')) return;
        const formData = new FormData();
        formData.append('token', token);
        await apiPost(config.adminBase + '/delete/' + token, formData, config.csrfToken);
        row.remove();
        const section = row.closest('section');
        if (section && !section.querySelectorAll('tbody tr').length) {
            section.style.display = 'none';
        }
    }

    function updateBulkDeleteButton() {
        const historyBtn = document.getElementById('js-history-delete-all-btn');
        if (historyBtn) {
            historyBtn.disabled = document.querySelectorAll('.js-history-check:checked').length === 0;
        }
        const pendingBtn = document.getElementById('js-pending-delete-all-btn');
        if (pendingBtn) {
            pendingBtn.disabled = document.querySelectorAll('.js-pending-check:checked').length === 0;
        }
    }

    async function deleteAllJobs(tokens) {
        const config = window.bcWpImportConfig || {};
        const body = new FormData();
        tokens.forEach(function (t) { body.append('tokens[]', t); });
        const response = await fetch(config.adminBase + '/delete_all', {
            method: 'POST',
            headers: { 'X-CSRF-Token': config.csrfToken },
            body,
        });
        if (!response.ok) {
            const json = await response.json().catch(function () { return {}; });
            throw new Error(json.message || 'Server error (' + response.status + ')');
        }
        // pending ・ history 両方の tbody から該当行を削除
        ['js-history-tbody', 'js-pending-tbody'].forEach(function (tbodyId) {
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            tokens.forEach(function (t) {
                const row = tbody.querySelector('tr[data-job-token="' + CSS.escape(t) + '"]');
                if (row) row.remove();
            });
            const section = tbody.closest('section');
            if (section && !tbody.querySelectorAll('tr').length) {
                section.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const config = window.bcWpImportConfig || {};

        // import-target に応じてブログ行・フォルダ行を表示切替
        const importTargetSelect = document.getElementById('import-target');
        const blogRow   = document.getElementById('js-blog-row');
        const folderRow = document.getElementById('js-folder-row');
        const toggleImportTarget = function () {
            const v = importTargetSelect ? importTargetSelect.value : 'all';
            if (blogRow)   blogRow.style.display   = (v === 'pages') ? 'none' : '';
            if (folderRow) folderRow.style.display = (v === 'posts') ? 'none' : '';
        };
        if (importTargetSelect) {
            importTargetSelect.addEventListener('change', toggleImportTarget);
            toggleImportTarget();
        }

        // author-strategy の表示切替
        const authorStrategySelect = document.getElementById('author-strategy');
        const authorAssignWrapper  = document.getElementById('js-author-assign-wrapper');
        if (authorStrategySelect && authorAssignWrapper) {
            const toggleAuthor = function () {
                authorAssignWrapper.style.display = authorStrategySelect.value === 'assign' ? '' : 'none';
            };
            authorStrategySelect.addEventListener('change', toggleAuthor);
            toggleAuthor();
        }

        // url-replace-mode の表示切替
        const urlReplaceModeSelect = document.getElementById('url-replace-mode');
        const urlReplaceWrapper    = document.getElementById('js-url-replace-wrapper');
        if (urlReplaceModeSelect && urlReplaceWrapper) {
            const toggleUrl = function () {
                urlReplaceWrapper.style.display = urlReplaceModeSelect.value === 'replace' ? '' : 'none';
            };
            urlReplaceModeSelect.addEventListener('change', toggleUrl);
            toggleUrl();
        }

        // アップロード & 解析
        const uploadBtn      = document.getElementById('js-upload-analyze-btn');
        const fileInput      = document.getElementById('wxr-file');
        const startImportBtn = document.getElementById('js-start-import-btn');

        if (uploadBtn && fileInput) {
            uploadBtn.addEventListener('click', async function () {
                showError('');
                if (!fileInput.files || !fileInput.files[0]) {
                    showError('WXRファイルを選択してください。');
                    return;
                }
                uploadBtn.disabled = true;
                try {
                    const uploadData = new FormData();
                    uploadData.append('wxr_file', fileInput.files[0]);
                    const uploadResult = await apiPost(config.adminBase + '/upload', uploadData, config.csrfToken);

                    const analyzeData = new FormData();
                    analyzeData.append('token', uploadResult.job.token);
                    const analyzeResult = await apiPost(config.adminBase + '/analyze', analyzeData, config.csrfToken);

                    const tokenInput = document.getElementById('js-review-token');
                    if (tokenInput) tokenInput.value = uploadResult.job.token;

                    showAnalysisSummary(analyzeResult.result ?? {});  // result = {token, status, phase, summary:{item_counts,authors,...}}
                    showImportSettings();
                    uploadBtn.style.display = 'none';
                    if (startImportBtn) startImportBtn.style.display = '';
                } catch (error) {
                    showError(error.message || '処理に失敗しました。');
                } finally {
                    uploadBtn.disabled = false;
                }
            });
        }

        // インポート実行
        if (startImportBtn) {
            startImportBtn.addEventListener('click', async function () {
                showError('');

                // バリデーション
                const importTarget = document.getElementById('import-target')?.value ?? 'all';
                const blogId       = document.getElementById('blog-content-id')?.value ?? '';
                const folderId     = document.getElementById('content-folder-id')?.value ?? '';
                const authorStrategy = document.getElementById('author-strategy')?.value ?? 'match';
                const assignUserId   = document.getElementById('author-assign-user-id')?.value ?? '';
                const urlMode        = document.getElementById('url-replace-mode')?.value ?? 'keep';
                const urlFrom        = document.getElementById('url-replace-from')?.value ?? '';
                const urlTo          = document.getElementById('url-replace-to')?.value ?? '';

                const errors = [];
                if (importTarget !== 'pages' && !blogId) {
                    errors.push('取込先ブログを選択してください。');
                }
                if (importTarget !== 'posts' && !folderId) {
                    errors.push('固定ページ配置先フォルダを選択してください。');
                }
                if (authorStrategy === 'assign' && !assignUserId) {
                    errors.push('指定ユーザーへ一括割当の場合、ユーザーを選択してください。');
                }
                if (urlMode === 'replace' && !urlFrom.trim()) {
                    errors.push('URL 置換の場合、置換元 URL を入力してください。');
                }
                if (urlMode === 'replace' && !urlTo.trim()) {
                    errors.push('URL 置換の場合、置換先 URL を入力してください。');
                }
                if (errors.length > 0) {
                    showError(errors.join('\n'));
                    return;
                }

                startImportBtn.disabled = true;
                showSection('js-progress-section');

                try {
                    await apiPost(config.adminBase + '/save_review_settings', collectReviewFormData(), config.csrfToken);

                    const tokenFormData = new FormData();
                    tokenFormData.append('token', document.getElementById('js-review-token')?.value ?? '');
                    const result = await apiPost(config.adminBase + '/import', tokenFormData, config.csrfToken);

                    showResult(result.result || {});
                } catch (error) {
                    showSection('js-upload-section');
                    showError(error.message || 'インポートに失敗しました。');
                    startImportBtn.disabled = false;
                }
            });
        }

        // 中止ボタンは同期インポートでは機能しないため削除済み

        // 新しいインポートを開始
        const restartBtn = document.getElementById('js-restart-btn');
        if (restartBtn) {
            restartBtn.addEventListener('click', function () {
                window.location.reload();
            });
        }

        // 個別削除ボタン（pending・history 両方）
        document.querySelectorAll('.js-delete-btn').forEach(function (button) {
            button.addEventListener('click', async function () {
                try {
                    await deleteJob(button.dataset.token, button.closest('tr'));
                    updateBulkDeleteButton();
                } catch (error) {
                    window.alert('削除に失敗しました: ' + (error.message || 'エラーが発生しました。'));
                }
            });
        });
        // 未完了: 全選チェックボックス
        const pendingCheckAll = document.getElementById('js-pending-check-all');
        if (pendingCheckAll) {
            pendingCheckAll.addEventListener('change', function () {
                document.querySelectorAll('.js-pending-check').forEach(function (cb) {
                    cb.checked = pendingCheckAll.checked;
                });
                updateBulkDeleteButton();
            });
        }

        // 未完了: 個別チェックボックス（イベント委譲）
        const pendingTbody = document.getElementById('js-pending-tbody');
        if (pendingTbody) {
            pendingTbody.addEventListener('change', function (e) {
                if (e.target.classList.contains('js-pending-check')) {
                    updateBulkDeleteButton();
                    if (!e.target.checked && pendingCheckAll) pendingCheckAll.checked = false;
                }
            });
        }

        // 未完了: 一括削除
        const pendingDeleteAllBtn = document.getElementById('js-pending-delete-all-btn');
        if (pendingDeleteAllBtn) {
            pendingDeleteAllBtn.addEventListener('click', async function () {
                const tokens = Array.from(document.querySelectorAll('.js-pending-check:checked')).map(function (cb) { return cb.value; });
                if (tokens.length === 0) return;
                if (!window.confirm(tokens.length + ' 件の未完了ジョブを削除しますか？')) return;
                pendingDeleteAllBtn.disabled = true;
                try {
                    await deleteAllJobs(tokens);
                    if (pendingCheckAll) pendingCheckAll.checked = false;
                    updateBulkDeleteButton();
                } catch (error) {
                    window.alert('削除に失敗しました: ' + (error.message || 'エラーが発生しました。'));
                    pendingDeleteAllBtn.disabled = false;
                    updateBulkDeleteButton();
                }
            });
        }
        // 履歴: 全選択チェックボックス
        const checkAll = document.getElementById('js-history-check-all');
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('.js-history-check').forEach(function (cb) {
                    cb.checked = checkAll.checked;
                });
                updateBulkDeleteButton();
            });
        }

        // 履歴: 個別チェックボックス（イベント委譲）
        const historyTbody = document.getElementById('js-history-tbody');
        if (historyTbody) {
            historyTbody.addEventListener('change', function (e) {
                if (e.target.classList.contains('js-history-check')) {
                    updateBulkDeleteButton();
                    if (!e.target.checked && checkAll) checkAll.checked = false;
                }
            });
        }

        // 履歴: 一括削除
        const deleteAllBtn = document.getElementById('js-history-delete-all-btn');
        if (deleteAllBtn) {
            deleteAllBtn.addEventListener('click', async function () {
                const tokens = Array.from(document.querySelectorAll('.js-history-check:checked')).map(function (cb) { return cb.value; });
                if (tokens.length === 0) return;
                if (!window.confirm(tokens.length + ' 件の履歴を削除しますか？')) return;
                deleteAllBtn.disabled = true;
                try {
                    await deleteAllJobs(tokens);
                    if (checkAll) checkAll.checked = false;
                    updateBulkDeleteButton();
                } catch (error) {
                    window.alert('削除に失敗しました: ' + (error.message || 'エラーが発生しました。'));
                    deleteAllBtn.disabled = false;
                    updateBulkDeleteButton();
                }
            });
        }
    });
})();

