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
    public static function formatMessage(string $text, bool $embeds_enabled = false): string
    {
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
            $conversions['/\[img\](.*?)\[\/img\]/is'] = '';
        }
        else
        {
            $conversions['/\[img\](.*?)\[\/img\]/is'] = '$1';
        }

        // Perform the conversions using regular expressions
        return preg_replace(array_keys($conversions), array_values($conversions), $text);
    }

    /**
     * Generate image link from [img] tags
     *
     * @param string $message
     * @return string
     */
    public static function getImageLink(string $message): string
    {
        preg_match('/\[img](.*?)\[\/img]/i', $message, $matches);

        $imageLink = '';
        if (isset($matches[1]))
        {
            $imageLink = $matches[1];
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
     * Get Webhook type explanation
     *
     * @param int $type
     * @return string
     */
    public static function getWebhookType(int $type): string
    {
        global $lang;

        $lang->load(Core::get_plugin_info('prefix'));

        $type_string = '';

        switch ($type)
        {
            case 1:
                $type_string = $lang->rt_discord_webhooks_webhooks_type_1_desc;
                break;
            case 2:
                $type_string = $lang->rt_discord_webhooks_webhooks_type_2_desc;
                break;
            case 3:
                $type_string = $lang->rt_discord_webhooks_webhooks_type_3_desc;
                break;
        }

        return $type_string;
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
        global $cache;

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

            if (!empty($row['watch_forums']))
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
                $row['user'] = get_user($row['bot_id']);
            }

            $data[] = $row;
        }

        return $data;
    }
}