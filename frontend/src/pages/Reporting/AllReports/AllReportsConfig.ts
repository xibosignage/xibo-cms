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

import type { TFunction } from 'i18next';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';

export interface ReportFilterInput {
  name: string;
  reportType: string;
  actionType: string;
}

export const INITIAL_FILTER_STATE: ReportFilterInput = {
  name: '',
  reportType: '',
  actionType: '',
};

export const getFilterKeys = (t: TFunction): FilterConfigItem<ReportFilterInput>[] => [
  {
    label: t('Name'),
    name: 'name',
    type: 'text',
    placeholder: ' ',
  },
  {
    label: t('Category'),
    name: 'reportType',
    type: 'select',
    options: [
      { label: t('Audit'), value: 'Audit' },
      { label: t('Display'), value: 'Display' },
      { label: t('Library'), value: 'Library' },
      { label: t('Proof of Play'), value: 'Proof of Play' },
    ],
  },
  {
    label: t('Action Type'),
    name: 'actionType',
    type: 'select',
    options: [
      { label: t('Report'), value: 'Report' },
      { label: t('Chart'), value: 'Chart' },
      { label: t('Export'), value: 'Export' },
    ],
  },
];
