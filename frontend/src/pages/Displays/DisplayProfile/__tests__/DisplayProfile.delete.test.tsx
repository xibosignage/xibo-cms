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

import { screen, fireEvent, waitFor, within } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  mockDisplayProfile,
  MULTIPLE_DISPLAY_PROFILES,
  SINGLE_DISPLAY_PROFILE,
} from './fixtures/displayProfile';
import { clickRowMenuItem, renderDisplayProfilePage } from './helpers/renderDisplayProfilePage';
import { mockFetchDisplayProfile } from './mocks/displayProfileApi';

import { deleteDisplayProfile, fetchDisplayProfile } from '@/services/displayProfileApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displayProfileApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const openDeleteModal = async () => {
  await screen.findByText(mockDisplayProfile.name);

  const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
  fireEvent.click(checkboxes[0]!);

  const deleteBtn = await screen.findByRole('button', { name: /Delete Selected/i });
  fireEvent.click(deleteBtn);

  return screen.findByRole('dialog');
};

// Selects the first `rowCount` rows and opens the bulk confirmation modal.
const openBulkDeleteModal = async (rowCount: number) => {
  await screen.findByText(mockDisplayProfile.name);

  const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
  for (let i = 0; i < rowCount; i++) {
    fireEvent.click(checkboxes[i]!);
  }

  fireEvent.click(await screen.findByRole('button', { name: /Delete Selected/i }));

  return screen.findByRole('dialog');
};

// =============================================================================
// Tests
// =============================================================================

// Full-page render + interaction can exceed the 5s default under parallel
// JSDOM contention (each test still runs in ~1s in isolation).
vi.setConfig({ testTimeout: 20_000 });

