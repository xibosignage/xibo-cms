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

import type { SavedReportData } from '@/services/savedReportApi';

// Charts use the Chart.js v2 shape the PHP report classes store.
export const buildSavedReportData = (
  overrides: Partial<SavedReportData> = {},
): SavedReportData => ({
  recordsTotal: 0,
  chart: null,
  table: [],
  metadata: null,
  error: null,
  ...overrides,
});

export const PERIOD_METADATA = { periodStart: '2027-01-01 00:00', periodEnd: '2027-01-07 23:59' };

// 90061 s = 1 day 1 hr 1 min 1 sec, hits every formatter unit.
export const POP_DATA = buildSavedReportData({
  table: [
    {
      type: 'layout',
      displayId: 1,
      display: 'Lobby Screen',
      parentCampaign: 'Spring',
      layoutId: 5,
      layout: 'Main Layout',
      widgetId: 0,
      media: '',
      tag: '',
      numberPlays: 3,
      duration: 90061,
      minStart: '2027-01-01 00:00',
      maxEnd: '2027-01-01 01:00',
    },
  ],
  metadata: PERIOD_METADATA,
});

// Two axes: duration bars and a count line (SummaryReport / DistributionReport).
export const SUMMARY_CHART = {
  type: 'bar',
  data: {
    labels: ['Mon', 'Tue'],
    datasets: [
      { label: 'Total Duration', data: [10, 20], yAxisID: 'Duration' },
      { label: 'Total Count', data: [1, 2], yAxisID: 'Count', type: 'line' },
    ],
  },
};

export const SUMMARY_DATA = buildSavedReportData({
  table: [
    { label: 'Mon', duration: 10, count: 1 },
    { label: 'Tue', duration: 20, count: 2 },
  ],
  chart: SUMMARY_CHART,
  metadata: PERIOD_METADATA,
});

// Stacked bars, stored Downtime first (TimeDisconnectedSummary).
export const TDS_CHART = {
  type: 'bar',
  data: {
    labels: ['Lobby Screen'],
    datasets: [
      { label: 'Downtime', data: [2], backgroundColor: 'rgb(255,0,0)' },
      { label: 'Uptime', data: [22], backgroundColor: 'rgb(0,255,0)' },
    ],
  },
  options: { scales: { xAxes: [{ stacked: true }], yAxes: [{ stacked: true }] } },
};

export const TDS_DATA = buildSavedReportData({
  table: [
    {
      displayId: 1,
      display: 'Lobby Screen',
      timeDisconnected: 2,
      timeConnected: 22,
      postUnits: 'hrs',
    },
  ],
  chart: TDS_CHART,
});

// Plain bars with a per-bar colour array (Bandwidth highlights deleted displays).
export const BANDWIDTH_CHART = {
  type: 'bar',
  data: {
    labels: ['Lobby Screen', 'Old Screen'],
    datasets: [{ label: 'Bandwidth', data: [5, 3], backgroundColor: ['#0e70f6', '#ff0000'] }],
  },
};

export const BANDWIDTH_DATA = buildSavedReportData({
  table: [
    { label: 'Lobby Screen', bandwidth: 5, unit: 'MB' },
    { label: 'Old Screen', bandwidth: 3, unit: 'MB' },
  ],
  chart: BANDWIDTH_CHART,
});

// Library Usage stores two named charts rather than one config.
export const LIBRARY_USAGE_CHART = {
  User_Percentage_Usage: {
    type: 'pie',
    data: { labels: ['TestUser'], datasets: [{ data: [100] }] },
  },
  Library_Usage: {
    type: 'pie',
    data: { labels: ['Used', 'Free'], datasets: [{ data: [40, 60] }] },
  },
};

export const LIBRARY_USAGE_DATA = buildSavedReportData({
  table: [{ userId: 1, userName: 'TestUser', bytesUsedFormatted: '40 MiB', numFiles: 4 }],
  chart: LIBRARY_USAGE_CHART,
});

export const EMPTY_LIBRARY_USAGE_CHART = {
  User_Percentage_Usage: { type: 'pie', data: { labels: [], datasets: [{ data: [] }] } },
  Library_Usage: { type: 'pie', data: { labels: [], datasets: [{ data: [] }] } },
};

// API Request History — the column set follows metadata.logType.
const apiRow = {
  logDate: '2027-01-01 10:00',
  startTime: '2027-01-01 10:00',
  userName: 'TestUser',
  userId: 1,
  applicationName: 'Xibo',
  requestId: 77,
  method: 'PUT',
  url: '/layout/5',
  entity: 'Layout',
  entityId: 5,
  message: 'Saved',
  objectAfter: '{"name":"Main"}',
  type: 'INFO',
};
export const API_AUDIT_DATA = buildSavedReportData({
  table: [apiRow],
  metadata: { logType: 'audit' },
});
export const API_DEBUG_DATA = buildSavedReportData({
  table: [apiRow],
  metadata: { logType: 'debug' },
});
export const API_REQUESTS_DATA = buildSavedReportData({
  table: [apiRow],
  metadata: { logType: 'requests' },
});

// Session History — the column set follows metadata.type.
const sessionRow = {
  logDate: '2027-01-01 10:00',
  userName: 'TestUser',
  userId: 1,
  ipAddress: '10.0.0.1',
  sessionHistoryId: 9,
  entity: 'Layout',
  entityId: 5,
  message: 'Saved &amp; closed',
  objectAfter: { name: 'Main' },
  channel: 'WEB',
  function: 'edit',
  type: 'INFO',
  page: '/layout',
};
export const SESSION_AUDIT_DATA = buildSavedReportData({
  table: [sessionRow],
  metadata: { type: 'audit' },
});
export const SESSION_DEBUG_DATA = buildSavedReportData({
  table: [sessionRow],
  metadata: { type: 'debug' },
});
export const SESSION_DATA = buildSavedReportData({
  table: [sessionRow],
  metadata: { type: 'session' },
});

// Time Connected stores an object, not a row array.
export const TIME_CONNECTED_TABLE = {
  timeConnected: [
    {
      '2027-01-01': { '1': { label: '1 Jan', percent: 100 }, '2': { label: '1 Jan', percent: 50 } },
      '2027-01-02': { '1': { label: '2 Jan', percent: 100 }, '2': { label: '2 Jan', percent: 0 } },
    },
  ],
  displays: [{ '1': 'Lobby Screen', '2': 'Back Office' }],
  displayMeta: {},
};

export const TIME_CONNECTED_DATA = buildSavedReportData({
  table: TIME_CONNECTED_TABLE as unknown as SavedReportData['table'],
});

export const EMPTY_TIME_CONNECTED_TABLE = { timeConnected: [], displays: [], displayMeta: {} };

export const UNKNOWN_REPORT_DATA = buildSavedReportData({ table: [{ alpha: 1, beta: 'x' }] });
