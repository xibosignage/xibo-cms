<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Resolution.php) is part of Xibo.
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


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Storage\PDOConnect;

class Resolution implements \JsonSerializable
{
    use EntityTrait;
    public $resolutionId;

    public $resolution;

    public $width;
    public $height;

    public $designerWidth;
    public $designerHeight;

    public $version;
    public $enabled;

    public function getId()
    {
        return $this->resolutionId;
    }

    public function getOwnerId()
    {
        // No owner
        return 1;
    }

    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->resolution))
            throw new \InvalidArgumentException(__('Please provide a name'));

        if (!v::int()->notEmpty()->min(1)->validate($this->width))
            throw new \InvalidArgumentException(__('Please provide a width'));

        if (!v::int()->notEmpty()->min(1)->validate($this->height))
            throw new \InvalidArgumentException(__('Please provide a height'));

        // Set the designer width and height
        $factor = min (800 / $this->width, 800 / $this->height);

        $this->designerWidth = round($this->width * $factor);
        $this->designerHeight = round($this->height * $factor);
    }

    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        if ($this->resolutionId == null || $this->resolutionId == 0)
            $this->add();
        else
            $this->edit();
    }

    public function delete()
    {
        PDOConnect::update('DELETE FROM resolution WHERE resolutionID = :resolutionId', ['resolutionId' => $this->resolutionId]);
    }

    private function add()
    {
        $this->resolutionId = PDOConnect::insert('
          INSERT INTO `resolution` (resolution, width, height, intended_width, intended_height, version)
            VALUES (:resolution, :width, :height, :intended_width, :intended_height, :version)
        ', [
            'resolution' => $this->resolution,
            'width' => $this->designerWidth,
            'height' => $this->designerHeight,
            'intended_width' => $this->width,
            'intended_height' => $this->height,
            'version' => 2
        ]);
    }

    private function edit()
    {
        PDOConnect::update('
          UPDATE resolution SET resolution = :resolution,
                width = :width,
                height = :height,
                intended_width = :intended_width,
                intended_height = :intended_height,
                enabled = :enabled
           WHERE resolutionID = :resolutionId
        ', [
            'resolutionId' => $this->resolutionId,
            'resolution' => $this->resolution,
            'width' => $this->designerWidth,
            'height' => $this->designerHeight,
            'intended_width' => $this->width,
            'intended_height' => $this->height,
            'enabled' => $this->enabled
        ]);
    }
}