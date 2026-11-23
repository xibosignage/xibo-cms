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
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import { buildPreferencesUser, queryKeys } from './fixtures/preferences';
import { mockUserContext, renderPreferencesPage } from './helpers/renderPreferencesPage';
import {
  api,
  holdSavePreferences,
  stubSavePreferencesFail,
  stubUserApiDefaults,
} from './mocks/preferencesApi';

import { testQueryClient } from '@/setupTests';
import { mockNotifySuccess } from '@/testUtils/notifyMock';

vi.mock('@/services/userApi');
vi.mock('@/context/UserContext', async () => {
  const { mockUserContext } = await import('./helpers/preferencesContext');
  return { useUserContext: () => mockUserContext };
});

const LIBRARY_DURATION = 'Force current Library duration?';
const THUMBNAIL_COLUMN = 'Auto show thumbnail column?';
const CLEAR_AUTO_SUBMIT = 'Clear all auto submit form choices?';
const FOLDER_TREE = 'Remember Folder tree state globally?';

const checkbox = (name: string) => screen.getByRole('checkbox', { name });
const saveButton = () => screen.getByRole('button', { name: /^Sav/ });

const renderCustomisation = (settings = {}) =>
  renderPreferencesPage('/preferences/customisation', {
    user: buildPreferencesUser({ settings }),
  });

describe('Preferences - Customisation tab', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    stubUserApiDefaults();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('default state', () => {
    test('each checkbox matches the saved settings', () => {
      renderCustomisation({
        useLibraryDuration: 1,
        showThumbnailColumn: 0,
        rememberFolderTreeStateGlobally: 0,
      });

      expect(screen.getByRole('heading', { name: 'Customisation' })).toBeInTheDocument();
      expect(checkbox(LIBRARY_DURATION)).toBeChecked();
      expect(checkbox(THUMBNAIL_COLUMN)).not.toBeChecked();
      expect(checkbox(FOLDER_TREE)).not.toBeChecked();
      expect(checkbox(CLEAR_AUTO_SUBMIT)).not.toBeChecked();
    });

    // The server can send settings as numbers, numeric strings or booleans.
    test('reads saved settings sent as text or true/false', () => {
      renderCustomisation({
        useLibraryDuration: '1',
        showThumbnailColumn: false,
        rememberFolderTreeStateGlobally: '0',
      });

      expect(checkbox(LIBRARY_DURATION)).toBeChecked();
      expect(checkbox(THUMBNAIL_COLUMN)).not.toBeChecked();
      expect(checkbox(FOLDER_TREE)).not.toBeChecked();
    });

    test('falls back to the defaults when nothing is saved', () => {
      renderCustomisation();

      expect(checkbox(LIBRARY_DURATION)).not.toBeChecked();
      expect(checkbox(THUMBNAIL_COLUMN)).toBeChecked();
      expect(checkbox(CLEAR_AUTO_SUBMIT)).not.toBeChecked();
      expect(checkbox(FOLDER_TREE)).toBeChecked();
    });
  });

  describe('save', () => {
    test('sends every preference as on or off', async () => {
      const user = userEvent.setup();
      renderCustomisation();

      await user.click(checkbox(LIBRARY_DURATION));
      await user.click(checkbox(THUMBNAIL_COLUMN));
      await user.click(saveButton());

      await waitFor(() => expect(api.saveUserPreferencesBulk).toHaveBeenCalledTimes(1));
      expect(api.saveUserPreferencesBulk).toHaveBeenCalledWith({
        useLibraryDuration: 'on',
        showThumbnailColumn: 'off',
        autoSubmitClearAll: 'off',
        rememberFolderTreeStateGlobally: 'on',
      });
    });

    test('updates the app settings, keeping other settings, and confirms', async () => {
      const user = userEvent.setup();
      renderCustomisation({ defaultLanguage: 'en' });

      await user.click(checkbox(LIBRARY_DURATION));
      await user.click(saveButton());

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledWith('Preferences saved'));
      expect(mockUserContext.updateUser).toHaveBeenCalledWith({
        settings: {
          defaultLanguage: 'en',
          useLibraryDuration: 1,
          showThumbnailColumn: 1,
          autoSubmitClearAll: 0,
          rememberFolderTreeStateGlobally: 1,
        },
      });
    });

    test('clearing auto submit forgets remembered choices, keeps other preferences and unticks itself', async () => {
      testQueryClient.setQueryData(queryKeys.autoSubmitPref, true);
      testQueryClient.setQueryData(queryKeys.otherPref, 'open');
      const user = userEvent.setup();
      renderCustomisation();

      await user.click(checkbox(CLEAR_AUTO_SUBMIT));
      await user.click(saveButton());

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalled());
      expect(api.saveUserPreferencesBulk).toHaveBeenCalledWith(
        expect.objectContaining({ autoSubmitClearAll: 'on' }),
      );
      expect(testQueryClient.getQueryData(queryKeys.autoSubmitPref)).toBeUndefined();
      expect(testQueryClient.getQueryData(queryKeys.otherPref)).toBe('open');
      expect(checkbox(CLEAR_AUTO_SUBMIT)).not.toBeChecked();
    });

    test('leaves remembered choices alone when clear auto submit is unticked', async () => {
      testQueryClient.setQueryData(queryKeys.autoSubmitPref, true);
      const user = userEvent.setup();
      renderCustomisation();

      await user.click(saveButton());

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalled());
      expect(testQueryClient.getQueryData(queryKeys.autoSubmitPref)).toBe(true);
    });

    test('Save is disabled while saving', async () => {
      const save = holdSavePreferences();
      const user = userEvent.setup();
      renderCustomisation();

      await user.click(saveButton());

      await waitFor(() => expect(saveButton()).toBeDisabled());

      save.resolve();

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledTimes(1));
      expect(saveButton()).toBeEnabled();
    });
  });

  describe('error handling', () => {
    test('a failed save shows an error and leaves settings alone', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
      stubSavePreferencesFail();
      const user = userEvent.setup();
      renderCustomisation();

      await user.click(saveButton());

      expect(await screen.findByRole('alert')).toHaveTextContent(
        'An error occurred while saving your preferences. Please try again.',
      );
      expect(api.saveUserPreferencesBulk).toHaveBeenCalledTimes(1);
      expect(mockNotifySuccess).not.toHaveBeenCalled();
      expect(mockUserContext.updateUser).not.toHaveBeenCalled();
    });
  });
});
