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

import type { ApiRequestsResponse, ApiRequestsRow } from '@/services/apiRequestsApi';
import type { BandwidthReportResponse, BandwidthTableRow } from '@/services/bandwidthReportApi';
import type { DisplayAlertsResponse, DisplayAlertsRow } from '@/services/displayAlertsApi';
import type { LibraryUsageResponse, LibraryUsageTableRow } from '@/services/libraryUsageApi';
import type { ProofOfPlayResponse, ProofOfPlayRow } from '@/services/proofOfPlayApi';
import type { SessionHistoryResponse, SessionHistoryRow } from '@/services/sessionHistoryApi';
import type { SspActivityResponse, SspActivityRow } from '@/services/sspActivityApi';
import type { StatsReportResponse, StatsReportTableRow } from '@/services/statsReportApi';
import type { TimeConnectedResponse } from '@/services/timeConnectedApi';
import type {
  TimeDisconnectedSummaryResponse,
  TimeDisconnectedSummaryRow,
} from '@/services/timeDisconnectedSummaryApi';

// Two rows per report plus an empty one; every first row names Lobby Screen.

export const settingsQueryKey = (settingsKey: string) => ['userPref', settingsKey] as const;

export const PERIOD = { periodStart: '2027-01-01 00:00', periodEnd: '2027-01-07 23:59' };

// --- Summary / Distribution ---------------------------------------------------
export const buildStatsRow = (o: Partial<StatsReportTableRow> = {}): StatsReportTableRow => ({
  label: 'Mon',
  duration: 120,
  count: 4,
  ...o,
});
export const STATS_RESPONSE: StatsReportResponse = {
  metadata: { ...PERIOD, type: 'layout', subject: 'Lobby Layout' },
  table: [buildStatsRow(), buildStatsRow({ label: 'Tue', duration: 9, count: 30 })],
  recordsTotal: 2,
};
export const STATS_EMPTY: StatsReportResponse = { ...STATS_RESPONSE, table: [], recordsTotal: 0 };

// --- Proof of Play ------------------------------------------------------------
export const buildPopRow = (o: Partial<ProofOfPlayRow> = {}): ProofOfPlayRow => ({
  type: 'layout',
  displayId: 1,
  display: 'Lobby Screen',
  layoutId: 301,
  layout: 'Lobby Layout',
  parentCampaignId: 0,
  parentCampaign: '',
  widgetId: 0,
  media: '',
  tag: '',
  numberPlays: 3,
  duration: 90061, // 1 day 1 hr 1 min 1 sec
  minStart: '2027-01-01 00:00',
  maxEnd: '2027-01-01 01:00',
  mediaId: 0,
  ...o,
});
export const POP_RESPONSE: ProofOfPlayResponse = {
  metadata: PERIOD,
  table: [
    buildPopRow(),
    buildPopRow({
      type: 'media',
      displayId: 2,
      display: 'Back Office',
      duration: 59,
      numberPlays: 12,
    }),
  ],
  recordsTotal: 2,
};
export const POP_EMPTY: ProofOfPlayResponse = { ...POP_RESPONSE, table: [], recordsTotal: 0 };

// --- Bandwidth ----------------------------------------------------------------
export const buildBandwidthRow = (o: Partial<BandwidthTableRow> = {}): BandwidthTableRow => ({
  label: 'Lobby Screen',
  bandwidth: 5,
  unit: 'M',
  deleted: false,
  ...o,
});
export const BANDWIDTH_RESPONSE: BandwidthReportResponse = {
  metadata: PERIOD,
  table: [
    buildBandwidthRow(),
    buildBandwidthRow({ label: 'Old Screen', bandwidth: 3, deleted: true }),
  ],
  recordsTotal: 2,
};
export const BANDWIDTH_EMPTY: BandwidthReportResponse = {
  ...BANDWIDTH_RESPONSE,
  table: [],
  recordsTotal: 0,
};

