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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDisplay, SINGLE_DISPLAY } from './fixtures/display';
import { renderDisplaysPage } from './helpers/renderDisplaysPage';
import { mockFetchDisplays } from './mocks/displaysApi';

import { deleteDisplay } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/services/displaysApi');
vi.mock('@/services/displayGroupApi', () => ({
  fetchDisplayGroups: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  deleteDisplayGroup: vi.fn(),
}));
vi.mock('@/services/displayProfileApi', () => ({
  fetchDisplayProfile: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchDisplayProfileById: vi.fn().mockResolvedValue(null),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('../hooks/useDisplaysFilterOptions', () => ({
  useDisplaysFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Helpers
// =============================================================================

const openDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockDisplay.display);

  const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
  await user.click(checkboxes[1]!);

  const deleteBtn = await screen.findByRole('button', { name: /Delete Selected/i });
  await user.click(deleteBtn);

  return screen.findByRole('dialog');
};

// =============================================================================
// Tests
// =============================================================================

describe('Delete Display', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays(SINGLE_DISPLAY);
    vi.mocked(deleteDisplay).mockResolvedValue(undefined);
  });

  // ---------------------------------------------------------------------------
  // Selecting a row and clicking Delete Selected opens the confirmation modal.
  // ---------------------------------------------------------------------------
  test('Delete Selected opens the confirmation modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    const dialog = await openDeleteModal(user);

    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Delete Display?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockDisplay.display)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel closes the modal without calling the API.
  // ---------------------------------------------------------------------------
  test('Cancel closes the modal without calling deleteDisplay', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteDisplay).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Yes, Delete" on success calls deleteDisplay and closes modal.
  // ---------------------------------------------------------------------------
  test('successful delete calls deleteDisplay and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteDisplay).mockResolvedValueOnce(undefined);

    renderDisplaysPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteDisplay).toHaveBeenCalledTimes(1);
    expect(deleteDisplay).toHaveBeenCalledWith(mockDisplay.displayId);
  });

  // ---------------------------------------------------------------------------
  // While deletion is in progress, the confirm button should switch to
  // "Deleting…" and be disabled so the user cannot submit a second time.
  // ---------------------------------------------------------------------------
  test('Yes, Delete button is disabled with loading label while deletion is in progress', async () => {
    const user = userEvent.setup();
    let resolveDelete!: () => void;
    const controlledPromise = new Promise<void>((resolve) => {
      resolveDelete = resolve;
    });
    vi.mocked(deleteDisplay).mockReturnValue(controlledPromise);

    renderDisplaysPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
    });
    expect(deleteDisplay).toHaveBeenCalledTimes(1);

    // Resolve and wait for the component to settle before the test tears down.
    resolveDelete();
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Delete modal shows error and stays open when deletion fails.
  // ---------------------------------------------------------------------------
  test('failed delete keeps the modal open and shows an error', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteDisplay).mockRejectedValueOnce(new Error('Cannot delete display'));

    renderDisplaysPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(deleteDisplay).toHaveBeenCalledTimes(1);
    expect(await screen.findByText(/could not be deleted/i)).toBeInTheDocument();
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });
});
