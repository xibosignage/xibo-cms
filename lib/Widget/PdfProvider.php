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
                try {
                    $sourceFile = $widget->getPrimaryMediaPath();

                    $this->getLog()->debug('fetchDuration: loading PDF file to get the number of pages, file: '
                        . $sourceFile);

                    // Try mPdf/FPDI first (works for unencrypted PDFs)
                    try {
                        $mPdf = new Mpdf([
                            'tempDir' => $widget->getLibraryTempPath(),
                        ]);
                        $pageCount = $mPdf->setSourceFile($sourceFile);
                    } catch (\Exception $fpdiException) {
                        // FPDI fails on encrypted/password-protected PDFs.
                        // Fall back to counting page objects in the raw PDF.
                        $this->getLog()->info('fetchDuration: FPDI failed (' . $fpdiException->getMessage()
                            . '), trying raw page count for: ' . $sourceFile);
                        $pageCount = $this->countPdfPages($sourceFile);
                    }

                    if ($pageCount >= 1) {
                        $widget->setOptionValue('pageCount', 'attrib', $pageCount);
                    }
                } catch (\Exception $e) {
                    $this->getLog()->error('fetchDuration: unable to get PDF page count, e: ' . $e->getMessage());
                }
            } else {
                $pageCount = $cachedPageCount;
            }

            $durationProvider->setDuration($durationProvider->getWidget()->calculatedDuration * $pageCount);
        }
        return $this;
    }

    /**
     * Count pages in a PDF by parsing its raw bytes.
     * Works with encrypted/password-protected PDFs where FPDI fails.
     */
    private function countPdfPages(string $filePath): int
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return 1;
        }

        // Method 1: Count /Type /Page leaf nodes (not /Type /Pages tree nodes)
        $count = preg_match_all('/\/Type\s*\/Page[^s]/i', $content);
        if ($count > 0) {
            return $count;
        }

        // Method 2: Find /Count N in page tree root (largest value = total pages)
        if (preg_match_all('/\/Count\s+(\d+)/', $content, $matches)) {
            return (int)max($matches[1]);
        }

        // Method 3: Use qpdf if available (handles all encryption types)
        $qpdfPath = trim(@shell_exec('which qpdf 2>/dev/null') ?? '');
        if (!empty($qpdfPath)) {
            $result = trim(@shell_exec($qpdfPath . ' --show-npages ' . escapeshellarg($filePath) . ' 2>/dev/null') ?? '');
            if (is_numeric($result) && (int)$result > 0) {
                return (int)$result;
            }
        }

        return 1;
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
