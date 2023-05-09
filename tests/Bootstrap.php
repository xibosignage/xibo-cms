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

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

require_once PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/tests/LocalWebTestCase.php';
require_once PROJECT_ROOT . '/tests/XmdsTestCase.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php'))
    die('Not configured');

\Xibo\Tests\LocalWebTestCase::setEnvironment();

\Xibo\Helper\Translate::InitLocale(null, 'en_GB');