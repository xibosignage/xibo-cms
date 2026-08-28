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
  assignDisplayGroups,
  fetchConnectCode,
  fetchConnectDetails,
  fetchConnectStatus,
  fetchDisplays,
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

const openAddModal = async (user: UserEvent, as = displayAdminUser) => {
  renderDisplaysPage(as);
  const addButton = await screen.findByRole('button', { name: /add display/i });
  await user.click(addButton);
  await screen.findByRole('dialog', { name: /^add display$/i });
};

/**
 * Add Display now opens on a chooser screen, so most tests have to pick a route first.
 * These wrap that step so the individual tests stay about behaviour, not navigation.
 */
const openCodeForm = async (user: UserEvent, as = displayAdminUser) => {
  await openAddModal(user, as);
  await user.click(await screen.findByRole('button', { name: /use activation code/i }));
};

const openManualForm = async (user: UserEvent, as = displayAdminUser) => {
  await openAddModal(user, as);
  await user.click(await screen.findByRole('button', { name: /manually configure/i }));
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
    // A pending code survives in localStorage by design, so it would otherwise leak between tests.
    window.localStorage.clear();
    mockFetchDisplays(EMPTY_DISPLAY_TABLE);

    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: 50,
      currentlyLicensed: 12,
      available: 38,
    });
    vi.mocked(addDisplayViaCode).mockResolvedValue(undefined);
    vi.mocked(assignDisplayGroups).mockResolvedValue(undefined);
    vi.mocked(updateDisplay).mockResolvedValue(freshlyRegistered);
    vi.mocked(fetchConnectDetails).mockResolvedValue({
      cmsAddress: 'https://cms.example.org',
      cmsKey: 'server-key-xyz',
    });
    vi.mocked(fetchConnectCode).mockResolvedValue({ code: '65A2', expiresInMinutes: 30 });
    // Default: the coded Player has not registered yet.
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: false,
      displayId: null,
      display: null,
    });
    vi.mocked(fetchDisplays).mockResolvedValue({ rows: [freshlyRegistered], totalCount: 1 });
  });

  // ---------------------------------------------------------------------------
  // Opening and validating the form.
  // ---------------------------------------------------------------------------
  test('clicking Add Display opens the connection method chooser', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    expect(screen.getByRole('dialog', { name: /^add display$/i })).toBeInTheDocument();
    expect(screen.getByText(/how would you like to connect your display/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /use activation code/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /manually configure/i })).toBeInTheDocument();
  });

  test('Save is disabled until both a code and a display name are entered', async () => {
    const user = userEvent.setup();
    await openCodeForm(user);

    expect(screen.getByRole('button', { name: 'Add' })).toBeDisabled();

    await user.type(screen.getByRole('textbox', { name: /code/i }), 'ABC-123');
    expect(screen.getByRole('button', { name: 'Add' })).toBeDisabled();

    await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Reception Screen');
    expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled();
  });

  test('shows how many licences are in use', async () => {
    const user = userEvent.setup();
    await openCodeForm(user);

    expect(await screen.findByText(/12 of 50 licences in use, 38 available/i)).toBeInTheDocument();
  });

  test('Cancel closes the modal without calling the API', async () => {
    const user = userEvent.setup();
    await openCodeForm(user);

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(screen.queryByRole('dialog', { name: /^add display$/i })).not.toBeInTheDocument();
    expect(addDisplayViaCode).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Submitting sends the code together with the chosen settings, which the CMS
  // caches against the code and applies itself when the Player registers.
  // ---------------------------------------------------------------------------
  test('submits the code with the chosen settings', async () => {
    const user = userEvent.setup();
    await openCodeForm(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    await waitFor(() => {
      expect(addDisplayViaCode).toHaveBeenCalledWith({
        userCode: 'VALID-CODE',
        displayName: 'Reception Screen',
        // The mock user has no home folder set, so the form falls back to
        // folder 1 rather than leaving it unset.
        folderId: 1,
        displayGroupId: null,
        authorised: true,
      });
    });
  });

  test('sends the chosen display group along with the code', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchDisplayGroups).mockResolvedValue({
      rows: [{ displayGroupId: 9, displayGroup: 'Test SSP' } as DisplayGroup],
      totalCount: 1,
    });

    await openCodeForm(user);
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
    await openCodeForm(user);
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
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 99,
      display: 'Reception Screen',
    });

    await openCodeForm(user);
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
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 99,
      display: 'Reception Screen',
    });

    await openCodeForm(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    expect(await screen.findByText(/display added successfully/i)).toBeInTheDocument();
    expect(updateDisplay).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Ambiguity is gone from BOTH routes: the CMS records which Player presented
  // the code, so nothing else registering at the same moment can be mistaken
  // for this one. Replaces the old "which one is yours?" picker.
  // ---------------------------------------------------------------------------
  test('activation code identifies the player by its code, not the display list', async () => {
    const user = userEvent.setup();

    await openCodeForm(user);
    await fillRequiredFields(user, 'VALID-CODE', 'Reception Screen');
    await submit(user);

    // The submitted code is what is watched - never the display list.
    await waitFor(() => {
      expect(fetchConnectStatus).toHaveBeenCalledWith('VALID-CODE');
    });
  });

  // ---------------------------------------------------------------------------
  // Manual configuration. There is no activation code, so the CMS cannot
  // correlate the registration and applies nothing itself - the form waits for
  // the Player, then saves the chosen settings against the display it created.
  // ---------------------------------------------------------------------------
  test('manual configuration shows the CMS address and key to copy', async () => {
    const user = userEvent.setup();
    await openManualForm(user);

    // The fields are read-only display text, not inputs - there is nothing for the
    // operator to edit, only to copy.
    expect(await screen.findByText('https://cms.example.org')).toBeInTheDocument();
    // The one-time code rides on the key, so there is a single value to copy.
    expect(screen.getByText('server-key-xyz||65A2')).toBeInTheDocument();

    // No activation code field is involved in this route.
    expect(screen.queryByRole('textbox', { name: /activation code/i })).not.toBeInTheDocument();
  });

  test('manual configuration locks Save and Cancel until the player connects', async () => {
    const user = userEvent.setup();
    await openManualForm(user);

    await user.type(await screen.findByRole('textbox', { name: /display name/i }), 'Foyer Screen');

    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
    expect(screen.getByRole('button', { name: /cancel/i })).toBeDisabled();
    // Back is never locked, so the operator is not trapped while waiting.
    expect(screen.getByRole('button', { name: /back/i })).toBeEnabled();
    expect(await screen.findByText(/waiting for display to connect/i)).toBeInTheDocument();
  });

  test('manual configuration saves the chosen settings once the player connects', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 99,
      display: 'Foyer Screen',
    });

    await openManualForm(user);

    // The name comes from the Player, so the form needs no typing here.
    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /display name/i })).toHaveValue('Foyer Screen');
    });

    // Detection only unlocks the form - nothing is saved until the operator says so.
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();
    });
    expect(updateDisplay).not.toHaveBeenCalled();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateDisplay).toHaveBeenCalledWith(
        99,
        expect.objectContaining({ display: 'Foyer Screen', licensed: 1 }),
      );
    });

    // REGRESSION GUARD. PUT /display/{id} is a full replace: omitting `license` blanks the
    // Player's hardware key and permanently disconnects it.
    const payload = vi.mocked(updateDisplay).mock.calls[0]?.[1];
    expect(payload?.license).toBe('hardware-key-abc123');

    // The activation-code relay has no part in this route.
    expect(addDisplayViaCode).not.toHaveBeenCalled();
  });

  test('manual configuration assigns the chosen display group on save', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 99,
      display: 'Foyer Screen',
    });
    vi.mocked(fetchDisplayGroups).mockResolvedValue({
      rows: [{ displayGroupId: 9, displayGroup: 'Test SSP' } as DisplayGroup],
      totalCount: 1,
    });

    await openManualForm(user);
    await screen.findByRole('textbox', { name: /display name/i });

    await user.click(screen.getByRole('combobox'));
    await user.click(await screen.findByRole('button', { name: 'Test SSP' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();
    });
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(assignDisplayGroups).toHaveBeenCalledWith(99, [9]);
    });
  });

  // ---------------------------------------------------------------------------
  // The point of the one-time code: correlation is an exact match by the CMS, not
  // a guess from the display list, so a second Player registering in the same
  // window cannot be mistaken for this one.
  // ---------------------------------------------------------------------------
  test('manual configuration identifies the player by its code, not the display list', async () => {
    const user = userEvent.setup();

    await openManualForm(user);
    await user.type(await screen.findByRole('textbox', { name: /display name/i }), 'Foyer Screen');

    expect(await screen.findByText(/waiting for display to connect/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();

    expect(fetchConnectStatus).toHaveBeenCalledWith('65A2');
  });

  test('manual configuration reports an expired code', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: true,
      connected: false,
      displayId: null,
      display: null,
    });

    await openManualForm(user);

    expect(await screen.findByText(/this code has expired/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
  });

  test('manual configuration adopts the name given on the player', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 99,
      display: 'Lobby TV',
    });

    await openManualForm(user);

    // The operator named it on the device, so the form starts from that.
    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /display name/i })).toHaveValue('Lobby TV');
    });
  });

  test('a name typed in the CMS is not overwritten by the player', async () => {
    const user = userEvent.setup();
    await openManualForm(user);
    await user.type(await screen.findByRole('textbox', { name: /display name/i }), 'Foyer Screen');

    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 99,
      display: 'Lobby TV',
    });

    // The watcher polls every few seconds, so allow for a cycle rather than the 1s default.
    await waitFor(
      () => {
        expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();
      },
      { timeout: 8000 },
    );
    expect(screen.getByRole('textbox', { name: /display name/i })).toHaveValue('Foyer Screen');
  });

  // ---------------------------------------------------------------------------
  // REGRESSION GUARD. Both routes remember a pending code in the same place, but
  // they are not interchangeable: resuming an activation code as a manual one
  // would offer it as the CMS key, which is meaningless to a Player.
  // ---------------------------------------------------------------------------
  test('a pending activation code is never resumed as a manual one', async () => {
    const user = userEvent.setup();

    // Leave an activation code behind, exactly as submitting one does.
    await openCodeForm(user);
    await fillRequiredFields(user, 'LEFTOVER-ACTIVATION', 'Reception Screen');
    await submit(user);
    await screen.findByText(/waiting for display to connect/i);

    await user.click(screen.getByRole('button', { name: /back/i }));
    await user.click(await screen.findByRole('button', { name: /manually configure/i }));

    // The manual route must issue its own code, not adopt the activation one.
    expect(await screen.findByText('server-key-xyz||65A2')).toBeInTheDocument();
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

    await openCodeForm(user);
    await fillRequiredFields(user, 'WRONG-CODE', 'Reception Screen');
    await submit(user);

    expect(screen.getByRole('dialog', { name: /^add display$/i })).toBeInTheDocument();
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The code provided does not match. Please double-check the code shown on the device you are trying to connect.',
    );
  });

  test('form is blank when the modal is reopened after being closed', async () => {
    const user = userEvent.setup();
    await openCodeForm(user);

    await fillRequiredFields(user, 'LEFTOVER-CODE', 'Leftover Name');
    await user.click(screen.getByRole('button', { name: /cancel/i }));

    await user.click(screen.getByRole('button', { name: /add display/i }));
    await screen.findByRole('dialog', { name: /^add display$/i });

    // Reopening returns to the chooser, so the route has to be picked again.
    await user.click(await screen.findByRole('button', { name: /use activation code/i }));

    expect(screen.getByRole('textbox', { name: /code/i })).toHaveValue('');
    expect(screen.getByRole('textbox', { name: /display name/i })).toHaveValue('');
  });
});
