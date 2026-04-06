<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->defineConstants();
        require app_path('Macros/macros.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    private function defineConstants()
    {
        $constants = $this->app['config']->get('constants');

        if ( ! is_null($constants)) {
            foreach ($constants as $key => $value) {
                if (!defined($key)) {
                    define($key, $value);
                }
            }
        }
    }
}
