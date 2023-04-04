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
namespace Xibo\Connector;

/**
 * A provider import request/result.
 * This is used to exchange a search result from a provider for a mediaId in the library.
 */
class ProviderImport implements \JsonSerializable
{
    /** @var \Xibo\Entity\SearchResult */
    public $searchResult;

    /** @var \Xibo\Entity\Media media */
    public $media;

    /** @var bool has this been configured for import */
    public $isConfigured = false;

    /** @var string the URL to use for the download */
    public $url;

    /** @var bool has this been uploaded */
    public $isUploaded = false;

    /** @var bool is error state? */
    public $isError = false;

    /** @var string error message, if in error state */
    public $error;

    /**
     * @return \Xibo\Connector\ProviderImport
     */
    public function configureDownload(): ProviderImport
    {
        $this->isConfigured = true;
        $this->url = $this->searchResult->download;
        return $this;
    }

    /**
     * @param $message
     * @return $this
     */
    public function setError($message): ProviderImport
    {
        $this->isUploaded = false;
        $this->isError = true;
        $this->error = $message;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'item' => $this->searchResult,
            'media' => $this->media,
            'isUploaded' => $this->isUploaded,
            'isError' => $this->isError,
            'error' => $this->error
        ];
    }
}
