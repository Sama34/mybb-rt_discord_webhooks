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
     * Format text for Discord message
     *
     * @param string $text
     * @return string
     */
    public static function formatText(string $text): string
    {
        // Convert [b]bold[/b] to **bold**
        $text = preg_replace('/\[b\](.*?)\[\/b\]/is', '**$1**', $text);

        // Convert [i]italic[/i] to *italic*
        $text = preg_replace('/\[i\](.*?)\[\/i\]/is', '*$1*', $text);

        // Convert [u]underline[/u] to __underline__
        $text = preg_replace('/\[u\](.*?)\[\/u\]/is', '__$1__', $text);

        // Convert [url]http://example.com[/url] to [http://example.com](http://example.com)
        $text = preg_replace('/\[url\](.*?)\[\/url\]/is', '[$1]($1)', $text);

        // Convert [img]http://example.com/image.jpg[/img] to ![Image](http://example.com/image.jpg)
        $text = preg_replace('/\[img\](.*?)\[\/img\]/is', '![]($1)', $text);

        // Convert [quote]quoted text[/quote] to > quoted text
        $text = preg_replace('/\[quote\](.*?)\[\/quote\]/is', '> $1', $text);

        // Convert [code]code text[/code] to ```code text```
        $text = preg_replace('/\[code\](.*?)\[\/code\]/is', '```$1```', $text);

        return $text;
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
        $type_string = '';

        switch ($type)
        {
            case 1:
                $type_string = 'Incoming (Incoming Webhooks can post messages to channels with a generated token)';
                break;
            case 2:
                $type_string = 'Channel Follower (Channel Follower Webhooks are internal webhooks used with Channel Following to post new messages into channels)';
                break;
            case 3:
                $type_string = 'Application (Application webhooks are webhooks used with Interactions)';
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