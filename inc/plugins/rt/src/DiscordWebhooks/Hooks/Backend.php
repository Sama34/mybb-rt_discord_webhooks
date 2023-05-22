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
        global $db, $mybb, $lang, $cache, $run_module, $action_file, $page, $sub_tabs, $form, $form_container;

        if ($run_module === 'tools' && $action_file === Core::get_plugin_info('prefix'))
        {
            $webhooks = new AdminWebhooksConfig($db, $mybb, $cache, $lang);
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

            switch (true)
            {
                // Add a webhook
                case $mybb->get_input('action') === 'add_webhook':
                    $webhooks->getAddWebhook($sub_tabs, $page);
                    break;

                // Edit a webhook
                case $mybb->get_input('action') === 'edit_webhook':
                    $webhooks->getEditWebhook($sub_tabs, $page);
                    break;

                // Default page
                default:
                    $webhooks->getViewWebhook($sub_tabs, $page);
                    break;
            }

            $page->output_footer();

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