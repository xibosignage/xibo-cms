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

import { getBucketFilterParams } from './OverviewConfig';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { FetchDisplaysRequest } from '@/services/displaysApi';
import type { DisplayOverviewBucket } from '@/types/displayOverview';

export interface OverviewFilterState {
  display: string | null;
  displayGroupId: string | null;
  healthStatus: string | null;
  authorised: string | null;
  lastAccessed: string | null;
}

export const INITIAL_OVERVIEW_FILTER_STATE: OverviewFilterState = {
  display: null,
  displayGroupId: null,
  healthStatus: null,
  authorised: null,
  lastAccessed: null,
};

// Mirrors the filter panel from the Display Management reference mock
// (display-management.html) — a display group picker, a combined Health
// status field (backed by the same bucket params the KPI tiles used to drive
// by click), Authorised, and a last-accessed cutoff. Name search lives in the
// page's own quick search box (Overview.tsx) instead of here, so there's only
// ever one control writing to `display`.
export function getOverviewFilterKeys(t: TFunction): FilterConfigItem<OverviewFilterState>[] {
  return [
    {
      label: t('Display Group'),
      name: 'displayGroupId',
      placeholder: t('All'),
      options: [],
    },
    {
      label: t('Health Status'),
      name: 'healthStatus',
      placeholder: t('All'),
      options: [
        { label: t('All'), value: '' },
        { label: t('Online'), value: 'online' },
        { label: t('Needs Attention'), value: 'needsAttention' },
        { label: t('Offline'), value: 'offline' },
        { label: t('Faults'), value: 'faults' },
      ],
    },
    {
      label: t('Authorised'),
      name: 'authorised',
      placeholder: t('All'),
      options: [
        { label: t('All'), value: '' },
        { label: t('Yes'), value: '1' },
        { label: t('No'), value: '0' },
      ],
    },
    {
      label: t('Last Accessed After'),
      name: 'lastAccessed',
      type: 'date',
    },
  ];
}

export function getOverviewFilterQueryParams(
  filters: OverviewFilterState,
): Partial<FetchDisplaysRequest> {
  return {
    ...getBucketFilterParams((filters.healthStatus as DisplayOverviewBucket | null) || null),
    display: filters.display || undefined,
    displayGroupId: filters.displayGroupId || undefined,
    authorised: filters.authorised ?? undefined,
    lastAccessed: filters.lastAccessed || undefined,
  };
}
