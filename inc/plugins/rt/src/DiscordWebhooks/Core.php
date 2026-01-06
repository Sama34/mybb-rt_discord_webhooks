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

use postParser;

class Core
{
	public static array $PLUGIN_DETAILS = [
		'name' => 'RT Discord Webhooks',
		'website' => 'https://github.com/RevertIT/mybb-rt_discord_webhooks',
		'description' => 'A simple integration of Discord Webhooks API',
		'author' => 'RevertIT',
		'authorsite' => 'https://github.com/RevertIT/',
		'version' => '1.6',
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
		global $db;

		static $is_installed;

		if ($is_installed === null) {
			$is_installed = $db->table_exists('rt_discord_webhooks');

			if ($is_installed) {
				$existing_columns = array_column($db->show_fields_from('rt_discord_webhooks'), 'Field');

				foreach (
					[
						'id',
						'webhook_url',
						'webhook_name',
						'webhook_message',
						'webhook_message_append',
						'webhook_embeds',
						'webhook_embeds_color',
						'webhook_embeds_thumbnail',
						'webhook_embeds_footer_text',
						'webhook_embeds_footer_icon_url',
						'bot_id',
						'watch_new_threads',
						'watch_new_posts',
						'watch_edit_threads',
						'watch_edit_posts',
						'watch_delete_threads',
						'watch_delete_posts',
						'watch_new_registrations',
						'character_limit',
						'allowed_mentions',
						'watch_usergroups',
						'watch_forums',
					] as $column_name
				) {
					$is_installed = $is_installed && in_array($column_name, $existing_columns);
				}
			}
		}

		return $is_installed;
	}

	public static function is_enabled(): bool
	{
		global $mybb;

		return isset($mybb->settings['rt_discord_webhooks_enabled']) && (int)$mybb->settings['rt_discord_webhooks_enabled'] === 1;
	}

