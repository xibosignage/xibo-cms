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

namespace Xibo\Twig;

use Slim\Flash\Messages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigMessages extends AbstractExtension
{
    /**
     * @var Messages
     */
    protected $flash;
    /**
     * Constructor.
     *
     * @param Messages $flash the Flash messages service provider
     */
    public function __construct(Messages $flash)
    {
        $this->flash = $flash;
    }
    /**
     * Extension name.
     *
     * @return string
     */
    public function getName()
    {
        return 'slim-twig-flash';
    }
    /**
     * Callback for twig.
     *
     * @return array
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('flash', [$this, 'getMessages']),
        ];
    }
    /**
     * Returns Flash messages; If key is provided then returns messages
     * for that key.
     *
     * @param string $key
     *
     * @return array
     */
    public function getMessages($key = null)
    {
        if (null !== $key) {
            return $this->flash->getMessage($key);
        }
        return $this->flash->getMessages();
    }
}