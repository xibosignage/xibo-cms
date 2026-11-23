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

// Registers the notify mock before the component loads.
import '@/testUtils/notifyMock';

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, afterEach, describe, test, expect } from 'vitest';

import {
  EXISTING_RECOVERY_CODES,
  GOOGLE_2FA_USER,
  QR_URL,
  RECOVERY_CODES,
} from './fixtures/profile';
import { mockUserContext } from './helpers/renderPreferencesPage';
import { chooseTwoFactor, renderProfile, stubScrollIntoView } from './helpers/renderProfile';
import {
  api,
  hold2FASetup,
  stub2FASetup,
  stub2FASetupFail,
  stubGenerateCodes,
  stubGenerateCodesFail,
  stubUserApiDefaults,
  stubShowCodes,
  stubShowCodesFail,
} from './mocks/profileApi';

import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { mockNotifyError, mockNotifySuccess } from '@/testUtils/notifyMock';

vi.mock('@/services/userApi');
vi.mock('@/context/UserContext', async () => {
  const { mockUserContext } = await import('./helpers/preferencesContext');
  return { useUserContext: () => mockUserContext };
});

const chooseGoogleAuth = (user: UserEvent) => chooseTwoFactor(user, 'Google Authenticator');

const generateButton = () => screen.getByRole('button', { name: /^\+ Generate$/ });
const showButton = () => screen.getByRole('button', { name: /^\+ Show$/ });

// #4076 asks for "an error message": accept the page's alert or a notify.error toast.
const errorIsShown = () =>
  expect(mockNotifyError.mock.calls.length > 0 || screen.queryByRole('alert') !== null).toBe(true);

