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
            // Add more conversion rules as needed
        ];

        if ($embeds_enabled === true)
        {
            // Remove img tags from embeds
            $conversions['/\[img\](.*?)\[\/img\]/is'] = '';
            // Remove @here/@everyone from embeds
            $conversions['/@(here|everyone)/is'] = '';
        }
        else
        {
            $conversions['/\[img\](.*?)\[\/img\]/is'] = '$1';
        }

        // Remove other BBCodes which are not added for conversion
        $conversions['/\[(.*?)=(.*?)\](.*?)\[\/(.*?)\]/is'] = '$3';

        // Perform the conversions using regular expressions
        return preg_replace(array_keys($conversions), array_values($conversions), $text);
    }

    /**
     * Get mentions list
     *
     * Add a nice list of @everyone and @here when enabled
     *
     * @param string $message
     * @return string
     */
    public static function getMentions(string $message): string
    {
        $pattern = '/@(here|everyone)/si';
        preg_match_all($pattern, $message, $matches);

        $mentions = $matches[0] ?? [];

        return implode(', ', $mentions);
    }

    /**
     * Generate image link from [img] or <img> tags
     *
     * @param string $message
     * @param bool $allow_html
     * @return string
     */
    public static function getImageLink(string $message, bool $allow_html = false): string
    {
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
        else
        {
            $imageLink = '';
        }

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
        catch (\Exception $e)
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
}