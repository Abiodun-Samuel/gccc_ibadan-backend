<?php

namespace App\Providers;

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
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $helpersPath = app_path('helpers.php');

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
        $this->app->singleton(AttendanceService::class, fn($app) => new AttendanceService());
        $this->app->singleton(ApiExceptionHandler::class);
    }

    /**
     * Bootstrap any application services.
     */
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

        // Log slow queries in development
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
    }

    protected function configureRateLimiting(): void
    {
        // Default API rate limiting
        RateLimiter::for('api', fn(Request $request) => Limit::perMinute(60)->by($this->resolveRateLimitKey($request)));

        // Stricter rate limiting for authentication routes
        RateLimiter::for('auth', fn(Request $request) => Limit::perMinute(5)->by($this->resolveRateLimitKey($request)));

        // Rate limiting for form submissions
        RateLimiter::for('forms', fn(Request $request) => Limit::perMinute(10)->by($this->resolveRateLimitKey($request)));

        // Rate limiting for search/listing endpoints
        RateLimiter::for('search', fn(Request $request) => Limit::perMinute(30)->by($this->resolveRateLimitKey($request)));

        // Global rate limiting with higher limits for authenticated users
        RateLimiter::for('global', function (Request $request) {
            if ($request->user()) {
                // Higher limits for authenticated users
                return Limit::perMinute(120)->by($request->user()->id);
            }

            // Lower limits for guests
            return Limit::perMinute(30)->by($request->ip());
        });

        // File upload rate limiting with multiple limits
        RateLimiter::for('uploads', fn(Request $request) => [
            Limit::perMinute(5)->by($this->resolveRateLimitKey($request)),
            Limit::perHour(50)->by($this->resolveRateLimitKey($request)),
        ]);

        // Admin routes with higher limits
        RateLimiter::for('admin', fn(Request $request) => Limit::perMinute(120)->by($this->resolveRateLimitKey($request)));
    }

    /**
     * Resolve the rate limiting key for the request.
     */
    private function resolveRateLimitKey(Request $request): string
    {
        // Use user ID if authenticated, otherwise use IP
        return $request->user()?->id ?? $request->ip();
    }
}
