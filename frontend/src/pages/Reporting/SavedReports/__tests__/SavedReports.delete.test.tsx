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
// Page integration: single and bulk delete
// Confirmation has no title, so it's found by heading (xibosignage/xibo#4039)
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import {
  apiError,
  EMPTY_SAVED_REPORT_TABLE,
  MULTIPLE_SAVED_REPORTS,
  mockSavedReport,
  PAGED_SAVED_REPORTS_P1,
  PAGED_SAVED_REPORTS_P2,
  queryKeys,
  SINGLE_SAVED_REPORT,
} from './fixtures/savedReport';
import { renderSavedReportsPage } from './helpers/renderSavedReportsPage';
import { mockFetchSavedReportPages, mockFetchSavedReports } from './mocks/savedReportApi';

import { notify } from '@/components/ui/Notification';
import { deleteSavedReport } from '@/services/savedReportApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { clickMenuAction, selectAllRows, selectRow } from '@/testUtils/rowActions';
import { waitForDialogToClose } from '@/testUtils/rtl';

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

const SINGLE_HEADING = 'Delete Saved Report?';
const BULK_HEADING = 'Delete Saved Reports?';

const openRowDelete = async (user: UserEvent, rowName = mockSavedReport.saveAs) => {
  await clickMenuAction(user, rowName, /^delete$/i);
  return screen.findByRole('heading', { name: SINGLE_HEADING });
};

const openBulkDelete = async (user: UserEvent) => {
  await screen.findByText('Saved Alpha');
  await selectAllRows(user);
  await user.click(await screen.findByRole('button', { name: /delete selected/i }));
  return screen.findByRole('heading', { name: BULK_HEADING });
};

const deletedIds = () => vi.mocked(deleteSavedReport).mock.calls.map(([id]) => id);

// =============================================================================
// Tests — single delete
// =============================================================================

describe('Saved Reports page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier implementations; reset the overridden one.
    vi.mocked(deleteSavedReport).mockReset().mockResolvedValue(undefined);
    mockFetchSavedReports(SINGLE_SAVED_REPORT);
  });

  test('the row Delete opens a confirmation naming the saved report', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();

    await openRowDelete(user);

    expect(screen.getByRole('dialog')).toHaveTextContent(mockSavedReport.saveAs);
  });

  test('Cancel closes the confirmation without deleting', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await openRowDelete(user);

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    await waitForDialogToClose(SINGLE_HEADING);
    expect(deleteSavedReport).not.toHaveBeenCalled();
  });

  test(
    'confirming deletes the saved report, shows a success message and refreshes',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup({ delay: null });
      renderSavedReportsPage();
      await openRowDelete(user);
      mockFetchSavedReports(EMPTY_SAVED_REPORT_TABLE);

      await user.click(screen.getByRole('button', { name: /yes, delete/i }));

      await waitForDialogToClose(SINGLE_HEADING);
      expect(deleteSavedReport).toHaveBeenCalledWith(mockSavedReport.savedReportId);
      expect(notify.success).toHaveBeenCalledWith('1 saved report(s) deleted successfully.');
      await screen.findByText('No results found.');
    },
  );

  test('while deleting the button reads Deleting… and a second click does nothing', async () => {
    const user = userEvent.setup({ delay: null });
    const pending = deferred<void>();
    vi.mocked(deleteSavedReport).mockReturnValueOnce(pending.promise);
    renderSavedReportsPage();
    await openRowDelete(user);

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    const busy = await screen.findByRole('button', { name: 'Deleting…' });
    expect(busy).toBeDisabled();
    await user.click(busy);
    expect(deleteSavedReport).toHaveBeenCalledTimes(1);

    pending.resolve();
    await waitForDialogToClose(SINGLE_HEADING);
  });
});

// =============================================================================
// Tests — bulk delete
// =============================================================================

describe('Saved Reports page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(deleteSavedReport).mockReset().mockResolvedValue(undefined);
    mockFetchSavedReports(MULTIPLE_SAVED_REPORTS);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  test('bulk delete says how many and sends every selected id', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();

    await openBulkDelete(user);
    expect(screen.getByRole('dialog')).toHaveTextContent(
      'Are you sure you want to delete 3 saved reports?',
    );
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitForDialogToClose(BULK_HEADING);
    expect(deletedIds()).toEqual(expect.arrayContaining([10, 11, 12]));
    expect(deletedIds()).toHaveLength(3);
  });

  test('saved reports selected on another page are still deleted', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchSavedReportPages(PAGED_SAVED_REPORTS_P1, PAGED_SAVED_REPORTS_P2);
    renderSavedReportsPage();

    await selectRow(user, 'Saved 1');
    await user.click(screen.getByRole('button', { name: /next/i }));
    await selectRow(user, 'Saved 11');
    await user.click(await screen.findByRole('button', { name: /delete selected/i }));
    await screen.findByRole('heading', { name: BULK_HEADING });
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => expect(deletedIds()).toEqual(expect.arrayContaining([100, 110])));
    expect(deletedIds()).toHaveLength(2);
  }, 30_000); // bumped — ~7s in isolation (two pages of rows)

  test('a partial failure keeps the confirmation open and explains what happened', async () => {
    const user = userEvent.setup({ delay: null });
    // Deletes go out in row order, so the second call is id 11.
    vi.mocked(deleteSavedReport)
      .mockResolvedValueOnce(undefined)
      .mockRejectedValueOnce(apiError('File is locked', 500))
      .mockResolvedValueOnce(undefined);
    renderSavedReportsPage();
    await openBulkDelete(user);
    const invalidate = vi.spyOn(testQueryClient, 'invalidateQueries');

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    const alert = await screen.findByRole('alert');
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /delete selected/i })).not.toBeInTheDocument(),
    );
    expect(invalidate).toHaveBeenCalledWith({ queryKey: queryKeys.savedReportAll });
    expect(alert).toHaveTextContent('2 saved report(s) deleted successfully.');
    expect(alert).toHaveTextContent('File is locked');
    expect(screen.getByRole('heading', { name: BULK_HEADING })).toBeInTheDocument();
    expect(deletedIds()).toEqual(expect.arrayContaining([10, 11, 12]));
    expect(notify.success).not.toHaveBeenCalled();
  });

  test('a failure with no server message uses the count wording', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(deleteSavedReport)
      .mockResolvedValueOnce(undefined)
      .mockRejectedValueOnce(new Error('network down'))
      .mockResolvedValueOnce(undefined);
    renderSavedReportsPage();
    await openBulkDelete(user);

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      '1 saved report(s) could not be deleted.',
    );
    expect(deletedIds()).toEqual(expect.arrayContaining([10, 11, 12]));
  });

  // A 404 means it's already gone, so it counts as success.
  test('an already-deleted saved report counts as deleted', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(deleteSavedReport)
      .mockResolvedValueOnce(undefined)
      .mockRejectedValueOnce(apiError('Not found', 404))
      .mockResolvedValueOnce(undefined);
    renderSavedReportsPage();
    await openBulkDelete(user);

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitForDialogToClose(BULK_HEADING);
    expect(notify.success).toHaveBeenCalledWith('3 saved report(s) deleted successfully.');
  });
});
