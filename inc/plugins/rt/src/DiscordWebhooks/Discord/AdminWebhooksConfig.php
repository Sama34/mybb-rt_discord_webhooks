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

namespace rt\DiscordWebhooks\Discord;

use datacache;
use DB_Base;
use MyBB;
use MyLanguage;
use Page;
use rt\DiscordWebhooks\Core;

class AdminWebhooksConfig
{
	protected DB_Base $db;
	protected MyBB $mybb;
	protected MyLanguage $lang;
	protected datacache $cache;
	protected string $table_prefix;
	protected string $prefix;

	public function __construct(DB_Base $db, MyBB $mybb, datacache $cache, MyLanguage $lang)
	{
		$this->mybb = $mybb;
		$this->db = $db;
		$this->cache = $cache;
		$this->lang = $lang;
		$this->prefix = Core::get_plugin_info('prefix');
		$this->table_prefix = TABLE_PREFIX;

		// Load language
		$this->lang->load(Core::get_plugin_info('prefix'));
	}

	/**
	 * Total Webhook Rows
	 *
	 * @return int
	 */
	public function totalWebhookRows(): int
	{
		$query = $this->db->write_query(
			<<<SQL
				SELECT
					COUNT(*) as hooks
				FROM
					{$this->table_prefix}rt_discord_webhooks
				SQL
		);

		return (int)$this->db->fetch_field($query, 'hooks');
	}

	/**
	 * Get Edit Webhook layout
	 *
	 * @param array $sub_tabs
	 * @param Page $page
	 * @return void
	 */
	public function getEditWebhook(array $sub_tabs, Page $page): void
	{
		$edit = new AdminWebhooksEdit($this->db, $this->mybb, $this->cache, $this->lang);

		$edit->load($sub_tabs, $page);
	}

	/**
	 * Get Add Webhook layout
	 *
	 * @param array $sub_tabs
	 * @param Page $page
	 * @return void
	 */
	public function getAddWebhook(array $sub_tabs, Page $page): void
	{
		$add = new AdminWebhooksAdd($this->db, $this->mybb, $this->cache, $this->lang);

		$add->load($sub_tabs, $page);
	}

	/**
	 * Get View Webhook layout
	 *
	 * @param array $sub_tabs
	 * @param Page $page
	 * @return void
	 */
	public function getViewWebhook(array $sub_tabs, Page $page): void
	{
		$view = new AdminWebhooksView($this->db, $this->mybb, $this->cache, $this->lang);

		$view->load($sub_tabs, $page);
	}

	/**
	 * Get Webhook Rows
	 *
	 * as array and pagination
	 *
	 * @param int $per_page
	 * @return array
	 */
	public function getWebhookRowsArray(int $per_page = 20): array
	{
		$pagenum = $this->mybb->get_input('page', MyBB::INPUT_INT);
		$total_rows = $this->totalWebhookRows();

		if ($pagenum) {
			$start = ($pagenum - 1) * $per_page;
			$pages = ceil($total_rows / $per_page);

			if ($pagenum > $pages) {
				$start = 0;
				$pagenum = 1;
			}
		} else {
			$start = 0;
			$pagenum = 1;
		}

		$query = $this->db->write_query(
			<<<SQL
				SELECT
				   *
				FROM
					{$this->table_prefix}rt_discord_webhooks
				ORDER BY
					id DESC
				LIMIT
					{$start}, {$per_page}
				SQL
		);

		$data = [];

		foreach ($query as $row) {
			$data['query'][] = $row;
		}

		$data['pagination'] = [
			'start' => $start,
			'pagenum' => $pagenum,
			'per_page' => $per_page,
			'total_rows' => $total_rows,
		];

		return $data;
	}

	/**
	 * @param int $webhook_int
	 * @return array
	 */
	public function getWebhookRowArray(int $webhook_int): array
	{
		$query = $this->db->simple_select(
			'rt_discord_webhooks',
			'*',
			"id = '{$this->db->escape_string($webhook_int)}'"
		);

		return (array)$this->db->fetch_array($query);
	}

	/**
	 * Check if Webhook exists in DB
	 *
	 * @param int $webhook_id
	 * @return bool
	 */
	public function webhookExists(int $webhook_id): bool
	{
		$query = $this->db->simple_select('rt_discord_webhooks', '*', "id = '{$this->db->escape_string($webhook_id)}'");

		return !empty($this->db->fetch_array($query));
	}

	/**
	 * Duplicate Webhook URL
	 *
	 * @param string $webhook_url
	 * @return bool
	 */
	public function duplicateWebhookUrl(string $webhook_url): bool
	{
		$query = $this->db->simple_select(
			'rt_discord_webhooks',
			'COUNT(webhook_url) as count',
			"webhook_url = '{$this->db->escape_string($webhook_url)}'"
		);

		return ((int)$this->db->fetch_field($query, 'count') > 1);
	}

	/**
	 * Rebuild Webhooks
	 *
	 * @return void
	 */
	public function rebuildWebhooks(): void
	{
		$to_cache = $this->getWebhookRowsArray(100)['query'] ?? [];

		$this->cache->update(Core::get_plugin_info('prefix') . '_cached_hooks', $to_cache);
	}
}