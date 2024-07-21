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

use DateTimeImmutable;
use Exception;
use rt\DiscordWebhooks\Core;

class DiscordHelper
{

    /**
     * Format BBCode to Discord Markdown
     *
     * @param string $text
     * @param bool $embeds_enabled
     * @return string
     */
    public static function formatMessage(string $text, bool $embeds_enabled = false): string
    {
        $text = strip_tags($text);

        $conversions = [
            '/\[b\](.*?)\[\/b\]/is' => "**$1**",
            '/\[i\](.*?)\[\/i\]/is' => "*$1*",
            '/\[u\](.*?)\[\/u\]/is' => "__$1__",
            '/\[s\](.*?)\[\/s\]/is' => "~~$1~~",
            '/\[url=(.*?)\](.*?)\[\/url\]/is' => "[$2]($1)",
            '/\[code\](.*?)\[\/code\]/is' => "```$1```",
            '/\[php\](.*?)\[\/php\]/is' => "```php\n$1```",
            // Conversion for unordered lists [list][*]...[*]...[/list]
            '/\[list\](.*?)\[\/list\]/is' => function($matches)
            {
                $items = preg_split('/\[\*]/', $matches[1], -1, PREG_SPLIT_NO_EMPTY);
                $markdown = '';
                foreach ($items as $item)
                {
                    $trimmedItem = trim($item);
                    if (!empty($trimmedItem))
                    {
                        $markdown .= "- " . $trimmedItem . "\n";
                    }
                }
                return trim($markdown);
            },
            // Conversion for ordered lists [list=1][*]...[*]...[/list]
            '/\[list=1\](.*?)\[\/list\]/is' => function($matches)
            {
                $items = preg_split('/\[\*]/', $matches[1], -1, PREG_SPLIT_NO_EMPTY);
                $markdown = '';
                $index = 1;
                foreach ($items as $item)
                {
                    $trimmedItem = trim($item);
                    if (!empty($trimmedItem))
                    {
                        $markdown .= $index . ". " . $trimmedItem . "\n";
                        $index++;
                    }
                }
                return trim($markdown);
            },
            // Additional rules can be added here
        ];

        if ($embeds_enabled === true)
        {
            // Remove img tags from embeds
            $conversions['/\[img\](.*?)\[\/img\]/is'] = '';
            // Remove Role ID, User ID, Channel ID, @here, and @everyone from embeds
            $conversions['/(<@&\d+>|<@\d+>|<#\d+>|@here|@everyone)/si'] = '';
        }
        else
        {
            $conversions['/\[img\](.*?)\[\/img\]/is'] = '$1';
        }

        // Remove other BBCodes which are not added for conversion
        $conversions['/\[(.*?)=(.*?)\](.*?)\[\/(.*?)\]/is'] = '$3';

        // Perform the conversions using regular expressions
        foreach ($conversions as $pattern => $replacement)
        {
            if (is_callable($replacement))
            {
                $text = preg_replace_callback($pattern, $replacement, $text);
            }
            else
            {
                $text = preg_replace($pattern, $replacement, $text);
            }
        }

        return $text;
    }

    /**
     * Get mentions list
     *
     * Add a nice list of Role ID, User ID, Channel ID, @here, and @everyone when enabled
     *
     * @param string $message
     * @return string
     */
    public static function getMentions(string $message): string
    {
        $pattern = '/(<@&\d+>|<@\d+>|<#\d+>|@here|@everyone)/si';
        preg_match_all($pattern, $message, $matches);

        $mentions = $matches[0] ?? [];

        return implode(', ', $mentions);
    }

    /**
     * Formatting allowed mentions
     *
     * @return array[]
     */
    public static function formatAllowedMentions(): array
    {
        return [
            'parse' => ['everyone', 'users', 'roles']
        ];
    }

