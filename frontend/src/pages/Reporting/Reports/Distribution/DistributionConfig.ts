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

import type { StatsFilter, StatsReportConfig, StatsSelectOption } from '../shared/types';

const INITIAL_FILTER_STATE: StatsFilter = {
  reportFilter: 'today',
  type: 'layout',
  layoutId: null,
  mediaId: null,
  eventTag: '',
  groupByFilter: 'byhour',
  displayId: null,
  displayGroupId: [],
};

const DATE_RANGE_OPTIONS: StatsSelectOption[] = [
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'thisweek', label: 'This Week' },
  { value: 'thismonth', label: 'This Month' },
  { value: 'thisyear', label: 'This Year' },
  { value: 'lastweek', label: 'Last Week' },
  { value: 'lastmonth', label: 'Last Month' },
  { value: 'lastyear', label: 'Last Year' },
];

const TYPE_OPTIONS: StatsSelectOption[] = [
  { value: 'layout', label: 'Layout' },
  { value: 'media', label: 'Media' },
  { value: 'event', label: 'Event' },
];

const GROUP_BY_OPTIONS: StatsSelectOption[] = [
  { value: 'byhour', label: 'Hour' },
  { value: 'bydayofweek', label: 'Day of the week' },
  { value: 'bydayofmonth', label: 'Day of the month' },
];

function getGroupByOptions(): StatsSelectOption[] {
  return GROUP_BY_OPTIONS;
}

function defaultGroupBy(): string {
  return 'byhour';
}

function shouldShowDataWarning(reportFilter: string): boolean {
  if (reportFilter.startsWith('range:')) {
    const [from, to] = reportFilter.replace('range:', '').split('|');
    if (from && to) {
      const days = (new Date(to).getTime() - new Date(from).getTime()) / 86400000;
      return days >= 30;
    }
    return false;
  }
  return ['thismonth', 'lastmonth', 'thisyear', 'lastyear'].includes(reportFilter);
}

export const DISTRIBUTION_CONFIG: StatsReportConfig = {
  reportName: 'distributionReport',
  title: 'Distribution by Layout, Media or Event',
  chartType: 'line',
  scheduleTitle: 'Schedule Distribution Report',
  tableStateKey: 'distribution_report_page',
  initialFilter: INITIAL_FILTER_STATE,
  dateRangeOptions: DATE_RANGE_OPTIONS,
  typeOptions: TYPE_OPTIONS,
  scheduleGroupByOptions: GROUP_BY_OPTIONS,
  getGroupByOptions,
  defaultGroupBy,
  shouldShowDataWarning,
};
