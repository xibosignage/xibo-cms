<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Entity;

use Carbon\Carbon;
use OpenApi\Attributes as OA;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Factory\FontFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;

/**
 * Class Font
 * @package Xibo\Entity
 */
#[OA\Schema]
class Font
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Font ID')]
    public $id;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Font created date')]
    public $createdAt;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Font modified date')]
    public $modifiedAt;

    /**
     * @var string
     */
    #[OA\Property(description: 'The name of the user that modified this font last')]
    public $modifiedBy;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Font name')]
    public $name;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Font file name')]
    public $fileName;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Font family name')]
    public $familyName;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Font file size in bytes')]
    public $size;

    /**
     * @var string
     */
    #[OA\Property(description: 'A MD5 checksum of the stored font file')]
    public $md5;

    /** @var  FontFactory */
    private $fontFactory;

    /**
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher, $fontFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->fontFactory = $fontFactory;
    }

    /**
     * @throws DuplicateEntityException
     */
    public function save($options = [])
    {
        $options = array_merge($options, ['validate' => true]);

        if ($options['validate']) {
            $this->validate();
        }

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

        $this->fontFactory->deleteFontFileAfterCommit((string)$this->fileName);
    }

    /**
     * Ensures no duplicate fonts are created
     *
     * @throws DuplicateEntityException
     */
    private function validate(): void
    {
        // Prevents uploading the same file under a different name
        if ($this->fontFactory->query(null, ['md5' => $this->md5])) {
            throw new DuplicateEntityException(__('This font file already exists in the library.'));
        }

        // Keeps unique file storage
        if ($this->fontFactory->query(null, ['fileName' => $this->fileName])) {
            throw new DuplicateEntityException(__('Similar font filename already exists in the library.'));
        }

        // Prevents multiple fonts with same name in UI
        if ($this->fontFactory->query(null, ['name' => $this->name])) {
            throw new DuplicateEntityException(__('Similar font name already exists in the library.'));
        }
    }
}
