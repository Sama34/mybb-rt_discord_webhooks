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
     * Hook: newthread_do_newthread_end
     *
     * @return void
     * @throws Exception
     */
    public function newthread_do_newthread_end(): void
    {
        global $mybb, $lang, $new_thread, $tid, $thread_info;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            $lang->load(Core::get_plugin_info('prefix'));

            foreach ($webhooks as $h)
            {
                // Permissions first
                if (
                    // Check if webhook is for new threads
                    (!isset($h['watch_new_threads']) || (int) $h['watch_new_threads'] !== 1) ||
                    // Check if webhook is watching the current forum
                    (!isset($h['watch_forums']) || !in_array((int) $new_thread['fid'], $h['watch_forums']) && !in_array(-1, $h['watch_forums'])) ||
                    // Check if the user is part of the allowed usergroups to post
                    (!isset($h['watch_usergroups']) || !in_array((int) $mybb->user['usergroup'], $h['watch_usergroups']))
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
                        'title' => $new_thread['subject'],
                        'url' => $mybb->settings['bburl'] . '/' . get_thread_link($tid),
                        'description' => DiscordHelper::formatMessage(DiscordHelper::truncateMessage((int) $h['character_limit'], $new_thread['message']), true),
                        'color' => DiscordHelper::colorHex($h['webhook_embeds_color']),
                        'timestamp' => (new DateTimeImmutable('@' . TIME_NOW))->format('Y-m-d\TH:i:s\Z'),
                        'thumbnail' => [
                            'url' => $h['webhook_embeds_thumbnail'],
                        ],
                        'footer' => [
                            'text' => $h['webhook_embeds_footer_text'],
                            'icon_url' => $h['webhook_embeds_footer_icon_url']
                        ],
                        'image' => [
                            'url' => DiscordHelper::getImageLink($new_thread['message']),
                        ]
                    ],
                ];

                $data = [
                    'username' => !empty($h['user']['username']) ?  $h['user']['username'] : $lang->na,
                    'avatar_url' => !empty($h['user']['avatar']) ? $h['user']['avatar'] : '',
                    'tts' => false,
                ];

                $thread_link = $mybb->settings['bburl'] . '/' . get_thread_link($tid);
                $user_link = $mybb->settings['bburl'] . '/' . get_profile_link($new_thread['uid']);
                $forum_link = $mybb->settings['bburl'] . '/' . get_forum_link($new_thread['fid']);
                $forum_name = isset(get_forum($new_thread['fid'])['name']) ? htmlspecialchars_uni(get_forum($new_thread['fid'])['name']) : $lang->na;

                $lang->rt_discord_webhooks_new_thread = $lang->sprintf($lang->rt_discord_webhooks_new_thread, $thread_link, $new_thread['subject'], $user_link, $new_thread['username'], $forum_link, $forum_name);

                // Check if we are using embeds
                if (!empty($h['webhook_embeds']))
                {
                    $data['embeds'] = $embeds;
                }
                else
                {
                    $data['content'] = DiscordHelper::formatMessage($lang->rt_discord_webhooks_new_thread);
                }

                // Send Webhook request to the Discord
                $api = \rt\DiscordWebhooks\fetch_api($h['webhook_url'] . '?wait=true', 'POST', $data, $headers);
                $api = json_decode($api, true);

                if (isset($api['id']))
                {
                    DiscordHelper::logDiscordApiRequest($api['id'], $api['channel_id'], $api['webhook_id'], $tid, $thread_info['pid']);
                }
            }
        }

    }

    /**
     * Hook: xmlhttp_update_post
     *
     * @return void
     * @throws Exception
     */
    public function xmlhttp_update_post(): void
    {
        global $mybb, $updatepost, $lang;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
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
                        (!isset($h['watch_usergroups']) || !in_array((int) $mybb->user['usergroup'], $h['watch_usergroups']))
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
                            'color' => DiscordHelper::colorHex($h['webhook_embeds_color']),
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
        global $mybb, $post, $lang;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
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
                        (!isset($h['watch_usergroups']) || !in_array((int) $mybb->user['usergroup'], $h['watch_usergroups']))
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
                            'color' => DiscordHelper::colorHex($h['webhook_embeds_color']),
                            'timestamp' => (new DateTimeImmutable('@' . TIME_NOW))->format('Y-m-d\TH:i:s\Z'),
                            'thumbnail' => [
                                'url' => $h['webhook_embeds_thumbnail'],
                            ],
                            'footer' => [
                                'text' => $h['webhook_embeds_footer_text'],
                                'icon_url' => $h['webhook_embeds_footer_icon_url']
                            ],
                            'image' => [
                                'url' => DiscordHelper::getImageLink($post['message']),
                            ]
                        ],
                    ];

                    $data = [
                        'username' => !empty($h['user']['username']) ?  $h['user']['username'] : $lang->na,
                        'avatar_url' => !empty($h['user']['avatar']) ? $h['user']['avatar'] : '',
                        'tts' => false,
                        'embeds' => $embeds,
                    ];

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
        global $mybb, $lang, $post, $tid, $pid, $thread_subject;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
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
                    (!isset($h['watch_usergroups']) || !in_array((int) $mybb->user['usergroup'], $h['watch_usergroups']))
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
                        'color' => DiscordHelper::colorHex($h['webhook_embeds_color']),
                        'timestamp' => (new DateTimeImmutable('@' . TIME_NOW))->format('Y-m-d\TH:i:s\Z'),
                        'thumbnail' => [
                            'url' => $h['webhook_embeds_thumbnail'],
                        ],
                        'footer' => [
                            'text' => $h['webhook_embeds_footer_text'],
                            'icon_url' => $h['webhook_embeds_footer_icon_url']
                        ],
                        'image' => [
                            'url' => DiscordHelper::getImageLink($post['message']),
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
                }
                else
                {
                    $data['content'] = DiscordHelper::formatMessage($lang->rt_discord_webhooks_new_post);
                }

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
        global $mybb, $lang;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            $lang->load(Core::get_plugin_info('prefix'));

            foreach ($webhooks as $h)
            {
                // Permissions first
                if (
                    // Check if webhook is for delete posts
                    (!isset($h['watch_delete_posts']) || (int) $h['watch_delete_posts'] !== 1) ||
                    // Check if the user is part of the allowed usergroups to use delete posts
                    (!isset($h['watch_usergroups']) || !in_array((int) $mybb->user['usergroup'], $h['watch_usergroups']))
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
        global $mybb, $lang;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
            $lang->load(Core::get_plugin_info('prefix'));

            foreach ($webhooks as $h)
            {
                // Permissions first
                if (
                    // Check if webhook is for delete threads
                    (!isset($h['watch_delete_threads']) || (int) $h['watch_delete_threads'] !== 1) ||
                    // Check if the user is part of the allowed usergroups to use delete threads
                    (!isset($h['watch_usergroups']) || !in_array((int) $mybb->user['usergroup'], $h['watch_usergroups']))
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
        global $mybb, $lang, $user_info;

        $webhooks = DiscordHelper::getCachedWebhooks();

        if (!empty($webhooks))
        {
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
                        'color' => DiscordHelper::colorHex($h['webhook_embeds_color']),
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

                // Send Webhook request to the Discord
                \rt\DiscordWebhooks\fetch_api($h['webhook_url'], 'POST', $data, $headers);
            }
        }
    }
}