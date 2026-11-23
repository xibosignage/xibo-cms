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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import AddDisplayModal from '../../../components/AddDisplay/AddDisplayModal';
import {
  readPendingCode,
  writePendingCode,
} from '../../../components/AddDisplay/hooks/usePendingConnect';
import { buildDisplay, mockUser } from '../../fixtures/display';

import { UserProvider } from '@/context/UserContext';
import {
  addDisplayViaCode,
  applyConnectSettings,
  fetchConnectCode,
  fetchConnectDetails,
  fetchConnectStatus,
  fetchDisplays,
  fetchLicenceUsage,
  type ConnectApplied,
} from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displaysApi', () => ({
  addDisplayViaCode: vi.fn(),
  applyConnectSettings: vi.fn(),
  fetchConnectCode: vi.fn(),
  fetchConnectDetails: vi.fn(),
  fetchConnectStatus: vi.fn(),
  fetchDisplays: vi.fn(),
  fetchLicenceUsage: vi.fn(),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn() },
}));
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/pages/Administration/Connectors/components/DisplayGroupSelect', () => ({
  default: () => null,
}));

// =============================================================================
// Helpers
// =============================================================================

const connectedDisplay = buildDisplay({ displayId: 5, display: 'Lobby', licensed: 1 });

const renderModal = (
  user: User = mockUser,
  prefill: React.ComponentProps<typeof AddDisplayModal>['prefill'] = null,
) => {
  const onClose = vi.fn();
  const onAdded = vi.fn();
  const { unmount } = render(
    <MemoryRouter>
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={user}>
          <AddDisplayModal onClose={onClose} onAdded={onAdded} prefill={prefill} />
        </UserProvider>
      </QueryClientProvider>
    </MemoryRouter>,
  );
  return { onClose, onAdded, unmount };
};

/** A promise the test settles by hand, to look at the form while a request is in flight. */
function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((r) => {
    resolve = r;
  });
  return { promise, resolve };
}

const chooseActivationCode = async (user: UserEvent) => {
  await user.click(await screen.findByRole('button', { name: /use activation code/i }));
};

// =============================================================================
// Tests
// =============================================================================

