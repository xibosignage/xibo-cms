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
// Page integration: default page state
// Search box is found by placeholder (xibosignage/xibo#4083)
// =============================================================================

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  EMPTY_SAVED_REPORT_TABLE,
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

import { fetchSavedReports } from '@/services/savedReportApi';
import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
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
// Tests
// =============================================================================

describe('Saved Reports page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier implementations; reset the overridden ones.
    vi.mocked(fetchUserPreference).mockReset().mockResolvedValue(null);
    vi.mocked(fetchSavedReports).mockReset();
    sessionStorage.clear();
  });

  test('the page shows a loading placeholder until the saved page settings arrive', async () => {
    const pref = deferred<null>();
    vi.mocked(fetchUserPreference).mockReturnValue(pref.promise);
    mockFetchSavedReports(SINGLE_SAVED_REPORT);
    renderSavedReportsPage({ hydrated: false });

    await screen.findByText('Loading...');
    expect(screen.getByPlaceholderText('Search')).toBeDisabled();
    expect(screen.getByRole('button', { name: /filters/i })).toBeDisabled();

    pref.resolve(null);

    await screen.findByText(mockSavedReport.saveAs);
    expect(screen.getByPlaceholderText('Search')).toBeEnabled();
    expect(screen.getByRole('button', { name: /filters/i })).toBeEnabled();
  });

  test(
    'each saved report is listed with its schedule, name, type and owner',
    { tags: ['smoke'] },
    async () => {
      mockFetchSavedReports(SINGLE_SAVED_REPORT);
      renderSavedReportsPage();

      const row = await findRow('Weekly PoP 2027-01-04');
      expect(row).toHaveTextContent('Weekly PoP');
      expect(row).toHaveTextContent('Weekly PoP 2027-01-04');
      expect(row).toHaveTextContent('Proof of Play');
      expect(row).toHaveTextContent('TestUser');
    },
  );

  test('an empty list shows the table empty state', async () => {
    mockFetchSavedReports(EMPTY_SAVED_REPORT_TABLE);
    renderSavedReportsPage();

    await screen.findByText('No results found.');
    expect(screen.queryByText(mockSavedReport.saveAs)).not.toBeInTheDocument();
  });

  test('a failed load shows the server error message', async () => {
    vi.mocked(fetchSavedReports).mockRejectedValue(new Error('Database unavailable'));
    renderSavedReportsPage();

    await screen.findByText(/database unavailable/i);
  });

  test('moving to page 2 asks the server for the second page', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchSavedReportPages(PAGED_SAVED_REPORTS_P1, PAGED_SAVED_REPORTS_P2);
    renderSavedReportsPage();

    await screen.findByText('Saved 1');
    await user.click(screen.getByRole('button', { name: /next/i }));

    await screen.findByText('Saved 11');
    expect(lastSavedReportRequest()).toEqual(expect.objectContaining({ start: 10, length: 10 }));
  });
});
