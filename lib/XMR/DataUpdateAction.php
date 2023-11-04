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

namespace Xibo\XMR;

/**
 * Class DataUpdateAction
 *  Used to indicate that a widget has been recently updated and should be downloaded again
 * @package Xibo\XMR
 */
class DataUpdateAction extends PlayerAction
{
    /**
     * @param int $widgetId The widgetId which has been updated
     */
    public function __construct(protected int $widgetId)
    {
        $this->setQos(5);
    }

    /** @inheritdoc */
    public function getMessage(): string
    {
        $this->setQos(1);
        $this->action = 'dataUpdate';

        return $this->serializeToJson(['widgetId']);
    }
}
