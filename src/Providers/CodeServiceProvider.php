<?php

namespace King\BaseToCode\Providers;

use Illuminate\Support\ServiceProvider;

class CodeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->publishes([
            __DIR__ . '/../config/basetocode.php' => config_path('basetocode.php')
        ]);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //绑定单例服务
        $this->app->singleton('basetocode', function ($app) {
            return new  Code($app['session'], $app['config']);
        });
    }

    public function provides()
    {
        // 需要延时加载， 需要定义providers
        return ['basetocode'];
    }
}
