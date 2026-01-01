<?php
/**
 * RT Discord Webhooks
 *
 * A simple integration of discord webhooks with multiple insertions
 *
 * @package rt_discord_webhooks
 * @author  RevertIT <https://github.com/revertit>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */

declare(strict_types=1);

// Disallow direct access to this file for security reasons
use rt\DiscordWebhooks\Core;

use function rt\Autoload\psr4_autoloader;
use function rt\DiscordWebHooks\autoload_plugin_hooks;
use function rt\DiscordWebhooks\check_curl_ext;
use function rt\DiscordWebhooks\check_php_version;
use function rt\DiscordWebhooks\check_pluginlibrary;
use function rt\DiscordWebhooks\load_curl_ext;
use function rt\DiscordWebhooks\load_plugin_version;
use function rt\DiscordWebhooks\load_pluginlibrary;

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.');
}

// Autoload classes
require_once MYBB_ROOT . 'inc/plugins/rt/vendor/autoload.php';

psr4_autoloader(
	'rt',
	'src',
	'rt\\DiscordWebhooks\\',
	[
		'rt/DiscordWebhooks/functions.php',
	]
);

// Autoload plugin hooks
autoload_plugin_hooks([
	'\rt\DiscordWebhooks\Hooks\Frontend',
	'\rt\DiscordWebhooks\Hooks\Backend',
]);

// Health checks
load_plugin_version();
load_pluginlibrary();
load_curl_ext();

function rt_discord_webhooks_info(): array
{
	return Core::$PLUGIN_DETAILS;
}

function rt_discord_webhooks_install(): void
{
	check_php_version();
	check_pluginlibrary();
	check_curl_ext();

	Core::add_database_modifications();
	Core::add_settings();
	Core::set_cache();
}

function rt_discord_webhooks_is_installed(): bool
{
	return Core::is_installed();
}

function rt_discord_webhooks_uninstall(): void
{
	check_php_version();
	check_pluginlibrary();
	check_curl_ext();

	Core::remove_database_modifications();
	Core::remove_settings();
	Core::remove_cache();
}

function rt_discord_webhooks_activate(): void
{
	global $db;

	if ($db->table_exists('rt_discord_webhooks') &&
		!$db->field_exists('webhook_message', 'rt_discord_webhooks')) {
		switch ($db->type) {
			case 'pgsql':
			case 'sqlite':
				$db->add_column('rt_discord_webhooks', 'webhook_message', 'TEXT');
				break;
			default:
				$db->add_column('rt_discord_webhooks', 'webhook_message', 'TEXT DEFAULT NULL');
		}
	}

	if ($db->table_exists('rt_discord_webhooks') &&
		!$db->field_exists('webhook_message_append', 'rt_discord_webhooks')) {
		switch ($db->type) {
			case 'pgsql':
				$db->add_column('rt_discord_webhooks', 'webhook_message_append', 'SMALLINT NOT NULL DEFAULT 0');
				break;
			case 'sqlite':
				$db->add_column('rt_discord_webhooks', 'webhook_message_append', 'INTEGER NOT NULL DEFAULT 0');
				break;
			default:
				$db->add_column('rt_discord_webhooks', 'webhook_message_append', 'TINYINT(4) NOT NULL DEFAULT 0');
		}
	}

	check_php_version();
	check_pluginlibrary();
	check_curl_ext();

	Core::add_settings();
	Core::set_cache();
}

function rt_discord_webhooks_deactivate(): void
{
	check_php_version();
	check_pluginlibrary();
	check_curl_ext();
}