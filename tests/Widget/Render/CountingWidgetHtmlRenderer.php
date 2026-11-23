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

namespace Xibo\Tests\Widget\Render;

use Xibo\Entity\Region;
use Xibo\Widget\Render\WidgetHtmlRenderer;

/**
 * Counts renders instead of rendering.
 */
class CountingWidgetHtmlRenderer extends WidgetHtmlRenderer
{
    public int $renders = 0;

    protected function render(int $widgetId, Region $region, array $widgets, array $moduleTemplates): string
    {
        return 'render ' . ++$this->renders;
    }
}