describe('DisplayProfile page - delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
  });

  // ---------------------------------------------------------------------------
  // Selecting a row and clicking Delete Selected opens the confirmation modal.
  // ---------------------------------------------------------------------------
  test('Delete Selected opens the confirmation modal', async () => {
    renderDisplayProfilePage();

    const dialog = await openDeleteModal();

    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Delete Display Profile?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockDisplayProfile.name)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Opening delete shows only the Delete modal — not Add, Edit, or Copy.
  // ---------------------------------------------------------------------------
  test('only the Delete modal opens (not Add, Edit, or Copy)', async () => {
    renderDisplayProfilePage();

    await openDeleteModal();

    expect(screen.getAllByRole('dialog')).toHaveLength(1);
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel closes the modal without calling the API.
  // ---------------------------------------------------------------------------
  test('Cancel closes the modal without calling deleteDisplayProfile', async () => {
    renderDisplayProfilePage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteDisplayProfile).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Yes, Delete" on success calls deleteDisplayProfile and closes the modal.
  // ---------------------------------------------------------------------------
  test('successful delete calls deleteDisplayProfile and closes the modal', async () => {
    vi.mocked(deleteDisplayProfile).mockResolvedValueOnce(undefined);

    renderDisplayProfilePage();
    await openDeleteModal();

    const fetchCountBefore = vi.mocked(fetchDisplayProfile).mock.calls.length;
    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteDisplayProfile).toHaveBeenCalledTimes(1);
    expect(deleteDisplayProfile).toHaveBeenCalledWith(mockDisplayProfile.displayProfileId);
    // Success triggers handleRefresh → query invalidation → a refetch.
    await waitFor(() => {
      expect(vi.mocked(fetchDisplayProfile).mock.calls.length).toBeGreaterThan(fetchCountBefore);
    });
  });

  // ---------------------------------------------------------------------------
  // Delete modal shows error and stays open when deletion fails.
  // ---------------------------------------------------------------------------
  test('failed delete keeps the modal open and shows an error', async () => {
    vi.mocked(deleteDisplayProfile).mockRejectedValueOnce(new Error('Cannot delete profile'));

    renderDisplayProfilePage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.getByText('1 item(s) could not be deleted.')).toBeInTheDocument();
    });
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The Delete action in a row's menu opens the same confirmation modal,
  // pre-loaded with just that one profile (singular heading + name).
  // ---------------------------------------------------------------------------
  test('choosing Delete from a row menu opens the confirmation modal', async () => {
    renderDisplayProfilePage();

    await clickRowMenuItem(/^delete$/i);

    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByText('Delete Display Profile?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockDisplayProfile.name)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // While the delete request is in flight the confirm button reads "Deleting…"
  // and is disabled, so the user can't double-submit.
  // ---------------------------------------------------------------------------
  test('the Delete button shows "Deleting…" while the request is in progress', async () => {
    let resolveDelete: () => void = () => {};
    vi.mocked(deleteDisplayProfile).mockReturnValueOnce(
      new Promise<void>((resolve) => {
        resolveDelete = () => resolve();
      }),
    );

    renderDisplayProfilePage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(await screen.findByRole('button', { name: /deleting/i })).toBeDisabled();

    resolveDelete();
  });

  // ---------------------------------------------------------------------------
  // Selecting more than one row switches the modal to its plural copy and shows
  // the count instead of a single profile name.
  // ---------------------------------------------------------------------------
  test('selecting multiple rows shows the plural heading and the count', async () => {
    mockFetchDisplayProfile(MULTIPLE_DISPLAY_PROFILES);
    renderDisplayProfilePage();

    const dialog = await openBulkDeleteModal(2);

    expect(within(dialog).getByText('Delete Display Profiles?')).toBeInTheDocument();
    expect(dialog).toHaveTextContent(/2 display profiles/i);
  });

  // ---------------------------------------------------------------------------
  // Confirming a bulk delete fires one API call per selected profile.
  // ---------------------------------------------------------------------------
  test('confirming a bulk delete calls deleteDisplayProfile for each selected profile', async () => {
    vi.mocked(deleteDisplayProfile).mockResolvedValue(undefined);
    mockFetchDisplayProfile(MULTIPLE_DISPLAY_PROFILES);
    renderDisplayProfilePage();

    await openBulkDeleteModal(2);
    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(deleteDisplayProfile).toHaveBeenCalledTimes(2);
    });
    expect(deleteDisplayProfile).toHaveBeenCalledWith(1);
    expect(deleteDisplayProfile).toHaveBeenCalledWith(2);
  });

  // ---------------------------------------------------------------------------
  // If some deletes succeed and some fail, the modal stays open and reports how
  // many could not be deleted.
  // ---------------------------------------------------------------------------
  test('a partial bulk failure shows an error and keeps the modal open', async () => {
    vi.mocked(deleteDisplayProfile)
      .mockResolvedValueOnce(undefined)
      .mockRejectedValueOnce(new Error('Cannot delete profile'));
    mockFetchDisplayProfile(MULTIPLE_DISPLAY_PROFILES);
    renderDisplayProfilePage();

    await openBulkDeleteModal(2);
    const fetchCountBefore = vi.mocked(fetchDisplayProfile).mock.calls.length;
    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.getByText('1 item(s) could not be deleted.')).toBeInTheDocument();
    });
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    // The failure branch clears the selection and refreshes the table.
    await waitFor(() => {
      const rowCheckboxes = screen.getAllByRole('checkbox', {
        name: /select row/i,
      }) as HTMLInputElement[];
      expect(rowCheckboxes.every((cb) => !cb.checked)).toBe(true);
    });
    expect(vi.mocked(fetchDisplayProfile).mock.calls.length).toBeGreaterThan(fetchCountBefore);
  });

  // ---------------------------------------------------------------------------
  // This page has no Share feature — neither a bulk "Share Selected" action nor
  // a Share item in the row menu.
  // ---------------------------------------------------------------------------
  test('the page exposes no Share action (no row-menu Share, no "Share Selected")', async () => {
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    // Selecting a row reveals "Delete Selected" but never a "Share Selected".
    fireEvent.click(screen.getAllByRole('checkbox', { name: /select row/i })[0]!);
    expect(await screen.findByRole('button', { name: /delete selected/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /share selected/i })).not.toBeInTheDocument();

    // The row actions menu offers Copy/Delete but no Share.
    fireEvent.click(screen.getByRole('button', { name: /more actions/i }));
    expect(await screen.findByRole('button', { name: /^copy$/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^share$/i })).not.toBeInTheDocument();
  });
});
