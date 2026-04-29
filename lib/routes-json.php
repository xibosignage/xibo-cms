<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

/**
 * Routes for the React/JSON frontend only.
 * Loaded by web/json/index.php — NOT exposed via the API entrypoint.
 */

/**
 * Reporting
 */
$app->get('/report/available', ['\Xibo\Controller\Stats', 'availableReports'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['report.view']))
    ->setName('report.available');

$app->get('/stats/export/count', ['\Xibo\Controller\Stats', 'exportStatsCount'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['proof-of-play']))
    ->setName('stats.export.count');
