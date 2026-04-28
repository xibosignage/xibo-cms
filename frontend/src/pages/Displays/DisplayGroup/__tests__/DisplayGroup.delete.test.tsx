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

import { mockDisplayGroup, SINGLE_DISPLAY_GROUP } from './fixtures/displayGroup';
import { renderDisplayGroupPage } from './helpers/renderDisplayGroupPage';
import { mockFetchDisplayGroups } from './mocks/displayGroupApi';

import { deleteDisplayGroup } from '@/services/displayGroupApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/services/displayGroupApi');
vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  sendCommand: vi.fn(),
  triggerWebhook: vi.fn(),
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

// =============================================================================
// Helpers
// =============================================================================

const openDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockDisplayGroup.displayGroup);

  const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
  await user.click(checkboxes[0]!);

  const deleteBtn = await screen.findByRole('button', { name: /Delete Selected/i });
  await user.click(deleteBtn);

  return screen.findByRole('dialog');
};

// =============================================================================
// Tests
// =============================================================================

describe('Delete Display Group', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayGroups(SINGLE_DISPLAY_GROUP);
  });

  // ---------------------------------------------------------------------------
  // Selecting a row and clicking Delete Selected opens the confirmation modal.
  // ---------------------------------------------------------------------------
  test('Delete Selected opens the confirmation modal', async () => {
    const user = userEvent.setup();
    renderDisplayGroupPage();

    const dialog = await openDeleteModal(user);

    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Delete Display Group?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockDisplayGroup.displayGroup)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel closes the modal without calling the API.
  // ---------------------------------------------------------------------------
  test('Cancel closes the modal without calling deleteDisplayGroup', async () => {
    const user = userEvent.setup();
    renderDisplayGroupPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteDisplayGroup).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Yes, Delete" on success calls deleteDisplayGroup and closes modal.
  // ---------------------------------------------------------------------------
  test('successful delete calls deleteDisplayGroup and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteDisplayGroup).mockResolvedValueOnce(undefined);

    renderDisplayGroupPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteDisplayGroup).toHaveBeenCalledTimes(1);
    expect(deleteDisplayGroup).toHaveBeenCalledWith(mockDisplayGroup.displayGroupId);
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
    vi.mocked(deleteDisplayGroup).mockReturnValue(controlledPromise);

    renderDisplayGroupPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
    });
    expect(deleteDisplayGroup).toHaveBeenCalledTimes(1);

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
    vi.mocked(deleteDisplayGroup).mockRejectedValueOnce(new Error('Cannot delete group'));

    renderDisplayGroupPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(deleteDisplayGroup).toHaveBeenCalledTimes(1);
    expect(await screen.findByText(/could not be deleted/i)).toBeInTheDocument();
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });
});
