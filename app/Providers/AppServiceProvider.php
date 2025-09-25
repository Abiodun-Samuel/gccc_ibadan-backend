<?php

namespace App\Providers;

use App\Services\AttendanceService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

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
}
