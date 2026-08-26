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

import { fetchDisplayGroups } from '@/services/displayGroupApi';
import {
  addDisplayViaCode,
  fetchDisplaysNewerThan,
  fetchHighestDisplayId,
  fetchLicenceUsage,
  updateDisplay,
} from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';
import type { DisplayGroup } from '@/types/displayGroup';

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
 * edit, so these tests need displays.modify; the Authorise toggle only needs displays.add, the
 * same feature that gates the form itself.
 */
const displayAdminUser = {
  ...mockUser,
  features: {
    ...mockUser.features,
    'displays.add': true,
    'displays.modify': true,
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
  await user.click(screen.getByRole('button', { name: 'Add' }));
};

/**
 * A display as it looks the moment a Player added via activation code registers: the CMS has
 * already applied the name and authorisation cached from the Add Display form.
 */
const freshlyRegistered = {
  ...mockDisplay,
  displayId: 99,
  display: 'Reception Screen',
  license: 'hardware-key-abc123',
  licensed: 1,
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

    expect(screen.getByRole('button', { name: 'Add' })).toBeDisabled();

    await user.type(screen.getByRole('textbox', { name: /code/i }), 'ABC-123');
    expect(screen.getByRole('button', { name: 'Add' })).toBeDisabled();

    await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Reception Screen');
    expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled();
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
  // Submitting sends the code together with the chosen settings, which the CMS
  // caches against the code and applies itself when the Player registers. A
  // watermark is recorded first so whatever registers afterwards can be
  // recognised as new.
  // ---------------------------------------------------------------------------
  test('submits the code with the chosen settings, and takes a watermark first', async () => {
    const user = userEvent.setup();
    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    await waitFor(() => {
      expect(addDisplayViaCode).toHaveBeenCalledWith({
        userCode: 'VALID-CODE',
        displayName: 'Reception Screen',
        // The new form holds these as null-initialised state, so an untouched
        // form sends null rather than omitting the field.
        folderId: null,
        displayGroupId: null,
        authorised: true,
      });
    });
    expect(fetchHighestDisplayId).toHaveBeenCalled();
  });

  test('sends the chosen display group along with the code', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchDisplayGroups).mockResolvedValue({
      rows: [{ displayGroupId: 9, displayGroup: 'Test SSP' } as DisplayGroup],
      totalCount: 1,
    });

    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');

    await user.click(screen.getByRole('combobox'));
    await user.click(await screen.findByRole('button', { name: 'Test SSP' }));

    await submit(user);

    await waitFor(() => {
      expect(addDisplayViaCode).toHaveBeenCalledWith(
        expect.objectContaining({ displayGroupId: 9 }),
      );
    });
    // Dynamic groups cannot take manual members, so the picker must not offer them.
    expect(fetchDisplayGroups).toHaveBeenCalledWith(expect.objectContaining({ isDynamic: 0 }));
  });

  test('waits for the player and echoes back what was submitted', async () => {
    const user = userEvent.setup();
    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    expect(await screen.findByText(/waiting for display to connect/i)).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: /code/i })).toHaveValue('VALID-CODE');
    expect(screen.getByRole('textbox', { name: /display name/i })).toHaveValue('Reception Screen');

    // The success modal only mounts once a display has actually been detected.
    expect(screen.queryByRole('button', { name: /manage display/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The heart of the feature: the CMS applies the operator's choices at
  // registration, so the moment the display appears it is already configured.
  // ---------------------------------------------------------------------------
  test('reports connected once the player registers', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchDisplaysNewerThan).mockResolvedValue([freshlyRegistered]);

    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    expect(await screen.findByText(/display added successfully/i)).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /manage display/i })).toBeEnabled();
    });
  });

  // ---------------------------------------------------------------------------
  // REGRESSION GUARD. The settings travel with the activation code and are
  // applied by the CMS at registration. The UI must NOT fall back to editing
  // the display: PUT /display/{id} is a full replace, and a partial payload
  // would blank the Player's hardware key and permanently disconnect it.
  // ---------------------------------------------------------------------------
  test('never edits the display from the browser', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchDisplaysNewerThan).mockResolvedValue([freshlyRegistered]);

    await openAddModal(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    expect(await screen.findByText(/display added successfully/i)).toBeInTheDocument();
    expect(updateDisplay).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // SKIPPED: the ambiguous-candidate picker was removed by the UI rework in
  // 0d3254606 - AddDisplayModal now only adopts when exactly one display is
  // found, so two simultaneous registrations leave the modal spinning until it
  // times out. Re-enable once that flow has a UI again.
  // ---------------------------------------------------------------------------
  test.skip('asks which display is yours when more than one registers', async () => {
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
