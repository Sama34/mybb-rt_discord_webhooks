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

use Form;
use MyBB;
use rt\DiscordWebhooks\Core;
use Page;
use Table;

final class AdminWebhooksView extends AdminWebhooksConfig
{
	public function load(array $sub_tabs, Page $page): void
	{
		$table = new Table();
		$page->output_header(
			$this->lang->{$this->prefix . '_menu'} . ' - ' . $this->lang->{$this->prefix . '_tab_' . 'webhooks'}
		);
		$page->output_nav_tabs($sub_tabs, 'webhooks');
		$webhooks_db = $this->getWebhookRowsArray()['query'] ?? [];
		$webhooks_pagination = $this->getWebhookRowsArray()['pagination'] ?? [];

		if ($this->mybb->request_method === 'post') {
			// Delete handler
			if (!empty($this->mybb->get_input('delete_all'))) {
				$this->db->delete_query(Core::get_plugin_info('prefix'));
				$num_deleted = $this->db->affected_rows();

				// Log admin action
				log_admin_action($num_deleted);

				// Rebuild Webhooks cache
				$this->rebuildWebhooks();

				flash_message($this->lang->rt_discord_webhooks_delete_all_deleted, 'success');
				admin_redirect("index.php?module=tools-{$this->prefix}&amp;action=webhooks");
			}
			if (!empty($this->mybb->get_input('webhook', MyBB::INPUT_ARRAY))) {
				$webhooks_id = implode(',', array_map('intval', $this->mybb->get_input('webhook', MyBB::INPUT_ARRAY)));

				if ($webhooks_id) {
					$this->db->delete_query(Core::get_plugin_info('prefix'), "id IN ({$webhooks_id})");
					$num_deleted = $this->db->affected_rows();

					// Log admin action
					log_admin_action($num_deleted);

					// Rebuild Webhooks cache
					$this->rebuildWebhooks();
				}
				flash_message($this->lang->rt_discord_webhooks_delete_selected_deleted, 'success');
				admin_redirect("index.php?module=tools-{$this->prefix}&amp;action=webhooks");
			}
		}

		$form = new Form("index.php?module=tools-{$this->prefix}&amp;action=webhooks", 'post', 'webhooks');
		$table->construct_header($form->generate_check_box('allbox', 1, '', array('class' => 'checkall')));
		$table->construct_header($this->lang->{$this->prefix . '_webhooks_id'});
		$table->construct_header($this->lang->{$this->prefix . '_webhook_embeds'}, [
			'class' => 'align_center'
		]);
		$table->construct_header($this->lang->{$this->prefix . '_webhooks_bot_id'});
		$table->construct_header($this->lang->{$this->prefix . '_webhooks_watch_new_threads'});
		$table->construct_header($this->lang->{$this->prefix . '_webhooks_watch_new_posts'});
		$table->construct_header($this->lang->{$this->prefix . '_webhooks_watch_new_registrations'});
		$table->construct_header($this->lang->{$this->prefix . '_webhooks_watch_usergroups'});
		$table->construct_header($this->lang->{$this->prefix . '_webhooks_watch_forums'});
		$table->construct_header($this->lang->{$this->prefix . '_webhooks_char_limit'});

		$table->construct_header($this->lang->{$this->prefix . '_webhooks_controls'});

		foreach ($webhooks_db as $row) {
			$row['webhook_id'] = "<a href='index.php?module=tools-{$this->prefix}&amp;action=edit_webhook&amp;id={$row['id']}'>" . (preg_match(
					'/\/(\d+)\//',
					$row['webhook_url'],
					$webhook_id
				) ? isset($webhook_id[1]) ? (int)$webhook_id[1] : (int)$row['id'] : (int)$row['id']) . '</a>';
			$user = get_user($row['bot_id']);
			$row['bot_id'] = (int)$row['bot_id'];
			$row['watch_new_threads'] = !empty($row['watch_new_threads']) ? $this->lang->rt_discord_webhooks_enabled : $this->lang->rt_discord_webhooks_disabled;
			$row['watch_new_posts'] = !empty($row['watch_new_posts']) ? $this->lang->rt_discord_webhooks_enabled : $this->lang->rt_discord_webhooks_disabled;
			$row['watch_new_registrations'] = !empty($row['watch_new_registrations']) ? $this->lang->rt_discord_webhooks_enabled : $this->lang->rt_discord_webhooks_disabled;
			$row['webhook_embeds'] = !empty($row['webhook_embeds']) ? $this->lang->rt_discord_webhooks_enabled : $this->lang->rt_discord_webhooks_disabled;
			$row['character_limit'] = number_format((float)$row['character_limit']);
			$row['watch_usergroups'] = htmlspecialchars_uni($row['watch_usergroups']);
			$row['watch_forums'] = htmlspecialchars_uni($row['watch_forums']);
			$row['webhook_name'] = !empty($row['webhook_name']) ? '<br><i>' . htmlspecialchars_uni(
					$row['webhook_name']
				) . '</i>' : '';

			if (!empty($user)) {
				$row['bot_id'] = $row['bot_id'] . ' (' . format_name(
						$user['username'],
						$user['usergroup'],
						$user['displaygroup']
					) . ')';
			}

			$row['controls'] = "<a href='index.php?module=tools-{$this->prefix}&amp;action=edit_webhook&amp;id={$row['id']}'>{$this->lang->edit}</a>";

			$table->construct_cell($form->generate_check_box("webhook[{$row['id']}]", $row['id'], ''));
			$table->construct_cell($row['webhook_id'] . $row['webhook_name'], [
				'class' => 'align_left',
			]);
			$table->construct_cell($row['webhook_embeds'], [
				'class' => 'align_center',
			]);
			$table->construct_cell($row['bot_id'], [
				'class' => 'align_left',
			]);
			$table->construct_cell($row['watch_new_threads'], [
				'class' => 'align_center',
			]);
			$table->construct_cell($row['watch_new_posts'], [
				'class' => 'align_center',
			]);
			$table->construct_cell($row['watch_new_registrations'], [
				'class' => 'align_center',
			]);
			$table->construct_cell($row['watch_usergroups'], [
				'class' => 'align_center',
			]);
			$table->construct_cell($row['watch_forums'], [
				'class' => 'align_center',
			]);
			$table->construct_cell($row['character_limit'], [
				'class' => 'align_center',
			]);
			$table->construct_cell($row['controls'], [
				'class' => 'align_center',
			]);
			$table->construct_row();
		}

		if ($table->num_rows() === 0) {
			$table->construct_cell($this->lang->rt_discord_webhooks_notfound, ['colspan' => '11']);
			$table->construct_row();
		}

		$table->output($this->lang->{$this->prefix . '_webhooks_list'});

		$buttons[] = $form->generate_submit_button(
			$this->lang->delete_selected,
			array('onclick' => "return confirm('{$this->lang->rt_discord_webhooks_delete_selected}');")
		);
		$buttons[] = $form->generate_submit_button(
			$this->lang->delete_all,
			array(
				'name' => 'delete_all',
				'onclick' => "return confirm('{$this->lang->rt_discord_webhooks_delete_all}');"
			)
		);
		$form->output_submit_wrapper($buttons);
		$form->end();

		echo draw_admin_pagination(
			$webhooks_pagination['pagenum'],
			$webhooks_pagination['per_page'],
			$webhooks_pagination['total_rows'],
			"index.php?module=tools-{$this->prefix}&amp;action=webhooks"
		);
	}

}