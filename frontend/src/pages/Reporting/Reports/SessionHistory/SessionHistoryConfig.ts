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

export type SessionHistoryLogType = 'sessions' | 'audit' | 'debug';

export type SessionHistoryFilter = {
  reportFilter: string;
  type: SessionHistoryLogType;
  userId: number | null;
  sessionHistoryId: string;
};

export const INITIAL_FILTER_STATE: SessionHistoryFilter = {
  reportFilter: 'lastweek',
  type: 'sessions',
  userId: null,
  sessionHistoryId: '',
};

export const ACTIVE_FILTER_KEYS: (keyof SessionHistoryFilter)[] = [
  'reportFilter',
  'type',
  'userId',
  'sessionHistoryId',
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

export const TYPE_OPTIONS = [
  { value: 'sessions', label: 'Sessions' },
  { value: 'audit', label: 'Audit' },
  { value: 'debug', label: 'Debug' },
];

export const SCHEDULE_TYPE_OPTIONS = [
  { value: 'audit', label: 'Audit' },
  { value: 'debug', label: 'Debug' },
];
