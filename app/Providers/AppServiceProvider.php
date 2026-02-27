<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Mariuzzo\LaravelJsLocalization\Commands\LangJsCommand;
use Mariuzzo\LaravelJsLocalization\Generators\LangJsGenerator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the Laravel JS Localization command into the app IOC.
        $this->app->singleton('localization.js', function ($app) {
            $app = $this->app;
            $laravelMajorVersion = (int) $app::VERSION;

            $files = $app['files'];

            if ($laravelMajorVersion === 4) {
                $langs = $app['path.base'] . '/app/lang';
            } elseif ($laravelMajorVersion >= 5 && $laravelMajorVersion < 9) {
                $langs = $app['path.base'] . '/resources/lang';
            } elseif ($laravelMajorVersion >= 9) {
                $langs = app()->langPath();
            }
            $messages = $app['config']->get('localization-js.messages');
            $generator = new LangJsGenerator($files, $langs, $messages);

            return new LangJsCommand($generator);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $setting = Setting::pluck('value', 'key')->toArray();
        if ($setting) {
            config([
                'mail.mailer' => $setting['mail_mailer'] ?? '',
                'mail.host' => $setting['mail_host'] ?? '',
                'mail.port' => $setting['mail_port'] ?? '',
                'mail.username' => $setting['mail_username'] ?? '',
                'mail.password' => $setting['mail_password'] ?? '',
                'mail.encryption' => $setting['mail_encryption'] ?? '',
                'mail.from.address' => ! empty($setting['mail_from_address'] ?? '')
                    ? $setting['mail_from_address']
                    : env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'mail.from.name' => ! empty($setting['mail_from_name'] ?? '')
                    ? $setting['mail_from_name']
                    : env('MAIL_FROM_NAME', 'Example'),
            ]);
        }
        Paginator::useBootstrap();
    }
}