	/**
	 * Add settings
	 *
	 * @return void
	 */
	public static function add_settings(): void
	{
		global $PL;

		$PL->settings(
			self::$PLUGIN_DETAILS['prefix'],
			'RT Discord Webhooks',
			'Setting group for the RT Discord Webhooks plugin.',
			[
				'enabled' => [
					'title' => 'Enable Discord Webhooks plugin?',
					'description' => 'Enable Discord Webhooks.',
					'optionscode' => 'yesno',
					'value' => 1
				],
				'thirdparty' => [
					'title' => 'Enable Discord Webhooks for Third-party plugins?',
					'description' => 'This will let plugins to hook into RT Discord Webhooks and send their custom hooks',
					'optionscode' => 'yesno',
					'value' => 1
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

		switch ($db->type) {
			case 'pgsql':
				$db->write_query(
					<<<PGSQL
                CREATE TABLE {$table_prefix}rt_discord_webhooks (
                    id SERIAL PRIMARY KEY,
                    webhook_url TEXT,
                    webhook_name VARCHAR(255) NULL DEFAULT NULL
                    webhook_message TEXT,
                    webhook_message_append SMALLINT NOT NULL DEFAULT 0,
                    webhook_embeds SMALLINT NOT NULL DEFAULT 0,
                    webhook_embeds_color TEXT,
                    webhook_embeds_thumbnail TEXT,
                    webhook_embeds_footer_text TEXT,
                    webhook_embeds_footer_icon_url TEXT,
                    bot_id INTEGER NOT NULL DEFAULT 0,
                    watch_new_threads SMALLINT NOT NULL DEFAULT 0,
                    watch_new_posts SMALLINT NOT NULL DEFAULT 0,
                    watch_edit_threads SMALLINT NOT NULL DEFAULT 0,
                    watch_edit_posts SMALLINT NOT NULL DEFAULT 0,
                    watch_delete_threads SMALLINT NOT NULL DEFAULT 0,
                    watch_delete_posts SMALLINT NOT NULL DEFAULT 0,
                    watch_new_registrations SMALLINT NOT NULL DEFAULT 0,
                    character_limit INTEGER NOT NULL DEFAULT 500,
                    allowed_mentions SMALLINT NOT NULL DEFAULT 0,
                    watch_usergroups TEXT,
                    watch_forums TEXT,
                );
                PGSQL
				);
				$db->write_query(
					<<<PGSQL
                CREATE TABLE {$table_prefix}rt_discord_webhooks_logs (
                    id SERIAL PRIMARY KEY,
                    discord_message_id TEXT,
                    discord_channel_id TEXT,
                    webhook_id TEXT,
                    tid INTEGER NOT NULL DEFAULT 0,
                    pid INTEGER NOT NULL DEFAULT 0,
                );
                PGSQL
				);
				break;
			case 'sqlite':
				$db->write_query(
					<<<SQLITE
                CREATE TABLE {$table_prefix}rt_discord_webhooks (
                    id INTEGER PRIMARY KEY,
                    webhook_url TEXT,
                    webhook_name VARCHAR(255) DEFAULT NULL,
                    webhook_message TEXT,
                    webhook_message_append INTEGER NOT NULL DEFAULT 0,
                    webhook_embeds INTEGER NOT NULL DEFAULT 0,
                    webhook_embeds_color TEXT,
                    webhook_embeds_thumbnail TEXT,
                    webhook_embeds_footer_text TEXT,
                    webhook_embeds_footer_icon_url TEXT,
                    bot_id INTEGER NOT NULL DEFAULT 0,
                    watch_new_threads INTEGER NOT NULL DEFAULT 0,
                    watch_new_posts INTEGER NOT NULL DEFAULT 0,
                    watch_edit_threads INTEGER NOT NULL DEFAULT 0,
                    watch_edit_posts INTEGER NOT NULL DEFAULT 0,
                    watch_delete_threads INTEGER NOT NULL DEFAULT 0,
                    watch_delete_posts INTEGER NOT NULL DEFAULT 0,
                    watch_new_registrations INTEGER NOT NULL DEFAULT 0,
                    character_limit INTEGER NOT NULL DEFAULT 500,
                    allowed_mentions INTEGER NOT NULL DEFAULT 0,
                    watch_usergroups TEXT,
                    watch_forums TEXT,
                );
                SQLITE
				);
				$db->write_query(
					<<<SQLITE
                CREATE TABLE {$table_prefix}rt_discord_webhooks_logs (
                    id INTEGER PRIMARY KEY,
                    discord_message_id TEXT,
                    discord_channel_id TEXT,
                    webhook_id TEXT,
                    tid INTEGER NOT NULL DEFAULT 0,
                    pid INTEGER NOT NULL DEFAULT 0,
                );
                SQLITE
				);
				break;
			default:
				$db->write_query(
					<<<SQL
                CREATE TABLE IF NOT EXISTS `{$table_prefix}rt_discord_webhooks`(
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `webhook_url` TEXT DEFAULT NULL,
                    `webhook_name` VARCHAR(255) NULL DEFAULT NULL,
                    `webhook_message` TEXT DEFAULT NULL,
                    `webhook_message_append` TINYINT(4) NOT NULL DEFAULT 0,
                    `webhook_embeds` TINYINT(4) NOT NULL DEFAULT 0,
                    `webhook_embeds_color` TEXT DEFAULT NULL,
                    `webhook_embeds_thumbnail` TEXT DEFAULT NULL,
                    `webhook_embeds_footer_text` text DEFAULT NULL,
                    `webhook_embeds_footer_icon_url` text DEFAULT NULL,
                    `bot_id` INT(11) NOT NULL DEFAULT 0,
                    `watch_new_threads` TINYINT(4) NOT NULL DEFAULT 0,
                    `watch_new_posts` TINYINT(4) NOT NULL DEFAULT 0,
                    `watch_edit_threads` TINYINT(4) NOT NULL DEFAULT 0,
                    `watch_edit_posts` TINYINT(4) NOT NULL DEFAULT 0,
                    `watch_delete_threads` TINYINT(4) NOT NULL DEFAULT 0,
                    `watch_delete_posts` TINYINT(4) NOT NULL DEFAULT 0,
                    `watch_new_registrations` TINYINT(4) NOT NULL DEFAULT 0,
                    `character_limit` INT(11) NOT NULL DEFAULT 500,
                    `allowed_mentions` TINYINT(4) NOT NULL DEFAULT 0,
                    `watch_usergroups` text DEFAULT NULL,
                    `watch_forums` text DEFAULT NULL,
                    PRIMARY KEY(`id`)
                ) ENGINE = InnoDB;
                SQL
				);
				$db->write_query(
					<<<SQL
                CREATE TABLE IF NOT EXISTS `{$table_prefix}rt_discord_webhooks_logs`(
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `discord_message_id` TEXT DEFAULT NULL,
                    `discord_channel_id` TEXT DEFAULT NULL,
                    `webhook_id` TEXT DEFAULT NULL,
                    `tid` INT(11) NOT NULL DEFAULT 0,
                    `pid` INT(11) NOT NULL DEFAULT 0,
                    PRIMARY KEY(`id`)
                ) ENGINE = InnoDB;
                SQL
				);
		}
	}

	public static function remove_database_modifications(): void
	{
		global $db, $mybb, $page, $lang;

		if ($mybb->request_method !== 'post') {
			$lang->load(self::$PLUGIN_DETAILS['prefix']);

			$page->output_confirm_action(
				'index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=' . self::$PLUGIN_DETAILS['prefix'],
				$lang->rt_discord_webhooks_uninstall_message,
				$lang->uninstall
			);
		}

		// Drop tables
		if (!isset($mybb->input['no'])) {
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

		if (!empty(self::$PLUGIN_DETAILS)) {
			$cache->update(self::$PLUGIN_DETAILS['prefix'], self::$PLUGIN_DETAILS);
		}
	}

	/**
	 * Remove plugin cache
	 *
	 * @return void
	 */
	public static function remove_cache(): void
	{
		global $cache;

		$cache->delete(self::$PLUGIN_DETAILS['prefix']);
		$cache->delete(self::$PLUGIN_DETAILS['prefix'] . '_cached_hooks');
	}

	public static function get_replace_objects(?int $post_id = null, ?int $user_id = null): array
	{
		global $mybb, $db, $lang, $plugins;

		if ($post_id) {
			$post_data = get_post($post_id);

			$user_id = (int)$post_data['uid'];

			$thread_id = (int)$post_data['tid'];

			$forum_id = (int)$post_data['fid'];
		}

		$user_data = get_user($user_id);

		$groups_cache = $mybb->cache->read('usergroups');

		$titles_cache = $mybb->cache->read('usertitles');

		$replace_objects = [
			'user_id' => $user_data['uid'] ?? 0,
			'user_posts' => my_number_format($user_data['postnum'] ?? 0),
			'user_threads' => my_number_format($user_data['threadnum'] ?? 0),
		];

		if (empty($user_data['hideemail'])) {
			$replace_objects['user_email'] = $user_data['email'] ?? '';
		}

		// Get the usergroup
		if (!empty($user_data['usergroup'])) {
			$user_group = usergroup_permissions($user_data['usergroup']);
		} else {
			$user_group = usergroup_permissions(1);
		}

		//$replace_objects['user_group_primary'] = $groups_cache[$user_data['usergroup']]['title'] ?? '';

		$replace_objects['user_group_additional'] = implode(
			$lang->comma ?? ', ',
			array_map(function ($gid) use ($groups_cache) {
				return $groups_cache[$gid]['title'] ?? '';
			}, explode(',', $user_data['additionalgroups'] ?? ''))
		);

		global $displaygroupfields;

		$displaygroupfields = array('title', 'description', 'namestyle', 'usertitle', 'image');

		if (empty($user_data['displaygroup'])) {
			$user_data['displaygroup'] = $user_data['usergroup'];
		}

		$display_group = usergroup_displaygroup($user_data['displaygroup']);

		if (is_array($display_group)) {
			$user_group = array_merge($user_group, $display_group);
		}

		$replace_objects['user_group_display'] = $groups_cache[$user_data['displaygroup']]['title'] ?? '';

		$replace_objects['user_username'] = $user_data['username'] ?? $lang->guest ?? 'Guest';

		// Event made by a registered user
		if (!empty($user_data['uid'])) {
			if (trim($user_data['usertitle']) != '') {
				// Do nothing, no need for an extra variable.
			} elseif ($user_group['usertitle'] != '') {
				$user_data['usertitle'] = $user_group['usertitle'];
			} elseif (is_array($titles_cache) && !$user_group['usertitle']) {
				reset($titles_cache);

				foreach ($titles_cache as $title) {
					if ($user_data['postnum'] >= $title['posts']) {
						$user_data['usertitle'] = $title['title'];

						break;
					}
				}
			}
		} elseif ($user_group['usertitle']) {
			$user_data['usertitle'] = $user_group['usertitle'];
		} else {
			$user_data['usertitle'] = $lang->guest ?? 'Guest';
		}

		$replace_objects['user_title'] = $user_data['usertitle'];

		global $parser;

		if (!($parser instanceof postParser)) {
			require_once MYBB_ROOT . 'inc/class_parser.php';

			$parser = new postParser();
		}

		$replace_objects['user_signature'] = strip_tags($parser->parse_message($user_data['signature'], array(
			'allow_html' => !empty($mybb->settings['sightml']),
			'allow_mycode' => false,
			'allow_smilies' => false,
			'filter_badwords' => true
		)));

		$replace_objects['user_reputation'] = strip_tags(get_reputation($user_data['reputation'] ?? 0));

		$warning_level = round($user_data['warningpoints'] / $mybb->settings['maxwarningpoints'] * 100);

		$replace_objects['user_warning_points'] = strip_tags(get_colored_warning_level(min($warning_level, 100)));

		$hook_arguments = [
			'post_id' => $post_id,
			'user_id' => $user_id,
			'replace_objects' => &$replace_objects,
		];

		$user_fields = [];

		$query = $db->simple_select('userfields', '*', "ufid='{$user_id}'");

		foreach ((array)$db->fetch_array($query) as $column_name => $field_value) {
			if (my_substr($column_name, 0, 3) === 'fid') {
				$user_fields[$column_name] = $field_value;
			}
		}

		$profile_fields_cache = $mybb->cache->read('profilefields');

		foreach ($profile_fields_cache as $custom_field) {
			if (!\is_member($custom_field['viewableby'], $user_data)) {
				continue;
			}

			$type = trim(
				explode(
					"\n",
					$custom_field['type'],
					2
				)[0]
			);

			$custom_field_value = '';

			$custom_field_value_multi = [];

			$field = "fid{$custom_field['fid']}";

			if (isset($user_fields[$field])) {
				$user_options = explode("\n", $user_fields[$field]);

				$custom_field_value = $comma = '';

				if (is_array($user_options) && ($type == 'multiselect' || $type == 'checkbox')) {
					foreach ($user_options as $val) {
						if ($val) {
							$custom_field_value_multi[] = $val;
						}
					}

					if ($custom_field_value_multi) {
						$custom_field_value = implode($lang->comma ?? ', ', $custom_field_value_multi);
					}
				} else {
					$parser_options = array(
						'allow_html' => !empty($custom_field['allowhtml']),
						'allow_mycode' => false,
						'allow_smilies' => false,
						'filter_badwords' => true
					);

					if ($custom_field['type'] == 'textarea') {
						$parser_options['me_username'] = $user_data['username'];
					} else {
						$parser_options['nl2br'] = false;
					}

					$custom_field_value = $parser->parse_message($user_fields[$field], $parser_options);
				}
			}

			$replace_objects['user_field_' . $custom_field['fid']] = strip_tags($custom_field_value ?? '');
		}

		if (!empty($thread_id) &&
			!empty($forum_id) &&
			function_exists('xthreads_gettfcache') &&
			$threadfield_cache = xthreads_gettfcache()) {
			$query_fields = ['t.tid'];

			foreach ($threadfield_cache as $k => &$threadfield) {
				$available = empty($v['forums']);

				if (!$available) {
					foreach (array_map('intval', explode(',', $threadfield['forums'])) as $fid) {
						if ($fid === $forum_id) {
							$available = true;

							break;
						}
					}
				}

				if ($available) {
					$query_fields[] = "tfd.`{$threadfield['field']}` AS `xthreads_{$threadfield['field']}`";
				}

				$replace_objects['xthreads_' . $threadfield['field']] = $replace_objects['xthreads_raw_' . $threadfield['field']] = '';
			}

			$query = $db->simple_select(
				"threads t LEFT JOIN {$db->table_prefix}threadfields_data tfd ON (tfd.tid=t.tid)",
				implode(', ', $query_fields),
				"t.tid='{$thread_id}'",
				['limit' => 1]
			);

			$xthreads_data = (array)$db->fetch_array($query);

			xthreads_set_threadforum_urlvars('thread', $thread_id);

			xthreads_set_threadforum_urlvars('forum', $forum_id);

			$threadfields = array();

			foreach ($threadfield_cache as $k => &$v) {
				if ($v['forums'] && strpos(',' . $v['forums'] . ',', ',' . $forum_id . ',') === false) {
					continue;
				}

				xthreads_get_xta_cache($v, $thread_id);

				$threadfields[$k] =& $xthreads_data['xthreads_' . $k];

				xthreads_sanitize_disp(
					$threadfields[$k],
					$v,
					($user_data['username'] !== '' ? $user_data['username'] : $xthreads_data['threadusername'])
				);
			}

			global $threadfields_x;

			foreach ($threadfields as $threadfield_name => $threadfield_value) {
				$replace_objects['xthreads_' . $threadfield_name] = strip_tags($threadfield_value);

				$replace_objects['xthreads_raw_' . $threadfield_name] = $threadfields_x[$threadfield_name]['raw_value'] ?? '';
			}
		}

		$plugins->run_hooks('rt_discord_webhooks_get_replace_objects', $hook_arguments);

		return array_flip(array_map(function (string $value): string {
			return '{' . $value . '}';
		}, array_flip($replace_objects)));
	}
}