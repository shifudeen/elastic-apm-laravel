<?php
namespace AG\ElasticApmLaravel;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use PhilKra\Helper\Timer;

use AG\ElasticApmLaravel\Agent;
// use AG\ElasticApmLaravel\Collectors\TimelineDataCollector;
use AG\ElasticApmLaravel\Contracts\VersionResolver;

class ServiceProvider extends BaseServiceProvider
{
    private $source_config_path = __DIR__ . '/../config/elastic-apm-laravel.php';

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->source_config_path, 'elastic-apm-laravel');
        $this->registerAgent();
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishConfig();
        $this->listenForQueries();
        $this->app->booted(
            function () {
                $this->app->make(Agent::class)->measureBootstrapTime();
            }
        );
    }

    /**
     * Register the APM Agent into the Service Container
     */
    protected function registerAgent(): Agent
    {
        $agent = new Agent($this->getAgentConfig());
        $this->app->singleton(Agent::class, function () use ($agent) {
            return $agent;
        });

        return $agent;
    }

    /**
     * Publish the config file
     *
     * @param  string $configPath
     */
    protected function publishConfig(): void
    {
        $this->publishes([$this->source_config_path => $this->getConfigPath()], 'config');
    }

    /**
     * Get the config path
     *
     * @return string
     */
    protected function getConfigPath(): string
    {
        return config_path('elastic-apm-laravel.php');
    }

    protected function getAgentConfig(): array
    {
        return array_merge(
            [
                'framework' => 'Laravel',
                'frameworkVersion' => app()->version(),
            ],
            [
                'active' => config('elastic-apm-laravel.active'),
                'httpClient' => config('elastic-apm-laravel.httpClient'),
            ],
            $this->getAppConfig(),
            config('elastic-apm-laravel.env'),
            config('elastic-apm-laravel.server')
        );
    }

    protected function getAppConfig(): array
    {
        $config = config('elastic-apm-laravel.app');
        if ($this->app->bound(VersionResolver::class)) {
            $config['appVersion'] = $this->app->make(VersionResolver::class)->getVersion();
        }

        return $config;
    }

    // protected function listenForQueries()
    // {
    //     $this->app->events->listen(QueryExecuted::class, function (QueryExecuted $query) {
    //         $query = [
    //             'name' => 'Eloquent Query',
    //             'type' => 'db.mysql.query',
    //             'start' => round((microtime(true) - $query->time / 1000 - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 3),
    //             // calculate start time from duration
    //             'duration' => round($query->time, 3),
    //             'stacktrace' => $stackTrace,
    //             'context' => [
    //                 'db' => [
    //                     'instance' => $query->connection->getDatabaseName(),
    //                     'statement' => $query->sql,
    //                     'type' => 'sql',
    //                     'user' => $query->connection->getConfig('username'),
    //                 ],
    //             ],
    //         ];

    //         $this->app->make(Agent::class)->addQuery($query);
    //     });
    // }
}
