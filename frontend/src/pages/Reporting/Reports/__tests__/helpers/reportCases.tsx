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

import type { UserEvent } from '@testing-library/user-event';
import type { ComponentType } from 'react';
import type { Mock } from 'vitest';

import ApiRequestsHistory from '../../ApiRequestsHistory/ApiRequestsHistory';
import Bandwidth from '../../Bandwidth/Bandwidth';
import DisplayAlerts from '../../DisplayAlerts/DisplayAlerts';
import Distribution from '../../Distribution/Distribution';
import LibraryUsage from '../../LibraryUsage/LibraryUsage';
import ProofOfPlay from '../../ProofOfPlay/ProofOfPlay';
import SessionHistory from '../../SessionHistory/SessionHistory';
import SspActivity from '../../SspActivity/SspActivity';
import Summary from '../../Summary/Summary';
import TimeConnected from '../../TimeConnected/TimeConnected';
import TimeDisconnectedSummary from '../../TimeDisconnectedSummary/TimeDisconnectedSummary';
import {
  ALERTS_EMPTY,
  ALERTS_RESPONSE,
  API_EMPTY,
  API_RESPONSE,
  BANDWIDTH_EMPTY,
  BANDWIDTH_RESPONSE,
  LIBRARY_EMPTY,
  LIBRARY_RESPONSE,
  POP_EMPTY,
  POP_RESPONSE,
  SESSION_EMPTY,
  SESSION_RESPONSE,
  SSP_EMPTY,
  SSP_RESPONSE,
  STATS_EMPTY,
  STATS_RESPONSE,
  TDS_EMPTY,
  TDS_RESPONSE,
  TIME_CONNECTED_EMPTY,
  TIME_CONNECTED_RESPONSE,
} from '../fixtures/reportRows';

import { pickOption } from './renderReportPage';

import { fetchApiRequests } from '@/services/apiRequestsApi';
import { fetchBandwidthReport } from '@/services/bandwidthReportApi';
import { fetchDisplayAlerts } from '@/services/displayAlertsApi';
import { fetchLibraryUsage } from '@/services/libraryUsageApi';
import { fetchProofOfPlay } from '@/services/proofOfPlayApi';
import { fetchSessionHistory } from '@/services/sessionHistoryApi';
import { fetchSspActivity } from '@/services/sspActivityApi';
import { fetchStatsReport } from '@/services/statsReportApi';
import { fetchTimeConnected } from '@/services/timeConnectedApi';
import { fetchTimeDisconnectedSummary } from '@/services/timeDisconnectedSummaryApi';

// One entry per report page; services in `fetch` must be mocked by the caller.
export interface ReportCase {
  name: string;
  route: string;
  Page: ComponentType;
  settingsKey: string;
  fetch: () => Mock;
  rows: unknown;
  empty: unknown;
  resultText: RegExp;
  placeholder: RegExp;
  // Makes Apply usable: Summary/Distribution need an item, SSP needs a display.
  prepare?: (user: UserEvent) => Promise<void>;
  schedule: boolean;
}

const PLACEHOLDER = /set your filters above and click apply to generate the report/i;

const pickLayout = (user: UserEvent) => pickOption(user, /^layout/i, 'Lobby Layout');
const pickDisplay = (user: UserEvent) => pickOption(user, /^display$/i, 'Lobby Screen');

const mocked = (fn: unknown) => () => fn as Mock;

