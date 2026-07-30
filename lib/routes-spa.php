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
 * React SPA shell routes. Loaded by web/index.php.
 *
 * MUST be required after both routes-web.php and routes.php (see the require order in
 * web/index.php). These patterns are deliberately broad ("everything under /library/..."), and
 * FastRoute's variable-route dispatcher matches in registration order — a broad pattern
 * registered before a specific one (e.g. /library/thumbnail/{id}) would silently shadow it with
 * no error raised. Requiring this file last guarantees every real PHP route is already
 * registered, so it always wins over these catch-alls.
 *
 * The section list itself lives on Xibo\Controller\Spa (SECTIONS/SECTIONS_NO_INDEX/
 * STANDALONE_ROUTES) rather than here, so lib/Middleware/State.php can build the exact same
 * publicRoutes patterns from one source instead of a hand-duplicated copy.
 */

use Xibo\Controller\Spa;

foreach (Spa::getRoutePatterns() as $pattern) {
    $app->get($pattern, ['\Xibo\Controller\Spa', 'shell']);
}
