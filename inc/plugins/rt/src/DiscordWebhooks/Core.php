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

namespace rt\DiscordWebhooks;

class Core
{
    public static array $PLUGIN_DETAILS = [
        'name' => 'RT Discord Webhooks',
        'website' => 'https://github.com/RevertIT/mybb-rt_discord_webhooks',
        'description' => 'A simple integration of discord webhooks with multiple insertions',
        'author' => 'RevertIT',
        'authorsite' => 'https://github.com/RevertIT/',
        'version' => '0.1',
        'compatibility' => '18*',
        'codename' => 'rt_discord_webhooks',
        'prefix' => 'rt_discord_webhooks',
    ];

    /**
     * Get plugin info
     *
     * @param string $info
     * @return string
     */
    public static function get_plugin_info(string $info): string
    {
        return self::$PLUGIN_DETAILS[$info] ?? '';
    }

    /**
     * Check if plugin is installed
     *
     * @return bool
     */
    public static function is_installed(): bool
    {
        global $mybb;

        if (isset($mybb->settings['rt_discord_webhooks_enabled']))
        {
            return true;
        }

        return false;
    }

    public static function is_enabled(): bool
    {
        global $mybb;

        return isset($mybb->settings['rt_discord_webhooks_enabled']) && (int) $mybb->settings['rt_discord_webhooks_enabled'] === 1;
    }

    /**
     * Add settings
     *
     * @return void
     */
    public static function add_settings(): void
    {
        global $PL;

        $PL->settings(self::$PLUGIN_DETAILS['prefix'],
            "RT Discord Webhooks",
            "Setting group for the RT Discord Webhooks plugin.",
            [
                "enabled" => [
                    "title" => "Enable Discord Webhooks plugin?",
                    "description" => "Enable Discord Webhooks.",
                    "optionscode" => "yesno",
                    "value" => 1
                ],
            ],
        );
    }

    public static function remove_settings(): void
    {
        global $PL;

        $PL->settings_delete(self::$PLUGIN_DETAILS['prefix'], true);
    }

    public static function add_database_modifications(): void
    {
        global $db;

        $table_prefix = TABLE_PREFIX;

        switch ($db->type)
        {
            case 'pgsql':
                $db->write_query(<<<PGSQL
                CREATE TABLE {$table_prefix}rt_discord_webhooks (
                    id SERIAL PRIMARY KEY,
                    webhook_url TEXT,
                    webhook_type SMALLINT NOT NULL DEFAULT 1,
                    bot_id INTEGER NOT NULL DEFAULT 0,
                    bot_color_name TEXT,
                    watch_new_threads SMALLINT NOT NULL DEFAULT 0,
                    watch_new_posts SMALLINT NOT NULL DEFAULT 0,
                    watch_new_registrations SMALLINT NOT NULL DEFAULT 0,
                    character_limit INTEGER NOT NULL DEFAULT 500,
                    thumbnail_image TEXT,
                    watch_usergroups TEXT,
                    watch_forums TEXT,
                );
                PGSQL);
                break;
            case 'sqlite':
                $db->write_query(<<<SQLITE
                CREATE TABLE {$table_prefix}rt_discord_webhooks (
                    id INTEGER PRIMARY KEY,
                    webhook_url TEXT,
                    webhook_type INTEGER NOT NULL DEFAULT 1,
                    bot_id INTEGER NOT NULL DEFAULT 0,
                    bot_color_name TEXT,
                    watch_new_threads INTEGER NOT NULL DEFAULT 0,
                    watch_new_posts INTEGER NOT NULL DEFAULT 0,
                    watch_new_registrations INTEGER NOT NULL DEFAULT 0,
                    character_limit INTEGER NOT NULL DEFAULT 500,
                    thumbnail_image TEXT,
                    watch_usergroups TEXT,
                    watch_forums TEXT,
                );
                SQLITE);
                break;
            default:
                $db->write_query(<<<SQL
                CREATE TABLE IF NOT EXISTS `{$table_prefix}rt_discord_webhooks`(
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `webhook_url` TEXT DEFAULT NULL,
                    `webhook_type` TINYINT(4) NOT NULL DEFAULT 1,
                    `bot_id` INT(11) NOT NULL DEFAULT 0,
                    `bot_color_name` TEXT DEFAULT NULL,
                    `watch_new_threads` TINYINT(4) NOT NULL DEFAULT 0,
                    `watch_new_posts` TINYINT(4) NOT NULL DEFAULT 0,
                    `watch_new_registrations` TINYINT(4) NOT NULL DEFAULT 0,
                    `character_limit` INT(11) NOT NULL DEFAULT 500,
                    `thumbnail_image` text DEFAULT NULL,
                    `watch_usergroups` text DEFAULT NULL,
                    `watch_forums` text DEFAULT NULL,
                    PRIMARY KEY(`id`)
                ) ENGINE = InnoDB;
                SQL);
        }
    }

    public static function remove_database_modifications(): void
    {
        global $db, $mybb, $page, $lang;

        if ($mybb->request_method !== 'post')
        {
            $lang->load(self::$PLUGIN_DETAILS['prefix']);

            $page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=' . self::$PLUGIN_DETAILS['prefix'], $lang->rt_discord_webhooks_uninstall_message, $lang->uninstall);
        }

        // Drop tables
        if (!isset($mybb->input['no']))
        {
            $db->drop_table(self::$PLUGIN_DETAILS['prefix'] . '_logs');
            $db->drop_table(self::$PLUGIN_DETAILS['prefix']);
        }
    }

    /**
     * Set plugin cache
     *
     * @return void
     */
    public static function set_cache(): void
    {
        global $cache;

        if (!empty(self::$PLUGIN_DETAILS))
        {
            $cache->update(self::$PLUGIN_DETAILS['prefix'], self::$PLUGIN_DETAILS);
        }
    }

    /**
     * Remove plugin cache
     *
     * @return void
     */
    public static function remove_cache()
    {
        global $cache;

        $cache->delete(self::$PLUGIN_DETAILS['prefix']);
        $cache->delete(self::$PLUGIN_DETAILS['prefix'] . '_cached_hooks');
    }
}