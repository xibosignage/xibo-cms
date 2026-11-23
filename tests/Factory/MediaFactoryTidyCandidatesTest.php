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

namespace Xibo\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Xibo\Factory\MediaFactory;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\PdoStorageService;

/**
 * A library tidy deletes media no DataSet image column refers to, so every such column must be part of the query.
 */
class MediaFactoryTidyCandidatesTest extends TestCase
{
    public function testEveryDataSetImageColumnIsIncludedWithItsHeadingQuoted()
    {
        $queries = [];
        $store = $this->createMock(PdoStorageService::class);
        $store->method('select')->willReturnCallback(function (string $sql) use (&$queries) {
            $queries[] = $sql;
            return count($queries) === 1
                ? [
                    ['dataSetId' => 3, 'heading' => 'Photo'],
                    ['dataSetId' => 4, 'heading' => 'Logo (main)'],
                    ['dataSetId' => 5, 'heading' => 'a`b'],
                ]
                : [];
        });

        $dependencies = new BaseDependenciesService();
        $dependencies->setStore($store);
        $dependencies->setLogger($this->createMock(LogServiceInterface::class));

        $factory = new MediaFactory(
            null,
            null,
            null,
            null,
            null,
            null,
            $this->createMock(LibraryStorageRegistryInterface::class)
        );
        $factory->useBaseDependenciesService($dependencies);
        $factory->getTidyCandidates(true, true, true);

        $this->assertStringContainsString('SELECT `Photo` AS mediaId FROM `dataset_3`', $queries[1]);
        $this->assertStringContainsString('SELECT `Logo (main)` AS mediaId FROM `dataset_4`', $queries[1]);
        $this->assertStringContainsString('SELECT `a``b` AS mediaId FROM `dataset_5`', $queries[1]);
    }
}