describe('Preferences - Profile - Google Authenticator', () => {
  beforeAll(stubScrollIntoView);

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    stubUserApiDefaults();
    stub2FASetup(QR_URL);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('turning it on', () => {
    test('the panel is hidden unless Google Authenticator is chosen', () => {
      renderProfile();

      expect(screen.queryByRole('button', { name: /^\+ Generate$/ })).not.toBeInTheDocument();
      expect(screen.queryByRole('textbox', { name: 'Access Code' })).not.toBeInTheDocument();
    });

    test('choosing Google Authenticator loads and shows the QR code', async () => {
      const user = userEvent.setup();
      renderProfile();

      await chooseGoogleAuth(user);

      expect(await screen.findByRole('img', { name: '2FA QR Code' })).toHaveAttribute(
        'src',
        QR_URL,
      );
      expect(api.fetch2FASetup).toHaveBeenCalled();
      expect(
        screen.getByText('Please scan the following image with your app:'),
      ).toBeInTheDocument();
      expect(screen.getByRole('textbox', { name: 'Access Code' })).toBeInTheDocument();
    });

    test('shows Loading... while the QR code loads, with Generate and Show disabled', async () => {
      const setup = hold2FASetup();
      const user = userEvent.setup();
      renderProfile();

      await chooseGoogleAuth(user);

      await screen.findByText('Loading...');
      expect(generateButton()).toBeDisabled();
      expect(showButton()).toBeDisabled();

      setup.resolve({ qRUrl: QR_URL });

      await screen.findByRole('img', { name: '2FA QR Code' });
      expect(generateButton()).toBeEnabled();
    });

    test('shows No QR Code available when the server returns none', async () => {
      // Back to the default: the server returns no setup.
      stubUserApiDefaults();
      const user = userEvent.setup();
      renderProfile();

      await chooseGoogleAuth(user);

      await screen.findByText('No QR Code available');
    });

    test('shows No QR Code available when loading the QR code fails', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
      stub2FASetupFail();
      const user = userEvent.setup();
      renderProfile();

      await chooseGoogleAuth(user);

      await screen.findByText('No QR Code available');
      expect(api.fetch2FASetup).toHaveBeenCalled();
    });

    test('turning it on with an access code saves it and updates the app', async () => {
      const user = userEvent.setup();
      renderProfile();

      await chooseGoogleAuth(user);
      await screen.findByRole('img', { name: '2FA QR Code' });
      await user.type(screen.getByLabelText('Current Password'), 'old');
      await user.type(screen.getByRole('textbox', { name: 'Access Code' }), '123456');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledWith('Profile saved'));
      expect(api.updateUserProfile).toHaveBeenCalledWith(
        expect.objectContaining({ password: 'old', twoFactorTypeId: '2', code: '123456' }),
      );
      expect(mockUserContext.updateUser).toHaveBeenCalledWith(
        expect.objectContaining({ twoFactorTypeId: 2 }),
      );
    });

    test('turning it on without an access code is flagged', async () => {
      const user = userEvent.setup();
      renderProfile();

      await chooseGoogleAuth(user);
      await screen.findByRole('img', { name: '2FA QR Code' });
      await user.type(screen.getByLabelText('Current Password'), 'old');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await screen.findByText('Access Code is empty');
      expect(api.updateUserProfile).not.toHaveBeenCalled();
    });
  });

  describe('already using Google Authenticator', () => {
    test('shows no QR code or access code, and loads no setup', () => {
      renderProfile(GOOGLE_2FA_USER);

      expect(generateButton()).toBeInTheDocument();
      expect(showButton()).toBeInTheDocument();
      expect(
        screen.queryByText('Please scan the following image with your app:'),
      ).not.toBeInTheDocument();
      expect(screen.queryByRole('textbox', { name: 'Access Code' })).not.toBeInTheDocument();
      expect(api.fetch2FASetup).not.toHaveBeenCalled();
    });
  });

  describe('recovery codes', () => {
    test('Generate shows the codes the server created', async () => {
      stubGenerateCodes(RECOVERY_CODES);
      const user = userEvent.setup();
      renderProfile(GOOGLE_2FA_USER);

      await user.click(generateButton());

      await screen.findByText('Here are your recovery codes.');
      expect(screen.getByText(RECOVERY_CODES[0])).toBeInTheDocument();
      expect(screen.getByText(RECOVERY_CODES[1])).toBeInTheDocument();
    });

    test('generated codes are sent when the profile is saved', async () => {
      stubGenerateCodes(RECOVERY_CODES);
      const user = userEvent.setup();
      renderProfile(GOOGLE_2FA_USER);

      await user.click(generateButton());
      await screen.findByText(RECOVERY_CODES[0]);
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledWith('Profile saved'));
      expect(api.updateUserProfile).toHaveBeenCalledWith(
        expect.objectContaining({
          twoFactorTypeId: '2',
          twoFactorRecoveryCodes: JSON.stringify(RECOVERY_CODES),
        }),
      );
    });

    test('Show loads the existing codes', async () => {
      stubShowCodes(RECOVERY_CODES);
      const user = userEvent.setup();
      renderProfile(GOOGLE_2FA_USER);

      await user.click(showButton());

      await screen.findByText(RECOVERY_CODES[0]);
      expect(screen.getByText(RECOVERY_CODES[1])).toBeInTheDocument();
      expect(api.fetch2FARecoveryCodes).toHaveBeenCalledTimes(1);
    });

    test('Show while turning it on says no codes found without asking the server', async () => {
      const user = userEvent.setup();
      renderProfile();

      await chooseGoogleAuth(user);
      await screen.findByRole('img', { name: '2FA QR Code' });
      await user.click(showButton());

      await screen.findByText('No codes found.');
      expect(api.fetch2FARecoveryCodes).not.toHaveBeenCalled();
    });

    test('Show after Generate reuses the generated codes', async () => {
      stubGenerateCodes(RECOVERY_CODES);
      stubShowCodes(EXISTING_RECOVERY_CODES);
      const user = userEvent.setup();
      renderProfile(GOOGLE_2FA_USER);

      await user.click(generateButton());
      await screen.findByText(RECOVERY_CODES[0]);
      await user.click(showButton());

      expect(api.fetch2FARecoveryCodes).not.toHaveBeenCalled();
      expect(screen.getByText(RECOVERY_CODES[1])).toBeInTheDocument();
      expect(screen.queryByText(EXISTING_RECOVERY_CODES[0])).not.toBeInTheDocument();

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledWith('Profile saved'));
      expect(api.updateUserProfile).toHaveBeenCalledWith(
        expect.objectContaining({ twoFactorRecoveryCodes: JSON.stringify(RECOVERY_CODES) }),
      );
    });

    // Open — xibosignage/xibo#4076.
    knownFailure(
      'a failed Generate shows an error',
      async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {});
        stubGenerateCodesFail();
        const user = userEvent.setup();
        renderProfile(GOOGLE_2FA_USER);

        await user.click(generateButton());

        await waitFor(() => expect(generateButton()).toBeEnabled());
        expect(api.generate2FARecoveryCodes).toHaveBeenCalledTimes(1);
        expect(screen.queryByText('Here are your recovery codes.')).not.toBeInTheDocument();
      },
      errorIsShown,
    );

    // Open — xibosignage/xibo#4076.
    knownFailure(
      'a failed Show shows an error',
      async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {});
        stubShowCodesFail();
        const user = userEvent.setup();
        renderProfile(GOOGLE_2FA_USER);

        await user.click(showButton());

        await waitFor(() => expect(showButton()).toBeEnabled());
        expect(api.fetch2FARecoveryCodes).toHaveBeenCalledTimes(1);
        expect(screen.queryByText('Here are your recovery codes.')).not.toBeInTheDocument();
      },
      errorIsShown,
    );
  });
});
