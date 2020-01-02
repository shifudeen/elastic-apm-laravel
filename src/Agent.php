<?php
namespace AG\ElasticApmLaravel;

use Illuminate\Support\Collection;

use PhilKra\Agent as PhilKraAgent;
use PhilKra\Events\EventBean;
use PhilKra\Events\EventFactoryInterface;
use PhilKra\Events\Transaction;
use PhilKra\Stores\TransactionsStore;

use AG\ElasticApmLaravel\Events\Span;
use AG\ElasticApmLaravel\Collectors\DataCollectorInterface;
use AG\ElasticApmLaravel\Collectors\TimelineDataCollector;

/**
 * The Elastic APM agent sends performance metrics and error logs to the APM Server.
 *
 * The agent records events, like HTTP requests and database queries.
 * The Agent automatically keeps track of queries to your data stores
 * to measure their duration and metadata (like the DB statement), as well as HTTP related information.
 * 
 * These events, called Transactions and Spans, are sent to the APM Server.
 * The APM Server converts them to a format suitable for Elasticsearch,
 * and sends them to an Elasticsearch cluster. You can then use the APM app
 * in Kibana to gain insight into latency issues and error culprits within your application.
 */
class Agent extends PhilKraAgent
{
    protected $apm_collectors;

    public function __construct(array $config, array $sharedContext = [], EventFactoryInterface $eventFactory = null, TransactionsStore $transactionsStore = null)
    {
        parent::__construct($config, $sharedContext, $eventFactory, $transactionsStore);

        $this->apm_collectors = new Collection();
        $this->registerCollectors();
    }

    public function registerCollectors(): void
    {
        $this->apm_collectors->put(TimelineDataCollector::getName(), new TimelineDataCollector());
    }

    public function measureBootstrapTime(): void
    {
        $this->apm_collectors->get(TimelineDataCollector::getName())->addMeasure(
            'laravel-bootstrap',
            $_SERVER['REQUEST_TIME_FLOAT'],
            microtime(true),
            'app'
        );
    }

    public function sendSpans(): void
    {
        $this->putSpanEvents($this->getTransaction('GET /ping'));
        // return parent::send();
        // file_put_contents(__DIR__ . '/send.json', json_encode($this->apm_collectors->get(TimelineDataCollector::getName())->collect()));
        // die('die');
        // return true;
    }

    private function putSpanEvents(Transaction $transaction)
    {
        file_put_contents(__DIR__ . '/send.json', json_encode($transaction));
        $this->apm_collectors->each(function ($collector) use ($transaction) {
            $collector->collect()->each(function ($measure) use ($transaction) {
                file_put_contents(__DIR__ . '/send.json', json_encode($measure), FILE_APPEND);
                $this->putEvent(new Span($measure, $transaction));
            });
        });
    }

    // public function startSpan(string $name, EventBean $parent): Span
    // {
    //     // Create and Store a Span
    //     $span = $this->factory()->newSpan($name, $parent);
    //     $span->start();
        
    //     $this->putEvent($span);

    //     return $span;
    // }
}
