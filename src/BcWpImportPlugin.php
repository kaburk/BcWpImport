<?php
declare(strict_types=1);

namespace BcWpImport;

use BaserCore\BcPlugin;
use BcWpImport\Command\CleanupCommand;
use BcWpImport\Command\RunImportCommand;
use Cake\Console\CommandCollection;
use Cake\Core\PluginApplicationInterface;
use Cake\Log\Log;

class BcWpImportPlugin extends BcPlugin
{
	public function bootstrap(PluginApplicationInterface $app): void
	{
		parent::bootstrap($app);

		if (!in_array('wp_import', Log::configured(), true)) {
			Log::setConfig('wp_import', [
				'className' => 'File',
				'path' => LOGS,
				'file' => 'wp_import',
				'levels' => ['info', 'warning', 'error'],
				'scopes' => ['wp_import'],
			]);
		}

		$tmpDir = TMP . 'bc_wp_import' . DS;
		if (!is_dir($tmpDir)) {
			mkdir($tmpDir, 0777, true);
		}
	}

	public function console(CommandCollection $commands): CommandCollection
	{
		$commands = parent::console($commands);
		$commands->add('BcWpImport.cleanup', CleanupCommand::class);
		$commands->add('bc_wp_import.run_import', RunImportCommand::class);

		return $commands;
	}
}