describe('Add Display wizard', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    window.localStorage.clear();
    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: 10,
      currentlyLicensed: 3,
      available: 7,
    });
    vi.mocked(fetchDisplays).mockResolvedValue({ rows: [connectedDisplay], totalCount: 1 });
  });

  test('shows the licence usage under Authorise automatically', async () => {
    const user = userEvent.setup();
    renderModal();
    await chooseActivationCode(user);

    expect(await screen.findByText('3 of 10 licences in use, 7 available.')).toBeInTheDocument();
  });

  test('shows no licence message when the CMS sets no maximum', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: null,
      currentlyLicensed: 3,
      available: null,
    });
    renderModal();
    await chooseActivationCode(user);

    await screen.findByRole('switch', { name: 'Authorise automatically' });
    await waitFor(() =>
      expect(screen.queryByText('Loading licence information...')).not.toBeInTheDocument(),
    );
    expect(screen.queryByText(/licences? (available|in use)/)).not.toBeInTheDocument();
  });

  // Authorising with every licence in use fails the whole save, so it isn't offered.
  test('with every licence in use, Authorise is switched off and disabled', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: 3,
      currentlyLicensed: 3,
      available: 0,
    });
    renderModal();
    await chooseActivationCode(user);

    await screen.findByText('3 of 3 licences in use, 0 available.');
    const authorise = screen.getByRole('switch', { name: 'Authorise automatically' });
    expect(authorise).toBeDisabled();
    expect(authorise).toHaveAttribute('aria-checked', 'false');
    expect(screen.getByRole('link', { name: 'Add more licences in Billing' })).toHaveAttribute(
      'href',
      '/administration/billing',
    );
  });

  // A licence with no displays: 0 is a real limit, not unlimited.
  test('with a limit of 0, Authorise is switched off and disabled', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: 0,
      currentlyLicensed: 0,
      available: 0,
    });
    renderModal();
    await chooseActivationCode(user);

    await screen.findByText('0 of 0 licences in use, 0 available.');
    expect(screen.getByRole('switch', { name: 'Authorise automatically' })).toBeDisabled();
  });

  describe('activation code', () => {
    test('Add stays disabled until a code and a name are entered', async () => {
      const user = userEvent.setup();
      renderModal();
      await chooseActivationCode(user);

      const add = screen.getByRole('button', { name: 'Add' });
      await waitFor(() => expect(add).toBeDisabled());

      await user.type(screen.getByRole('textbox', { name: /activation code/i }), 'AB12');
      expect(add).toBeDisabled();

      await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Lobby');
      await waitFor(() => expect(add).toBeEnabled());
    });

    test('submits the code with its settings, then shows the connected display', async () => {
      const user = userEvent.setup();
      vi.mocked(addDisplayViaCode).mockResolvedValue(undefined);
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: true,
        displayId: 5,
        display: 'Lobby',
      });
      const { onAdded } = renderModal();
      await chooseActivationCode(user);

      await user.type(screen.getByRole('textbox', { name: /activation code/i }), 'AB12');
      await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Lobby');
      await waitFor(() => expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Add' }));

      expect(addDisplayViaCode).toHaveBeenCalledWith(
        expect.objectContaining({ userCode: 'AB12', displayName: 'Lobby', authorised: true }),
      );
      expect(
        await screen.findByRole('dialog', { name: 'Display Added Successfully' }),
      ).toBeInTheDocument();
      expect(fetchConnectStatus).toHaveBeenCalledWith('AB12');
      expect(onAdded).toHaveBeenCalled();
    });

    // The CMS licenses the display when the Player registers, after the form read the count.
    test('the success panel counts the licence the new display took', async () => {
      const user = userEvent.setup();
      const recount = deferred<{
        maxLicensed: number;
        currentlyLicensed: number;
        available: number;
      }>();
      vi.mocked(fetchLicenceUsage)
        .mockResolvedValueOnce({ maxLicensed: 10, currentlyLicensed: 3, available: 7 })
        .mockReturnValue(recount.promise);
      vi.mocked(addDisplayViaCode).mockResolvedValue(undefined);
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: true,
        displayId: 5,
        display: 'Lobby',
      });
      renderModal();
      await chooseActivationCode(user);

      await screen.findByText('3 of 10 licences in use, 7 available.');
      await user.type(screen.getByRole('textbox', { name: /activation code/i }), 'AB12');
      await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Lobby');
      await waitFor(() => expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Add' }));

      const success = await screen.findByRole('dialog', { name: 'Display Added Successfully' });

      // While the count is read again, a spinner stands in for it rather than the old figure.
      expect(
        within(success).getByRole('status', { name: 'Loading licence information...' }),
      ).toBeInTheDocument();
      expect(within(success).queryByText(/3 of 10/)).not.toBeInTheDocument();

      recount.resolve({ maxLicensed: 10, currentlyLicensed: 4, available: 6 });

      expect(
        await within(success).findByText('| 4 of 10 licences in use, 6 available.'),
      ).toBeInTheDocument();
      expect(within(success).queryByRole('status')).not.toBeInTheDocument();
    });

    // e.g. the Player connected once without the code before it was set up with it.
    test('a Player that was already registered ends the wait and says so', async () => {
      const user = userEvent.setup();
      vi.mocked(addDisplayViaCode).mockResolvedValue(undefined);
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: true,
        displayId: 5,
        display: 'Lobby',
        existing: true,
      });
      renderModal();
      await chooseActivationCode(user);

      await user.type(screen.getByRole('textbox', { name: /activation code/i }), 'AB12');
      await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Reception');
      await waitFor(() => expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Add' }));

      expect(
        await screen.findByRole('dialog', { name: 'Display Already Registered' }),
      ).toBeInTheDocument();
      expect(readPendingCode()).toBeNull();
    });

    test('a rejected code keeps the form open and shows the server message', async () => {
      const user = userEvent.setup();
      const message =
        'The code provided does not match. Please double-check the code shown on the device you are trying to connect.';
      vi.mocked(addDisplayViaCode).mockRejectedValueOnce({
        isAxiosError: true,
        response: { data: { message } },
      });
      renderModal();
      await chooseActivationCode(user);

      await user.type(screen.getByRole('textbox', { name: /activation code/i }), 'WRONG');
      await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Lobby');
      await waitFor(() => expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Add' }));

      expect(await screen.findByRole('alert')).toHaveTextContent(message);
      expect(await screen.findByRole('textbox', { name: /activation code/i })).toHaveValue('WRONG');
      expect(fetchConnectStatus).not.toHaveBeenCalled();

      // The code was never used, so it is still there to correct after going back.
      await user.click(screen.getByRole('button', { name: 'Back' }));
      await chooseActivationCode(user);
      expect(screen.getByRole('textbox', { name: /activation code/i })).toHaveValue('WRONG');
    });

    // The display name column holds 50 characters, as the Edit Display form already allows.
    test('the display name stops at 50 characters and says so', async () => {
      const user = userEvent.setup();
      renderModal();
      await chooseActivationCode(user);
      const name = screen.getByRole('textbox', { name: /display name/i });

      await user.type(name, 'a'.repeat(49));
      expect(
        screen.queryByText('Display names can be up to 50 characters.'),
      ).not.toBeInTheDocument();

      await user.type(name, 'aa');

      expect(name).toHaveValue('a'.repeat(50));
      expect(screen.getByText('Display names can be up to 50 characters.')).toBeInTheDocument();
    });

    test('an expired code says so and offers to try again', async () => {
      const user = userEvent.setup();
      vi.mocked(addDisplayViaCode).mockResolvedValue(undefined);
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: true,
        connected: false,
        displayId: null,
        display: null,
      });
      renderModal();
      await chooseActivationCode(user);

      await user.type(screen.getByRole('textbox', { name: /activation code/i }), 'AB12');
      await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Lobby');
      await waitFor(() => expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Add' }));

      expect(await screen.findByText(/your activation code has expired/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Try Again' })).toBeInTheDocument();
    });

    // Naming and filing a display is an edit. Without displays.modify only the code is needed,
    // and the settings are not sent because the CMS would ignore them.
    test('without displays.modify only the code is required and no settings are sent', async () => {
      const user = userEvent.setup();
      vi.mocked(addDisplayViaCode).mockResolvedValue(undefined);
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: false,
        displayId: null,
        display: null,
      });
      const addOnly = buildCurrentUser(PERSONAS.user, {
        features: { 'displays.view': true, 'displays.add': true, 'displays.modify': false },
      });
      renderModal(addOnly);
      await chooseActivationCode(user);

      await user.type(screen.getByRole('textbox', { name: /activation code/i }), 'AB12');
      await waitFor(() => expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Add' }));

      expect(addDisplayViaCode).toHaveBeenCalledWith({
        userCode: 'AB12',
        displayName: undefined,
        folderId: undefined,
        displayGroupId: undefined,
        authorised: true,
      });
      // Fields the CMS would ignore aren't offered either.
      expect(screen.queryByRole('textbox', { name: /display name/i })).not.toBeInTheDocument();
    });

    // Try Again on an expired code goes back to the choice of route. Choosing the activation
    // code again must offer a fresh form, not the old attempt's waiting screen, and must not
    // offer to send the expired code again. The other settings still apply to a new code.
    test('after an expired code, choosing Activation Code again offers a fresh form', async () => {
      const user = userEvent.setup();
      vi.mocked(addDisplayViaCode).mockResolvedValue(undefined);
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: true,
        connected: false,
        displayId: null,
        display: null,
      });
      renderModal();
      await chooseActivationCode(user);

      await user.type(screen.getByRole('textbox', { name: /activation code/i }), 'AB12');
      await user.type(screen.getByRole('textbox', { name: /display name/i }), 'Lobby');
      await waitFor(() => expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Add' }));
      await user.click(await screen.findByRole('button', { name: 'Try Again' }));

      await chooseActivationCode(user);

      expect(screen.getByRole('textbox', { name: /activation code/i })).toHaveValue('');
      expect(screen.getByRole('textbox', { name: /display name/i })).toHaveValue('Lobby');
      expect(screen.getByRole('button', { name: 'Add' })).toBeDisabled();
      expect(screen.queryByText('Waiting for display to connect...')).not.toBeInTheDocument();
      expect(addDisplayViaCode).toHaveBeenCalledTimes(1);
    });

    // The "Return to Add Display" link on a pending-connect notification carries a code this
    // account already submitted, so the form picks up waiting rather than submitting it again.
    test('a deep link with an already-submitted code resumes waiting for the Player', async () => {
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: false,
        displayId: null,
        display: null,
      });
      renderModal(mockUser, { code: 'AB12', displayName: 'Lobby' });

      expect(await screen.findByText('Waiting for display to connect...')).toBeInTheDocument();
      expect(addDisplayViaCode).not.toHaveBeenCalled();
      expect(readPendingCode()).toEqual({ code: 'AB12', mode: 'code' });
    });

    // A scanned QR code carries a code nobody has submitted yet, so the form is left to fill in.
    test('a deep link with a new code leaves the form to fill in', async () => {
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: true,
        connected: false,
        displayId: null,
        display: null,
      });
      renderModal(mockUser, { code: 'AB12', displayName: 'Lobby' });

      await waitFor(() => expect(fetchConnectStatus).toHaveBeenCalledWith('AB12'));
      expect(await screen.findByRole('textbox', { name: /activation code/i })).toHaveValue('AB12');
      expect(screen.queryByText('Waiting for display to connect...')).not.toBeInTheDocument();
    });
  });

  describe('manual configuration', () => {
    beforeEach(() => {
      vi.mocked(fetchConnectDetails).mockResolvedValue({
        cmsAddress: 'https://cms.example.com',
        cmsKey: 'SERVERKEY',
      });
      vi.mocked(fetchConnectCode).mockResolvedValue({ code: 'K7QX', expiresInMinutes: 30 });
    });

    const waitingForPlayer = () =>
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: false,
        displayId: null,
        display: null,
      });

    const playerConnected = () =>
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: true,
        displayId: 5,
        display: 'Lobby',
      });

    /** What the CMS answers once it has applied the settings. */
    const applied = (overrides: Partial<ConnectApplied> = {}): ConnectApplied => ({
      displayId: 5,
      display: 'Lobby',
      licensed: 1,
      folderId: 1,
      viewable: true,
      displayGroupAssigned: true,
      ...overrides,
    });

    // Until the Player connects there is no display to apply settings to, so only the details
    // to copy into it are shown.
    test('before the Player connects, only the CMS URL and Secret Key are shown', async () => {
      const user = userEvent.setup();
      waitingForPlayer();
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));

      expect(await screen.findByText('https://cms.example.com')).toBeInTheDocument();
      expect(screen.getByText('SERVERKEY||K7QX')).toBeInTheDocument();
      await waitFor(() => expect(fetchConnectStatus).toHaveBeenCalledWith('K7QX'));

      expect(screen.queryByRole('textbox', { name: /display name/i })).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument();
      // The code carries no settings: the Player registers unauthorised and Save applies them.
      expect(fetchConnectCode).toHaveBeenCalledWith();
    });

    test('closing before the Player connects asks first, and Close leaves the code live', async () => {
      const user = userEvent.setup();
      waitingForPlayer();
      const { onClose } = renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await screen.findByText('SERVERKEY||K7QX');

      await user.click(screen.getByRole('button', { name: 'Close' }));
      await user.click(await screen.findByRole('button', { name: 'Keep waiting' }));
      expect(await screen.findByRole('dialog', { name: 'Add Display' })).toBeInTheDocument();
      expect(onClose).not.toHaveBeenCalled();

      await user.click(screen.getByRole('button', { name: 'Close' }));
      expect(await screen.findByRole('dialog', { name: 'Close this form?' })).toBeInTheDocument();
      await user.click(screen.getByRole('button', { name: 'Close' }));

      expect(onClose).toHaveBeenCalled();
      // Still remembered, so the background watcher can say when the Player arrives.
      expect(readPendingCode()).toEqual({ code: 'K7QX', mode: 'manual' });
    });

    // The Player may already have the key, and forgetting the code won't stop it connecting.
    test('Cancel before the Player connects asks first, and Cancel anyway abandons the code', async () => {
      const user = userEvent.setup();
      waitingForPlayer();
      const { onClose } = renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await screen.findByText('SERVERKEY||K7QX');

      await user.click(screen.getByRole('button', { name: 'Cancel' }));
      expect(
        await screen.findByRole('dialog', { name: 'Cancel adding this display?' }),
      ).toBeInTheDocument();
      await user.click(screen.getByRole('button', { name: 'Keep waiting' }));
      expect(await screen.findByRole('dialog', { name: 'Add Display' })).toBeInTheDocument();
      expect(onClose).not.toHaveBeenCalled();
      expect(readPendingCode()).toEqual({ code: 'K7QX', mode: 'manual' });

      await user.click(screen.getByRole('button', { name: 'Cancel' }));
      await user.click(await screen.findByRole('button', { name: 'Cancel anyway' }));

      expect(onClose).toHaveBeenCalled();
      // Forgotten, so nothing keeps checking for a Player in the background.
      expect(readPendingCode()).toBeNull();
    });

    test('Cancel while the details are still loading closes without asking', async () => {
      const user = userEvent.setup();
      const details = deferred<{ cmsAddress: string; cmsKey: string }>();
      vi.mocked(fetchConnectDetails).mockReturnValue(details.promise);
      const { onClose, unmount } = renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await screen.findByText('Retrieving your CMS connection details...');
      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(onClose).toHaveBeenCalled();
      expect(
        screen.queryByRole('dialog', { name: 'Cancel adding this display?' }),
      ).not.toBeInTheDocument();

      // Settle the load, or React holds back every later test's transitions behind it.
      unmount();
      details.resolve({ cmsAddress: 'https://cms.example.com', cmsKey: 'SERVERKEY' });
      await new Promise((r) => setTimeout(r, 0));
    });

    test('once the Player connects, Save applies the settings to it', async () => {
      const user = userEvent.setup();
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: true,
        displayId: 5,
        display: 'Lobby',
      });
      vi.mocked(applyConnectSettings).mockResolvedValue(applied());
      const { onAdded } = renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));

      // The name the operator typed on the Player is adopted as the starting point.
      await waitFor(() =>
        expect(screen.getByRole('textbox', { name: /display name/i })).toHaveValue('Lobby'),
      );
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(
        await screen.findByRole('dialog', { name: 'Display Added Successfully' }),
      ).toBeInTheDocument();
      // The CMS applies them, so it works wherever the Player registered.
      expect(applyConnectSettings).toHaveBeenCalledWith(
        'K7QX',
        expect.objectContaining({ displayName: 'Lobby', authorised: true }),
      );
      expect(screen.getByRole('button', { name: 'Manage Display' })).toBeInTheDocument();
      expect(onAdded).toHaveBeenCalled();
    });

    // Saving is what authorises the display, after the form read the count.
    test('the success panel counts the licence the saved display took', async () => {
      const user = userEvent.setup();
      vi.mocked(fetchLicenceUsage)
        .mockResolvedValueOnce({ maxLicensed: 10, currentlyLicensed: 3, available: 7 })
        .mockResolvedValue({ maxLicensed: 10, currentlyLicensed: 4, available: 6 });
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: true,
        displayId: 5,
        display: 'Lobby',
      });
      vi.mocked(applyConnectSettings).mockResolvedValue(applied());
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await screen.findByText('3 of 10 licences in use, 7 available.');
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Save' }));

      const success = await screen.findByRole('dialog', { name: 'Display Added Successfully' });
      expect(
        await within(success).findByText('| 4 of 10 licences in use, 6 available.'),
      ).toBeInTheDocument();
      expect(within(success).queryByText(/3 of 10/)).not.toBeInTheDocument();
    });

    // It isn't a new display, so the form's settings are never offered for it.
    test('a Player that was already registered ends the flow without offering settings', async () => {
      const user = userEvent.setup();
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: true,
        displayId: 5,
        display: 'Lobby',
        existing: true,
      });
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));

      expect(
        await screen.findByRole('dialog', { name: 'Display Already Registered' }),
      ).toBeInTheDocument();
      expect(screen.queryByRole('textbox', { name: /display name/i })).not.toBeInTheDocument();
      expect(applyConnectSettings).not.toHaveBeenCalled();
      expect(readPendingCode()).toBeNull();
    });

    // Folder access wouldn't help: the display belongs to somebody else.
    test('a Player already registered to a display the user cannot view says so', async () => {
      const user = userEvent.setup();
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: true,
        displayId: 5,
        display: null,
        existing: true,
      });
      vi.mocked(fetchDisplays).mockResolvedValue({ rows: [], totalCount: 0 });
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));

      expect(
        await screen.findByText(
          "This Player is already registered to a display you don't have access to.",
        ),
      ).toBeInTheDocument();
      expect(screen.queryByText(/grant your user group access/i)).not.toBeInTheDocument();
    });

    test('closing after the Player connects offers to save the settings', async () => {
      const user = userEvent.setup();
      playerConnected();
      vi.mocked(applyConnectSettings).mockResolvedValue(applied());
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());

      await user.click(screen.getByRole('button', { name: 'Close' }));
      expect(
        await screen.findByRole('dialog', { name: "Save this display's settings?" }),
      ).toBeInTheDocument();
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(
        await screen.findByRole('dialog', { name: 'Display Added Successfully' }),
      ).toBeInTheDocument();
      expect(applyConnectSettings).toHaveBeenCalledWith('K7QX', expect.anything());
    });

    test('Close without saving leaves the display as it registered', async () => {
      const user = userEvent.setup();
      playerConnected();
      const { onClose, onAdded } = renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());

      await user.click(screen.getByRole('button', { name: 'Cancel' }));
      await user.click(await screen.findByRole('button', { name: 'Close without saving' }));

      expect(applyConnectSettings).not.toHaveBeenCalled();
      expect(onClose).toHaveBeenCalled();
      // The display exists now, so the list refreshes to show it.
      expect(onAdded).toHaveBeenCalled();
      expect(readPendingCode()).toBeNull();
    });

    // Without displays.modify only authorisation can be applied, as with an activation code, so
    // the name, folder and group aren't offered.
    test('without displays.modify, Save sends the authorisation and nothing else', async () => {
      const user = userEvent.setup();
      playerConnected();
      vi.mocked(applyConnectSettings).mockResolvedValue(applied());
      const addOnly = buildCurrentUser(PERSONAS.user, {
        features: { 'displays.view': true, 'displays.add': true, 'displays.modify': false },
      });
      renderModal(addOnly);

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());

      expect(screen.queryByRole('textbox', { name: /display name/i })).not.toBeInTheDocument();
      expect(screen.getByRole('switch', { name: 'Authorise automatically' })).toBeChecked();
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(
        await screen.findByRole('dialog', { name: 'Display Added Successfully' }),
      ).toBeInTheDocument();
      expect(applyConnectSettings).toHaveBeenCalledWith('K7QX', {
        displayName: undefined,
        folderId: undefined,
        displayGroupId: undefined,
        authorised: true,
      });
    });

    // With DISPLAY_AUTO_AUTH on, the CMS authorises the display itself when the Player registers.
    test('without displays.modify, Save with Authorise off un-authorises an auto-authorised display', async () => {
      const user = userEvent.setup();
      playerConnected();
      vi.mocked(fetchDisplays).mockResolvedValue({
        rows: [buildDisplay({ displayId: 5, display: 'Lobby', licensed: 0 })],
        totalCount: 1,
      });
      vi.mocked(applyConnectSettings).mockResolvedValue(applied({ licensed: 0 }));
      const addOnly = buildCurrentUser(PERSONAS.user, {
        features: { 'displays.view': true, 'displays.add': true, 'displays.modify': false },
      });
      renderModal(addOnly);

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());

      await user.click(screen.getByRole('switch', { name: 'Authorise automatically' }));
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(
        await screen.findByRole('dialog', { name: 'Display Added Successfully' }),
      ).toBeInTheDocument();
      expect(applyConnectSettings).toHaveBeenCalledWith(
        'K7QX',
        expect.objectContaining({ authorised: false }),
      );
      expect(screen.getByText('Not authorised')).toBeInTheDocument();
    });

    // A save in flight can't be interrupted, or the success dialog and a close confirmation
    // would both end up open.
    test('while saving, the form cannot be closed', async () => {
      const user = userEvent.setup();
      playerConnected();
      const saving = deferred<ConnectApplied>();
      vi.mocked(applyConnectSettings).mockReturnValue(saving.promise);
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled());
      await user.click(screen.getByRole('button', { name: 'Close' }));
      expect(
        screen.queryByRole('dialog', { name: "Save this display's settings?" }),
      ).not.toBeInTheDocument();

      saving.resolve(applied());
      expect(
        await screen.findByRole('dialog', { name: 'Display Added Successfully' }),
      ).toBeInTheDocument();
    });

    // Filed in a folder the user can't see, e.g. the default folder when they couldn't choose one.
    test("a display added out of the user's view still finishes, without Manage Display", async () => {
      const user = userEvent.setup();
      playerConnected();
      vi.mocked(applyConnectSettings).mockResolvedValue(applied({ viewable: false }));
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(
        await screen.findByRole('dialog', { name: 'Display Added Successfully' }),
      ).toBeInTheDocument();
      expect(screen.getByText(/in a folder you can't access/i)).toBeInTheDocument();
      expect(screen.getByText('Authorised')).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Manage Display' })).not.toBeInTheDocument();
      expect(fetchDisplays).not.toHaveBeenCalledWith(expect.objectContaining({ displayId: 5 }));
    });

    test('a failed save keeps the form open and shows the server message', async () => {
      const user = userEvent.setup();
      playerConnected();
      vi.mocked(applyConnectSettings).mockRejectedValueOnce({
        isAxiosError: true,
        response: { data: { message: 'Access Denied to Folder' } },
      });
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(await screen.findByRole('alert')).toHaveTextContent('Access Denied to Folder');
      // The form stays open with Save available to try again, once the save has settled.
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
    });

    // The CMS has the settings by then and won't take them twice, so going back to the form
    // would leave the operator stuck on "already saved".
    test('a save that succeeds but cannot be read back still finishes', async () => {
      const user = userEvent.setup();
      playerConnected();
      vi.mocked(applyConnectSettings).mockResolvedValue(applied());
      vi.mocked(fetchDisplays).mockRejectedValue(new Error('Network Error'));
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(
        await screen.findByRole('dialog', { name: 'Display Added Successfully' }),
      ).toBeInTheDocument();
      expect(screen.getByText(/could not be loaded/i)).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Manage Display' })).not.toBeInTheDocument();
      expect(readPendingCode()).toBeNull();
    });

    // The rest of the settings stand, so it is still a success, but the summary mustn't claim
    // the group.
    test('a display group the CMS could not assign is pointed out', async () => {
      const user = userEvent.setup();
      playerConnected();
      vi.mocked(applyConnectSettings).mockResolvedValue(applied({ displayGroupAssigned: false }));
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(
        await screen.findByRole('dialog', { name: 'Display Added Successfully' }),
      ).toBeInTheDocument();
      expect(screen.getByText(/couldn't be added to the .*display group/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Manage Display' })).toBeInTheDocument();
    });

    // As with Cancel: the Player may already have the key.
    test('Back before the Player connects asks first, and Go back anyway abandons the code', async () => {
      const user = userEvent.setup();
      waitingForPlayer();
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await screen.findByText('SERVERKEY||K7QX');

      await user.click(screen.getByRole('button', { name: 'Back' }));
      expect(
        await screen.findByRole('dialog', { name: 'Stop waiting for this Player?' }),
      ).toBeInTheDocument();
      await user.click(screen.getByRole('button', { name: 'Keep waiting' }));
      expect(await screen.findByText('SERVERKEY||K7QX')).toBeInTheDocument();
      expect(readPendingCode()).toEqual({ code: 'K7QX', mode: 'manual' });

      await user.click(screen.getByRole('button', { name: 'Back' }));
      await user.click(await screen.findByRole('button', { name: 'Go back anyway' }));

      expect(
        await screen.findByRole('button', { name: /manually configure/i }),
      ).toBeInTheDocument();
      expect(readPendingCode()).toBeNull();
    });

    test('without a name from the CMS, the banner still says the display connected', async () => {
      const user = userEvent.setup();
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: false,
        connected: true,
        displayId: 5,
        display: null,
      });
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));

      expect(
        await screen.findByText('Connected. Save to apply your settings.'),
      ).toBeInTheDocument();
    });

    // The question asked on close follows the Player: if it connects while "Close this form?" is
    // showing, the confirmation becomes the offer to save.
    test('a Player connecting while "Close this form?" shows turns it into the offer to save', async () => {
      const user = userEvent.setup();
      vi.mocked(fetchConnectStatus)
        .mockResolvedValueOnce({ expired: false, connected: false, displayId: null, display: null })
        .mockResolvedValue({ expired: false, connected: true, displayId: 5, display: 'Lobby' });
      renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await screen.findByText('SERVERKEY||K7QX');
      await user.click(screen.getByRole('button', { name: 'Close' }));
      expect(await screen.findByRole('dialog', { name: 'Close this form?' })).toBeInTheDocument();

      // The watcher checks again a few seconds later and finds the Player.
      expect(
        await screen.findByRole(
          'dialog',
          { name: "Save this display's settings?" },
          { timeout: 6000 },
        ),
      ).toBeInTheDocument();
      expect(screen.queryByRole('dialog', { name: 'Close this form?' })).not.toBeInTheDocument();
    }, 10000);

    // A code from earlier that's still live is picked up when the form opens, so the form watches
    // it and starting another setup can't silently replace it.
    test('opening the form resumes a manual code that is still waiting', async () => {
      waitingForPlayer();
      writePendingCode({ code: 'R2D2', mode: 'manual' });
      renderModal();

      expect(await screen.findByText('SERVERKEY||R2D2')).toBeInTheDocument();
      expect(fetchConnectCode).not.toHaveBeenCalled();
    });

    test('opening the form forgets a manual code that has expired', async () => {
      vi.mocked(fetchConnectStatus).mockResolvedValue({
        expired: true,
        connected: false,
        displayId: null,
        display: null,
      });
      writePendingCode({ code: 'R2D2', mode: 'manual' });
      renderModal();

      await waitFor(() => expect(readPendingCode()).toBeNull());
      expect(screen.getByRole('button', { name: /manually configure/i })).toBeInTheDocument();
    });

    // Closing before the details arrive leaves nothing behind for the background watcher.
    test('closing while the details load does not remember the code', async () => {
      const user = userEvent.setup();
      waitingForPlayer();
      const details = deferred<{ cmsAddress: string; cmsKey: string }>();
      vi.mocked(fetchConnectDetails).mockReturnValue(details.promise);
      const { onClose, unmount } = renderModal();

      await user.click(await screen.findByRole('button', { name: /manually configure/i }));
      await user.click(screen.getByRole('button', { name: 'Close' }));
      expect(onClose).toHaveBeenCalled();
      unmount();

      details.resolve({ cmsAddress: 'https://cms.example.com', cmsKey: 'SERVERKEY' });
      await new Promise((r) => setTimeout(r, 0));

      expect(fetchConnectCode).not.toHaveBeenCalled();
      expect(readPendingCode()).toBeNull();
    });
  });
});
