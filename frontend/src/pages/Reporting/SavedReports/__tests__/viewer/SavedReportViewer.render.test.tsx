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
// Page: the saved-report viewer's states and controls
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildSavedReportData,
  PERIOD_METADATA,
  POP_DATA,
  SUMMARY_DATA,
  TIME_CONNECTED_DATA,
} from '../fixtures/savedReportData';
import { renderSavedReportViewer } from '../helpers/renderSavedReportViewer';
import { mockSavedReportData } from '../mocks/savedReportApi';

import { fetchSavedReportData } from '@/services/savedReportApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';

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

describe('Saved report viewer - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchSavedReportData).mockReset();
  });

  test('requests the saved report named in the address and shows its rows', async () => {
    mockSavedReportData(POP_DATA);
    renderSavedReportViewer(10, 'proofofplayReport');

    await screen.findByRole('cell', { name: 'Lobby Screen' });
    expect(fetchSavedReportData).toHaveBeenCalledWith(10, 'proofofplayReport');
  });

  // The spinner has no accessible name, so check the table is absent.
  test('shows no table until the report has loaded', async () => {
    const pending = deferred<typeof POP_DATA>();
    vi.mocked(fetchSavedReportData).mockReturnValueOnce(pending.promise);
    renderSavedReportViewer();

    await waitFor(() => expect(fetchSavedReportData).toHaveBeenCalled());
    expect(screen.queryByRole('table')).not.toBeInTheDocument();

    pending.resolve(POP_DATA);
    await screen.findByRole('cell', { name: 'Lobby Screen' });
  });

  test('a failed load explains and offers Retry, which loads it again', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(fetchSavedReportData)
      .mockRejectedValueOnce(new Error('Not found'))
      .mockResolvedValueOnce(POP_DATA);
    renderSavedReportViewer();

    await screen.findByText(
      'This report could not be loaded. It may have been deleted or corrupted.',
    );
    await user.click(screen.getByRole('button', { name: /retry/i }));

    await screen.findByRole('cell', { name: 'Lobby Screen' });
  });

  test('the report period is shown when the saved report has one', async () => {
    mockSavedReportData(POP_DATA);
    renderSavedReportViewer();

    await screen.findByRole('cell', { name: 'Lobby Screen' });
    expect(document.body).toHaveTextContent(`From: ${PERIOD_METADATA.periodStart}`);
    expect(document.body).toHaveTextContent(`To: ${PERIOD_METADATA.periodEnd}`);
  });

  test('no period is shown when the saved report has none', async () => {
    mockSavedReportData({ ...POP_DATA, metadata: null });
    renderSavedReportViewer();

    await screen.findByRole('cell', { name: 'Lobby Screen' });
    expect(screen.queryByText(/^From/)).not.toBeInTheDocument();
  });

  test('a recorded error shows as a warning instead of the no-data message', async () => {
    mockSavedReportData(buildSavedReportData({ error: 'Partial data: display 4 offline' }));
    renderSavedReportViewer();

    await screen.findByText('Partial data: display 4 offline');
    expect(screen.queryByText('This report contains no data.')).not.toBeInTheDocument();
  });

  test('a report with nothing in it says so', async () => {
    mockSavedReportData(buildSavedReportData());
    renderSavedReportViewer();

    await screen.findByText('This report contains no data.');
  });

  test('the Table/Chart switch only appears when there is a chart to draw', async () => {
    mockSavedReportData(SUMMARY_DATA);
    const { unmount } = renderSavedReportViewer(10, 'summaryReport');
    await screen.findByRole('button', { name: 'Chart View' });
    expect(screen.getByRole('button', { name: 'Table View' })).toBeInTheDocument();
    unmount();

    testQueryClient.clear();
    mockSavedReportData(POP_DATA);
    renderSavedReportViewer(11, 'proofofplayReport');
    await screen.findByRole('cell', { name: 'Lobby Screen' });
    expect(screen.queryByRole('button', { name: 'Chart View' })).not.toBeInTheDocument();
  });

  test('switching to Chart shows the chart, and back shows the table', async () => {
    const user = userEvent.setup({ delay: null });
    mockSavedReportData(SUMMARY_DATA);
    renderSavedReportViewer(10, 'summaryReport');
    await screen.findByRole('cell', { name: 'Mon' });

    await user.click(screen.getByRole('button', { name: 'Chart View' }));
    expect(screen.getByRole('img', { name: 'Saved report chart' })).toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Table View' }));
    expect(screen.getByRole('cell', { name: 'Mon' })).toBeInTheDocument();
  });

  test('Back returns to the previous page', async () => {
    const user = userEvent.setup({ delay: null });
    mockSavedReportData(POP_DATA);
    renderSavedReportViewer();
    await screen.findByRole('cell', { name: 'Lobby Screen' });

    await user.click(screen.getByRole('button', { name: /^back$/i }));

    expect(mockNavigate).toHaveBeenCalledWith(-1);
  });

  test('a saved Time Connected report shows display timeline rows, not a table', async () => {
    mockSavedReportData(TIME_CONNECTED_DATA);
    renderSavedReportViewer(10, 'timeconnected');

    await screen.findByText('Lobby Screen');
    expect(screen.getByText('Back Office')).toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });
});
