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

namespace Xibo\Tests\Controller;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\ServerRequest as PsrServerRequest;
use PHPUnit\Framework\TestCase;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Xibo\Controller\SavedReport;
use Xibo\Entity\SavedReport as SavedReportEntity;
use Xibo\Entity\User;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\BrandServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Only a user who can edit a saved report can convert it.
 */
class SavedReportConvertPermissionTest extends TestCase
{
    public function testAUserWhoCannotEditTheReportCannotConvertIt()
    {
        $savedReport = $this->createMock(SavedReportEntity::class);
        $savedReport->schemaVersion = 1;
        $savedReport->expects($this->never())->method('save');

        $savedReportFactory = $this->createMock(SavedReportFactory::class);
        $savedReportFactory->method('getById')->with(3)->willReturn($savedReport);

        $reportService = $this->createMock(ReportServiceInterface::class);
        $reportService->expects($this->never())->method('convertSavedReportResults');

        $user = $this->createMock(User::class);
        $user->method('checkEditable')->with($savedReport)->willReturn(false);

        $base = $this->createMock(BaseDependenciesService::class);
        $base->method('getUser')->willReturn($user);
        $base->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));

        $controller = new SavedReport(
            $reportService,
            $this->createMock(ReportScheduleFactory::class),
            $savedReportFactory,
            $this->createMock(MediaFactory::class),
            $this->createMock(UserFactory::class),
            $this->createMock(BrandServiceInterface::class)
        );
        $controller->useBaseDependenciesService($base);

        $this->expectException(AccessDeniedException::class);
        $controller->savedReportConvert(
            new ServerRequest(new PsrServerRequest('GET', '/savereport/3/report/proofofplay/convert')),
            new Response(new PsrResponse(), new HttpFactory()),
            3,
            'proofofplay'
        );
    }
}
