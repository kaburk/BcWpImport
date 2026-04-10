<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class CreateBcWpImportJobs extends BcMigration
{
    public function up()
    {
        $this->table('bc_wp_import_jobs', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('job_token', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'pending'])
            ->addColumn('phase', 'string', ['limit' => 30, 'null' => false, 'default' => 'upload'])
            ->addColumn('mode', 'string', ['limit' => 20, 'null' => false, 'default' => 'strict'])
            ->addColumn('import_target', 'string', ['limit' => 30, 'null' => false, 'default' => 'all'])
            ->addColumn('blog_content_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('content_folder_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('author_strategy', 'string', ['limit' => 30, 'null' => false, 'default' => 'match'])
            ->addColumn('author_assign_user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('slug_strategy', 'string', ['limit' => 30, 'null' => false, 'default' => 'suffix'])
            ->addColumn('publish_strategy', 'string', ['limit' => 30, 'null' => false, 'default' => 'keep'])
            ->addColumn('url_replace_mode', 'string', ['limit' => 30, 'null' => false, 'default' => 'keep'])
            ->addColumn('url_replace_from', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('url_replace_to', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('source_filename', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('wxr_path', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('parsed_summary', 'text', ['null' => true, 'default' => null])
            ->addColumn('import_settings', 'text', ['null' => true, 'default' => null])
            ->addColumn('analysis_position', 'biginteger', ['null' => true, 'default' => 0])
            ->addColumn('import_position', 'biginteger', ['null' => true, 'default' => 0])
            ->addColumn('total_items', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('analyzable_items', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('importable_items', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('processed', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('success_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('skip_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('warning_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('error_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('unsupported_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('error_log_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('warning_log_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('report_csv_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('expires_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('started_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('ended_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['job_token'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['phase'])
            ->addIndex(['expires_at'])
            ->addIndex(['blog_content_id'])
            ->addIndex(['created'])
            ->create();
    }

    public function down()
    {
        $this->table('bc_wp_import_jobs')->drop()->save();
    }
}
