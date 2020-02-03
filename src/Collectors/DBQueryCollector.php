<?php
namespace AG\ElasticApmLaravel\Collectors;

use Exception;

use Illuminate\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use Jasny\DB\MySQL\QuerySplitter;

use AG\ElasticApmLaravel\Collectors\TimelineDataCollector;
use AG\ElasticApmLaravel\Collectors\Interfaces\DataCollectorInterface;

/**
 * Collects info about the database executed queries.
 */
class DBQueryCollector extends TimelineDataCollector implements DataCollectorInterface
{
    protected $app;

    public function __construct(Application $app, float $request_start_time)
    {
        parent::__construct($request_start_time);

        $this->app = $app;
        $this->registerEventListeners();
    }

    protected function registerEventListeners(): void
    {
        $this->app->events->listen(QueryExecuted::class, function (QueryExecuted $query) {
            $this->onQueryExecutedEvent($query);
        });
    }

    public function onQueryExecutedEvent(QueryExecuted $query): void
    {

        if (config('elastic-apm-laravel.spans.querylog.enabled') === 'auto') {
            if ($query->time < config('elastic-apm-laravel.spans.querylog.threshold')) {
                return;
            }
        }

        $start_time = microtime(true) - $this->request_start_time - $query->time / 1000;
        $end_time = $start_time + $query->time / 1000;

        $query = [
            'name' => $this->getQueryName($query->sql),
            'type' => 'db.mysql.query',
            'action' => 'query',
            'start' => $start_time,
            'end' => $end_time,
            'stacktrace' => $this->getStackTrace(),
            'context' => [
                'db' => [
                    'statement' => (string) $query->sql,
                    'type' => 'sql',
                ],
            ],
        ];

        $this->addMeasure(
            $query['name'],
            $query['start'],
            $query['end'],
            $query['type'],
            $query['action'],
            $query['context']
        );
    }

    public static function getName(): string
    {
        return 'query-collector';
    }

    private function getQueryName(string $sql): string
    {
        $fallback = 'Eloquent Query';

        try {
            $query_type = QuerySplitter::getQueryType($sql);
            $tables = QuerySplitter::splitTables($sql);

            if (isset($query_type) && is_array($tables)) {
                // Query type and tables
                return $query_type . ' ' . join(', ', array_values($tables));
            }

            return $fallback;
        } catch (Exception $e) {
            return $fallback;
        }
    }

    private function getStackTrace(): Collection
    {
        $stackTrace = $this->stripVendorTraces(
            collect(
                debug_backtrace(
                    DEBUG_BACKTRACE_PROVIDE_OBJECT,
                    config('elastic-apm-laravel.spans.backtraceDepth', 50)
                )
            )
        );

        return $stackTrace->map(function ($trace) {
            $sourceCode = $this->getSourceCode($trace);

            return [
                'function' => Arr::get($trace, 'function') . Arr::get($trace, 'type') . Arr::get($trace, 'function'),
                'abs_path' => Arr::get($trace, 'file'),
                'filename' => basename(Arr::get($trace, 'file')),
                'lineno' => Arr::get($trace, 'line', 0),
                'library_frame' => false,
                'vars' => null,
                'pre_context' => optional($sourceCode->get('pre_context'))->toArray(),
                'context_line' => optional($sourceCode->get('context_line'))->first(),
                'post_context' => optional($sourceCode->get('post_context'))->toArray(),
            ];
        })->values();
    }

    /**
     * Remove vendor code from stack traces
     *
     * @param  Collection $stackTrace
     * @return Collection
     */
    private function stripVendorTraces(Collection $stackTrace): Collection
    {
        return collect($stackTrace)->filter(function ($trace) {
            return !Str::startsWith((Arr::get($trace, 'file')), [
                base_path() . '/vendor',
            ]);
        });
    }

    /**
     * @param  array $stackTrace
     * @return Collection
     */
    private function getSourceCode(array $stackTrace): Collection
    {
        if (config('elastic-apm-laravel.spans.renderSource', false) === false) {
            return collect([]);
        }

        if (empty(Arr::get($stackTrace, 'file'))) {
            return collect([]);
        }

        $fileLines = file(Arr::get($stackTrace, 'file'));
        return collect($fileLines)->filter(function ($code, $line) use ($stackTrace) {
            //file starts counting from 0, debug_stacktrace from 1
            $stackTraceLine = Arr::get($stackTrace, 'line') - 1;

            $lineStart = $stackTraceLine - 5;
            $lineStop = $stackTraceLine + 5;

            return $line >= $lineStart && $line <= $lineStop;
        })->groupBy(function ($code, $line) use ($stackTrace) {
            if ($line < Arr::get($stackTrace, 'line')) {
                return 'pre_context';
            }

            if ($line == Arr::get($stackTrace, 'line')) {
                return 'context_line';
            }

            if ($line > Arr::get($stackTrace, 'line')) {
                return 'post_context';
            }

            return 'trash';
        });
    }
}
