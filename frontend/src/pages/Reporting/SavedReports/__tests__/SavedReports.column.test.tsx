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
// Page integration: column picker, sorting, Report Type
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { getSavedReportColumns } from '../SavedReportsConfig';

import {
  buildSavedReport,
  mockSavedReport,
  PAGED_SAVED_REPORTS_P1,
  PAGED_SAVED_REPORTS_P2,
  SINGLE_SAVED_REPORT,
} from './fixtures/savedReport';
import { renderSavedReportsPage } from './helpers/renderSavedReportsPage';
import {
  lastSavedReportRequest,
  mockFetchSavedReportPages,
  mockFetchSavedReports,
} from './mocks/savedReportApi';

import { propsWith } from '@/components/ui/table/__tests__/contracts/helpers';
import { testQueryClient } from '@/setupTests';
import { exportColumnValue } from '@/testUtils/columnContract';
import { findRow } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal();
  return { ...(actual as object), useNavigate: () => mockNavigate };
});

vi.mock('@/services/savedReportApi', () => ({
  fetchSavedReports: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  deleteSavedReport: vi.fn().mockResolvedValue(undefined),
  fetchSavedReportData: vi.fn(),
}));
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useSavedReportFilterOptions', () => ({
  useSavedReportFilterOptions: vi.fn(() => ({
    filterOptions: [],
    reportDescriptionMap: { proofofplayReport: 'Proof of Play' },
    isLoading: false,
  })),
}));

// =============================================================================
// Helpers
// =============================================================================

const openColumnPicker = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: /toggle columns/i }));
  return screen.findByRole('checkbox', { name: /^owner$/i });
};

const clickSort = async (user: UserEvent, header: RegExp) => {
  const th = screen.getByRole('columnheader', { name: header });
  await user.click(within(th).getByRole('button'));
};

// =============================================================================
// Part 1 — column picker
// =============================================================================

describe('Saved Reports page - column picker', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSavedReports(SINGLE_SAVED_REPORT);
  });

  // Saved As is the row's identity, so it can't be hidden.
  test('Saved As cannot be hidden from the column picker; the other columns can', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await screen.findByText(mockSavedReport.saveAs);

    await openColumnPicker(user);

    expect(screen.queryByRole('checkbox', { name: /^saved as$/i })).not.toBeInTheDocument();
    for (const label of [/^report schedule$/i, /^report type$/i, /^generated on$/i, /^owner$/i]) {
      expect(screen.getByRole('checkbox', { name: label })).toBeInTheDocument();
    }
  });

  test('unticking a column in the picker removes it from the table', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await screen.findByRole('columnheader', { name: /^owner$/i });

    await user.click(await openColumnPicker(user));

    await waitFor(() =>
      expect(screen.queryByRole('columnheader', { name: /^owner$/i })).not.toBeInTheDocument(),
    );
  });
});

// =============================================================================
// Part 2 — sorting
// =============================================================================

describe('Saved Reports page - sorting', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('clicking a header sorts on the server, then descending, back on page 1', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchSavedReportPages(PAGED_SAVED_REPORTS_P1, PAGED_SAVED_REPORTS_P2);
    renderSavedReportsPage();
    await screen.findByText('Saved 1');
    await user.click(screen.getByRole('button', { name: /next/i }));
    await screen.findByText('Saved 11');

    await clickSort(user, /^generated on$/i);
    await waitFor(() =>
      expect(lastSavedReportRequest()).toEqual(
        expect.objectContaining({ sortBy: 'generatedOn', sortDir: 'asc', start: 0 }),
      ),
    );
    await clickSort(user, /^generated on$/i);

    await waitFor(() =>
      expect(lastSavedReportRequest()).toEqual(
        expect.objectContaining({ sortBy: 'generatedOn', sortDir: 'desc', start: 0 }),
      ),
    );
  });
});

// =============================================================================
// Part 3 — Report Type
// =============================================================================

describe('Saved Reports grid - Report Type', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('Report Type shows the friendly name, falling back to the internal one', async () => {
    mockFetchSavedReports({
      rows: [
        buildSavedReport({ savedReportId: 1, saveAs: 'Known Type' }),
        buildSavedReport({ savedReportId: 2, saveAs: 'Unknown Type', reportName: 'customReport' }),
      ],
      totalCount: 2,
    });
    renderSavedReportsPage();

    expect(await findRow('Known Type')).toHaveTextContent('Proof of Play');
    expect(await findRow('Unknown Type')).toHaveTextContent('customReport');
  });

  test('Report Type exports the same friendly name the grid shows', () => {
    const columns = getSavedReportColumns(
      propsWith({ reportDescriptionMap: { proofofplayReport: 'Proof of Play' } }),
    );

    expect(exportColumnValue(columns, 'reportName', mockSavedReport)).toBe('Proof of Play');
    expect(
      exportColumnValue(columns, 'reportName', buildSavedReport({ reportName: 'customReport' })),
    ).toBe('customReport');
  });
});
