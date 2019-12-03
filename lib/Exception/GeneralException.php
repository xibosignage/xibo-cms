<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Exception;

/**
 * Class GeneralException
 * @package Xibo\Exception
 */
class GeneralException extends XiboException
{
    public $httpStatusCode = 400;
    public $handledException = true;
    public $entity = null;

    /**
     * GeneralException constructor.
     * @param string $message
     * @param string $entity
     */
    public function __construct($message = null, $entity = null)
    {
        $this->entity = $entity;

        if ($message === null)
            $message = __('Error');

        parent::__construct($message);
    }

    /**
     * @return array
     */
    public function getErrorData()
    {
        return ['entity' => $this->entity];
    }
}