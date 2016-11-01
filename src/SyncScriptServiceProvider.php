<?php namespace AwkwardIdeas\SyncScript;

use Illuminate\Support\ServiceProvider;
use AwkwardIdeas\MyPDO\MyPDOServiceProvider;

class SyncScriptServiceProvider extends ServiceProvider
{

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/syncscript.php';

        $this->publishes([$configPath => $this->getConfigPath()], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->mergeConfigFrom(__DIR__ . '/../config/syncscript.php', 'syncscript');

        $this->app['syncscript.generate'] = $this->app->share(function () {
            return new Commands\SyncScriptGenerate();
        });
        $this->commands(
            'syncscript.generate'
        );

        $this->app->register(\AwkwardIdeas\MyPDO\MyPDOServiceProvider::class);
    }
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

    private function getConfigPath()
    {
        return config_path('syncscript.php');
    }
}
