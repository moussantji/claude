<?php

namespace App\Console\Commands;

use App\Services\PlayerTransferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verify all transfer host connections configured in .env.
 *
 * For each platform it checks:
 *   1. Whether credentials are present
 *   2. Whether the API/account authenticates (a lightweight live call)
 *
 *   php artisan episodes:check-connections
 *   php artisan episodes:check-connections --host=doodstream
 */
class EpisodesCheckConnections extends Command
{
    protected $signature = 'episodes:check-connections
        {--host= : Only check this platform}';

    protected $description = 'Verify all episode-transfer host connections/credentials configured in .env.';

    public function handle(PlayerTransferService $service): int
    {
        $only = strtolower((string) $this->option('host'));

        $this->info('═══ Checking transfer host connections ═══');
        $this->newLine();

        $platforms = $only !== '' ? [$only] : $service->availablePlatforms();
        $rows = [];
        $anyOk = false;

        foreach ($platforms as $platform) {
            [$state, $detail] = $this->checkPlatform($platform);

            $icon = match ($state) {
                'ok' => '<fg=green>✓ OK</>',
                'no-creds' => '<fg=gray>— not configured</>',
                'auth-fail' => '<fg=red>✗ auth failed</>',
                'unreachable' => '<fg=yellow>⚠ unreachable</>',
                default => '<fg=yellow>? unknown</>',
            };

            if ($state === 'ok') {
                $anyOk = true;
            }

            $rows[] = [$platform, $icon, $detail];
        }

        $this->table(['Platform', 'Status', 'Detail'], $rows);
        $this->newLine();

        if (! $anyOk) {
            $this->warn('No host is fully connected yet. Fill in TRANSFER_*_* values in your .env.');
            $this->line('Then run: php artisan config:clear && php artisan episodes:check-connections');

            return self::FAILURE;
        }

        $this->info('At least one host is ready. You can run transfers on the OK hosts.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}  [state, detail]
     */
    private function checkPlatform(string $platform): array
    {
        try {
            return match ($platform) {
                'vidmoly' => $this->checkVidmoly(),
                'doodstream' => $this->checkDoodstream(),
                'streamtape' => $this->checkStreamtape(),
                'mp4upload' => $this->checkMp4upload(),
                'dailymotion' => $this->checkDailymotion(),
                'sendvid' => $this->checkGenericKey('TRANSFER_SENDVID_API_KEY', 'API key set (upload API unverified)'),
                'sibnet' => $this->checkGenericLogin('TRANSFER_SIBNET_LOGIN', 'TRANSFER_SIBNET_PASSWORD', 'credentials set (form upload, unverified)'),
                'myvi' => $this->checkGenericLogin('TRANSFER_MYVI_LOGIN', 'TRANSFER_MYVI_PASSWORD', 'credentials set (endpoints unverified)'),
                default => ['unknown', 'no checker'],
            };
        } catch (Throwable $e) {
            return ['unreachable', substr($e->getMessage(), 0, 60)];
        }
    }

    private function checkVidmoly(): array
    {
        $key = env('TRANSFER_VIDMOLY_API_KEY', '');
        if ($key === '') {
            return ['no-creds', 'TRANSFER_VIDMOLY_API_KEY empty'];
        }
        // API host is api.vidmoly.to (api.vidmoly.me does not exist / won't resolve).
        // Try the configured base first, then fall back to api.vidmoly.to.
        $configured = rtrim(env('TRANSFER_VIDMOLY_API_BASE', 'https://api.vidmoly.to'), '/');
        $bases = array_values(array_unique(array_filter([
            $configured,
            'https://api.vidmoly.to',
        ], fn ($b) => ! str_contains($b, 'vidmoly.me')))); // drop non-resolving .me API host
        if (empty($bases)) {
            $bases = ['https://api.vidmoly.to'];
        }

        $lastMsg = 'key rejected';

        foreach ($bases as $base) {
            foreach (['/api/account/info', '/api/upload/server'] as $path) {
                try {
                    $resp = Http::timeout(12)->acceptJson()->get("{$base}{$path}", ['key' => $key]);
                } catch (Throwable) {
                    continue;
                }

                $result = $resp->json('result');
                $status = $resp->json('status');

                if ($resp->successful() && $result && (int) ($status ?? 200) < 400) {
                    $email = is_array($result) ? ($result['email'] ?? '') : '';
                    $hint = $base === $configured ? '' : " (use {$base})";

                    return ['ok', ($email ? "account: {$email}" : 'API reachable').$hint];
                }

                $msg = $resp->json('msg') ?: $this->bodySnippet($resp->body());
                if ($msg) {
                    $lastMsg = $msg;
                }
            }
        }

        return ['auth-fail', $lastMsg." — check key (vidmoly.me API access may be uploader-only)"];
    }

    private function checkDoodstream(): array
    {
        $key = env('TRANSFER_DOODSTREAM_API_KEY', '');
        if ($key === '') {
            return ['no-creds', 'TRANSFER_DOODSTREAM_API_KEY empty'];
        }
        $base = rtrim(env('TRANSFER_DOODSTREAM_API_BASE', 'https://doodapi.com/api'), '/');
        // account/info is the lightweight auth check
        $resp = Http::timeout(15)->acceptJson()->get("{$base}/account/info", ['key' => $key]);
        $status = $resp->json('status');
        if ($resp->successful() && (int) $status === 200) {
            $email = $resp->json('result.email') ?? '';
            return ['ok', 'account: '.($email ?: 'authenticated')];
        }

        return ['auth-fail', 'status '.($status ?? $resp->status()).' — check API key'];
    }

    private function checkStreamtape(): array
    {
        $login = env('TRANSFER_STREAMTAPE_LOGIN', '');
        $key = env('TRANSFER_STREAMTAPE_API_KEY', '');
        if ($login === '' || $key === '') {
            return ['no-creds', 'login and/or key empty'];
        }
        $base = rtrim(env('TRANSFER_STREAMTAPE_API_BASE', 'https://api.streamtape.com'), '/');
        $resp = Http::timeout(15)->acceptJson()->get("{$base}/account/info", ['login' => $login, 'key' => $key]);
        $status = $resp->json('status');
        if ($resp->successful() && (int) $status === 200) {
            $email = $resp->json('result.email') ?? '';
            return ['ok', 'account: '.($email ?: 'authenticated')];
        }

        // Streamtape returns status 403 with a msg when login/key is wrong or swapped
        $msg = $resp->json('msg') ?: $this->bodySnippet($resp->body());

        return ['auth-fail', 'status '.($status ?? $resp->status()).($msg ? " — {$msg}" : '').' (login+key swapped?)'];
    }

    private function bodySnippet(?string $body): string
    {
        $body = trim((string) $body);
        if ($body === '') {
            return '';
        }
        $body = preg_replace('/\s+/', ' ', strip_tags($body));

        return strlen($body) > 60 ? substr($body, 0, 60).'…' : $body;
    }

    private function checkMp4upload(): array
    {
        $key = env('TRANSFER_MP4UPLOAD_API_KEY', '');
        if ($key === '') {
            return ['no-creds', 'TRANSFER_MP4UPLOAD_API_KEY empty'];
        }
        $base = rtrim(env('TRANSFER_MP4UPLOAD_API_BASE', 'https://www.mp4upload.com/api'), '/');
        $resp = Http::timeout(15)->acceptJson()->get("{$base}/upload/server", ['key' => $key]);
        if ($resp->successful() && $resp->json('result')) {
            return ['ok', 'upload server reachable'];
        }

        return ['auth-fail', 'HTTP '.$resp->status().' — check API key'];
    }

    private function checkDailymotion(): array
    {
        $key = env('TRANSFER_DAILYMOTION_API_KEY', '');
        $secret = env('TRANSFER_DAILYMOTION_API_SECRET', '');
        $user = env('TRANSFER_DAILYMOTION_USERNAME', '');
        $pass = env('TRANSFER_DAILYMOTION_PASSWORD', '');
        if ($key === '' || $secret === '' || $user === '' || $pass === '') {
            return ['no-creds', 'api_key/secret/username/password incomplete'];
        }
        $resp = Http::timeout(15)->asForm()->post('https://api.dailymotion.com/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $key,
            'client_secret' => $secret,
            'username' => $user,
            'password' => $pass,
            'scope' => 'manage_videos',
        ]);
        if ($resp->successful() && $resp->json('access_token')) {
            return ['ok', 'OAuth token obtained'];
        }

        return ['auth-fail', $resp->json('error_description') ?? 'OAuth failed'];
    }

    private function checkGenericKey(string $envKey, string $okMsg): array
    {
        return env($envKey, '') !== '' ? ['ok', $okMsg] : ['no-creds', "{$envKey} empty"];
    }

    private function checkGenericLogin(string $loginKey, string $passKey, string $okMsg): array
    {
        return (env($loginKey, '') !== '' && env($passKey, '') !== '')
            ? ['ok', $okMsg]
            : ['no-creds', "{$loginKey}/{$passKey} empty"];
    }
}
