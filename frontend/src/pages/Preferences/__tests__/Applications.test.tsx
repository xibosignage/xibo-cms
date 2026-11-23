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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  EMPTY_APPLICATION_TABLE,
  SINGLE_APPLICATION,
  buildApplications,
  mockApplication,
  mockUser,
} from './fixtures/preferences';
import { renderPreferencesPage } from './helpers/renderPreferencesPage';
import {
  api,
  holdApplications,
  holdRevoke,
  stubApplications,
  stubApplicationsFail,
  stubRevokeFail,
  stubUserApiDefaults,
} from './mocks/preferencesApi';

import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { mockNotifyError, mockNotifySuccess } from '@/testUtils/notifyMock';

vi.mock('@/services/userApi');
vi.mock('@/context/UserContext', async () => {
  const { mockUserContext } = await import('./helpers/preferencesContext');
  return { useUserContext: () => mockUserContext };
});

const renderApplications = () => renderPreferencesPage('/preferences/applications');

const revokeButtons = () => screen.getAllByRole('button', { name: 'Revoke' });

describe('Preferences - My Applications tab', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    stubUserApiDefaults();
    stubApplications(SINGLE_APPLICATION);
  });

  describe('default state', () => {
    test("loads the signed-in user's applications and shows each one", async () => {
      renderApplications();

      expect(screen.getByRole('heading', { name: 'My Applications' })).toBeInTheDocument();
      await screen.findByText('Zapier');
      expect(api.fetchUserApplications).toHaveBeenCalledWith(mockUser.userId);
      expect(screen.getByText('2026-01-02 10:00')).toBeInTheDocument();
      expect(screen.getByText('10.0.0.1')).toBeInTheDocument();
      expect(revokeButtons()).toHaveLength(1);
    });

    test('says there are no applications when the list is empty', async () => {
      stubApplications(EMPTY_APPLICATION_TABLE);
      renderApplications();

      await screen.findByRole('heading', { name: 'No applications found.' });
    });

    test('shows Loading... instead of the empty message while the list loads', async () => {
      const request = holdApplications();
      renderApplications();

      await screen.findByText('Loading...');
      expect(
        screen.queryByRole('heading', { name: 'No applications found.' }),
      ).not.toBeInTheDocument();

      request.resolve(SINGLE_APPLICATION);

      await screen.findByText('Zapier');
      expect(screen.queryByText('Loading...')).not.toBeInTheDocument();
    });

    test('shows applications five per page', async () => {
      stubApplications(buildApplications(7));
      const user = userEvent.setup();
      renderApplications();

      await screen.findByText('App 5');
      expect(screen.queryByText('App 6')).not.toBeInTheDocument();

      await user.click(screen.getByRole('button', { name: 'Next' }));

      await screen.findByText('App 6');
      expect(screen.getByText('App 7')).toBeInTheDocument();
      expect(screen.queryByText('App 1')).not.toBeInTheDocument();
    });

    // Open — xibosignage/xibo#4077.
    knownFailure(
      'a failed load is not shown as an empty list',
      async () => {
        // The test query cache logs every query error.
        vi.spyOn(console, 'log').mockImplementation(() => {});
        stubApplicationsFail();
        renderApplications();

        await waitFor(() => expect(api.fetchUserApplications).toHaveBeenCalledTimes(1));
        await waitFor(() => expect(screen.queryByText('Loading...')).not.toBeInTheDocument());
      },
      () =>
        expect(
          screen.queryByRole('heading', { name: 'No applications found.' }),
        ).not.toBeInTheDocument(),
    );
  });

  describe('revoke', () => {
    test('removes access, drops the row and confirms by name', async () => {
      const user = userEvent.setup();
      renderApplications();
      await screen.findByText('Zapier');

      await user.click(revokeButtons()[0]!);

      await waitFor(() => expect(screen.queryByText('Zapier')).not.toBeInTheDocument());
      expect(api.revokeApplicationAccess).toHaveBeenCalledWith(mockApplication.id, mockUser.userId);
      expect(mockNotifySuccess).toHaveBeenCalledWith('Access to Zapier revoked');
    });

    test('a failed revoke keeps the row and shows an error', async () => {
      stubRevokeFail();
      const user = userEvent.setup();
      renderApplications();
      await screen.findByText('Zapier');

      await user.click(revokeButtons()[0]!);

      await waitFor(() =>
        expect(mockNotifyError).toHaveBeenCalledWith(
          'Failed to revoke access for this application',
        ),
      );
      expect(api.revokeApplicationAccess).toHaveBeenCalledTimes(1);
      expect(screen.getByText('Zapier')).toBeInTheDocument();
    });

    test('every Revoke button is disabled while a revoke is in progress', async () => {
      stubApplications(buildApplications(2));
      const revoke = holdRevoke();
      const user = userEvent.setup();
      renderApplications();
      await screen.findByText('App 2');

      await user.click(revokeButtons()[0]!);

      await waitFor(() => revokeButtons().forEach((button) => expect(button).toBeDisabled()));

      revoke.resolve();

      await waitFor(() => expect(screen.queryByText('App 1')).not.toBeInTheDocument());
      revokeButtons().forEach((button) => expect(button).toBeEnabled());
    });

    test('revoking the only application on the last page goes back a page', async () => {
      stubApplications(buildApplications(6));
      const user = userEvent.setup();
      renderApplications();
      await screen.findByText('App 1');
      await user.click(screen.getByRole('button', { name: 'Next' }));
      await screen.findByText('App 6');

      await user.click(revokeButtons()[0]!);

      await screen.findByText('App 1');
      expect(screen.getByText('App 5')).toBeInTheDocument();
      expect(screen.queryByText('App 6')).not.toBeInTheDocument();
    });
  });
});
