<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Module.php) is part of Xibo.
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

class Module
{
    use EntityTrait;
    public $moduleId;

    public $name;
    public $description;
    public $validExtensions;
    public $imageUri;
    public $type;

    public $enabled;
    public $regionSpecific;
    public $previewEnabled;
    public $assignable;
    public $renderAs;
    public $settings = [];
    public $schemaVersion;
    public $viewPath = '../modules';

    public function __toString()
    {
        return sprintf('%s - %s', $this->type, $this->name);
    }

    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->imageUri))
            throw new \InvalidArgumentException(__('Image Uri is a required field.'));
    }

    public function save()
    {
        if ($this->moduleId == null || $this->moduleId == 0)
            $this->add();
        else
            $this->edit();

        $this->validate();
    }

    private function add()
    {
        $this->moduleId = PDOConnect::insert('
          INSERT INTO `module` (`Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`,
                `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`, `settings`, `viewPath`)
            VALUES (:module, :name, :enabled, :region_specific, :description,
                :image_uri, :schema_version, :valid_extensions, :preview_enabled, :assignable, :render_as, :settings, :viewPath)
        ', [
            'module' => $this->type,
            'name' => $this->name,
            'enabled' => $this->enabled,
            'region_specific' => $this->regionSpecific,
            'description' => $this->description,
            'image_uri' => $this->imageUri,
            'schema_version' => $this->schemaVersion,
            'valid_extensions' => $this->validExtensions,
            'preview_enabled' => $this->previewEnabled,
            'assignable' => $this->assignable,
            'render_as' => $this->renderAs,
            'settings' => json_encode($this->settings),
            'viewPath' => $this->viewPath
        ]);
    }

    private function edit()
    {
        $dbh = PDOConnect::init();

        $sth = $dbh->prepare('UPDATE module SET settings = :settings WHERE moduleid = :moduleId');

        $sth->execute(array(
            'moduleId' => $this->moduleId,
            'settings' => json_encode($this->settings)
        ));
    }
}