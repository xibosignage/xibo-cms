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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EMPTY_DISPLAY_TABLE } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import { notify } from '@/components/ui/Notification';
import { addDisplayViaCode } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => {
  const t = (key: string) => key;
  return {
    useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
    Trans: ({ children }: { children: React.ReactNode }) => children,
  };
});

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
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn() },
}));
vi.mock('../../hooks/useDisplaysFilterOptions', () => ({
  useDisplaysFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Helpers
// =============================================================================

const openAddModal = async (user: UserEvent) => {
  renderDisplaysPage();
  const addButton = await screen.findByRole('button', { name: /add display/i });
  await user.click(addButton);
  await screen.findByRole('dialog', { name: /add display via code/i });
};

// =============================================================================
// Tests
// =============================================================================

describe('Displays page - add display', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays(EMPTY_DISPLAY_TABLE);
  });

  // ---------------------------------------------------------------------------
  // Clicking the Add Display button on the page header opens the Add modal.
  // ---------------------------------------------------------------------------
  test('clicking Add Display opens the Add Display modal', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    expect(screen.getByRole('dialog', { name: /add display via code/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The Save button must be disabled until the user has typed something into
  // the code field — submitting an empty code would be a no-op on the server.
  // ---------------------------------------------------------------------------
  test('Save button is disabled when the code field is empty', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Typing any non-whitespace character into the code field enables the button.
  // ---------------------------------------------------------------------------
  test('Save button becomes enabled once a code is typed', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    await user.type(screen.getByRole('textbox', { name: /code/i }), 'ABC-123');

    expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel must close the modal without contacting the server.
  // ---------------------------------------------------------------------------
  test('Cancel closes the modal without calling the API', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(screen.queryByRole('dialog', { name: /add display via code/i })).not.toBeInTheDocument();
    expect(addDisplayViaCode).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // A correct code triggers the success notification and closes the modal.
  // The notification message "CMS Credentials Added" confirms to the user that
  // the CMS address and key have been accepted by the authentication service.
  // ---------------------------------------------------------------------------
  test('correct code shows "CMS Credentials Added" notification and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(addDisplayViaCode).mockResolvedValue(undefined);

    await openAddModal(user);
    await user.type(screen.getByRole('textbox', { name: /code/i }), 'VALID-CODE');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(notify.success).toHaveBeenCalledWith('CMS Credentials Added');
    });
    expect(screen.queryByRole('dialog', { name: /add display via code/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // A wrong code returns an error from the server. The modal must stay open
  // and show the exact message so the user knows what went wrong.
  // ---------------------------------------------------------------------------
  test('wrong code keeps the modal open and shows the server error message', async () => {
    const user = userEvent.setup();
    vi.mocked(addDisplayViaCode).mockRejectedValueOnce({
      response: {
        data: {
          message:
            'The code provided does not match. Please double-check the code shown on the device you are trying to connect.',
        },
      },
    });

    await openAddModal(user);
    await user.type(screen.getByRole('textbox', { name: /code/i }), 'WRONG-CODE');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(screen.getByRole('dialog', { name: /add display via code/i })).toBeInTheDocument();
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The code provided does not match. Please double-check the code shown on the device you are trying to connect.',
    );
  });

  // ---------------------------------------------------------------------------
  // The code field must be blank every time the modal opens so a previously
  // typed (and rejected) code cannot accidentally be submitted for a different
  // display. The useEffect in AddDisplayModal resets state whenever isOpen
  // flips from false back to true.
  // ---------------------------------------------------------------------------
  test('code field is blank when the modal is reopened after being closed', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    await user.type(screen.getByRole('textbox', { name: /code/i }), 'LEFTOVER-CODE');
    await user.click(screen.getByRole('button', { name: /cancel/i }));

    // Reopen the modal
    await user.click(screen.getByRole('button', { name: /add display/i }));
    await screen.findByRole('dialog', { name: /add display via code/i });

    expect(screen.getByRole('textbox', { name: /code/i })).toHaveValue('');
  });
});
