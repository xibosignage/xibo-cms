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

import { EMAIL_2FA_USER } from './fixtures/profile';
import { mockUserContext } from './helpers/renderPreferencesPage';
import { chooseTwoFactor, renderProfile, stubScrollIntoView } from './helpers/renderProfile';
import {
  api,
  holdProfileSave,
  stubProfileSaveApiError,
  stubProfileSaveFail,
  stubUserApiDefaults,
} from './mocks/profileApi';

import { testQueryClient } from '@/setupTests';
import { mockNotifySuccess } from '@/testUtils/notifyMock';

vi.mock('@/services/userApi');
vi.mock('@/context/UserContext', async () => {
  const { mockUserContext } = await import('./helpers/preferencesContext');
  return { useUserContext: () => mockUserContext };
});

const FIX_ERRORS = 'Please fix the highlighted errors before saving.';

const emailField = () => screen.getByRole('textbox', { name: 'Email' });
const twoFactorSelect = () => screen.getByRole('combobox', { name: 'Two Factor Authentication' });

const replaceEmail = async (user: UserEvent, value: string) => {
  await user.clear(emailField());
  if (value) await user.type(emailField(), value);
};

const clickSave = (user: UserEvent) => user.click(screen.getByRole('button', { name: 'Save' }));

describe('Preferences - Profile', () => {
  beforeAll(stubScrollIntoView);

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    stubUserApiDefaults();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('default state', () => {
    test('fields are pre-filled from the signed-in user', { tags: ['smoke'] }, () => {
      renderProfile(EMAIL_2FA_USER);

      const userName = screen.getByRole('textbox', { name: 'User Name' });
      expect(userName).toHaveValue('jdoe');
      expect(userName).toBeDisabled();
      expect(emailField()).toHaveValue('jane@example.com');
      expect(twoFactorSelect()).toHaveTextContent('Email');
    });
  });

  describe('validation', () => {
    test('mismatched new passwords are flagged on the retype field', async () => {
      const user = userEvent.setup();
      renderProfile();

      await user.type(screen.getByLabelText('Current Password'), 'old');
      await user.type(screen.getByLabelText('New Password'), 'abc');
      await user.type(screen.getByLabelText('Retype New Password'), 'abd');
      await clickSave(user);

      await screen.findByText('New passwords do not match.');
      expect(screen.getByRole('alert')).toHaveTextContent(FIX_ERRORS);
      expect(api.updateUserProfile).not.toHaveBeenCalled();
    });

    test('a new password needs the current password', async () => {
      const user = userEvent.setup();
      renderProfile();

      await user.type(screen.getByLabelText('New Password'), 'abc');
      await user.type(screen.getByLabelText('Retype New Password'), 'abc');
      await clickSave(user);

      await screen.findByText('Please enter your password');
      expect(api.updateUserProfile).not.toHaveBeenCalled();
    });

    // Browser email validation already blocks values without "@"; this one passes it but not Zod.
    test('an invalid email address is flagged', async () => {
      const user = userEvent.setup();
      renderProfile();

      await replaceEmail(user, 'jane@example');
      await clickSave(user);

      await screen.findByText('Please enter a valid email address.');
      expect(api.updateUserProfile).not.toHaveBeenCalled();
    });

    test('Email two factor needs an email address', async () => {
      const user = userEvent.setup();
      renderProfile();

      await replaceEmail(user, '');
      await chooseTwoFactor(user, 'Email');
      await user.type(screen.getByLabelText('Current Password'), 'old');
      await clickSave(user);

      await screen.findByText('Please provide valid email address');
      expect(api.updateUserProfile).not.toHaveBeenCalled();
    });

    test('changing the two factor setting needs the current password', async () => {
      const user = userEvent.setup();
      renderProfile();

      await chooseTwoFactor(user, 'Email');
      await clickSave(user);

      await screen.findByText('Please enter your password');
      expect(api.updateUserProfile).not.toHaveBeenCalled();
    });

    test('changing the email while using Email two factor needs the current password', async () => {
      const user = userEvent.setup();
      renderProfile(EMAIL_2FA_USER);

      await replaceEmail(user, 'new@example.com');
      await clickSave(user);

      await screen.findByText('Please enter your password');
      expect(api.updateUserProfile).not.toHaveBeenCalled();
    });

    test('changing the email with two factor off saves without the current password', async () => {
      const user = userEvent.setup();
      renderProfile();

      await replaceEmail(user, 'new@example.com');
      await clickSave(user);

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledWith('Profile saved'));
      expect(api.updateUserProfile).toHaveBeenCalledWith(
        expect.objectContaining({ email: 'new@example.com', password: '', twoFactorTypeId: '0' }),
      );
      expect(screen.queryByText('Please enter your password')).not.toBeInTheDocument();
    });
  });

  describe('save', () => {
    test(
      'a valid save sends the details, updates the app and confirms',
      { tags: ['smoke'] },
      async () => {
        const user = userEvent.setup();
        renderProfile();

        await user.type(screen.getByLabelText('Current Password'), 'old');
        await user.type(screen.getByLabelText('New Password'), 'abc');
        await user.type(screen.getByLabelText('Retype New Password'), 'abc');
        await replaceEmail(user, 'new@example.com');
        await clickSave(user);

        await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledWith('Profile saved'));
        expect(api.updateUserProfile).toHaveBeenCalledWith(
          expect.objectContaining({
            password: 'old',
            newPassword: 'abc',
            retypeNewPassword: 'abc',
            email: 'new@example.com',
            twoFactorTypeId: '0',
            twoFactorRecoveryCodes: '',
            code: '',
          }),
        );
        expect(mockUserContext.updateUser).toHaveBeenCalledWith(
          expect.objectContaining({ email: 'new@example.com', twoFactorTypeId: 0 }),
        );
      },
    );

    // The app's copy of the user must take the type from the form, not keep the old one.
    test('switching to Email two factor saves it and updates the app', async () => {
      const user = userEvent.setup();
      renderProfile();

      await chooseTwoFactor(user, 'Email');
      await user.type(screen.getByLabelText('Current Password'), 'old');
      await clickSave(user);

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledWith('Profile saved'));
      expect(api.updateUserProfile).toHaveBeenCalledWith(
        expect.objectContaining({
          password: 'old',
          email: 'jane@example.com',
          twoFactorTypeId: '1',
        }),
      );
      expect(mockUserContext.updateUser).toHaveBeenCalledWith(
        expect.objectContaining({ twoFactorTypeId: 1 }),
      );
    });

    test('Save shows Saving... and is disabled while saving', async () => {
      const save = holdProfileSave();
      const user = userEvent.setup();
      renderProfile();

      await replaceEmail(user, 'new@example.com');
      await clickSave(user);

      await waitFor(() => expect(screen.getByRole('button', { name: 'Saving...' })).toBeDisabled());

      save.resolve();

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledWith('Profile saved'));
    });
  });

  describe('error handling', () => {
    beforeEach(() => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
    });

    test('a server error about a field is shown under that field', async () => {
      stubProfileSaveApiError('Email already in use', 'email');
      const user = userEvent.setup();
      renderProfile();

      await replaceEmail(user, 'new@example.com');
      await clickSave(user);

      await screen.findByText('Email already in use');
      expect(api.updateUserProfile).toHaveBeenCalledTimes(1);
      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
      expect(mockNotifySuccess).not.toHaveBeenCalled();
    });

    test('a server error without a field is shown as a banner', async () => {
      stubProfileSaveApiError('Server unhappy');
      const user = userEvent.setup();
      renderProfile();

      await replaceEmail(user, 'new@example.com');
      await clickSave(user);

      expect(await screen.findByRole('alert')).toHaveTextContent('Server unhappy');
      expect(api.updateUserProfile).toHaveBeenCalledTimes(1);
      expect(mockNotifySuccess).not.toHaveBeenCalled();
    });

    test('an unexpected failure shows a generic message', async () => {
      stubProfileSaveFail();
      const user = userEvent.setup();
      renderProfile();

      await replaceEmail(user, 'new@example.com');
      await clickSave(user);

      expect(await screen.findByRole('alert')).toHaveTextContent('An unexpected error occurred.');
      expect(api.updateUserProfile).toHaveBeenCalledTimes(1);
      expect(mockNotifySuccess).not.toHaveBeenCalled();
    });
  });
});
