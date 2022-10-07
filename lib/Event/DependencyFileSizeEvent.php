<?php

namespace Xibo\Event;

class DependencyFileSizeEvent extends Event
{
    public static $NAME = 'dependency.file.size.event';
    /** @var array */
    private $results;

    public function __construct($results)
    {
        $this->results = $results;
    }

    public function addResult($result)
    {
        $this->results[] = $result;
    }

    public function getResults()
    {
        return $this->results;
    }
}
