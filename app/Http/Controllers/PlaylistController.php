<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlaylistController extends Controller
{
    /**
     * Serve a personalised M3U playlist for an IPTV player.
     *
     * Players authenticate with the credentials shown on the dashboard:
     *   /playlist.m3u?username=USER&password=PASS
     */
    public function m3u(Request $request): Response
    {
        $user = User::where('iptv_username', $request->query('username'))
            ->where('iptv_password', $request->query('password'))
            ->first();

        if (! $user) {
            return response("#EXTM3U\n# Invalid credentials.\n", 401)
                ->header('Content-Type', 'text/plain');
        }

        if (! $user->activeSubscription()) {
            return response("#EXTM3U\n# No active subscription.\n", 403)
                ->header('Content-Type', 'text/plain');
        }

        $channels = Channel::active()->with('category')->orderBy('name')->get();

        $lines = ['#EXTM3U'];
        foreach ($channels as $channel) {
            $group = $channel->category?->name ?? 'General';
            $logo = $channel->logo_url ?? '';
            $lines[] = sprintf(
                '#EXTINF:-1 tvg-name="%s" tvg-logo="%s" group-title="%s",%s',
                $channel->name,
                $logo,
                $group,
                $channel->name
            );
            $lines[] = $channel->stream_url ?: 'http://example.com/stream/placeholder.m3u8';
        }

        return response(implode("\n", $lines) . "\n", 200)
            ->header('Content-Type', 'application/x-mpegurl')
            ->header('Content-Disposition', 'attachment; filename="streamwave.m3u"');
    }
}
