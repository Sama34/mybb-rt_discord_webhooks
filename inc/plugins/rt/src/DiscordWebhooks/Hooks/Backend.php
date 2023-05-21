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

namespace rt\DiscordWebhooks\Hooks;

use Exception;
use Form;
use FormContainer;
use MyBB;
use rt\DiscordWebhooks\Core;
use rt\DiscordWebhooks\Discord\AdminWebhooksConfig;
use rt\DiscordWebhooks\Discord\DiscordHelper;
use Table;

final class Backend
{
    /**
     * Hook: admin_tools_action_handler
     *
     * @return void
     */
    public function admin_load(): void
    {
        global $db, $mybb, $lang, $run_module, $action_file, $page, $sub_tabs;

        if ($run_module === 'tools' && $action_file === Core::get_plugin_info('prefix'))
        {
            $table = new Table();
            $webhooks = new AdminWebhooksConfig();
            $prefix = Core::get_plugin_info('prefix');
            $lang->load(Core::get_plugin_info('prefix'));

            $page->add_breadcrumb_item($lang->{$prefix . '_menu'}, "index.php?module=tools-{$prefix}");

            $page_url = "index.php?module={$run_module}-{$action_file}";

            $sub_tabs = [];

            $allowed_actions =
            $tabs = [
                'webhooks',
                'add_webhook',
                'edit_webhook'
            ];

            foreach ($tabs as $row)
            {
                $sub_tabs[$row] = [
                    'link' => $page_url . '&amp;action=' . $row,
                    'title' => $lang->{$prefix . '_tab_' . $row},
                    'description' => $lang->{$prefix . '_tab_' . $row . '_desc'},
                ];
            }

            if (empty($mybb->get_input('action')) || $mybb->get_input('action') === 'webhooks')
            {
                $page->output_header($lang->{$prefix . '_menu'} . ' - ' . $lang->{$prefix .'_tab_' . 'webhooks'});
                $page->output_nav_tabs($sub_tabs, 'webhooks');
                $webhooks_db = $webhooks->getWebhookRowsArray()['query'] ?? [];
                $webhooks_pagination = $webhooks->getWebhookRowsArray()['pagination'] ?? [];

                if ($mybb->request_method === 'post')
                {
                    // Delete handler
                    if (!empty($mybb->get_input('delete_all')))
                    {
                        $db->delete_query(Core::get_plugin_info('prefix'));
                        $num_deleted = $db->affected_rows();

                        // Log admin action
                        log_admin_action($num_deleted);

                        // Rebuild Webhooks cache
                        $webhooks->rebuildWebhooks();

                        flash_message($lang->rt_discord_webhooks_delete_all_deleted, 'success');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=webhooks");
                    }
                    if (!empty($mybb->get_input('webhook', MyBB::INPUT_ARRAY)))
                    {
                        $webhooks_id = implode(",", array_map("intval", $mybb->get_input('webhook', MyBB::INPUT_ARRAY)));

                        if($webhooks_id)
                        {
                            $db->delete_query(Core::get_plugin_info('prefix'), "id IN ({$webhooks_id})");
                            $num_deleted = $db->affected_rows();

                            // Log admin action
                            log_admin_action($num_deleted);

                            // Rebuild Webhooks cache
                            $webhooks->rebuildWebhooks();
                        }
                        flash_message($lang->rt_discord_webhooks_delete_selected_deleted, 'success');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=webhooks");
                    }
                }

                $form = new Form("index.php?module=tools-{$prefix}&amp;action=webhooks", "post", "webhooks");
                $table->construct_header($form->generate_check_box("allbox", 1, '', array('class' => 'checkall')));
                $table->construct_header($lang->{$prefix . '_webhooks_id'});
                $table->construct_header($lang->{$prefix . '_webhooks_type'});
                $table->construct_header($lang->{$prefix . '_webhook_embeds'}, [
                    'class' => 'align_center'
                ]);
                $table->construct_header($lang->{$prefix . '_webhooks_bot_id'});
                $table->construct_header($lang->{$prefix . '_webhooks_watch_new_threads'});
                $table->construct_header($lang->{$prefix . '_webhooks_watch_new_posts'});
                $table->construct_header($lang->{$prefix . '_webhooks_watch_new_registrations'});
                $table->construct_header($lang->{$prefix . '_webhooks_watch_usergroups'});
                $table->construct_header($lang->{$prefix . '_webhooks_watch_forums'});
                $table->construct_header($lang->{$prefix . '_webhooks_char_limit'});

                $table->construct_header($lang->{$prefix . '_webhooks_controls'});

                foreach ($webhooks_db as $row)
                {
                    $row['webhook_id'] = "<a href='index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$row['id']}'>" . (preg_match('/\/(\d+)\//', $row['webhook_url'], $webhook_id) ? isset($webhook_id[1]) ? (int) $webhook_id[1] : (int) $row['id'] : (int) $row['id']) . "</a>";
                    $row['webhook_type'] = DiscordHelper::getWebhookType((int) $row['webhook_type']);
                    $user = get_user($row['bot_id']);
                    $row['bot_id'] = (int) $row['bot_id'];
                    $row['watch_new_threads'] = !empty($row['watch_new_threads']) ? $lang->rt_discord_webhooks_enabled : $lang->rt_discord_webhooks_disabled;
                    $row['watch_new_posts'] = !empty($row['watch_new_posts']) ? $lang->rt_discord_webhooks_enabled : $lang->rt_discord_webhooks_disabled;
                    $row['watch_new_registrations'] = !empty($row['watch_new_registrations']) ? $lang->rt_discord_webhooks_enabled : $lang->rt_discord_webhooks_disabled;
                    $row['webhook_embeds'] = !empty($row['webhook_embeds']) ? $lang->rt_discord_webhooks_enabled : $lang->rt_discord_webhooks_disabled;
                    $row['character_limit'] = number_format((float) $row['character_limit']);
                    $row['watch_usergroups'] = htmlspecialchars_uni($row['watch_usergroups']);
                    $row['watch_forums'] = htmlspecialchars_uni($row['watch_forums']);

                    if (!empty($user))
                    {
                        $row['bot_id'] = $row['bot_id'] . ' (' . format_name($user['username'], $user['usergroup'], $user['displaygroup']) . ')';
                    }

                    $row['controls'] = "<a href='index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$row['id']}'>{$lang->edit}</a>";

                    $table->construct_cell($form->generate_check_box("webhook[{$row['id']}]", $row['id'], ''));
                    $table->construct_cell($row['webhook_id'], [
                        'class' =>  'align_left',
                    ]);
                    $table->construct_cell(htmlspecialchars_uni($row['webhook_type']), [
                        'class' => 'align_left'
                    ]);
                    $table->construct_cell($row['webhook_embeds'], [
                        'class' =>  'align_center',
                    ]);
                    $table->construct_cell($row['bot_id'], [
                        'class' =>  'align_left',
                    ]);
                    $table->construct_cell($row['watch_new_threads'], [
                        'class' =>  'align_center',
                    ]);
                    $table->construct_cell($row['watch_new_posts'], [
                        'class' =>  'align_center',
                    ]);
                    $table->construct_cell($row['watch_new_registrations'], [
                        'class' =>  'align_center',
                    ]);
                    $table->construct_cell($row['watch_usergroups'], [
                        'class' =>  'align_center',
                    ]);
                    $table->construct_cell($row['watch_forums'], [
                        'class' =>  'align_center',
                    ]);
                    $table->construct_cell($row['character_limit'], [
                        'class' =>  'align_center',
                    ]);
                    $table->construct_cell($row['controls'], [
                        'class' =>  'align_center',
                    ]);
                    $table->construct_row();
                }

                if($table->num_rows() === 0)
                {
                    $table->construct_cell($lang->rt_discord_webhooks_notfound, ['colspan' => '12']);
                    $table->construct_row();
                }

                $table->output($lang->{$prefix . '_webhooks_list'});

                $buttons[] = $form->generate_submit_button($lang->delete_selected, array('onclick' => "return confirm('{$lang->rt_discord_webhooks_delete_selected}');"));
                $buttons[] = $form->generate_submit_button($lang->delete_all, array('name' => 'delete_all', 'onclick' => "return confirm('{$lang->rt_discord_webhooks_delete_all}');"));
                $form->output_submit_wrapper($buttons);
                $form->end();

                echo draw_admin_pagination($webhooks_pagination['pagenum'], $webhooks_pagination['per_page'], $webhooks_pagination['total_rows'], "index.php?module=tools-{$prefix}&amp;action=webhooks");

                $page->output_footer();
            }
            elseif ($mybb->get_input('action') === 'add_webhook')
            {
                $page->output_header($lang->{$prefix . '_menu'} . ' - ' . $lang->{$prefix .'_tab_' . 'add_webhook'});
                $page->output_nav_tabs($sub_tabs, 'add_webhook');

                if ($mybb->request_method === 'post')
                {
                    if ($webhooks->totalWebhookRows() > 100)
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_more_than_100, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=add_webhook");
                    }
                    // Validate the webhook URL
                    if (!preg_match('/^https:\/\/discord\.com\/api\/webhooks\/\d+\/[\w-]+$/i', $mybb->get_input('webhook_url')))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_url_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=add_webhook");
                    }
                    if ($webhooks->duplicateWebhookUrl($mybb->get_input('webhook_url')))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_url_duplicate, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=add_webhook");
                    }
                    if ($mybb->get_input('webhook_type', MyBB::INPUT_INT) > 3 || $mybb->get_input('webhook_type', MyBB::INPUT_INT) < 1)
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_type_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=add_webhook");
                    }
                    if (empty($mybb->get_input('bot_id', MyBB::INPUT_INT)))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_bot_id_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=add_webhook");
                    }
                    if (!get_user($mybb->get_input('bot_id', MyBB::INPUT_INT)))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_bot_id_not_found, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=add_webhook");
                    }
                    if (!empty($mybb->get_input('webhook_embeds_color')) && !DiscordHelper::isValidHexColor($mybb->get_input('webhook_embeds_color')))
                    {
                        flash_message($lang->rt_discord_webhooks_webhook_embeds_color_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=add_webhook");
                    }
                    if ($mybb->get_input('character_limit', MyBB::INPUT_INT) > 2000)
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_char_limit_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=add_webhook");
                    }

                    $watch_forums = 0;
                    if (!empty($mybb->get_input('watch_forums', MyBB::INPUT_ARRAY)))
                    {
                        $watch_forums =  implode(',', array_map('intval', $mybb->get_input('watch_forums', MyBB::INPUT_ARRAY)));
                    }

                    $watch_usergroups = 0;
                    if (!empty($mybb->get_input('watch_usergroups', MyBB::INPUT_ARRAY)))
                    {
                        $watch_usergroups =  implode(',', array_map('intval', $mybb->get_input('watch_usergroups', MyBB::INPUT_ARRAY)));
                    }

                    $insert_data = [
                        'webhook_url' => $db->escape_string($mybb->get_input('webhook_url')),
                        'webhook_type' => $mybb->get_input('webhook_type', MyBB::INPUT_INT),
                        'bot_id' => $mybb->get_input('bot_id', MyBB::INPUT_INT),
                        'webhook_embeds' => !empty($mybb->get_input('webhook_embeds', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_new_threads' => !empty($mybb->get_input('watch_new_threads', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_new_posts' => !empty($mybb->get_input('watch_new_posts', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_edit_threads' => !empty($mybb->get_input('watch_edit_threads', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_edit_posts' => !empty($mybb->get_input('watch_edit_posts', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_delete_threads' => !empty($mybb->get_input('watch_delete_threads', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_delete_posts' => !empty($mybb->get_input('watch_delete_posts', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_new_registrations' => !empty($mybb->get_input('watch_new_registrations', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_usergroups' => $db->escape_string($watch_usergroups),
                        'watch_forums' => $db->escape_string($watch_forums)
                    ];

                    if (!empty($mybb->get_input('webhook_embeds_color')))
                    {
                        $insert_data['webhook_embeds_color'] = $db->escape_string($mybb->get_input('webhook_embeds_color'));
                    }

                    if (!empty($mybb->get_input('webhook_embeds_thumbnail')))
                    {
                        $insert_data['webhook_embeds_thumbnail'] = $db->escape_string($mybb->get_input('webhook_embeds_thumbnail'));
                    }

                    if (!empty($mybb->get_input('webhook_embeds_footer_text')))
                    {
                        $insert_data['webhook_embeds_footer_text'] = $db->escape_string($mybb->get_input('webhook_embeds_footer_text'));
                    }

                    if (!empty($mybb->get_input('webhook_embeds_footer_icon_url')))
                    {
                        $insert_data['webhook_embeds_footer_icon_url'] = $db->escape_string($mybb->get_input('webhook_embeds_footer_icon_url'));
                    }

                    if (!empty($mybb->get_input('character_limit')))
                    {
                        $insert_data['character_limit'] = $db->escape_string($mybb->get_input('character_limit', MyBB::INPUT_INT));
                    }

                    $db->insert_query(Core::get_plugin_info('prefix'), $insert_data);

                    // Rebuild Webhooks cache
                    $webhooks->rebuildWebhooks();

                    flash_message($lang->rt_discord_webhooks_webhooks_added, 'success');
                    admin_redirect("index.php?module=tools-{$prefix}");
                }

                $form = new Form("index.php?module=tools-{$prefix}&amp;action=add_webhook", "post", "add_webhook");
                $form_container = new FormContainer($lang->rt_discord_webhooks_tab_add_webhook);
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_url." <em>*</em>", "", $form->generate_text_box('webhook_url', $mybb->get_input('webhook_url'), array('id' => 'webhook_url')), 'webhook_url');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_type." <em>*</em>", $lang->rt_discord_webhooks_webhooks_type_desc, $form->generate_select_box('webhook_type', [1 => $lang->rt_discord_webhooks_webhooks_type_1, 2 => $lang->rt_discord_webhooks_webhooks_type_2, 3 => $lang->rt_discord_webhooks_webhooks_type_3], $mybb->get_input('webhook_type', MyBB::INPUT_INT), array('id' => 'webhook_type')), 'webhook_type');
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds." <em>*</em>", $lang->rt_discord_webhooks_webhook_embeds_desc, $form->generate_on_off_radio('webhook_embeds', $mybb->get_input('webhook_embeds', MyBB::INPUT_INT), true, array('id' => 'webhook_embeds_on', 'class' => 'webhook_embeds'), array('id' => 'webhook_embeds_off', 'class' => 'webhook_embeds')), 'webhook_embeds');
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds_color, "", $form->generate_text_box('webhook_embeds_color', $mybb->get_input('webhook_embeds_color'), array('id' => 'webhook_embeds_color')), 'webhook_embeds_color', ['class' => 'webhook_embeds_color']);
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds_thumbnail, "", $form->generate_text_box('webhook_embeds_thumbnail', $mybb->get_input('webhook_embeds_thumbnail'), array('id' => 'webhook_embeds_thumbnail')), 'webhook_embeds_thumbnail', ['class' => 'webhook_embeds_thumbnail']);
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds_footer_text, "", $form->generate_text_box('webhook_embeds_footer_text', $mybb->get_input('webhook_embeds_footer_text'), array('id' => 'webhook_embeds_footer_text')), 'webhook_embeds_footer_text', ['class' => 'webhook_embeds_footer_text']);
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds_footer_icon_url, "", $form->generate_text_box('webhook_embeds_footer_icon_url', $mybb->get_input('webhook_embeds_footer_icon_url'), array('id' => 'webhook_embeds_footer_icon_url')), 'webhook_embeds_footer_icon_url', ['class' => 'webhook_embeds_footer_icon_url']);
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_char_limit." <em>*</em>", $lang->rt_discord_webhooks_webhooks_char_limit_desc, $form->generate_numeric_field('character_limit', $mybb->get_input('character_limit', MyBB::INPUT_INT), array('id' => 'character_limit', 'min' => 1, 'max' => 2000)), 'character_limit');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_bot_id." <em>*</em>", "", $form->generate_numeric_field('bot_id', $mybb->get_input('bot_id', MyBB::INPUT_INT), array('id' => 'bot_id')), 'bot_id');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_new_threads." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_new_threads_desc, $form->generate_on_off_radio('watch_new_threads', $mybb->get_input('watch_new_threads', MyBB::INPUT_INT), true, array('id' => 'watch_new_threads_on'), array('id' => 'watch_new_threads_off')), 'watch_new_threads');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_new_posts." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_new_posts_desc, $form->generate_on_off_radio('watch_new_posts', $mybb->get_input('watch_new_posts', MyBB::INPUT_INT), true, array('id' => 'watch_new_posts_on'), array('id' => 'watch_new_posts_off')), 'watch_new_posts');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_edit_threads." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_edit_threads_desc, $form->generate_on_off_radio('watch_edit_threads', $mybb->get_input('watch_edit_threads', MyBB::INPUT_INT), true, array('id' => 'watch_edit_threads_on'), array('id' => 'watch_edit_threads_off')), 'watch_edit_threads');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_edit_posts." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_edit_posts_desc, $form->generate_on_off_radio('watch_edit_posts', $mybb->get_input('watch_edit_posts', MyBB::INPUT_INT), true, array('id' => 'watch_edit_posts_on'), array('id' => 'watch_edit_posts_off')), 'watch_edit_posts_posts');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_delete_threads." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_delete_threads_desc, $form->generate_on_off_radio('watch_delete_threads', $mybb->get_input('watch_delete_threads', MyBB::INPUT_INT), true, array('id' => 'watch_delete_threads_on'), array('id' => 'watch_delete_threads_off')), 'watch_delete_threads');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_delete_posts." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_delete_posts_desc, $form->generate_on_off_radio('watch_delete_posts', $mybb->get_input('watch_delete_posts', MyBB::INPUT_INT), true, array('id' => 'watch_delete_posts_on'), array('id' => 'watch_delete_posts_off')), 'watch_delete_posts');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_new_registrations." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_new_registrations_desc, $form->generate_on_off_radio('watch_new_registrations', $mybb->get_input('watch_new_registrations', MyBB::INPUT_INT), true, array('id' => 'watch_new_registrations_on'), array('id' => 'watch_new_registrations_off')), 'watch_new_registrations');

                $selected_values = [];
                if (!empty($mybb->get_input('watch_usergroups', MyBB::INPUT_ARRAY)))
                {
                    foreach ($mybb->get_input('watch_usergroups', MyBB::INPUT_ARRAY) as $value)
                    {
                        $selected_values[] = (int) $value;
                    }
                }
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_usergroups." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_usergroups_desc, $form->generate_group_select('watch_usergroups[]', $selected_values, array('multiple' => true, 'size' => 5)), 'watch_usergroups');

                $selected_values = [];
                if (!empty($mybb->get_input('watch_forums', MyBB::INPUT_ARRAY)))
                {
                    foreach ($mybb->get_input('watch_forums', MyBB::INPUT_ARRAY) as $value)
                    {
                        $selected_values[] = (int) $value;
                    }
                }
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_forums." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_forums_desc, $form->generate_forum_select('watch_forums[]', $selected_values, array('multiple' => true, 'size' => 5, 'main_option' => $lang->all_forums)), 'watch_forums');
                $form_container->end();

                $buttons[] = $form->generate_submit_button($lang->rt_discord_webhooks_webhooks_submit);

                $form->output_submit_wrapper($buttons);
                $form->end();

                // Add Peekers
                echo <<<PEEKERS
                <script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
                <script type="text/javascript">
                    $(function()
                    {
                        new Peeker($(".webhook_embeds"), $(".webhook_embeds_footer_text, .webhook_embeds_footer_icon_url, .webhook_embeds_color, .webhook_embeds_thumbnail"), 1, true);
                    });
                </script>
                PEEKERS;

                $page->output_footer();
            }
            elseif ($mybb->get_input('action') === 'edit_webhook')
            {
                $page->output_header($lang->{$prefix . '_menu'} . ' - ' . $lang->{$prefix .'_tab_' . 'edit_webhook'});
                $page->output_nav_tabs($sub_tabs, 'edit_webhook');

                $row = $webhooks->getWebhookRowArray($mybb->get_input('id', MyBB::INPUT_INT));

                if ($mybb->request_method === 'post')
                {
                    if ($webhooks->totalWebhookRows() > 100)
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_more_than_100, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$mybb->get_input('id', MyBB::INPUT_INT)}");
                    }
                    // Validate the webhook URL
                    if (!preg_match('/^https:\/\/discord\.com\/api\/webhooks\/\d+\/[\w-]+$/i', $mybb->get_input('webhook_url')))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_url_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$mybb->get_input('id', MyBB::INPUT_INT)}");
                    }
                    if ($webhooks->duplicateWebhookUrl($mybb->get_input('webhook_url')))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_url_duplicate, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$mybb->get_input('id', MyBB::INPUT_INT)}");
                    }
                    if ($mybb->get_input('webhook_type', MyBB::INPUT_INT) > 3 || $mybb->get_input('webhook_type', MyBB::INPUT_INT) < 1)
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_type_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$mybb->get_input('id', MyBB::INPUT_INT)}");
                    }
                    if (empty($mybb->get_input('bot_id', MyBB::INPUT_INT)))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_bot_id_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$mybb->get_input('id', MyBB::INPUT_INT)}");
                    }
                    if (!get_user($mybb->get_input('bot_id', MyBB::INPUT_INT)))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_bot_id_not_found, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$mybb->get_input('id', MyBB::INPUT_INT)}");
                    }
                    if (!empty($mybb->get_input('webhook_embeds_color')) && !DiscordHelper::isValidHexColor($mybb->get_input('webhook_embeds_color')))
                    {
                        flash_message($lang->rt_discord_webhooks_webhook_embeds_color_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$mybb->get_input('id', MyBB::INPUT_INT)}");
                    }
                    if ($mybb->get_input('character_limit', MyBB::INPUT_INT) > 2000)
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_char_limit_invalid, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$mybb->get_input('id', MyBB::INPUT_INT)}");
                    }

                    $watch_forums = -1;
                    if (!empty($mybb->get_input('watch_forums', MyBB::INPUT_ARRAY)))
                    {
                        $watch_forums = implode(',', array_map('intval', $mybb->get_input('watch_forums', MyBB::INPUT_ARRAY)));
                    }

                    $watch_usergroups = 0;
                    if (!empty($mybb->get_input('watch_usergroups', MyBB::INPUT_ARRAY)))
                    {
                        $watch_usergroups = implode(',', array_map('intval', $mybb->get_input('watch_usergroups', MyBB::INPUT_ARRAY)));
                    }

                    $update_data = [
                        'webhook_url' => $db->escape_string($mybb->get_input('webhook_url')),
                        'webhook_type' => $mybb->get_input('webhook_type', MyBB::INPUT_INT),
                        'bot_id' => $mybb->get_input('bot_id', MyBB::INPUT_INT),
                        'webhook_embeds' => !empty($mybb->get_input('webhook_embeds', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_new_threads' => !empty($mybb->get_input('watch_new_threads', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_new_posts' => !empty($mybb->get_input('watch_new_posts', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_edit_threads' => !empty($mybb->get_input('watch_edit_threads', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_edit_posts' => !empty($mybb->get_input('watch_edit_posts', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_delete_threads' => !empty($mybb->get_input('watch_delete_threads', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_delete_posts' => !empty($mybb->get_input('watch_delete_posts', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_new_registrations' => !empty($mybb->get_input('watch_new_registrations', MyBB::INPUT_INT)) ? 1 : 0,
                        'watch_usergroups' => $db->escape_string($watch_usergroups),
                        'watch_forums' => $db->escape_string($watch_forums)
                    ];

                    if ($mybb->get_input('webhook_embeds_color') !== $row['webhook_embeds_color'])
                    {
                        $update_data['webhook_embeds_color'] = $db->escape_string($mybb->get_input('webhook_embeds_color'));
                    }

                    if ($mybb->get_input('webhook_embeds_thumbnail') !== $row['webhook_embeds_thumbnail'])
                    {
                        $update_data['webhook_embeds_thumbnail'] = $db->escape_string($mybb->get_input('webhook_embeds_thumbnail'));
                    }

                    if ($mybb->get_input('webhook_embeds_footer_text') !== $row['webhook_embeds_footer_text'])
                    {
                        $update_data['webhook_embeds_footer_text'] = $db->escape_string($mybb->get_input('webhook_embeds_footer_text'));
                    }

                    if ($mybb->get_input('webhook_embeds_footer_icon_url') !== $row['webhook_embeds_footer_icon_url'])
                    {
                        $update_data['webhook_embeds_footer_icon_url'] = $db->escape_string($mybb->get_input('webhook_embeds_footer_icon_url'));
                    }

                    if ($mybb->get_input('character_limit') !== $row['character_limit'])
                    {
                        $update_data['character_limit'] = $db->escape_string($mybb->get_input('character_limit', MyBB::INPUT_INT));
                    }

                    $db->update_query(Core::get_plugin_info('prefix'), $update_data, "id = '{$db->escape_string($mybb->get_input('id', MyBB::INPUT_INT))}'");

                    // Rebuild Webhooks cache
                    $webhooks->rebuildWebhooks();

                    flash_message($lang->rt_discord_webhooks_webhooks_edited, 'success');
                    admin_redirect("index.php?module=tools-{$prefix}&amp;action=webhooks");
                }
                else
                {
                    // Webhook checks
                    if (empty($mybb->get_input('id', MyBB::INPUT_INT)))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_edit_missing_id, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}");
                    }
                    if (!$webhooks->webhookExists($mybb->get_input('id', MyBB::INPUT_INT)))
                    {
                        flash_message($lang->rt_discord_webhooks_webhooks_edit_not_exist, 'error');
                        admin_redirect("index.php?module=tools-{$prefix}");
                    }
                }

                $form = new Form("index.php?module=tools-{$prefix}&amp;action=edit_webhook&amp;id={$mybb->get_input('id', MyBB::INPUT_INT)}", "post", "edit_webhook");
                $form_container = new FormContainer($lang->rt_discord_webhooks_tab_add_webhook);
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_url." <em>*</em>", "", $form->generate_text_box('webhook_url', $row['webhook_url'], array('id' => 'webhook_url')), 'webhook_url');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_type." <em>*</em>", $lang->rt_discord_webhooks_webhooks_type_desc, $form->generate_select_box('webhook_type', [1 => $lang->rt_discord_webhooks_webhooks_type_1, 2 => $lang->rt_discord_webhooks_webhooks_type_2, 3 => $lang->rt_discord_webhooks_webhooks_type_3], $row['webhook_type'], array('id' => 'webhook_type')), 'webhook_type');
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds." <em>*</em>", $lang->rt_discord_webhooks_webhook_embeds_desc, $form->generate_on_off_radio('webhook_embeds', $row['webhook_embeds'], true, array('id' => 'webhook_embeds_on', 'class' => 'webhook_embeds'), array('id' => 'webhook_embeds_off', 'class' => 'webhook_embeds')), 'webhook_embeds');
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds_color, "", $form->generate_text_box('webhook_embeds_color', $row['webhook_embeds_color'], array('id' => 'webhook_embeds_color')), 'webhook_embeds_color', ['class' => 'webhook_embeds_color']);
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds_thumbnail, "", $form->generate_text_box('webhook_embeds_thumbnail', $row['webhook_embeds_thumbnail'], array('id' => 'webhook_embeds_thumbnail')), 'webhook_embeds_thumbnail', ['class' => 'webhook_embeds_thumbnail']);
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds_footer_text, "", $form->generate_text_box('webhook_embeds_footer_text', $row['webhook_embeds_footer_text'], array('id' => 'webhook_embeds_footer_text')), 'webhook_embeds_footer_text', ['class' => 'webhook_embeds_footer_text']);
                $form_container->output_row($lang->rt_discord_webhooks_webhook_embeds_footer_icon_url, "", $form->generate_text_box('webhook_embeds_footer_icon_url', $row['webhook_embeds_footer_icon_url'], array('id' => 'webhook_embeds_footer_icon_url')), 'webhook_embeds_footer_icon_url', ['class' => 'webhook_embeds_footer_icon_url']);
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_char_limit." <em>*</em>", $lang->rt_discord_webhooks_webhooks_char_limit_desc, $form->generate_numeric_field('character_limit', $row['character_limit'], array('id' => 'character_limit', 'min' => 1, 'max' => 2000)), 'character_limit');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_bot_id." <em>*</em>", "", $form->generate_numeric_field('bot_id', $row['bot_id'], array('id' => 'bot_id')), 'bot_id');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_new_threads." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_new_threads_desc, $form->generate_on_off_radio('watch_new_threads', $row['watch_new_threads'], true, array('id' => 'watch_new_threads_on'), array('id' => 'watch_new_threads_off')), 'watch_new_threads');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_new_posts." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_new_posts_desc, $form->generate_on_off_radio('watch_new_posts', $row['watch_new_posts'], true, array('id' => 'watch_new_posts_on'), array('id' => 'watch_new_posts_off')), 'watch_new_posts');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_edit_threads." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_edit_threads_desc, $form->generate_on_off_radio('watch_edit_threads', $row['watch_edit_threads'], true, array('id' => 'watch_edit_threads_on'), array('id' => 'watch_edit_threads_off')), 'watch_edit_threads');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_edit_posts." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_edit_posts_desc, $form->generate_on_off_radio('watch_edit_posts', $row['watch_edit_posts'], true, array('id' => 'watch_edit_posts_on'), array('id' => 'watch_edit_posts_off')), 'watch_edit_posts_posts');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_delete_threads." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_delete_threads_desc, $form->generate_on_off_radio('watch_delete_threads', $row['watch_delete_threads'], true, array('id' => 'watch_delete_threads_on'), array('id' => 'watch_delete_threads_off')), 'watch_delete_threads');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_delete_posts." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_delete_posts_desc, $form->generate_on_off_radio('watch_delete_posts', $row['watch_delete_posts'], true, array('id' => 'watch_delete_posts_on'), array('id' => 'watch_delete_posts_off')), 'watch_delete_posts');
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_new_registrations." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_new_registrations_desc, $form->generate_on_off_radio('watch_new_registrations', $row['watch_new_registrations'], true, array('id' => 'watch_new_registrations_on'), array('id' => 'watch_new_registrations_off')), 'watch_new_registrations');

                $selected_values = [];
                if (!empty($row['watch_usergroups']))
                {
                    $row['watch_usergroups'] = explode(',', $row['watch_usergroups']);
                    foreach ($row['watch_usergroups'] as $value)
                    {
                        $selected_values[] = (int) $value;
                    }
                }
                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_usergroups." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_usergroups_desc, $form->generate_group_select('watch_usergroups[]', $selected_values, array('multiple' => true, 'size' => 5)), 'watch_usergroups');

                $selected_values = [];
                if (!empty($row['watch_forums']) && $row['watch_forums'] !== '-1')
                {
                    $row['watch_forums'] = explode(',', $row['watch_forums']);
                    foreach ($row['watch_forums'] as $value)
                    {
                        $selected_values[] = (int) $value;
                    }
                }

                $form_container->output_row($lang->rt_discord_webhooks_webhooks_watch_forums." <em>*</em>", $lang->rt_discord_webhooks_webhooks_watch_forums_desc, $form->generate_forum_select('watch_forums[]', $selected_values, array('multiple' => true, 'size' => 5, 'main_option' => $lang->all_forums)), 'watch_forums');
                $form_container->end();
                $buttons[] = $form->generate_submit_button($lang->rt_discord_webhooks_webhooks_submit);

                $form->output_submit_wrapper($buttons);
                $form->end();

                // Add Peekers
                echo <<<PEEKERS
                <script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
                <script type="text/javascript">
                    $(function()
                    {
                        new Peeker($(".webhook_embeds"), $(".webhook_embeds_footer_text, .webhook_embeds_footer_icon_url, .webhook_embeds_color, .webhook_embeds_thumbnail"), 1, true);
                    });
                </script>
                PEEKERS;

                $page->output_footer();
            }

            try
            {
                if (!in_array($mybb->get_input('action'), $allowed_actions))
                {
                    throw new Exception('Not allowed!');
                }
            }
            catch (Exception $e)
            {
                flash_message($e->getMessage(), 'error');
                admin_redirect("index.php?module=tools-{$prefix}");
            }

        }
    }

    /**
     * Hook: admin_tools_action_handler
     *
     * @param array $actions
     * @return void
     */
    public function admin_tools_action_handler(array &$actions): void
    {
        $prefix = Core::get_plugin_info('prefix');

        $actions[$prefix] = [
            'active' => $prefix,
            'file' => $prefix,
        ];
    }

    /**
     * Hook: admin_tools_menu
     *
     * @param array $sub_menu
     * @return void
     */
    public function admin_tools_menu(array &$sub_menu): void
    {
        global $lang;

        $prefix = Core::get_plugin_info('prefix');

        $lang->load($prefix);

        $sub_menu[] = [
            'id' => $prefix,
            'title' => $lang->rt_discord_webhooks_menu_name,
            'link' => 'index.php?module=tools-' . $prefix,
        ];
    }

}