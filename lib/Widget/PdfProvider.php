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

namespace Xibo\Widget;

use Carbon\Carbon;
use Mpdf\Mpdf;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * PDF provider to calculate the duration if durationIsPerItem is selected.
 */
class PdfProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        return $this;
    }

    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        $widget = $durationProvider->getWidget();
        if ($widget->getOptionValue('durationIsPerItem', 0) == 1) {
            // Do we already have an option stored for the number of pages?
            $pageCount = 1;
            $cachedPageCount = $widget->getOptionValue('pageCount', null);
            if ($cachedPageCount === null) {
                $sourceFile = null;
                try {
                    $sourceFile = $widget->getPrimaryMediaPath();

                    $this->getLog()->debug('fetchDuration: loading PDF file to get the number of pages, file: '
                        . $sourceFile);

                    $mPdf = new Mpdf([
                        'tempDir' => $widget->getLibraryTempPath(),
                    ]);
                    $pageCount = $mPdf->setSourceFile($sourceFile);

                    $widget->setOptionValue('pageCount', 'attrib', $pageCount);
                } catch (\Exception $e) {
                    $this->getLog()->error('fetchDuration: unable to get PDF page count, e: ' . $e->getMessage());
                } finally {
                    if ($sourceFile !== null) {
                        $widget->releasePrimaryMediaPath($sourceFile);
                    }
                }
            } else {
                $pageCount = $cachedPageCount;
            }

            $durationProvider->setDuration($durationProvider->getWidget()->calculatedDuration * $pageCount);
        }
        return $this;
    }

    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string
    {
        return null;
    }

    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        return null;
    }
}
