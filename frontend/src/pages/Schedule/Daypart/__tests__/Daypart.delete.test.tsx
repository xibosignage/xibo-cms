/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDaypart, MULTIPLE_DAYPARTS, SINGLE_DAYPART } from './fixtures/daypart';
import { renderDaypartPage } from './helpers/renderDaypartPage';
import { mockFetchDaypart, mockFetchDaypartScheduleCount } from './mocks/daypartApi';

import { deleteDaypart, fetchDaypart } from '@/services/daypartApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/daypartApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useDaypartFilterOptions', () => ({
  useDaypartFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Helpers
// =============================================================================

const openRowDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockDaypart.name);
  await user.click(screen.getByRole('button', { name: /^delete$/i }));
};

const selectAllRows = async (user: UserEvent) => {
  const checkboxes = screen.getAllByRole('checkbox', { name: /select/i });
  await user.click(checkboxes[0]!);
};

// =============================================================================
// Tests — Single delete
// =============================================================================

describe('Dayparting page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(SINGLE_DAYPART);
    mockFetchDaypartScheduleCount();
  });

  test('the Delete row action opens the confirmation modal showing the daypart name', async () => {
    const user = userEvent.setup();
    renderDaypartPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Daypart?')).toBeInTheDocument();
    expect(screen.getByText(mockDaypart.name, { selector: 'strong' })).toBeInTheDocument();

    // Only the Delete modal opens — not the Edit or Share modal.
    expect(screen.queryByRole('dialog', { name: /edit daypart/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('dialog', { name: /share daypart/i })).not.toBeInTheDocument();
  });

  test('clicking Cancel closes the modal without deleting', async () => {
    const user = userEvent.setup();
    renderDaypartPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(screen.queryByText('Delete Daypart?')).not.toBeInTheDocument();
    expect(deleteDaypart).not.toHaveBeenCalled();
  });

  test('clicking Yes, Delete removes the daypart, refreshes the table, and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteDaypart).mockResolvedValue(undefined);
    renderDaypartPage();

    await screen.findByText(mockDaypart.name);
    const callsBefore = vi.mocked(fetchDaypart).mock.calls.length;

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteDaypart).toHaveBeenCalledWith(mockDaypart.dayPartId);
    });
    await waitFor(() => {
      expect(screen.queryByText('Delete Daypart?')).not.toBeInTheDocument();
    });
    // The table is refreshed (handleRefresh invalidates the daypart query).
    await waitFor(() => {
      expect(vi.mocked(fetchDaypart).mock.calls.length).toBeGreaterThan(callsBefore);
    });
  });

  test('the Delete button shows "Deleting…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveDelete: () => void = () => {};
    vi.mocked(deleteDaypart).mockReturnValueOnce(
      new Promise<void>((resolve) => {
        resolveDelete = resolve;
      }),
    );
    renderDaypartPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByRole('button', { name: /deleting/i })).toBeDisabled();

    // Resolve so the test doesn't leak a pending promise.
    resolveDelete();
  });

  test('a failed delete keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteDaypart).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Cannot delete — daypart is in use.' } },
    });
    renderDaypartPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByText('Cannot delete — daypart is in use.')).toBeInTheDocument();
    expect(screen.getByText('Delete Daypart?')).toBeInTheDocument();
  });

  test('single item: the heading is singular', async () => {
    const user = userEvent.setup();
    renderDaypartPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Daypart?')).toBeInTheDocument();
    expect(screen.queryByText('Delete Dayparts?')).not.toBeInTheDocument();
  });
});

// =============================================================================
// Tests — Bulk delete
// =============================================================================

describe('Dayparting page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(MULTIPLE_DAYPARTS);
  });

  test('multiple items: the heading is plural and shows the count', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText('Daypart Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));

    expect(await screen.findByText('Delete Dayparts?')).toBeInTheDocument();
    expect(screen.getByText('2', { selector: 'strong' })).toBeInTheDocument();
  });

  test('bulk confirm deletes every selected item', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteDaypart).mockResolvedValue(undefined);
    renderDaypartPage();
    await screen.findByText('Daypart Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Dayparts?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteDaypart).toHaveBeenCalledTimes(2);
    });
    const calledIds = vi
      .mocked(deleteDaypart)
      .mock.calls.map((c) => c[0])
      .sort();
    expect(calledIds).toEqual([1, 2]);
  });

  test('a partial bulk delete failure shows the error and refreshes the table', async () => {
    const user = userEvent.setup();
    // Row 1 resolves, row 2 rejects with a server message.
    vi.mocked(deleteDaypart).mockImplementation((id: number | string) => {
      if (id === 2) {
        return Promise.reject({
          isAxiosError: true,
          response: { data: { message: 'Daypart 2 is in use.' } },
        });
      }
      return Promise.resolve(undefined);
    });
    renderDaypartPage();
    await screen.findByText('Daypart Alpha');
    const initialFetchCount = vi.mocked(fetchDaypart).mock.calls.length;

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Dayparts?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByText('Daypart 2 is in use.')).toBeInTheDocument();
    expect(screen.getByText('Delete Dayparts?')).toBeInTheDocument();

    expect(deleteDaypart).toHaveBeenCalledTimes(2);
    await waitFor(() => {
      expect(vi.mocked(fetchDaypart).mock.calls.length).toBeGreaterThan(initialFetchCount);
    });
  });
});
