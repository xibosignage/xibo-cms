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

import type { StatsFilter, StatsReportConfig, StatsSelectOption } from '../shared/types';

const INITIAL_FILTER_STATE: StatsFilter = {
  reportFilter: 'today',
  type: 'layout',
  layoutId: null,
  mediaId: null,
  eventTag: '',
  groupByFilter: 'byday',
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

const GROUP_BY_DAY: StatsSelectOption = { value: 'byday', label: 'Day' };
const GROUP_BY_WEEK: StatsSelectOption = { value: 'byweek', label: 'Week' };
const GROUP_BY_MONTH: StatsSelectOption = { value: 'bymonth', label: 'Month' };

function getGroupByOptions(reportFilter: string): StatsSelectOption[] {
  if (reportFilter.startsWith('range:') || reportFilter === '') {
    return [GROUP_BY_DAY, GROUP_BY_WEEK, GROUP_BY_MONTH];
  }
  if (reportFilter === 'thismonth' || reportFilter === 'lastmonth') {
    return [GROUP_BY_DAY, GROUP_BY_WEEK];
  }
  if (reportFilter === 'thisyear' || reportFilter === 'lastyear') {
    return [GROUP_BY_DAY, GROUP_BY_WEEK, GROUP_BY_MONTH];
  }
  return [];
}

function defaultGroupBy(reportFilter: string): string {
  if (reportFilter === 'thisyear' || reportFilter === 'lastyear') {
    return 'bymonth';
  }
  return 'byday';
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

export const SUMMARY_CONFIG: StatsReportConfig = {
  reportName: 'summaryReport',
  title: 'Summary by Layout, Media or Event',
  chartType: 'line',
  scheduleTitle: 'Schedule Summary Report',
  tableStateKey: 'summary_report_page',
  initialFilter: INITIAL_FILTER_STATE,
  dateRangeOptions: DATE_RANGE_OPTIONS,
  typeOptions: TYPE_OPTIONS,
  getGroupByOptions,
  defaultGroupBy,
  shouldShowDataWarning,
};
