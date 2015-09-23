<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-15 Daniel Garner
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
namespace Xibo\Widget;

use InvalidArgumentException;
use Respect\Validation\Validator as v;
use Xibo\Helper\Sanitize;

class LocalVideo extends ModuleWidget
{
    /**
     * Validate
     */
    public function validate()
    {
        // Validate
        if (!v::string()->notEmpty()->validate($this->getOption('uri')))
            throw new InvalidArgumentException(__('Please enter a full path name giving the location of this video on the client'));

        if (!v::int()->min(1)->validate($this->getDuration()))
            throw new InvalidArgumentException(__('You must enter a duration.'));
    }

    /**
     * Add Media to the Database
     */
    public function add()
    {
        // Set some options
        $this->setDuration(Sanitize::getInt('duration'));
        $this->setOption('uri', urlencode(Sanitize::getString('uri')));

        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Edit Media in the Database
     */
    public function edit()
    {
        // Set some options
        $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));
        $this->setOption('uri', urlencode(Sanitize::getString('uri')));

        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    public function isValid()
    {
        // Client dependant
        return 2;
    }
}
