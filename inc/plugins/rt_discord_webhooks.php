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
if (!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

// Autoload classes
require MYBB_ROOT . 'inc/plugins/rt/vendor/autoload.php';

\rt\Autoload\psr4_autoloader(
    'rt',
    'src',
    'rt\\DiscordWebhooks\\',
    [
        'rt/DiscordWebhooks/functions.php',
    ]
);

// Autoload plugin hooks
\rt\DiscordWebHooks\autoload_plugin_hooks([
    '\rt\DiscordWebhooks\Hooks\Frontend',
    '\rt\DiscordWebhooks\Hooks\Backend',
]);

// Health checks
\rt\DiscordWebhooks\load_plugin_version();
\rt\DiscordWebhooks\load_pluginlibrary();

function rt_discord_webhooks_info(): array
{
    return \rt\DiscordWebhooks\Core::$PLUGIN_DETAILS;
}

function rt_discord_webhooks_install(): void
{
    \rt\DiscordWebhooks\check_php_version();
    \rt\DiscordWebhooks\check_pluginlibrary();

    \rt\DiscordWebhooks\Core::add_settings();
    \rt\DiscordWebhooks\Core::set_cache();
}

function rt_discord_webhooks_is_installed(): bool
{
    return \rt\DiscordWebhooks\Core::is_installed();
}

function rt_discord_webhooks_uninstall(): void
{
    \rt\DiscordWebhooks\check_php_version();
    \rt\DiscordWebhooks\check_pluginlibrary();

    \rt\DiscordWebhooks\Core::remove_database_modifications();
    \rt\DiscordWebhooks\Core::remove_settings();
    \rt\DiscordWebhooks\Core::remove_cache();
}

function rt_discord_webhooks_activate(): void
{
    \rt\DiscordWebhooks\check_php_version();
    \rt\DiscordWebhooks\check_pluginlibrary();

    \rt\DiscordWebhooks\Core::add_settings();
    \rt\DiscordWebhooks\Core::set_cache();
}

function rt_discord_webhooks_deactivate(): void
{
    \rt\DiscordWebhooks\check_php_version();
    \rt\DiscordWebhooks\check_pluginlibrary();
}