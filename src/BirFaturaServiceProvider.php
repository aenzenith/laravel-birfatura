<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura;

use Aenzenith\BirFatura\Console\AboutCommand as BirFaturaAboutCommand;
use Aenzenith\BirFatura\Console\TokenCommand;
use Aenzenith\BirFatura\Http\Middleware\ProtectIntegration;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class BirFaturaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/birfatura.php', 'birfatura');

        $this->app->singleton(BirFatura::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'birfatura');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/birfatura.php' => $this->app->configPath('birfatura.php'),
            ], 'birfatura-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/birfatura'),
            ], 'birfatura-lang');

            $this->commands([BirFaturaAboutCommand::class, TokenCommand::class]);

            $this->registerAboutSection();
        }
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached() || ! (bool) config('birfatura.routes.enabled', true)) {
            return;
        }

        $domain = config('birfatura.routes.domain');
        $extra = config('birfatura.routes.middleware', []);

        Route::group(array_filter([
            'prefix' => trim((string) config('birfatura.routes.prefix', 'birfatura'), '/'),
            'domain' => is_string($domain) && $domain !== '' ? $domain : null,
            'as' => 'birfatura.',
            // Deliberately outside the `web` group (no session, no CSRF) and the
            // `api` group (its throttle is replaced by the package's own).
            'middleware' => [ProtectIntegration::class, ...(is_array($extra) ? $extra : [])],
        ], static fn (mixed $value): bool => $value !== null && $value !== ''), function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/birfatura.php');
        });
    }

    private function registerAboutSection(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('BirFatura', function (): array {
            try {
                $birFatura = $this->app->make(BirFatura::class);

                return [
                    'Enabled' => $birFatura->enabled() ? '<fg=green;options=bold>YES</>' : '<fg=yellow;options=bold>NO</>',
                    'Site address' => $birFatura->baseUrl(),
                ];
            } catch (Throwable) {
                return ['Status' => '<fg=red;options=bold>MISCONFIGURED</> (run birfatura:about)'];
            }
        });
    }
}
