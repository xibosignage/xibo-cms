<?php

namespace Xibo\Event;

use Xibo\Entity\Widget;

class WidgetEditOptionRequestEvent extends Event
{
    public static $NAME = 'widget.edit.option.event';
    private $widget;
    private $options;

    public function __construct($widget)
    {
        $this->widget = $widget;
    }

    /**
     * @return \Xibo\Entity\Widget|null
     */
    public function getWidget(): ?Widget
    {
        return $this->widget;
    }

    /**
     */
    public function getOptions(): array
    {
        if ($this->options === null) {
            $this->options = [];
        }

        return $this->options;
    }

    /**
     * @return $this
     */
    public function setOptions(array $options): WidgetEditOptionRequestEvent
    {
        $this->options = $options;
        return $this;
    }
}
