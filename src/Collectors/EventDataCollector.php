<?php
namespace AG\ElasticApmLaravel\Collectors;

use Closure;
use ReflectionFunction;
use Illuminate\Events\Dispatcher;

use AG\ElasticApmLaravel\Helper\Time;

/**
 * Collects info about the request duration as well as providing
 * a way to log duration of any operations.
 */
class EventDataCollector extends TimelineDataCollector implements DataCollectorInterface
{
    protected $events;

    public function __construct($request_start_time = null)
    {
        parent::__construct($request_start_time);
    }

    public function onWildcardEvent($name = null, $data = []): void
    {
        $time = microtime(true);

        // Find all listeners for the current event
        foreach ($this->events->getListeners($name) as $index => $listener) {
            // Check if it's an object + method name
            if (is_array($listener) && count($listener) > 1 && is_object($listener[0])) {
                list($class, $method) = $listener;
                // Skip this class itself
                if ($class instanceof static) {
                    continue;
                }

                // Format the listener to readable format
                $listener = get_class($class) . '@' . $method;
            // Handle closures
            } elseif ($listener instanceof Closure) {
                $reflector = new ReflectionFunction($listener);

                // Format the closure to a readable format
                $filename = ltrim(str_replace(base_path(), '', $reflector->getFileName()), '/');
                $listener = $reflector->getName() . ' (' . $filename . ':' . $reflector->getStartLine() . '-' . $reflector->getEndLine() . ')';
            }

            $data['listeners.' . $index] = $listener;
        }

        $this->addMeasure($name, $time, $time, $data);
    }

    public function subscribe(Dispatcher $events): void
    {
        $this->events = $events;
        $events->listen('*', [$this, 'onWildcardEvent']);
    }

    public function collect(): array 
    {
        return parent::collect();
    }

    public static function getName(): string
    {
        return 'event';
    }
}
