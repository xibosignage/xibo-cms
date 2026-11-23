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

// =============================================================================
// Contract: what each report-data service sends
// The only place request building is checked; other tests mock these services.
// =============================================================================

import axios from 'axios';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { fetchApiRequests } from '@/services/apiRequestsApi';
import { fetchBandwidthReport } from '@/services/bandwidthReportApi';
import { fetchDisplayAlerts } from '@/services/displayAlertsApi';
import { fetchLibraryUsage } from '@/services/libraryUsageApi';
import { fetchProofOfPlay } from '@/services/proofOfPlayApi';
import { fetchSessionHistory } from '@/services/sessionHistoryApi';
import { fetchStatsReport } from '@/services/statsReportApi';
import { fetchTimeConnected } from '@/services/timeConnectedApi';
import { fetchTimeDisconnectedSummary } from '@/services/timeDisconnectedSummaryApi';
import { knownFailure } from '@/testUtils/knownFailure';

vi.mock('axios', async (importOriginal) => {
  const actual = (await importOriginal()) as { default: Record<string, unknown> };
  return { ...actual, default: { ...actual.default, get: vi.fn() } };
});

// One entry per service; `dates` = explicit-range fields (null: no range).
interface ServiceCase {
  name: string;
  call: (req: Record<string, unknown>) => Promise<unknown>;
  path: string;
  named: boolean;
  // false: also sends explicit dates with a named range (harmless, not pinned).
  exclusiveDates?: boolean;
  dates: [string, string] | null;
  displays: boolean;
}

const SERVICES: ServiceCase[] = [
  {
    name: 'Summary / Distribution',
    call: (req) =>
      fetchStatsReport('summaryReport', { ...req, type: 'layout', layoutId: 301 } as never),
    path: '/report/data/summaryReport',
    named: true,
    dates: ['statsFromDt', 'statsToDt'],
    displays: true,
  },
  {
    name: 'Proof of Play',
    call: (req) => fetchProofOfPlay(req as never),
    path: '/report/data/proofofplayReport',
    named: true,
    dates: ['statsFromDt', 'statsToDt'],
    displays: true,
  },
  {
    name: 'Bandwidth',
    call: (req) => fetchBandwidthReport(req as never),
    path: '/report/data/bandwidth',
    named: false,
    dates: ['fromDt', 'toDt'],
    displays: false,
  },
  {
    name: 'Time Connected',
    call: (req) => fetchTimeConnected({ groupByFilter: 'bydayofmonth', ...req } as never),
    path: '/report/data/timeconnected',
    named: true,
    exclusiveDates: false,
    dates: ['fromDt', 'toDt'],
    displays: false,
  },
  {
    name: 'Time Connected Summary',
    call: (req) => fetchTimeDisconnectedSummary({ groupBy: 'display', ...req } as never),
    path: '/report/data/timedisconnectedsummary',
    named: true,
    dates: ['fromDt', 'toDt'],
    displays: true,
  },
  {
    name: 'Display Alerts',
    call: (req) => fetchDisplayAlerts(req as never),
    path: '/report/data/displayalerts',
    named: true,
    dates: ['fromDt', 'toDt'],
    displays: true,
  },
  {
    name: 'Library Usage',
    call: (req) => fetchLibraryUsage(req as never),
    path: '/report/data/libraryusage',
    named: false,
    dates: null,
    displays: false,
  },
  {
    name: 'Session History',
    call: (req) => fetchSessionHistory({ type: 'sessions', ...req } as never),
    path: '/report/data/sessionhistory',
    named: true,
    dates: ['fromDt', 'toDt'],
    displays: false,
  },
  {
    name: 'API Request History',
    call: (req) => fetchApiRequests({ type: 'audit', ...req } as never),
    path: '/report/data/apirequests',
    named: true,
    dates: ['fromDt', 'toDt'],
    displays: false,
  },
];

