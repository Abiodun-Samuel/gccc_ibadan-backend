<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Observers\AttendanceObserver;
use App\Services\ApiExceptionHandler;
use App\Services\AttendanceService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $helpersPath = app_path('helpers.php');

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
        $this->app->singleton(AttendanceService::class, fn($app) => new AttendanceService());
        $this->app->singleton(ApiExceptionHandler::class);
    }

    public function boot(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
        $this->configureRateLimiting();
        Model::shouldBeStrict(!$this->app->isProduction());

        if ($this->app->environment('local')) {
            DB::listen(function ($query) {
                if ($query->time > 1000) {
                    logger()->warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => "{$query->time}ms"
                    ]);
                }
            });
        }

        Attendance::observe(AttendanceObserver::class);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn(Request $request) => Limit::perMinute(60)->by($this->resolveRateLimitKey($request)));

        RateLimiter::for('auth', fn(Request $request) => Limit::perMinute(5)->by($this->resolveRateLimitKey($request)));

        RateLimiter::for('forms', fn(Request $request) => Limit::perMinute(10)->by($this->resolveRateLimitKey($request)));

        RateLimiter::for('search', fn(Request $request) => Limit::perMinute(30)->by($this->resolveRateLimitKey($request)));

        RateLimiter::for('global', function (Request $request) {
            if ($request->user()) {
                return Limit::perMinute(120)->by($request->user()->id);
            }

            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('uploads', fn(Request $request) => [
            Limit::perMinute(5)->by($this->resolveRateLimitKey($request)),
            Limit::perHour(50)->by($this->resolveRateLimitKey($request)),
        ]);

        RateLimiter::for('admin', fn(Request $request) => Limit::perMinute(120)->by($this->resolveRateLimitKey($request)));
    }

    private function resolveRateLimitKey(Request $request): string
    {
        return $request->user()?->id ?? $request->ip();
    }
}