export const REPORT_CASES: ReportCase[] = [
  {
    name: 'Summary',
    route: '/reporting/summary',
    Page: Summary,
    settingsKey: 'summary_report_page',
    fetch: mocked(fetchStatsReport),
    rows: STATS_RESPONSE,
    empty: STATS_EMPTY,
    resultText: /^Tue$/,
    placeholder: /select a type and an item, set your filters, then click apply/i,
    prepare: pickLayout,
    schedule: true,
  },
  {
    name: 'Distribution',
    route: '/reporting/distribution',
    Page: Distribution,
    settingsKey: 'distribution_report_page',
    fetch: mocked(fetchStatsReport),
    rows: STATS_RESPONSE,
    empty: STATS_EMPTY,
    resultText: /^Tue$/,
    placeholder: /select a type and an item, set your filters, then click apply/i,
    prepare: pickLayout,
    schedule: true,
  },
  {
    name: 'Proof of Play',
    route: '/reporting/proof-of-play',
    Page: ProofOfPlay,
    settingsKey: 'proof_of_play_page',
    fetch: mocked(fetchProofOfPlay),
    rows: POP_RESPONSE,
    empty: POP_EMPTY,
    resultText: /^Back Office$/,
    placeholder: PLACEHOLDER,
    schedule: true,
  },
  {
    name: 'Bandwidth',
    route: '/reporting/bandwidth',
    Page: Bandwidth,
    settingsKey: 'bandwidth_report_page',
    fetch: mocked(fetchBandwidthReport),
    rows: BANDWIDTH_RESPONSE,
    empty: BANDWIDTH_EMPTY,
    resultText: /Displays Bandwidth/,
    placeholder: PLACEHOLDER,
    schedule: true,
  },
  {
    name: 'Time Connected',
    route: '/reporting/time-connected',
    Page: TimeConnected,
    settingsKey: 'timeconnected_report_page',
    fetch: mocked(fetchTimeConnected),
    rows: TIME_CONNECTED_RESPONSE,
    empty: TIME_CONNECTED_EMPTY,
    resultText: /^Back Office$/,
    placeholder: PLACEHOLDER,
    schedule: true,
  },
  {
    name: 'Time Connected Summary',
    route: '/reporting/time-connected-summary',
    Page: TimeDisconnectedSummary,
    settingsKey: 'time_disconnected_summary_page',
    fetch: mocked(fetchTimeDisconnectedSummary),
    rows: TDS_RESPONSE,
    empty: TDS_EMPTY,
    resultText: /^Back Office$/,
    placeholder: PLACEHOLDER,
    schedule: true,
  },
  {
    name: 'Display Alerts',
    route: '/reporting/display-alerts',
    Page: DisplayAlerts,
    settingsKey: 'display_alerts_page',
    fetch: mocked(fetchDisplayAlerts),
    rows: ALERTS_RESPONSE,
    empty: ALERTS_EMPTY,
    resultText: /^Back Office$/,
    placeholder: PLACEHOLDER,
    schedule: true,
  },
  {
    name: 'Library Usage',
    route: '/reporting/library-usage',
    Page: LibraryUsage,
    settingsKey: 'libraryusage_report_page',
    fetch: mocked(fetchLibraryUsage),
    rows: LIBRARY_RESPONSE,
    empty: LIBRARY_EMPTY,
    resultText: /^900 MiB$/,
    placeholder: PLACEHOLDER,
    schedule: true,
  },
  {
    name: 'Session History',
    route: '/reporting/session-history',
    Page: SessionHistory,
    settingsKey: 'session_history_page',
    fetch: mocked(fetchSessionHistory),
    rows: SESSION_RESPONSE,
    empty: SESSION_EMPTY,
    resultText: /^bob$/,
    placeholder: PLACEHOLDER,
    schedule: true,
  },
  {
    name: 'API Request History',
    route: '/reporting/api-requests',
    Page: ApiRequestsHistory,
    settingsKey: 'api_requests_history_page',
    fetch: mocked(fetchApiRequests),
    rows: API_RESPONSE,
    empty: API_EMPTY,
    resultText: /^bob$/,
    placeholder: PLACEHOLDER,
    schedule: true,
  },
  {
    name: 'SSP Activity',
    route: '/reporting/ssp-activity',
    Page: SspActivity,
    settingsKey: 'ssp_activity_report_page',
    fetch: mocked(fetchSspActivity),
    rows: SSP_RESPONSE,
    empty: SSP_EMPTY,
    resultText: /^camp-1$/,
    placeholder: /select a display, set your date range, then click apply/i,
    prepare: pickDisplay,
    schedule: false,
  },
];