const lastRequest = () => {
  const calls = vi.mocked(axios.get).mock.calls;
  const [url, config] = calls[calls.length - 1]!;
  return { url, params: new URLSearchParams((config as { params: URLSearchParams }).params) };
};

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(axios.get)
    .mockReset()
    .mockResolvedValue({ data: { table: [] } });
});

describe.each(SERVICES)('$name service', (c) => {
  test("calls the report's own address, with the session cookie", async () => {
    await c.call({});

    const calls = vi.mocked(axios.get).mock.calls;
    expect(calls[calls.length - 1]![0]).toBe(c.path);
    expect(calls[calls.length - 1]![1]).toEqual(expect.objectContaining({ withCredentials: true }));
  });

  test.runIf(c.named)('a named range is sent as-is, without explicit dates', async () => {
    await c.call({
      reportFilter: 'lastweek',
      fromDt: 'x',
      toDt: 'y',
      statsFromDt: 'x',
      statsToDt: 'y',
    });

    const { params } = lastRequest();
    expect(params.get('reportFilter')).toBe('lastweek');
    if (c.exclusiveDates !== false) {
      expect(params.has(c.dates![0])).toBe(false);
      expect(params.has(c.dates![1])).toBe(false);
    }
  });

  test.runIf(c.dates !== null)(
    'explicit dates are sent under the names the server reads',
    async () => {
      const [from, to] = c.dates!;
      await c.call({ [from]: '2027-01-01 00:00:00', [to]: '2027-01-07 23:59:59' });

      const { params } = lastRequest();
      expect(params.get(from)).toBe('2027-01-01 00:00:00');
      expect(params.get(to)).toBe('2027-01-07 23:59:59');
      expect(params.has('reportFilter')).toBe(false);
    },
  );

  test.runIf(c.displays)('an empty display is left out; display groups repeat', async () => {
    await c.call({ displayId: null, displayGroupId: [201, 202] });

    const { params } = lastRequest();
    expect(params.has('displayId')).toBe(false);
    expect(params.getAll('displayGroupId[]')).toEqual(['201', '202']);
  });

  test('nothing is sent as the text "null" or "undefined"', async () => {
    await c.call({ displayId: null, userId: null, groupId: null, displayGroupId: [] });

    const values = [...lastRequest().params.values()];
    expect(values).not.toContain('null');
    expect(values).not.toContain('undefined');
  });
});

// Defaults the service adds itself; only checked here.
describe('Display Alerts service defaults', () => {
  test('with no event type chosen, every type is requested (-1)', async () => {
    await fetchDisplayAlerts({} as never);

    expect(lastRequest().params.get('eventType')).toBe('-1');
  });

  test('a chosen event type is sent as its number', async () => {
    await fetchDisplayAlerts({ eventType: '3' } as never);

    expect(lastRequest().params.get('eventType')).toBe('3');
  });

  test('tags are matched against display groups, with the exact and AND/OR choices', async () => {
    await fetchDisplayAlerts({ tags: 'lobby', exactTags: true, logicalOperator: 'AND' } as never);

    const { params } = lastRequest();
    expect(params.get('tags')).toBe('lobby');
    expect(params.get('tagsType')).toBe('dg');
    expect(params.get('exactTags')).toBe('1');
    expect(params.get('logicalOperator')).toBe('AND');
  });
});

// Known issue 1: xibosignage/xibo#4092 (sub-folder installs)
describe('Pinned until https://github.com/xibosignage/xibo/issues/4092 is fixed', () => {
  knownFailure(
    'no report-data request goes to a root-absolute address',
    async () => {
      for (const c of SERVICES) await c.call({});
      // Control: every service made a request.
      expect(vi.mocked(axios.get).mock.calls.length).toBe(SERVICES.length);
      return vi.mocked(axios.get).mock.calls.map(([url]) => String(url));
    },
    (urls) => expect(urls.filter((url) => url.startsWith('/report/'))).toEqual([]),
  );
});
