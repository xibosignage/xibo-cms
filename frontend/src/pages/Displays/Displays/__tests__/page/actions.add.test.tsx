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

import { EMPTY_DISPLAY_TABLE, mockDisplay, mockUser } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import {
  addDisplayViaCode,
  fetchDisplaysNewerThan,
  fetchHighestDisplayId,
  fetchLicenceUsage,
  updateDisplay,
} from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

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

/**
 * The default mock user only holds folder.view. Naming and filing a display while adding it is an
 * edit, so these tests need displays.modify; the Authorise toggle needs displays.authorise.
 */
const displayAdminUser = {
  ...mockUser,
  features: {
    ...mockUser.features,
    'displays.add': true,
    'displays.modify': true,
    'displays.authorise': true,
  },
};

const openAddModal = async (user: UserEvent) => {
  renderDisplaysPage(displayAdminUser);
  const addButton = await screen.findByRole('button', { name: /add display/i });
  await user.click(addButton);
  await screen.findByRole('dialog', { name: /^add display$/i });
};

const fillRequiredFields = async (user: UserEvent, code: string, displayName: string) => {
  await user.type(screen.getByRole('textbox', { name: /code/i }), code);
  await user.type(screen.getByRole('textbox', { name: /display name/i }), displayName);
};

const submit = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: 'Save' }));
};

/** A display as it looks the moment a Player registers: default name, unauthorised. */
const freshlyRegistered = {
  ...mockDisplay,
  displayId: 99,
  display: 'chromeOS Player',
  license: 'hardware-key-abc123',
  licensed: 0,
  folderId: 1,
};

// =============================================================================
// Tests
// =============================================================================

describe('Displays page - add display', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays(EMPTY_DISPLAY_TABLE);

    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: 50,
      currentlyLicensed: 12,
      available: 38,
    });
    vi.mocked(fetchHighestDisplayId).mockResolvedValue(98);
    vi.mocked(addDisplayViaCode).mockResolvedValue(undefined);
    // Default: the Player has not registered yet.
    vi.mocked(fetchDisplaysNewerThan).mockResolvedValue([]);
    vi.mocked(updateDisplay).mockResolvedValue(freshlyRegistered);
  });

  // ---------------------------------------------------------------------------
  // Opening and validating the form.
  // ---------------------------------------------------------------------------
  test('clicking Add Display opens the Add Display modal', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    expect(screen.getByRole('dialog', { name: /^add display$/i })).toBeInTheDocument();
  });

  test('Save is disabled until both a code and a display name are entered', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();

    await user.type(screen.getByRole('textbox', { name: /code/i }), 'ABC-123');
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();

    await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Reception Screen');
    expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();
  });

  test('shows how many licences are in use', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    expect(await screen.findByText(/12 of 50 licences in use, 38 available/i)).toBeInTheDocument();
  });

  test('Cancel closes the modal without calling the API', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(screen.queryByRole('dialog', { name: /^add display$/i })).not.toBeInTheDocument();
    expect(addDisplayViaCode).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Submitting sends only the code, and records a watermark first so whatever
  // registers afterwards can be recognised as new.
  // ---------------------------------------------------------------------------
  test('submits only the activation code, and takes a watermark first', async () => {
    const user = userEvent.setup();
    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    await waitFor(() => {
      expect(addDisplayViaCode).toHaveBeenCalledWith('VALID-CODE');
    });
    expect(fetchHighestDisplayId).toHaveBeenCalled();
  });

  test('waits for the player and echoes back what was submitted', async () => {
    const user = userEvent.setup();
    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    expect(await screen.findByText(/waiting for your player to connect/i)).toBeInTheDocument();
    expect(screen.getByText('VALID-CODE')).toBeInTheDocument();
    expect(screen.getByText('Reception Screen')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /manage display/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // The heart of the feature: adopt the newly registered display and apply the
  // operator's choices to it.
  // ---------------------------------------------------------------------------
  test('applies the name, folder and authorisation once the player registers', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchDisplaysNewerThan).mockResolvedValue([freshlyRegistered]);

    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    await waitFor(() => {
      expect(updateDisplay).toHaveBeenCalledWith(
        99,
        expect.objectContaining({ display: 'Reception Screen', licensed: 1 }),
      );
    });

    expect(await screen.findByText(/connected\. your display is ready/i)).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /manage display/i })).toBeEnabled();
    });
  });

  // ---------------------------------------------------------------------------
  // REGRESSION GUARD. PUT /display/{id} is a full replace: omitting `license`
  // blanks the Player's hardware key and permanently disconnects it.
  // ---------------------------------------------------------------------------
  test('preserves the hardware key when applying settings', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchDisplaysNewerThan).mockResolvedValue([freshlyRegistered]);

    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    await waitFor(() => {
      expect(updateDisplay).toHaveBeenCalled();
    });

    const payload = vi.mocked(updateDisplay).mock.calls[0]?.[1];
    expect(payload?.license).toBe('hardware-key-abc123');
  });

  // ---------------------------------------------------------------------------
  // Two displays registering at once cannot be told apart, so ask.
  // ---------------------------------------------------------------------------
  test('asks which display is yours when more than one registers', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchDisplaysNewerThan).mockResolvedValue([
      freshlyRegistered,
      { ...freshlyRegistered, displayId: 100, license: 'hardware-key-other' },
    ]);

    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    expect(await screen.findByText(/which one is yours/i)).toBeInTheDocument();
    expect(updateDisplay).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Failure paths.
  // ---------------------------------------------------------------------------
  test('wrong code keeps the modal open and shows the server error message', async () => {
    const user = userEvent.setup();
    vi.mocked(addDisplayViaCode).mockRejectedValueOnce({
      isAxiosError: true,
      response: {
        data: {
          message:
            'The code provided does not match. Please double-check the code shown on the device you are trying to connect.',
        },
      },
    });

    await openAddModal(user);
    await fillRequiredFields(user, 'WRONG-CODE', 'Reception Screen');
    await submit(user);

    expect(screen.getByRole('dialog', { name: /^add display$/i })).toBeInTheDocument();
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The code provided does not match. Please double-check the code shown on the device you are trying to connect.',
    );
  });

  test('form is blank when the modal is reopened after being closed', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    await fillRequiredFields(user, 'LEFTOVER-CODE', 'Leftover Name');
    await user.click(screen.getByRole('button', { name: /cancel/i }));

    await user.click(screen.getByRole('button', { name: /add display/i }));
    await screen.findByRole('dialog', { name: /^add display$/i });

    expect(screen.getByRole('textbox', { name: /code/i })).toHaveValue('');
    expect(screen.getByRole('textbox', { name: /display name/i })).toHaveValue('');
  });
});
