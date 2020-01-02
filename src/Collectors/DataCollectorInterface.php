<?php
namespace AG\ElasticApmLaravel\Collectors;

use Illuminate\Support\Collection;

interface DataCollectorInterface
{
    function collect(): Collection;
    static function getName(): string;
} 
