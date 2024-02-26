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

namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class MediaManager
 * @package Xibo\Controller
 */
class MediaManager extends Base
{
    private StorageServiceInterface $store;
    private ModuleFactory $moduleFactory;
    private MediaFactory $mediaFactory;

    /**
     * Set common dependencies.
     */
    public function __construct(
        StorageServiceInterface $store,
        ModuleFactory $moduleFactory,
        MediaFactory $mediaFactory
    ) {
        $this->store = $store;
        $this->moduleFactory = $moduleFactory;
        $this->mediaFactory = $mediaFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'media-manager-page';
        $this->getState()->setData([
            'library' => $this->getLibraryUsage()
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get the library usage
     * @return array
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function getLibraryUsage(): array
    {
        // Set up some suffixes
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $params = [];

        // Library Size in Bytes
        $sql = '
            SELECT COUNT(`mediaId`) AS countOf,
                IFNULL(SUM(`FileSize`), 0) AS SumSize,
                `type`
              FROM `media`
             WHERE 1 = 1 ';

        $this->mediaFactory->viewPermissionSql(
            'Xibo\Entity\Media',
            $sql,
            $params,
            '`media`.mediaId',
            '`media`.userId',
            [],
            'media.permissionsFolderId'
        );
        $sql .= ' GROUP BY type ';
        $sql .= ' ORDER BY 2 ';

        $results = $this->store->select($sql, $params);

        $libraryUsage = [];
        $totalCount = 0;
        $totalSize = 0;
        foreach ($results as $library) {
            $bytes = doubleval($library['SumSize']);
            $totalSize += $bytes;
            $totalCount += $library['countOf'];

            try {
                $title = $this->moduleFactory->getByType($library['type'])->name;
            } catch (NotFoundException) {
                $title = $library['type'] === 'module' ? __('Widget cache') : ucfirst($library['type']);
            }
            $libraryUsage[] = [
                'title' => $title,
                'count' => $library['countOf'],
                'size' => $bytes,
            ];
        }

        // Decide what our units are going to be, based on the size
        $base = ($totalSize === 0) ? 0 : floor(log($totalSize) / log(1024));

        return [
            'countOf' => $totalCount,
            'size' => ByteFormatter::format($totalSize, 1, true),
            'types' => $libraryUsage,
            'typesSuffix' => $suffixes[$base],
            'typesBase' => $base,
        ];
    }
}
