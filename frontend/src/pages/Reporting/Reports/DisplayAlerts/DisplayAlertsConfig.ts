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

export type DisplayAlertsFilter = {
  reportFilter: string;
  eventType: string;
  displayId: number | null;
  displayGroupId: number[];
  tags: Tag[];
  exactTags: boolean;
  logicalOperator: string;
  onlyLoggedIn: boolean;
};

export const INITIAL_FILTER_STATE: DisplayAlertsFilter = {
  reportFilter: 'lastweek',
  eventType: '',
  displayId: null,
  displayGroupId: [],
  tags: [],
  exactTags: false,
  logicalOperator: 'OR',
  onlyLoggedIn: false,
};

export const ACTIVE_FILTER_KEYS: (keyof DisplayAlertsFilter)[] = [
  'reportFilter',
  'eventType',
  'displayId',
  'displayGroupId',
  'tags',
  'onlyLoggedIn',
];

export const EVENT_TYPE_OPTIONS = [
  { value: '1', label: 'Display Up/down' },
  { value: '2', label: 'App Start' },
  { value: '3', label: 'Power Cycle' },
  { value: '4', label: 'Network Cycle' },
  { value: '5', label: 'TV Monitoring' },
  { value: '6', label: 'Player Fault' },
  { value: '7', label: 'Command' },
  { value: '8', label: 'Other' },
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
