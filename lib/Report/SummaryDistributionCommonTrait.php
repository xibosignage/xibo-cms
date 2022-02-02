<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Report;

use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Common function between the Summary and Distribution reports
 */
trait SummaryDistributionCommonTrait
{
    /** @inheritdoc */
    public function restructureSavedReportOldJson($result)
    {
        $durationData = $result['durationData'];
        $countData = $result['countData'];
        $labels = $result['labels'];
        $backgroundColor = $result['backgroundColor'];
        $borderColor = $result['borderColor'];
        $periodStart = $result['periodStart'];
        $periodEnd = $result['periodEnd'];

        return [
            'hasData' => count($durationData) > 0 && count($countData) > 0,
            'chart' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => __('Total duration'),
                            'yAxisID' => 'Duration',
                            'backgroundColor' => $backgroundColor,
                            'data' => $durationData
                        ],
                        [
                            'label' => __('Total count'),
                            'yAxisID' => 'Count',
                            'borderColor' => $borderColor,
                            'type' => 'line',
                            'fill' => false,
                            'data' =>  $countData
                        ]
                    ]
                ],
                'options' => [
                    'scales' => [
                        'yAxes' => [
                            [
                                'id' => 'Duration',
                                'type' => 'linear',
                                'position' =>  'left',
                                'display' =>  true,
                                'scaleLabel' =>  [
                                    'display' =>  true,
                                    'labelString' => __('Duration(s)')
                                ],
                                'ticks' =>  [
                                    'beginAtZero' => true
                                ]
                            ], [
                                'id' => 'Count',
                                'type' => 'linear',
                                'position' =>  'right',
                                'display' =>  true,
                                'scaleLabel' =>  [
                                    'display' =>  true,
                                    'labelString' => __('Count')
                                ],
                                'ticks' =>  [
                                    'beginAtZero' => true
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,

        ];
    }

    /**
     * @param \Xibo\Support\Sanitizer\SanitizerInterface $sanitizedParams
     * @return array
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function getReportScheduleFormTitle(SanitizerInterface $sanitizedParams): array
    {
        $type = $sanitizedParams->getString('type');
        if ($type == 'layout') {
            $selectedId = $sanitizedParams->getInt('layoutId');
            $title = sprintf(
                __('Add Report Schedule for %s - %s'),
                $type,
                $this->layoutFactory->getById($selectedId)->layout
            );
        } elseif ($type == 'media') {
            $selectedId = $sanitizedParams->getInt('mediaId');
            $title = sprintf(
                __('Add Report Schedule for %s - %s'),
                $type,
                $this->mediaFactory->getById($selectedId)->name
            );
        } elseif ($type == 'event') {
            $selectedId = 0; // we only need eventTag
            $eventTag = $sanitizedParams->getString('eventTag');
            $title = sprintf(
                __('Add Report Schedule for %s - %s'),
                $type,
                $eventTag
            );
        } else {
            throw new InvalidArgumentException(__('Unknown type ') . $type, 'type');
        }
        
        return [
            'title' => $title,
            'selectedId' => $selectedId
        ];
    }
}
