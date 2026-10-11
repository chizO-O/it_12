<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read-only deployment preflight. Never prints credentials or writes to customer records.
 */
class CheckProductionReadiness extends Command
{
    protected $signature = 'grovemap:preflight
        {--skip-database : Skip the live PostgreSQL connection probe}
        {--probe-storage : Make a read-only request to the private Supabase CTS bucket}';

    protected $description = 'Check deployment configuration safely without displaying credentials or modifying records';

    private int $failures = 0;
    private int $warnings = 0;

    public function handle(): int
    {
        $this->components->info('GroveMap production readiness — no records are changed');

        $this->check(PHP_VERSION_ID >= 80200, 'PHP version >= 8.2');
        foreach (['pdo_pgsql', 'mbstring', 'fileinfo', 'openssl', 'intl'] as $extension) {
            $this->check(extension_loaded($extension), "PHP extension: {$extension}");
        }

        $this->check(config('app.env') === 'production', 'APP_ENV=production');
        $this->check(config('app.debug') === false, 'APP_DEBUG=false');

        $site = (string) config('app.url');
        $this->check(filter_var($site, FILTER_VALIDATE_URL) && str_starts_with($site, 'https://'), 'APP_URL is a valid HTTPS URL');

        $key = (string) config('app.key');
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $this->check(is_string($decoded) && strlen($decoded) === 32, 'APP_KEY has a valid 32-byte encryption key');

        $this->check(config('database.default') === 'pgsql', 'PostgreSQL is the default DB_CONNECTION');
        $this->check(config('database.connections.pgsql.search_path') === 'laravel', 'PostgreSQL search path is laravel');
        $this->check(in_array(config('database.connections.pgsql.sslmode'), ['require', 'verify-ca', 'verify-full'], true), 'PostgreSQL SSL is required');

        $this->check(in_array(config('session.driver'), ['database', 'redis'], true), 'Sessions use a shared persistent store');
        $this->check(config('session.secure') === true, 'Session cookies are restricted to HTTPS');
        $this->check(in_array(config('session.same_site'), ['lax', 'strict'], true), 'Session cookies use SameSite protection');

        $mailer = (string) config('mail.default');
        $this->check(! in_array($mailer, ['log', 'array', 'null'], true), 'MAIL_MAILER can deliver real email');
        $from = (string) config('mail.from.address');
        $this->check(filter_var($from, FILTER_VALIDATE_EMAIL) && ! str_ends_with(strtolower($from), '@example.com'), 'Mail sender has a real configured address');

        $supabaseUrl = rtrim((string) config('services.supabase.url'), '/');
        $storageKey = (string) config('services.supabase.service_role_key');
        $bucket = (string) config('services.supabase.cts_bucket');
        $this->check(str_starts_with($supabaseUrl, 'https://') && filter_var($supabaseUrl, FILTER_VALIDATE_URL), 'Supabase Storage URL is HTTPS');
        $this->check($storageKey !== '', 'Supabase server-side Storage credential is configured (value hidden)');
        $this->check((bool) preg_match('/^[a-z0-9][a-z0-9_-]*$/', $bucket), 'CTS Storage bucket name is valid');

        $this->check(is_file(public_path('build/manifest.json')), 'Vite production assets have been built');
        $this->check(! is_file(public_path('hot')), 'Vite dev-server marker public/hot is absent');
        $this->warnIf(! app()->configurationIsCached(), 'Laravel configuration is not cached (run php artisan optimize after applying environment variables)');

        $googleId = (string) config('services.google.client_id');
        $googleSecret = (string) config('services.google.client_secret');
        $googleRedirect = (string) config('services.google.redirect');
        if ($googleId === '' || $googleSecret === '') {
            $this->warnIf(true, 'Google OAuth is not fully configured; Google sign-in will be unavailable');
        } else {
            $this->check(str_starts_with($googleRedirect, 'https://')
                && parse_url($googleRedirect, PHP_URL_HOST) === parse_url($site, PHP_URL_HOST)
                && parse_url($googleRedirect, PHP_URL_PATH) === '/auth/google/callback', 'Google OAuth redirect matches the HTTPS deployment domain');
        }

        if (! $this->option('skip-database') && config('database.default') === 'pgsql') {
            try {
                $row = DB::selectOne('SELECT current_schema() AS schema_name');
                $this->check(($row->schema_name ?? null) === 'laravel', 'Live PostgreSQL connection uses schema laravel');
                $this->check(DB::getSchemaBuilder()->hasTable('users'), 'Live PostgreSQL has the users table');
            } catch (Throwable) {
                $this->check(false, 'Cannot connect to PostgreSQL or inspect its schema (details hidden)');
            }
        } elseif ($this->option('skip-database')) {
            $this->warnIf(true, 'Database connectivity was deliberately skipped');
        }

        if ($this->option('probe-storage')) {
            if ($supabaseUrl === '' || $storageKey === '' || $bucket === '') {
                $this->check(false, 'Cannot probe private CTS Storage; configuration is incomplete');
            } else {
                try {
                    $response = Http::withToken($storageKey)->withHeaders(['apikey' => $storageKey])
                        ->timeout(10)->get("{$supabaseUrl}/storage/v1/bucket/{$bucket}");
                    $this->check($response->successful(), 'Private CTS bucket is reachable (HTTP '.$response->status().')');
                } catch (Throwable) {
                    $this->check(false, 'Private CTS bucket probe failed (details hidden)');
                }
            }
        } else {
            $this->warnIf(true, 'Private CTS bucket was not probed; optionally run with --probe-storage');
        }

        $this->newLine();
        $this->line("Result: {$this->failures} failures, {$this->warnings} warnings");
        if ($this->failures > 0) {
            $this->components->error('Not ready for production. Resolve every failure before launch.');
            return self::FAILURE;
        }
        $this->components->info('Automated configuration checks passed. Manual security and end-to-end checks are still required.');
        return self::SUCCESS;
    }

    private function check(bool $passes, string $message): void
    {
        if ($passes) {
            $this->line("<info>PASS</info>  {$message}");
        } else {
            $this->line("<error>FAIL</error>  {$message}");
            $this->failures++;
        }
    }

    private function warnIf(bool $condition, string $message): void
    {
        if ($condition) {
            $this->line("<comment>WARN</comment>  {$message}");
            $this->warnings++;
        }
    }
}
