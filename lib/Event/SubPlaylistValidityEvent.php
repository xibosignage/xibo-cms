<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Event;

use Xibo\Entity\Widget;

/**
 * Sub Playlist Validity Check
 */
class SubPlaylistValidityEvent extends Event
{
    public static $NAME = 'widget.sub-playlist.validity';

    /** @var \Xibo\Entity\Widget */
    protected $widget;

    private $isValid = true;

    /**
     * constructor.
     * @param \Xibo\Entity\Widget $widget
     */
    public function __construct(\Xibo\Entity\Widget $widget)
    {
        $this->widget = $widget;
    }

    /**
     * @return \Xibo\Entity\Widget
     */
    public function getWidget(): Widget
    {
        return $this->widget;
    }

    /**
     * @param bool $isValid
     * @return $this
     */
    public function setIsValid(bool $isValid): SubPlaylistValidityEvent
    {
        $this->isValid = $isValid;
        return $this;
    }

    /**
     * @return bool true if valid
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }
}
