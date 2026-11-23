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
// Page: the viewer's column set for each saved report type
// =============================================================================

import { screen, within } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  API_AUDIT_DATA,
  API_DEBUG_DATA,
  API_REQUESTS_DATA,
  buildSavedReportData,
  POP_DATA,
  SESSION_AUDIT_DATA,
  SESSION_DATA,
  SESSION_DEBUG_DATA,
  UNKNOWN_REPORT_DATA,
} from '../fixtures/savedReportData';
import { renderSavedReportViewer } from '../helpers/renderSavedReportViewer';
import { mockSavedReportData } from '../mocks/savedReportApi';

import { fetchSavedReportData } from '@/services/savedReportApi';
import type { SavedReportData } from '@/services/savedReportApi';
import { testQueryClient } from '@/setupTests';

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  useNavigate: () => mockNavigate,
}));
vi.mock('@/services/savedReportApi', () => ({
  fetchSavedReports: vi.fn(),
  fetchSavedReportData: vi.fn(),
  exportSavedReport: vi.fn(),
}));
// Keep the chart helpers real so the table/chart switch behaves as in the app.
vi.mock('../../SavedReportViewer/SavedReportChart', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  default: () => <div role="img" aria-label="Saved report chart" />,
}));

const headingsFor = async (reportName: string, data: SavedReportData) => {
  testQueryClient.clear();
  mockSavedReportData(data);
  const view = renderSavedReportViewer(10, reportName);
  await screen.findByRole('table');
  const headings = screen.getAllByRole('columnheader').map((th) => th.textContent ?? '');
  view.unmount();
  return headings;
};

const ONE_ROW = buildSavedReportData({ table: [{ placeholder: 1 }] });

describe('Saved report viewer - columns', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchSavedReportData).mockReset();
  });

  test.each([
    ['bandwidth', ['', 'Bandwidth', 'Unit']],
    [
      'displayalerts',
      ['Display ID', 'Display', 'Event Type', 'Start', 'End', 'Reference', 'Detail'],
    ],
    ['distributionReport', ['Period', 'Duration', 'Count']],
    ['libraryusage', ['ID', 'User', 'Usage', 'Count Files']],
    [
      'proofofplayReport',
      [
        'Type',
        'Display ID',
        'Display',
        'Campaign',
        'Layout ID',
        'Layout',
        'Widget ID',
        'Media',
        'Tag',
        'Number of Plays',
        'Total Duration',
        'Total Duration (s)',
        'First Shown',
        'Last Shown',
      ],
    ],
    ['summaryReport', ['Period', 'Duration', 'Count']],
    [
      'timedisconnectedsummary',
      ['Display ID', 'Display', 'Time Disconnected', 'Time Connected', 'Units'],
    ],
  ])('a saved %s report shows its own headings', async (reportName, expected) => {
    expect(await headingsFor(reportName, ONE_ROW)).toEqual(expected);
  });

  test('API Request History headings follow the saved log type', async () => {
    const audit = await headingsFor('apirequests', API_AUDIT_DATA);
    const debug = await headingsFor('apirequests', API_DEBUG_DATA);
    const requests = await headingsFor('apirequests', API_REQUESTS_DATA);

    expect(audit).toEqual(expect.arrayContaining(['Entity', 'Entity ID', 'Message', 'Details']));
    expect(debug).toEqual(expect.arrayContaining(['Level', 'Details']));
    expect(debug).not.toContain('Entity');
    expect(requests).toEqual([
      'Date',
      'UserName',
      'User ID',
      'Application',
      'Request ID',
      'Method',
      'Url',
    ]);
  });

  test('Session History headings follow the saved log type', async () => {
    const audit = await headingsFor('sessionhistory', SESSION_AUDIT_DATA);
    const debug = await headingsFor('sessionhistory', SESSION_DEBUG_DATA);
    const sessions = await headingsFor('sessionhistory', SESSION_DATA);

    expect(audit).toEqual(expect.arrayContaining(['Entity', 'Entity ID', 'Object']));
    expect(debug).toEqual(
      expect.arrayContaining(['Channel', 'Function', 'Level', 'Page', 'Details']),
    );
    expect(sessions).toEqual([
      'Date',
      'User Name',
      'User ID',
      'IP Address',
      'Session ID',
      'Message',
      'Object',
    ]);
  });

  test('an unknown report type shows one column per field in the data', async () => {
    mockSavedReportData(UNKNOWN_REPORT_DATA);
    renderSavedReportViewer(10, 'customReport');

    await screen.findByRole('table');
    expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
      'alpha',
      'beta',
    ]);
    expect(screen.getByRole('cell', { name: 'x' })).toBeInTheDocument();
  });

  test('Proof of Play shows total duration both spelled out and in seconds', async () => {
    mockSavedReportData(POP_DATA);
    renderSavedReportViewer(10, 'proofofplayReport');

    await screen.findByRole('cell', { name: '1day 1hr 1min 1sec' });
    expect(screen.getByRole('cell', { name: '90061' })).toBeInTheDocument();
  });

  // Object, JSON and HTML-encoded JSON all read the same; non-JSON is shown as is.
  test('detail fields are shown as readable key: value pairs', async () => {
    const row = API_AUDIT_DATA.table![0]!;
    mockSavedReportData({
      ...API_AUDIT_DATA,
      table: [
        { ...row, requestId: 1, objectAfter: { a: 1 } },
        { ...row, requestId: 2, objectAfter: '{"a":1}' },
        { ...row, requestId: 3, objectAfter: '{&quot;a&quot;:1}' },
        { ...row, requestId: 4, objectAfter: 'not json' },
      ],
    });
    renderSavedReportViewer(10, 'apirequests');

    await screen.findByRole('table');
    const rows = screen.getAllByRole('row').slice(1);
    const lastCell = (r: HTMLElement) => {
      const cells = within(r).getAllByRole('cell');
      return cells[cells.length - 1]!.textContent;
    };
    expect(rows.map(lastCell)).toEqual(['a: 1', 'a: 1', 'a: 1', 'not json']);
  });

  test('HTML-encoded messages are shown decoded', async () => {
    mockSavedReportData(SESSION_DATA);
    renderSavedReportViewer(10, 'sessionhistory');

    await screen.findByRole('cell', { name: 'Saved & closed' });
  });
});