    /**
     * Generate image link from [img] or <img> tags
     *
     * @param string $message
     * @param bool $allow_html
     * @return string
     */
    public static function getImageLink(string $message, bool $allow_html = false, int $pid = 0): string
    {
        global $plugins;

        $imageLink = '';

        $hook_arguments = [
            'message' => &$message,
            'allow_html' => &$allow_html,
            'pid' => &$pid,
            'imageLink' => &$imageLink
        ];

        preg_match('/\[img](.*)\[\/img]/i', $message, $bbcode);
        $bbcode_imagelink = $bbcode[1] ?? '';

        if ($allow_html === true)
        {
            preg_match('/src\s*=\s*(?:\"|\')(.*)(?:\"|\')/i', $message, $html);
            $html_imagelink = $html[1] ?? '';
        }

        if (!empty($bbcode_imagelink))
        {
            $imageLink = $bbcode_imagelink;
        }
        elseif ($allow_html === true && !empty($html_imagelink))
        {
            $imageLink = $html_imagelink;
        }

        // Hook into RT Discord Webhooks end
        $plugins->run_hooks('rt_discord_webhooks_get_image_link_end', $hook_arguments);

        return $imageLink;
    }

    /**
     * Color Hex
     *
     * @param string $color
     * @return int
     */
    public static function colorHex(string $color): int
    {
        try
        {
            return hexdec(ltrim($color, '#'));
        }
        catch (Exception $e)
        {
            return 0;
        }
    }
    /**
     * Check if hexColor is valid
     *
     * @param string $color
     * @return bool
     */
    public static function isValidHexColor(string $color): bool
    {
        // Remove the '#' symbol if present
        $color = ltrim($color, '#');

        // Check if the remaining string is a valid hex color code
        return preg_match('/^([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color) === 1;
    }

    public static function truncateMessage(int $length, string $message): string
    {
        if (strlen($message) > $length)
        {
            $message = substr($message, 0, $length) . '...';
        }

        return $message;
    }

    /**
     * Get Cached Webhooks
     *
     * @return array
     */
    public static function getCachedWebhooks(): array
    {
        global $cache, $lang;

        $cached = $cache->read(Core::get_plugin_info('prefix') . '_cached_hooks');

        if (empty($cached))
        {
            return [];
        }

        $data = [];
        foreach ($cached as $row)
        {
            if (!empty($row['watch_usergroups']))
            {
                $row['watch_usergroups'] = explode(',', $row['watch_usergroups']);
            }

            if (isset($row['watch_forums']))
            {
                if ($row['watch_forums'] !== '-1')
                {
                    $row['watch_forums'] = explode(',', $row['watch_forums']);

                }
                else
                {
                    $row['watch_forums'] = [-1];
                }
            }

            if (!empty($row['bot_id']))
            {
                $user = get_user($row['bot_id']);

                if (!empty($user))
                {
                    $row['user'] = [
                        'uid' => (int) $row['bot_id'],
                        'username' => $user['username'],
                        'avatar' => $user['avatar'],
                        'usergroup' => (int) $user['usergroup'],
                        'displaygroup' => (int) $user['displaygroup']
                    ];
                }
                else
                {
                    // In case user has been deleted, we add mockup data
                    $row['user'] = [
                        'uid' => 0,
                        'username' => $lang->na,
                        'avatar' => '',
                        'usergroup' => 0,
                        'displaygroup' => 0
                    ];
                }
            }

            $data[] = $row;
        }

        return $data;
    }

    public static function logDiscordApiRequest(string $discord_message_id, string $discord_channel_id, string $webhook_id, int $tid = 0, int $pid = 0): void
    {
        global $db;

        $data = [
            'discord_message_id' => $discord_message_id,
            'discord_channel_id' => $discord_channel_id,
            'webhook_id' => $webhook_id,
            'tid' => $tid,
            'pid' => $pid,
        ];

        $db->insert_query(Core::get_plugin_info('prefix') . '_logs', $data);
    }

    public static function deleteDiscordMessageApiLog(int $pid, int $tid = 0): void
    {
        global $db;

        if ($tid > 0)
        {
            $db->delete_query(Core::get_plugin_info('prefix'). '_logs', "tid = '{$db->escape_string($tid)}'");
        }
        else
        {
            $db->delete_query(Core::get_plugin_info('prefix'). '_logs', "pid = '{$db->escape_string($pid)}'");
        }
    }

    public static function getDiscordMessage(int $pid, string $field = 'discord_message_id'): int
    {
        global $db;

        $allowed = ['discord_message_id', 'webhook_id', 'discord_channel_id', 'tid', 'pid'];

        if (!in_array($field, $allowed))
        {
            return 0;
        }

        $query = $db->simple_select(Core::get_plugin_info('prefix') . '_logs', $field, "pid = '{$db->escape_string($pid)}'");

        return (int) $db->fetch_field($query, $field);
    }

    /**
     * Third-party plugin integration
     *
     * Hook start: rt_discord_webhooks_thirdparty_start
     * Hook end: rt_discord_webhooks_thirdparty_end
     *
     * @throws Exception
     */
    public static function thirdPartyIntegration(): void
    {
        global $mybb, $db, $cache, $plugins;

        $hook_data = [
            'webhook_url' => '', // Webhook url from the Discord channel (Set into your plugin a text field where user will be able to set Webhook URL for your plugin)
            'webhook_method' => 'POST', // Webhook method when sending request to API
            'bot' => [
                'username' => '', // Bot username
                'avatar_url' => '', // Bot image url
            ],
            'author' => [
                'name' => '', // Embeds author name
                'url' => '', // Embeds author profile url
                'icon_url' => '' // Embeds author avatar url
            ],
            'title' => '', // Embeds title of the content
            'url' => '', // Embeds url for the title
            'description' => '', // Embeds description
            'char_limit' => 2000, // Embeds max character to be parsed
            'embeds_enabled' => true, // Enable / Disable embeds
            'allow_mentions' => true, // Allow mentions in embeds (@here, @all)
            'color' => '', // HEX embeds color border (eg. #ff0000)
            'timestamp' => (new DateTimeImmutable('@' . TIME_NOW))->format('Y-m-d\TH:i:s\Z'), // Timestamp of the post
            'thumbnail' => [
                'url' => '', // Embeds thumbnail image url
            ],
            'footer' => [
                'text' => '', // Embeds footer text
                'icon_url' => '' // Embeds small footer image url
            ],
            'image' => [
                'url' => '', // Embeds image url
            ]
        ];

        $headers = [
            'Content-Type: application/json',
        ];

        // Hook into RT Discord Webhooks start
        $plugins->run_hooks('rt_discord_webhooks_thirdparty_start');

        $embeds = [
            [
                'author' => [
                    'name' => $hook_data['author']['name'],
                    'url' => $hook_data['author']['url'],
                    'icon_url' => $hook_data['author']['icon_url'],
                ],
                'title' => $hook_data['title'],
                'url' => $hook_data['url'],
                'description' => DiscordHelper::formatMessage(DiscordHelper::truncateMessage((int) $hook_data['char_limit'], $hook_data['description']), (bool) $hook_data['embeds_enabled']),
                'color' => DiscordHelper::colorHex((string) $hook_data['color']),
                'timestamp' => $hook_data['timestamp'],
                'thumbnail' => [
                    'url' => $hook_data['thumbnail']['url'],
                ],
                'footer' => [
                    'text' => $hook_data['footer']['text'],
                    'icon_url' => $hook_data['footer']['icon_url']
                ],
                'image' => [
                    'url' => DiscordHelper::getImageLink($hook_data['image']['url']),
                ]
            ],
        ];

        $data = [
            'username' => $hook_data['bot']['username'],
            'avatar_url' => $hook_data['bot']['avatar_url'],
            'tts' => false,
            'embeds' => $embeds,
        ];

        // Check if mentions are allowed
        if ((bool) $hook_data['allow_mentions'] === true)
        {
            $data['allowed_mentions'] = self::formatAllowedMentions();
            $data['content'] = DiscordHelper::getMentions($hook_data['description']);
        }
        else
        {
            $data['content'] = '';
        }

        // Hook into RT Discord Webhooks end
        $plugins->run_hooks('rt_discord_webhooks_thirdparty_end');

        if (!empty($hook_data['webhook_url']))
        {
            // Send Webhook request to the Discord
            \rt\DiscordWebhooks\fetch_api($hook_data['webhook_url'], $hook_data['webhook_method'], $data, $headers);
        }
    }
}