<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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


namespace Xibo\Entity;


use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

class DataSetRss implements \JsonSerializable
{
    use EntityTrait;

    public $id;
    public $dataSetId;
    public $summaryColumnId;
    public $contentColumnId;
    public $publishedDateColumnId;

    public $psk;
    public $title;
    public $author;

    public $sort;
    public $filter;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    /**
     * Save
     */
    public function save()
    {
        if ($this->id == null) {
            $this->add();

            $this->audit($this->id, 'Added', []);
        } else {
            $this->edit();

            $this->audit($this->id, 'Saved');
        }
    }

    /**
     * Delete
     */
    public function delete()
    {
        $this->getStore()->update('DELETE FROM `datasetrss` WHERE id = :id', ['id' => $this->id]);

        $this->audit($this->id, 'Deleted');
    }

    private function add()
    {
        $this->id = $this->getStore()->insert('
            INSERT INTO datasetrss (dataSetId, psk, title, author, summaryColumnId, contentColumnId, publishedDateColumnId, sort, filter) VALUES 
             (:dataSetId, :psk, :title, :author, :summaryColumnId, :contentColumnId, :publishedDateColumnId, :sort, :filter)
        ', [
            'dataSetId' => $this->dataSetId,
            'psk' => $this->psk,
            'title' => $this->title,
            'author' => $this->author,
            'summaryColumnId' => $this->summaryColumnId,
            'contentColumnId' => $this->contentColumnId,
            'publishedDateColumnId' => $this->publishedDateColumnId,
            'sort' => $this->sort,
            'filter' => $this->filter
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('
            UPDATE `datasetrss` SET
                psk = :psk,
                title = :title,
                author = :author,
                summaryColumnId = :summaryColumnId,
                contentColumnId = :contentColumnId,
                publishedDateColumnId = :publishedDateColumnId,
                sort = :sort,
                filter = :filter
             WHERE id = :id
        ', [
            'id' => $this->id,
            'dataSetId' => $this->dataSetId,
            'psk' => $this->psk,
            'title' => $this->title,
            'author' => $this->author,
            'summaryColumnId' => $this->summaryColumnId,
            'contentColumnId' => $this->contentColumnId,
            'publishedDateColumnId' => $this->publishedDateColumnId,
            'sort' => $this->sort,
            'filter' => $this->filter
        ]);
    }
}