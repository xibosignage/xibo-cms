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

import type { Tag } from '@/types/tag';

export type GroupBy = 'display' | 'displayGroup';

export type TimeDisconnectedSummaryFilter = {
  reportFilter: string;
  groupBy: GroupBy;
  displayId: number | null;
  displayGroupId: number[];
  tags: Tag[];
  exactTags: boolean;
  logicalOperator: string;
  onlyLoggedIn: boolean;
};

export const INITIAL_FILTER_STATE: TimeDisconnectedSummaryFilter = {
  reportFilter: 'lastweek',
  groupBy: 'display',
  displayId: null,
  displayGroupId: [],
  tags: [],
  exactTags: false,
  logicalOperator: 'OR',
  onlyLoggedIn: false,
};

export const ACTIVE_FILTER_KEYS: (keyof TimeDisconnectedSummaryFilter)[] = [
  'reportFilter',
  'displayId',
  'displayGroupId',
  'tags',
  'onlyLoggedIn',
];

export const DATE_RANGE_OPTIONS = [
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'thisweek', label: 'This Week' },
  { value: 'thismonth', label: 'This Month' },
  { value: 'thisyear', label: 'This Year' },
  { value: 'lastweek', label: 'Last Week' },
  { value: 'lastmonth', label: 'Last Month' },
  { value: 'lastyear', label: 'Last Year' },
];

export const GROUP_BY_OPTIONS = [
  { value: 'display', label: 'Display' },
  { value: 'displayGroup', label: 'Display Group' },
];
