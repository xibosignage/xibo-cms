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
// Page: the viewer's Export as PDF button
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import { POP_DATA } from '../fixtures/savedReportData';
import { renderSavedReportViewer } from '../helpers/renderSavedReportViewer';
import { mockSavedReportData } from '../mocks/savedReportApi';

import { exportSavedReport } from '@/services/savedReportApi';
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

const createObjectURL = vi.fn(() => 'blob:pdf-1');
const revokeObjectURL = vi.fn();
let downloads: string[] = [];

describe('Saved report viewer - export', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(exportSavedReport).mockReset();
    mockSavedReportData(POP_DATA);
    downloads = [];
    Object.assign(URL, { createObjectURL, revokeObjectURL });
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
      this: HTMLAnchorElement,
    ) {
      downloads.push(this.download);
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  const exportButton = () => screen.getByRole('button', { name: /export as pdf/i });

  test('Export downloads a PDF named after the report and releases it afterwards', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(exportSavedReport).mockResolvedValueOnce(new Blob(['%PDF']));
    renderSavedReportViewer(10, 'proofofplayReport');
    await screen.findByRole('cell', { name: 'Lobby Screen' });

    await user.click(exportButton());

    await waitFor(() => expect(downloads).toEqual(['proofofplayReport_10.pdf']));
    expect(exportSavedReport).toHaveBeenCalledWith(10, 'proofofplayReport');
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:pdf-1');
  });

  test('Export is disabled while it runs', async () => {
    const user = userEvent.setup({ delay: null });
    const pending = deferred<Blob>();
    vi.mocked(exportSavedReport).mockReturnValueOnce(pending.promise);
    renderSavedReportViewer();
    await screen.findByRole('cell', { name: 'Lobby Screen' });

    await user.click(exportButton());

    await waitFor(() => expect(exportButton()).toBeDisabled());
    pending.resolve(new Blob(['%PDF']));
    await waitFor(() => expect(exportButton()).toBeEnabled());
  });

  test("a failed export shows the server's reason", async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(exportSavedReport).mockRejectedValueOnce(new Error('PDF engine unavailable'));
    renderSavedReportViewer();
    await screen.findByRole('cell', { name: 'Lobby Screen' });

    await user.click(exportButton());

    await screen.findByText('PDF engine unavailable');
    expect(downloads).toEqual([]);
  });
});
