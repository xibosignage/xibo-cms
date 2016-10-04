<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (NotFound.php) is part of Xibo.
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
 * Class NotFoundException
 * @package Xibo\Exception
 */
class NotFoundException extends XiboException
{
    public $httpStatusCode = 404;
    public $handledException = true;
    public $entity = null;

    /**
     * NotFoundException constructor.
     * @param string $message
     * @param string $entity
     */
    public function __construct($message = null, $entity = null)
    {
        $this->entity = $entity;

        if ($message === null)
            $message = __('Not Found');

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