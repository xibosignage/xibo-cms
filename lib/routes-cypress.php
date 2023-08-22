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

use Slim\Routing\RouteCollectorProxy;
use Xibo\Middleware\FeatureAuth;
use Xibo\Middleware\LayoutLock;
use Xibo\Middleware\SuperAdminAuth;

defined('XIBO') or die('Sorry, you are not allowed to directly access this page.');


/**
 * Cypress endpoints
 * @SWG\Tag(
 *  name="cypress",
 *  description="Cypress endpoints for tests"
 * )
 */

$app->post('/createCommand', ['\Xibo\Controller\CypressTest','createCommand']);
$app->post('/createCampaign', ['\Xibo\Controller\CypressTest','createCampaign']);
$app->post('/scheduleCampaign', ['\Xibo\Controller\CypressTest','scheduleCampaign']);
$app->post('/displaySetStatus', ['\Xibo\Controller\CypressTest','displaySetStatus']);
$app->get('/displayStatusEquals', ['\Xibo\Controller\CypressTest','displayStatusEquals']);
