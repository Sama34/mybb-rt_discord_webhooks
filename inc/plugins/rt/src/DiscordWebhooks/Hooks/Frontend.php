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

use DateTimeImmutable;
use Exception;
use rt\DiscordWebhooks\Core;
use rt\DiscordWebhooks\Discord\DiscordHelper;

final class Frontend
{

    /**
     * Hook: global_start
     *
     * @return void
     * @throws Exception
     */
    public function global_start(): void
    {
        // Permissions first
        if (
           isset($mybb->settings['rt_webhooks_enabled'], $mybb->settings['rt_discord_webhooks_thirdparty']) &&
           // Check if webhook embeds are enabled
           (int) $mybb->settings['rt_webhooks_enabled'] === 1 &&
           // Check if webhook is for third party is enabled
           (int) $mybb->settings['rt_discord_webhooks_thirdparty'] === 1
        )
        {
            DiscordHelper::thirdPartyIntegration();
        }
    }

    /**
     * Hook: datahandler_post_insert_thread_end
     *
     * @return void
     * @throws Exception
     */

    function datahandler_post_insert_thread_end(\PostDataHandler $post_data_handler):\PostDataHandler
    {
        global $mybb, $lang, $plugins;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            $hook_arguments = [
                'post_data_handler' => &$post_data_handler
            ];

            $thread = &$this->data;

            $tid = (int)$post_data_handler->tid;

            $pid = (int)$post_data_handler->pid;

            $uid = (int)$thread['uid'];

            $user = get_user($uid);

            $username = $thread['username'];

            // If the poster is unregistered and hasn't set a username, call them Guest
            if(!$thread['uid'] && !$thread['username'])
            {
                $username = htmlspecialchars_uni($lang->guest);
            }

            // Hook into RT Discord Webhooks start
            $plugins->run_hooks('rt_discord_webhooks_datahandler_insert_thread_start', $hook_arguments);

            $lang->load(Core::get_plugin_info('prefix'));

            foreach ($webhooks as $h)
            {
                // Permissions first
                if (
                    // Check if webhook is for new threads
                    (!isset($h['watch_new_threads']) || (int) $h['watch_new_threads'] !== 1) ||
                    // Check if webhook is watching the current forum
                    (!isset($h['watch_forums']) || !in_array((int) $thread['fid'], $h['watch_forums']) && !in_array(-1, $h['watch_forums'])) ||
                    // Check if the user is part of the allowed usergroups to post
                    (!isset($h['watch_usergroups']) || !\rt\DiscordWebhooks\is_member($h['watch_usergroups'], $user))
                )
                {
                    continue;
                }

                $headers = [
                    'Content-Type: application/json',
                ];

                $embeds = [
                    [
                        'author' => [
                            'name' => $username,
                            'url' => $mybb->settings['bburl'] . '/' . get_profile_link($uid),
                            'icon_url' => !empty($user['avatar']) ? $user['avatar'] : $mybb->settings['bburl'] . '/images/default_avatar.png',
                        ],
                        'title' => $thread['subject'],
                        'url' => $mybb->settings['bburl'] . '/' . get_thread_link($tid),
                        'description' => DiscordHelper::formatMessage(DiscordHelper::truncateMessage((int) $h['character_limit'], $thread['message']), true),
                        'color' => DiscordHelper::colorHex((string) $h['webhook_embeds_color']),
                        'timestamp' => (new DateTimeImmutable('@' . TIME_NOW))->format('Y-m-d\TH:i:s\Z'),
                        'thumbnail' => [
                            'url' => $h['webhook_embeds_thumbnail'],
                        ],
                        'footer' => [
                            'text' => $h['webhook_embeds_footer_text'],
                            'icon_url' => $h['webhook_embeds_footer_icon_url']
                        ],
                        'image' => [
                            'url' => isset($forum['allowhtml']) && (int) $forum['allowhtml'] === 1 ? DiscordHelper::getImageLink($thread['message'], true) : DiscordHelper::getImageLink($thread['message']),
                        ]
                    ],
                ];

                $data = [
                    'username' => !empty($h['user']['username']) ?  $h['user']['username'] : $lang->na,
                    'avatar_url' => !empty($h['user']['avatar']) ? $h['user']['avatar'] : '',
                    'tts' => false,
                ];

                $thread_link = $mybb->settings['bburl'] . '/' . get_thread_link($tid);
                $user_link = $mybb->settings['bburl'] . '/' . get_profile_link($thread['uid']);
                $forum_link = $mybb->settings['bburl'] . '/' . get_forum_link($thread['fid']);
                $forum_name = isset(get_forum($thread['fid'])['name']) ? htmlspecialchars_uni(get_forum($thread['fid'])['name']) : $lang->na;

                $lang->rt_discord_webhooks_new_thread = $lang->sprintf($lang->rt_discord_webhooks_new_thread, $thread_link, $thread['subject'], $user_link, $username, $forum_link, $forum_name);

                // Check if we are using embeds
                if (!empty($h['webhook_embeds']))
                {
                    $data['embeds'] = $embeds;

                    // Check if mentions are allowed
                    if ((int) $h['allowed_mentions'] === 1)
                    {
                        $data['allowed_mentions'] = DiscordHelper::formatAllowedMentions();
                        $data['content'] = DiscordHelper::getMentions($thread['message']);
                    }
                    else
                    {
                        $data['content'] = '';
                    }
                }
                else
                {
                    $data['content'] = DiscordHelper::formatMessage($lang->rt_discord_webhooks_new_thread);
                }

                // Hook into RT Discord Webhooks end
                $plugins->run_hooks('rt_discord_webhooks_datahandler_insert_thread_end', $hook_arguments);

                // Send Webhook request to the Discord
                $api = \rt\DiscordWebhooks\fetch_api($h['webhook_url'] . '?wait=true', 'POST', $data, $headers);
                $api = json_decode($api, true);

                if (isset($api['id']))
                {
                    DiscordHelper::logDiscordApiRequest($api['id'], $api['channel_id'], $api['webhook_id'], $tid, $pid);
                }
            }
        }

