<?php
namespace AG\ElasticApmLaravel\Events;

use JsonSerializable;
use PhilKra\Events\EventBean;
use PhilKra\Events\TraceableEvent;
use PhilKra\Helper\Encoding;
// use PhilKra\Traits\Events\Stacktrace;

/**
 *
 * Spans
 *
 * @link https://www.elastic.co/guide/en/apm/server/master/span-api.html
 *
 */
class Span extends TraceableEvent implements JsonSerializable
{
    // use Stacktrace;

    /**
     * @var string
     */
    private $name;

    /**
     * @var float
     */
    private $start;

    /**
     * @var int
     */
    private $duration = 0;

    /**
     * @var string
     */
    private $type = 'request';

    /**
     * @var mixed array|null
     */
    private $context = null;

    /**
     * @var mixed array|null
     */
    private $stacktrace = [];

    /**
     * @param array $info
     * @param EventBean $parent
     */
    public function __construct(array $info, EventBean $parent)
    {
        parent::__construct([]);

        $this->name = trim($info['label']);
        $this->type = 'framework';
        $this->subtype = 'laravel';
        $this->action = 'boot';
        $this->start = $info['start'];
        $this->duration = $info['duration'];
        $this->setParent($parent);
    }

    /**
    * Get the Event Name
    *
    * @return string
    */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Set the Spans' Type
     *
     * @param string $type
     */
    public function setType(string $type)
    {
        $this->type = trim($type);
    }

    /**
     * Provide additional Context to the Span
     *
     * @link https://www.elastic.co/guide/en/apm/server/master/span-api.html
     *
     * @param array $context
     */
    public function setContext(array $context)
    {
        $this->context = $context;
    }

    /**
     * Set a complimentary Stacktrace for the Span
     *
     * @link https://www.elastic.co/guide/en/apm/server/master/span-api.html
     *
     * @param array $stacktrace
     */
    public function setStacktrace(array $stacktrace)
    {
        $this->stacktrace = $stacktrace;
    }

    /**
     * Serialize Span Event
     *
     * @link https://www.elastic.co/guide/en/apm/server/master/span-api.html
     *
     * @return array
     */
    public function jsonSerialize() : array
    {
        return [
            'span' => [
                'id'             => $this->getId(),
                'transaction_id' => $this->getParentId(),
                'trace_id'       => $this->getTraceId(),
                'parent_id'      => $this->getParentId(),
                'type'           => Encoding::keywordField($this->type),
                'subtype'        => Encoding::keywordField($this->subtype),
                'action'         => Encoding::keywordField($this->action),
                'context'        => $this->getContext(),
                'start'          => 0,
                'duration'       => $this->duration,
                'name'           => Encoding::keywordField($this->getName()),
                'stacktrace'     => $this->stacktrace,
                'sync'           => true,
                'timestamp'      => $this->getTimestamp(),
            ]
        ];
    }
}
