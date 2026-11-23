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
