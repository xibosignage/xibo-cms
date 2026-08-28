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
