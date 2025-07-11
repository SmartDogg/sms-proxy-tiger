<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SmsProxyService;
use App\Repositories\SmsApiRepository;

class SmsProxyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Регистрируем репозиторий как singleton для переиспользования соединений
        $this->app->singleton(SmsApiRepository::class, function ($app) {
            return new SmsApiRepository();
        });

        // Регистрируем сервис
        $this->app->singleton(SmsProxyService::class, function ($app) {
            return new SmsProxyService(
                $app->make(SmsApiRepository::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Ничего не делаем в boot - всё необходимое уже настроено
    }
}