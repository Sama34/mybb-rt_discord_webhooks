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

use rt\DiscordWebhooks\Discord\DiscordHelper;

final class Frontend
{

    /**
     * Hook: newthread_do_newthread_end
     *
     * @return void
     */
    public function newthread_do_newthread_end(): void
    {
        global $mybb, $lang, $new_thread, $tid;
    }

    /**
     * Hook: newreply_do_newreply_end
     *
     * @return void
     */
    public function newreply_do_newreply_end(): void
    {
        global $mybb, $lang, $post, $tid, $pid, $thread_subject;
    }

    /**
     * Hook: member_do_register_end
     *
     * @return void
     */
    public function member_do_register_end(): void
    {
        global $mybb, $lang, $user_info;
    }
}