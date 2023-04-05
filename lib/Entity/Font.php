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

namespace Xibo\Entity;

use Carbon\Carbon;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Font
 * @package Xibo\Entity
 * @SWG\Definition()
 */
class Font
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Font ID")
     * @var int
     */
    public $id;

    /**
     * @SWG\Property(description="The Font created date")
     * @var string
     */
    public $createdAt;

    /**
     * @SWG\Property(description="The Font modified date")
     * @var string
     */
    public $modifiedAt;

    /**
     * @SWG\Property(description="The name of the user that modified this font last")
     * @var string
     */
    public $modifiedBy;

    /**
     * @SWG\Property(description="The Font name")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="The Font file name")
     * @var string
     */
    public $fileName;

    /**
     * @SWG\Property(description="The Font family name")
     * @var string
     */
    public $familyName;

    /**
     * @SWG\Property(description="The Font file size in bytes")
     * @var int
     */
    public $size;

    /**
     * @SWG\Property(description="A MD5 checksum of the stored font file")
     * @var string
     */
    public $md5;

    /** @var ConfigServiceInterface */
    private $config;

    /**
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher, $config)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->config = $config;
    }

    public function getFilePath()
    {
        return $this->config->getSetting('LIBRARY_LOCATION') . 'fonts/' . $this->fileName;
    }

    public function save()
    {
        if ($this->id === null || $this->id === 0) {
            $this->add();

            $this->audit($this->id, 'Added font', [
                'mediaId' => $this->id,
                'name' => $this->name,
                'fileName' => $this->fileName,
            ]);
        } else {
            $this->edit();
        }
    }

    private function add()
    {
        $this->id = $this->getStore()->insert('
            INSERT INTO `fonts` (`createdAt`, `modifiedAt`, modifiedBy, name, fileName, familyName, size, md5)
              VALUES (:createdAt, :modifiedAt, :modifiedBy, :name, :fileName, :familyName, :size, :md5)
        ', [
            'createdAt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'modifiedAt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'modifiedBy' => $this->modifiedBy,
            'name' => $this->name,
            'fileName' => $this->fileName,
            'familyName' => $this->familyName,
            'size' => $this->size,
            'md5' => $this->md5
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('UPDATE `fonts` SET modifiedAt = :modifiedAt, modifiedBy = :modifiedBy, name = :name WHERE id = :id', [
                'modifiedAt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
                'modifiedBy' => $this->modifiedBy,
                'name' => $this->name,
                'id' => $this->id
            ]);
    }

    public function delete()
    {
        // delete record
        $this->getStore()->update('DELETE FROM `fonts` WHERE id = :id', [
            'id' => $this->id
        ]);

        // delete file
        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');

        if (file_exists($libraryLocation . 'fonts/' . $this->fileName)) {
            unlink($libraryLocation . 'fonts/' . $this->fileName);
        }
    }
}