// --- Time Connected -----------------------------------------------------------
// Lobby Screen is 100% in both periods; Back Office is 50% then 0% (25% overall).
export const TIME_CONNECTED_RESPONSE: TimeConnectedResponse = {
  table: {
    timeConnected: [
      {
        '2027-01-01': {
          '1': { label: '1 Jan', percent: 100 },
          '2': { label: '1 Jan', percent: 50 },
        },
        '2027-01-02': {
          '1': { label: '2 Jan', percent: 100 },
          '2': { label: '2 Jan', percent: 0 },
        },
      },
    ],
    displays: [{ '1': 'Lobby Screen', '2': 'Back Office' }],
    displayMeta: { '1': { lastAccessed: '2027-01-02 09:00' }, '2': { lastAccessed: null } },
  },
  metadata: PERIOD,
} as unknown as TimeConnectedResponse;
export const TIME_CONNECTED_EMPTY = {
  table: { timeConnected: [], displays: [], displayMeta: {} },
  metadata: PERIOD,
} as unknown as TimeConnectedResponse;

// --- Time Connected Summary ---------------------------------------------------
export const buildTdsRow = (
  o: Partial<TimeDisconnectedSummaryRow> = {},
): TimeDisconnectedSummaryRow => ({
  displayId: 1,
  display: 'Lobby Screen',
  displayGroupId: 201,
  displayGroup: 'Ground Floor',
  timeDisconnected: 2,
  timeConnected: 22,
  avgTimeDisconnected: 1,
  avgTimeConnected: 11,
  availabilityPercentage: '91.67',
  postUnits: 'hrs',
  ...o,
});
export const TDS_RESPONSE: TimeDisconnectedSummaryResponse = {
  metadata: PERIOD,
  table: [
    buildTdsRow(),
    buildTdsRow({ displayId: 2, display: 'Back Office', timeDisconnected: 20, timeConnected: 4 }),
  ],
  recordsTotal: 2,
};
export const TDS_EMPTY: TimeDisconnectedSummaryResponse = {
  ...TDS_RESPONSE,
  table: [],
  recordsTotal: 0,
};

// --- Display Alerts -----------------------------------------------------------
// Epoch seconds: 2027-01-01 00:00 → 01:30 UTC (1 h 30 min).
export const buildAlertRow = (o: Partial<DisplayAlertsRow> = {}): DisplayAlertsRow => ({
  displayId: 1,
  display: 'Lobby Screen',
  start: '1798761600',
  end: '1798767000',
  eventTypeId: 1,
  refId: 0,
  detail: 'Display went offline',
  eventType: 'Display Up/down',
  ...o,
});
export const ALERTS_RESPONSE: DisplayAlertsResponse = {
  metadata: PERIOD,
  table: [
    buildAlertRow(),
    buildAlertRow({
      displayId: 2,
      display: 'Back Office',
      eventTypeId: 2,
      eventType: 'App Start',
      end: '',
    }),
  ],
  recordsTotal: 2,
};
export const ALERTS_EMPTY: DisplayAlertsResponse = {
  ...ALERTS_RESPONSE,
  table: [],
  recordsTotal: 0,
};

// --- Library Usage ------------------------------------------------------------
// bytesUsed differs in order from the formatted text, so a text sort would be wrong.
export const buildLibraryRow = (o: Partial<LibraryUsageTableRow> = {}): LibraryUsageTableRow => ({
  userId: 1,
  userName: 'alice',
  bytesUsed: 1610612736, // 1.5 GiB
  bytesUsedFormatted: '1.5 GiB',
  numFiles: 40,
  ...o,
});
export const LIBRARY_RESPONSE: LibraryUsageResponse = {
  metadata: PERIOD,
  table: [
    buildLibraryRow(),
    buildLibraryRow({
      userId: 2,
      userName: 'bob',
      bytesUsed: 943718400,
      bytesUsedFormatted: '900 MiB',
      numFiles: 7,
    }),
  ],
  recordsTotal: 2,
  chart: {
    User_Percentage_Usage: {
      type: 'pie',
      data: { labels: ['alice', 'bob'], datasets: [{ backgroundColor: [], data: [63, 37] }] },
    },
    Library_Usage: {
      type: 'pie',
      data: { labels: ['Used', 'Free'], datasets: [{ backgroundColor: [], data: [40, 60] }] },
    },
  },
};
export const LIBRARY_EMPTY: LibraryUsageResponse = {
  ...LIBRARY_RESPONSE,
  table: [],
  recordsTotal: 0,
  chart: {
    User_Percentage_Usage: {
      type: 'pie',
      data: { labels: [], datasets: [{ backgroundColor: [], data: [] }] },
    },
    Library_Usage: {
      type: 'pie',
      data: { labels: [], datasets: [{ backgroundColor: [], data: [] }] },
    },
  },
};

