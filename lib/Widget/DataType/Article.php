<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

namespace Xibo\Widget\DataType;

use Xibo\Widget\Definition\DataType;

/**
 * An article, usually from a blog or news feed.
 */
class Article implements \JsonSerializable, DataTypeInterface
{
    public static $NAME = 'article';
    public $title;
    public $summary;
    public $content;
    public $author;
    public $permalink;
    public $link;
    public $image;

    /** @var \Carbon\Carbon */
    public $date;

    /** @var \Carbon\Carbon */
    public $publishedDate;

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'author' => $this->author,
            'permalink' => $this->permalink,
            'link' => $this->link,
            'date' => $this->date->format('c'),
            'publishedDate' => $this->publishedDate->format('c'),
            'image' => $this->image,
        ];
    }

    public function getDefinition(): DataType
    {
        $dataType = new DataType();
        $dataType->id = self::$NAME;
        $dataType->name = __('Article');
        $dataType
            ->addField('title', __('Title'), 'text')
            ->addField('summary', __('Summary'), 'text')
            ->addField('content', __('Content'), 'text')
            ->addField('author', __('Author'), 'text')
            ->addField('permalink', __('Permalink'), 'text')
            ->addField('link', __('Link'), 'text')
            ->addField('date', __('Created Date'), 'datetime')
            ->addField('publishedDate', __('Published Date'), 'datetime')
            ->addField('image', __('Image'), 'image');
        return $dataType;
    }
}