        return $post_data_handler;
    }

    /**
     * Hook: xmlhttp_update_post
     *
     * @return void
     * @throws Exception
     */
    public function xmlhttp_update_post(): void
    {
        global $mybb, $updatepost, $lang, $plugins;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            // Hook into RT Discord Webhooks start
            $plugins->run_hooks('rt_discord_webhooks_xmlhttp_update_post_start');

            $lang->load(Core::get_plugin_info('prefix'));
            $thread = get_thread(DiscordHelper::getDiscordMessage((int)$updatepost['pid'], 'tid'));

            // Check if thread exists
            if (!empty($thread))
            {
                // Generate watch type
                $watch_type = 'watch_edit_posts';
                if ((int) $thread['firstpost'] === (int) $updatepost['pid'])
                {
                    $watch_type = 'watch_edit_threads';
                }

                foreach ($webhooks as $h)
                {
                    // Permissions first
                    if (
                        // Check if webhook embeds are enabled
                        empty($h['webhook_embeds']) ||
                        // Check if webhook is for edit threads/posts
                        (!isset($h[$watch_type]) || (int) $h[$watch_type] !== 1) ||
                        // Check if webhook is watching the current forum
                        (!isset($h['watch_forums']) || !in_array((int) $thread['fid'], $h['watch_forums']) && !in_array(-1, $h['watch_forums'])) ||
                        // Check if the user is part of the allowed usergroups to edit threads/posts
                        (!isset($h['watch_usergroups']) || !\rt\DiscordWebhooks\is_member($h['watch_usergroups'], $mybb->user))
                    )
                    {
                        continue;
                    }

                    $headers = [
                        'Content-Type: application/json',
                    ];

                    $embeds = [
                        [
                            'author' => [
                                'name' => !empty($mybb->user['uid']) ?  $mybb->user['username'] : $lang->na,
                                'url' => $mybb->settings['bburl'] . '/' . get_profile_link($mybb->user['uid']),
                                'icon_url' => !empty($mybb->user['avatar']) ? $mybb->user['avatar'] : $mybb->settings['bburl'] . '/images/default_avatar.png',
                            ],
                            'title' => $watch_type === 'watch_edit_posts' ? $lang->rt_discord_webhooks_re . $thread['subject'] : $thread['subject'],
                            'url' => $watch_type === 'watch_edit_posts' ? $mybb->settings['bburl'] . '/' . get_post_link($updatepost['pid'], $thread['tid']) . "#pid{$updatepost['pid']}" : $mybb->settings['bburl'] . '/' . get_thread_link($thread['tid']),
                            'description' => DiscordHelper::formatMessage(DiscordHelper::truncateMessage((int) $h['character_limit'], $updatepost['message']), true),
                            'color' => DiscordHelper::colorHex((string) $h['webhook_embeds_color']),
                            'timestamp' => (new DateTimeImmutable('@' . TIME_NOW))->format('Y-m-d\TH:i:s\Z'),
                            'thumbnail' => [
                                'url' => $h['webhook_embeds_thumbnail'],
                            ],
                            'footer' => [
                                'text' => $h['webhook_embeds_footer_text'],
                                'icon_url' => $h['webhook_embeds_footer_icon_url']
                            ],
                            'image' => [
                                'url' => DiscordHelper::getImageLink($updatepost['message']),
                            ]
                        ],
                    ];

                    $data = [
                        'username' => !empty($h['user']['username']) ?  $h['user']['username'] : $lang->na,
                        'avatar_url' => !empty($h['user']['avatar']) ? $h['user']['avatar'] : '',
                        'tts' => false,
                        'embeds' => $embeds,
                    ];

                    // Check if mentions are allowed
                    if ((int) $h['allowed_mentions'] === 1)
                    {
                        $data['allowed_mentions'] = DiscordHelper::formatAllowedMentions();
                        $data['content'] = DiscordHelper::getMentions($updatepost['message']);
                    }
                    else
                    {
                        $data['content'] = '';
                    }

                    // Hook into RT Discord Webhooks end
                    $plugins->run_hooks('rt_discord_webhooks_xmlhttp_update_post_end');

                    // Send Webhook request to the Discord
                    \rt\DiscordWebhooks\fetch_api($h['webhook_url'] . '/messages/' . DiscordHelper::getDiscordMessage((int) $updatepost['pid']), 'PATCH', $data, $headers);
                }
            }
        }
    }

    /**
     * Hook: editpost_do_editpost_end
     *
     * @return void
     * @throws Exception
     */
    public function editpost_do_editpost_end(): void
    {
        global $mybb, $post, $lang, $forum, $plugins;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            // Hook into RT Discord Webhooks start
            $plugins->run_hooks('rt_discord_webhooks_editpost_do_editpost_start');

            $lang->load(Core::get_plugin_info('prefix'));
            $thread = get_thread(DiscordHelper::getDiscordMessage((int)$post['pid'], 'tid'));

            // Check if thread exists
            if (!empty($thread))
            {
                // Generate watch type
                $watch_type = 'watch_edit_posts';
                if ((int) $thread['firstpost'] === (int) $post['pid'])
                {
                    $watch_type = 'watch_edit_threads';
                }

                foreach ($webhooks as $h)
                {
                    // Permissions first
                    if (
                        // Check if webhook embeds are enabled
                        empty($h['webhook_embeds']) ||
                        // Check if webhook is for edit threads/posts
                        (!isset($h[$watch_type]) || (int) $h[$watch_type] !== 1) ||
                        // Check if webhook is watching the current forum
                        (!isset($h['watch_forums']) || !in_array((int) $thread['fid'], $h['watch_forums']) && !in_array(-1, $h['watch_forums'])) ||
                        // Check if the user is part of the allowed usergroups to edit threads/posts
                        (!isset($h['watch_usergroups']) || !\rt\DiscordWebhooks\is_member($h['watch_usergroups'], $mybb->user))
                    )
                    {
                        continue;
                    }

                    $headers = [
                        'Content-Type: application/json',
                    ];

                    $embeds = [
                        [
                            'author' => [
                                'name' => !empty($mybb->user['uid']) ?  $mybb->user['username'] : $lang->na,
                                'url' => $mybb->settings['bburl'] . '/' . get_profile_link($mybb->user['uid']),
                                'icon_url' => !empty($mybb->user['avatar']) ? $mybb->user['avatar'] : $mybb->settings['bburl'] . '/images/default_avatar.png',
                            ],
                            'title' => $post['subject'],
                            'url' => $watch_type === 'watch_edit_posts' ? $mybb->settings['bburl'] . '/' . get_post_link($post['pid'], $thread['tid']) . "#pid{$post['pid']}" : $mybb->settings['bburl'] . '/' . get_thread_link($thread['tid']),
                            'description' => DiscordHelper::formatMessage(DiscordHelper::truncateMessage((int) $h['character_limit'], $post['message']), true),
                            'color' => DiscordHelper::colorHex((string) $h['webhook_embeds_color']),
                            'timestamp' => (new DateTimeImmutable('@' . TIME_NOW))->format('Y-m-d\TH:i:s\Z'),
                            'thumbnail' => [
                                'url' => $h['webhook_embeds_thumbnail'],
                            ],
                            'footer' => [
                                'text' => $h['webhook_embeds_footer_text'],
                                'icon_url' => $h['webhook_embeds_footer_icon_url']
                            ],
                            'image' => [
                                'url' => isset($forum['allowhtml']) && (int) $forum['allowhtml'] === 1 ? DiscordHelper::getImageLink($post['message'], true) : DiscordHelper::getImageLink($post['message']),
                            ]
                        ],
                    ];

                    $data = [
                        'username' => !empty($h['user']['username']) ?  $h['user']['username'] : $lang->na,
                        'avatar_url' => !empty($h['user']['avatar']) ? $h['user']['avatar'] : '',
                        'tts' => false,
                        'embeds' => $embeds,
                    ];

                    // Check if mentions are allowed
                    if ((int) $h['allowed_mentions'] === 1)
                    {
                        $data['allowed_mentions'] = DiscordHelper::formatAllowedMentions();
                        $data['content'] = DiscordHelper::getMentions($post['message']);
                    }
                    else
                    {
                        $data['content'] = '';
                    }

                    // Hook into RT Discord Webhooks end
                    $plugins->run_hooks('rt_discord_webhooks_editpost_do_editpost_end');

                    // Send Webhook request to the Discord
                    \rt\DiscordWebhooks\fetch_api($h['webhook_url'] . '/messages/' . DiscordHelper::getDiscordMessage((int) $post['pid']), 'PATCH', $data, $headers);
                }
            }
        }
    }

    /**
     * Hook: newreply_do_newreply_end
     *
     * @return void
     * @throws Exception
     */
    public function newreply_do_newreply_end(): void
    {
        global $mybb, $lang, $post, $postinfo, $tid, $pid, $thread_subject, $forum, $plugins;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            // Hook into RT Discord Webhooks start
            $plugins->run_hooks('rt_discord_webhooks_newreply_do_newreply_start');

            $lang->load(Core::get_plugin_info('prefix'));

            foreach ($webhooks as $h)
            {
                // Permissions first
                if (
                    // Check if webhook is for new posts
                    (!isset($h['watch_new_posts']) || (int) $h['watch_new_posts'] !== 1) ||
                    // Check if webhook is watching the current forum
                    (!isset($h['watch_forums']) || !in_array((int) $post['fid'], $h['watch_forums']) && !in_array(-1, $h['watch_forums'])) ||
                    // Check if the user is part of the allowed usergroups to post reply
                    (!isset($h['watch_usergroups']) || !\rt\DiscordWebhooks\is_member($h['watch_usergroups'], $mybb->user))
                )
                {
                    continue;
                }

                $headers = [
                    'Content-Type: application/json',
                ];

                $embeds = [
                    [
                        'author' => [
                            'name' => !empty($mybb->user['uid']) ?  $mybb->user['username'] : $lang->na,
                            'url' => $mybb->settings['bburl'] . '/' . get_profile_link($mybb->user['uid']),
                            'icon_url' => !empty($mybb->user['avatar']) ? $mybb->user['avatar'] : $mybb->settings['bburl'] . '/images/default_avatar.png',
                        ],
                        'title' => $lang->rt_discord_webhooks_re . $thread_subject,
                        'url' => $mybb->settings['bburl'] . '/' . get_post_link($pid, $tid) . "#pid{$pid}",
                        'description' => DiscordHelper::formatMessage(DiscordHelper::truncateMessage((int) $h['character_limit'], $post['message']), true),
                        'color' => DiscordHelper::colorHex((string) $h['webhook_embeds_color']),
                        'timestamp' => (new DateTimeImmutable('@' . TIME_NOW))->format('Y-m-d\TH:i:s\Z'),
                        'thumbnail' => [
                            'url' => $h['webhook_embeds_thumbnail'],
                        ],
                        'footer' => [
                            'text' => $h['webhook_embeds_footer_text'],
                            'icon_url' => $h['webhook_embeds_footer_icon_url']
                        ],
                        'image' => [
                            'url' => isset($forum['allowhtml']) && (int) $forum['allowhtml'] === 1 ? DiscordHelper::getImageLink($post['message'], true) : DiscordHelper::getImageLink($post['message']),
                        ]
                    ],
                ];

                $data = [
                    'username' => !empty($h['user']['username']) ?  $h['user']['username'] : $lang->na,
                    'avatar_url' => !empty($h['user']['avatar']) ? $h['user']['avatar'] : '',
                    'tts' => false,
                ];

                $post_link = $mybb->settings['bburl'] . '/' . get_post_link($pid, $tid)."#pid{$pid}";
                $thread_link = $mybb->settings['bburl'] . '/' . get_thread_link($tid);
                $user_link = $mybb->settings['bburl'] . '/' . get_profile_link($post['uid']);
                $forum_link = $mybb->settings['bburl'] . '/' . get_forum_link($post['fid']);
                $forum_name = isset(get_forum($post['fid'])['name']) ? htmlspecialchars_uni(get_forum($post['fid'])['name']) : $lang->na;

                $lang->rt_discord_webhooks_new_post = $lang->sprintf($lang->rt_discord_webhooks_new_post, $post_link, $thread_link, $thread_subject, $user_link, $post['username'], $forum_link, $forum_name);

                // Check if we are using embeds
                if (!empty($h['webhook_embeds']))
                {
                    $data['embeds'] = $embeds;

                    // Check if mentions are allowed
                    if ((int) $h['allowed_mentions'] === 1)
                    {
                        $data['allowed_mentions'] = DiscordHelper::formatAllowedMentions();
                        $data['content'] = DiscordHelper::getMentions($post['message']);
                    }
                    else
                    {
                        $data['content'] = '';
                    }
                }
                else
                {
                    $data['content'] = DiscordHelper::formatMessage($lang->rt_discord_webhooks_new_post);
                }

                // Hook into RT Discord Webhooks end
                $plugins->run_hooks('rt_discord_webhooks_newreply_do_newreply_end');

                // Send Webhook request to the Discord
                $api = \rt\DiscordWebhooks\fetch_api($h['webhook_url'] . '?wait=true', 'POST', $data, $headers);
                $api = json_decode($api, true);

                if (isset($api['id']))
                {
                    DiscordHelper::logDiscordApiRequest($api['id'], $api['channel_id'], $api['webhook_id'], $tid, $pid);
                }
            }
        }
    }

    /**
     * Hook: class_moderation_soft_delete_posts
     *
     * @param $pids
     * @return void
     * @throws Exception
     */
    public function class_moderation_soft_delete_posts(&$pids): void
    {
        global $mybb, $lang, $plugins;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            // Hook into RT Discord Webhooks start
            $plugins->run_hooks('rt_discord_webhooks_class_moderation_soft_delete_posts_start');

            $lang->load(Core::get_plugin_info('prefix'));

            foreach ($webhooks as $h)
            {
                // Permissions first
                if (
                    // Check if webhook is for delete posts
                    (!isset($h['watch_delete_posts']) || (int) $h['watch_delete_posts'] !== 1) ||
                    // Check if the user is part of the allowed usergroups to use delete posts
                    (!isset($h['watch_usergroups']) || !\rt\DiscordWebhooks\is_member($h['watch_usergroups'], $mybb->user))
                )
                {
                    continue;
                }

                $headers = [
                    'Content-Type: application/json',
                ];

                // Delete all selected post ids
                foreach ($pids as $p)
                {
                    $post = get_post($p);

                    // Check if webhook is watching the current forum
                    if (!isset($h['watch_forums']) || !in_array((int) $post['fid'], $h['watch_forums']) && !in_array(-1, $h['watch_forums']))
                    {
                        continue 2;
                    }

                    // Hook into RT Discord Webhooks end
                    $plugins->run_hooks('rt_discord_webhooks_class_moderation_soft_delete_posts_end');

                    // Send Webhook request to the Discord
                    \rt\DiscordWebhooks\fetch_api($h['webhook_url'] . '/messages/' . DiscordHelper::getDiscordMessage((int) $p), 'DELETE', [], $headers);

                    // Delete logs
                    DiscordHelper::deleteDiscordMessageApiLog((int) $p);
                }
            }
        }
    }

    /**
     * Hook: class_moderation_soft_delete_threads
     *
     * @param $tids
     * @return void
     * @throws Exception
     */
    public function class_moderation_soft_delete_threads(&$tids): void
    {
        global $mybb, $lang, $plugins;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            // Hook into RT Discord Webhooks start
            $plugins->run_hooks('rt_discord_webhooks_class_moderation_soft_delete_threads_start');

            $lang->load(Core::get_plugin_info('prefix'));

            foreach ($webhooks as $h)
            {
                // Permissions first
                if (
                    // Check if webhook is for delete threads
                    (!isset($h['watch_delete_threads']) || (int) $h['watch_delete_threads'] !== 1) ||
                    // Check if the user is part of the allowed usergroups to use delete threads
                    (!isset($h['watch_usergroups']) || !\rt\DiscordWebhooks\is_member($h['watch_usergroups'], $mybb->user))
                )
                {
                    continue;
                }

                $headers = [
                    'Content-Type: application/json',
                ];

                // Delete all selected thread ids
                foreach ($tids as $t)
                {
                    $thread = get_thread($t);

                    // Check if webhook is watching the current forum
                    if (!isset($h['watch_forums']) || !in_array((int) $thread['fid'], $h['watch_forums']) && !in_array(-1, $h['watch_forums']))
                    {
                        continue 2;
                    }

                    // Hook into RT Discord Webhooks end
                    $plugins->run_hooks('rt_discord_webhooks_class_moderation_soft_delete_threads_end');

                    // Send Webhook request to the Discord
                    \rt\DiscordWebhooks\fetch_api($h['webhook_url'] . '/messages/' . DiscordHelper::getDiscordMessage((int) $thread['firstpost']), 'DELETE', [], $headers);

                    // Delete logs
                    DiscordHelper::deleteDiscordMessageApiLog(0, (int) $t);
                }
            }
        }
    }

    /**
     * Hook: member_do_register_end
     *
     * @return void
     * @throws Exception
     */
    public function member_do_register_end(): void
    {
        global $mybb, $lang, $user_info, $plugins;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            // Hook into RT Discord Webhooks start
            $plugins->run_hooks('rt_discord_webhooks_member_do_register_start');

            $lang->load(Core::get_plugin_info('prefix'));

            foreach ($webhooks as $h)
            {
                // Permissions first
                if (
                    // Check if webhook is for new posts
                (!isset($h['watch_new_registrations']) || (int) $h['watch_new_registrations'] !== 1)
                )
                {
                    continue;
                }

                $headers = [
                    'Content-Type: application/json',
                ];

                $embeds = [
                    [
                        'title' => $lang->sprintf($lang->rt_discord_webhooks_new_registrations_title, !empty($user_info['username']) ? $user_info['username'] : $lang->na),
                        'url' => $mybb->settings['bburl'] . '/' . get_profile_link($user_info['uid']),
                        'description' => DiscordHelper::formatMessage($lang->sprintf($lang->rt_discord_webhooks_new_registrations_desc, $user_info['username']), true),
                        'color' => DiscordHelper::colorHex((string) $h['webhook_embeds_color']),
                        'timestamp' => (new DateTimeImmutable('@' . TIME_NOW))->format('Y-m-d\TH:i:s\Z'),
                        'thumbnail' => [
                            'url' => $h['webhook_embeds_thumbnail'],
                        ],
                        'footer' => [
                            'text' => $h['webhook_embeds_footer_text'],
                            'icon_url' => $h['webhook_embeds_footer_icon_url']
                        ],
                    ],
                ];

                $data = [
                    'username' => !empty($h['user']['username']) ?  $h['user']['username'] : $lang->na,
                    'avatar_url' => !empty($h['user']['avatar']) ? $h['user']['avatar'] : '',
                    'tts' => false,
                ];

                $user_link = $mybb->settings['bburl'] . '/' . get_profile_link($user_info['uid']);

                $lang->rt_discord_webhooks_new_registrations = $lang->sprintf($lang->rt_discord_webhooks_new_registrations, $user_link, $user_info['username']);

                // Check if we are using embeds
                if (!empty($h['webhook_embeds']))
                {
                    $data['embeds'] = $embeds;
                }
                else
                {
                    $data['content'] = DiscordHelper::formatMessage($lang->rt_discord_webhooks_new_registrations);
                }

                // Hook into RT Discord Webhooks end
                $plugins->run_hooks('rt_discord_webhooks_member_do_register_end');

                // Send Webhook request to the Discord
                \rt\DiscordWebhooks\fetch_api($h['webhook_url'], 'POST', $data, $headers);
            }
        }
    }
}