// --- Session History ----------------------------------------------------------
export const buildSessionRow = (o: Partial<SessionHistoryRow> = {}): SessionHistoryRow => ({
  sessionHistoryId: '9',
  startTime: '2027-01-01 09:00',
  lastUsedTime: '2027-01-01 10:00',
  duration: '1 hour',
  userId: 1,
  userName: 'alice',
  userType: 'Super Admin',
  ipAddress: '10.0.0.1',
  userAgent: 'Firefox',
  logDate: '2027-01-01 09:30',
  message: 'Saved layout',
  objectAfter: '{"name":"Lobby Layout"}',
  entity: 'Layout',
  entityId: 301,
  ...o,
});
export const SESSION_RESPONSE: SessionHistoryResponse = {
  metadata: PERIOD,
  table: [
    buildSessionRow(),
    buildSessionRow({ sessionHistoryId: '10', userName: 'bob', userId: 2, objectAfter: '' }),
  ],
  recordsTotal: 2,
};
export const SESSION_EMPTY: SessionHistoryResponse = {
  ...SESSION_RESPONSE,
  table: [],
  recordsTotal: 0,
};

// --- API Request History ------------------------------------------------------
export const buildApiRow = (o: Partial<ApiRequestsRow> = {}): ApiRequestsRow => ({
  logDate: '2027-01-01 09:30',
  startTime: '2027-01-01 09:30',
  userName: 'alice',
  userId: 1,
  applicationName: 'Xibo App',
  requestId: '77',
  method: 'PUT',
  url: '/layout/301',
  entity: 'Layout',
  entityId: 301,
  message: 'Saved layout',
  objectAfter: '{"name":"Lobby Layout"}',
  type: 'INFO',
  ...o,
});
export const API_RESPONSE: ApiRequestsResponse = {
  metadata: { ...PERIOD, logType: 'audit' },
  table: [
    buildApiRow(),
    buildApiRow({ requestId: '78', userName: 'bob', userId: 2, objectAfter: '' }),
  ],
  recordsTotal: 2,
};
export const API_EMPTY: ApiRequestsResponse = { ...API_RESPONSE, table: [], recordsTotal: 0 };

// --- SSP Activity -------------------------------------------------------------
export const SSP_CONNECTOR_ID = 5;
export const SSP_PARTNERS = { partnerA: { name: 'Partner A' }, partnerB: { name: 'Partner B' } };
export const buildSspRow = (o: Partial<SspActivityRow> = {}): SspActivityRow => ({
  scheduledAt: '2027-01-01T09:15:00Z',
  campaignId: 'camp-1',
  displayId: 1,
  isPlayed: true,
  isErrored: false,
  impressions: 2,
  impressionDate: '2027-01-01T09:16:00Z',
  impressionActual: 2,
  errors: 0,
  errorDate: null,
  errorCode: null,
  ...o,
});
export const SSP_RESPONSE: SspActivityResponse = {
  success: true,
  data: [
    buildSspRow(),
    buildSspRow({
      isPlayed: false,
      isErrored: true,
      errors: 1,
      errorCode: 'E42',
      impressions: 0,
      impressionActual: 0,
    }),
  ],
  recordsTotal: 2,
};
export const SSP_EMPTY: SspActivityResponse = { success: true, data: [], recordsTotal: 0 };
