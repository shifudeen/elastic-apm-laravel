<?php
namespace AG\ElasticApmLaravel\Middleware;

use Closure;
use Throwable;
use PhilKra\Events\Transaction;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AG\ElasticApmLaravel\Agent;

/**
 * This middleware will record a transaction from the moment the request hits the server, until the response is sent to the client.
 * This transaction will include:
 *   - The timestamp of the event.
 *   - A unique id, type, and name.
 *   - Data about the environment in which the event is recorded.
 *   - The stacktrace of executed code.
 *
 * The transaction will be send to Elastic server AFTER the reponse has been sent to the browser:
 * https://laravel.com/docs/5.5/middleware#terminable-middleware
 */
class RecordTransaction
{
    protected $agent;
    protected $transaction_name;

    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
        $this->apm_collectors = new Collection();
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $transaction_name = $this->getTransactionName($request);

        // Start a transaction and measure the time the application takes to boot
        $transaction = $this->agent->startTransaction($transaction_name, [], $_SERVER['REQUEST_TIME_FLOAT']);

        // Execute the application logic
        $response = $next($request);

        $this->addMetadata($transaction, $request, $response);

        // Measure the transaction and measure the time
        $this->agent->stopTransaction($transaction_name);

        return $response;
    }

    public function addMetadata(Transaction $transaction, Request $request, Response $response): void
    {
        $transaction->setResponse([
            'finished' => true,
            'headers_sent' => true,
            'status_code' => $response->getStatusCode(),
            'headers' => $this->formatHeaders($response->headers->all()),
        ]);

        $user = $request->user();
        $transaction->setUserContext([
            'id' => optional($user)->id,
            'ip' => $request->ip(),
            'user-agent' => $request->userAgent(),
        ]);

        $transaction->setMeta([
            'result' => $response->getStatusCode(),
            'type' => 'HTTP'
        ]);
    }

    public function terminate($request, $response): void 
    {
        try {
            $this->agent->sendSpans();
            // $this->agent->send();
        } catch(Throwable $t) {
            Log::error($t);
        }
    }

    protected function getTransactionName(Request $request): string
    {
        $uri = $request->getPathInfo();
        return $request->method() . ' ' . $this->normalizeUri($uri);
    }

    protected function normalizeUri(string $uri): string
    {
        // Fix leading /
        return '/' . trim($uri, '/');
    }

    protected function formatHeaders(array $headers): array
    {
        return collect($headers)->map(function ($values, $header) {
            return head($values);
        })->toArray();
    }
}
