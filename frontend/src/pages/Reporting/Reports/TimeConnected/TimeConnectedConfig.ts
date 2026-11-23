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

export type GroupByFilter = 'byhour' | 'bydayofmonth';
export type SortBy = 'uptime_desc' | 'uptime_asc';

export type TimeConnectedFilter = {
  reportFilter: string;
  fromDt: string | null;
  toDt: string | null;
  groupByFilter: GroupByFilter;
  displaySpecificGroupIds: number[];
  displayGroupIds: number[];
  sortBy: SortBy;
};

export const INITIAL_FILTER_STATE: TimeConnectedFilter = {
  reportFilter: 'today',
  fromDt: null,
  toDt: null,
  groupByFilter: 'bydayofmonth',
  displaySpecificGroupIds: [],
  displayGroupIds: [],
  sortBy: 'uptime_desc',
};

export const ACTIVE_FILTER_KEYS: (keyof TimeConnectedFilter)[] = [
  'reportFilter',
  'fromDt',
  'toDt',
  'displaySpecificGroupIds',
  'displayGroupIds',
];

export const DATE_RANGE_OPTIONS = [
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'lastweek', label: 'Last Week' },
  { value: 'lastmonth', label: 'Last Month' },
  { value: 'lastyear', label: 'Last Year' },
];

export const GROUP_BY_OPTIONS = [
  { value: 'bydayofmonth', label: 'Day of Month' },
  { value: 'byhour', label: 'Hour' },
];

export const SORT_BY_OPTIONS = [
  { value: 'uptime_desc', label: 'Uptime (high to low)' },
  { value: 'uptime_asc', label: 'Uptime (low to high)' },
];

export type DisplayReportRow = {
  displayId: number;
  displayName: string;
  lastAccessed: string | null;
  periods: Array<{ label: string; percent: number }>;
  uptimePercent: number;
};
