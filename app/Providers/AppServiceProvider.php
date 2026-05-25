<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Stringable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->environment('local') || ! config('database.log_queries')) {
            return;
        }

        DB::listen(function ($query): void {
            Log::channel(config('database.query_log_channel'))->debug('database.query', [
                'sql' => $query->sql,
                'bindings' => $this->sanitizeQueryBindings($query->bindings, $query->sql),
                'time_ms' => $query->time,
                'connection' => $query->connectionName,
            ]);
        });
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @return array<int, mixed>
     */
    private function sanitizeQueryBindings(array $bindings, string $sql): array
    {
        $forceRedactStrings = $this->containsSensitiveQueryTarget($sql);

        return array_map(function (mixed $binding) use ($forceRedactStrings): mixed {
            if ($binding instanceof \DateTimeInterface) {
                return $binding->format(DATE_ATOM);
            }

            if ($binding instanceof Stringable) {
                $binding = (string) $binding;
            }

            if (! is_string($binding)) {
                return $binding;
            }

            if (! mb_check_encoding($binding, 'UTF-8')) {
                return sprintf('[binary string redacted, %d bytes]', strlen($binding));
            }

            $maxLength = (int) config('database.query_log_max_binding_length', 120);

            if (! $forceRedactStrings && mb_strlen($binding) <= $maxLength) {
                return $binding;
            }

            return sprintf('[string redacted, %d chars]', mb_strlen($binding));
        }, $bindings);
    }

    private function containsSensitiveQueryTarget(string $sql): bool
    {
        $normalizedSql = strtolower($sql);

        foreach ([
            'password',
            'token',
            'secret',
            'payload',
            'remember_token',
            'user_agent',
            '`sessions`',
            '`personal_access_tokens`',
            '`password_reset_tokens`',
        ] as $sensitiveNeedle) {
            if (str_contains($normalizedSql, $sensitiveNeedle)) {
                return true;
            }
        }

        return false;
    }
}
