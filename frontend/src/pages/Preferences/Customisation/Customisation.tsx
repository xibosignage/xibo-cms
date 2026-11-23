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

import { useQueryClient } from '@tanstack/react-query';
import { useActionState, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import InfoBanner from '@/components/ui/InfoBanner';
import { notify } from '@/components/ui/Notification';
import Checkbox from '@/components/ui/forms/Checkbox';
import { useUserContext } from '@/context/UserContext';
import PreferencesLayout from '@/pages/Preferences/PreferencesLayout';
import { saveUserPreferencesBulk } from '@/services/userApi';
import { isPreferenceEnabled } from '@/utils/preferences';

export default function Customisation() {
  const { t } = useTranslation();
  const { user, updateUser } = useUserContext();
  const queryClient = useQueryClient();

  const [useLibraryDuration, setUseLibraryDuration] = useState<boolean>(
    isPreferenceEnabled(user?.settings?.useLibraryDuration, false),
  );
  const [showThumbnailColumn, setShowThumbnailColumn] = useState<boolean>(
    isPreferenceEnabled(user?.settings?.showThumbnailColumn, true),
  );
  const [autoSubmitClearAll, setAutoSubmitClearAll] = useState<boolean>(false);
  const [rememberFolderTreeGlobally, setRememberFolderTreeGlobally] = useState<boolean>(
    isPreferenceEnabled(user?.settings?.rememberFolderTreeStateGlobally, true),
  );

  const [submitError, submitAction, isPending] = useActionState(async () => {
    try {
      const payload = {
        useLibraryDuration: useLibraryDuration ? 'on' : 'off',
        showThumbnailColumn: showThumbnailColumn ? 'on' : 'off',
        autoSubmitClearAll: autoSubmitClearAll ? 'on' : 'off',
        rememberFolderTreeStateGlobally: rememberFolderTreeGlobally ? 'on' : 'off',
      };

      await saveUserPreferencesBulk(payload);

      if (autoSubmitClearAll) {
        queryClient.removeQueries({
          predicate: (query) =>
            query.queryKey[0] === 'userPref' &&
            typeof query.queryKey[1] === 'string' &&
            query.queryKey[1].startsWith('autoSubmit.'),
        });
      }

      if (user) {
        updateUser({
          settings: {
            ...(user.settings ?? {}),
            useLibraryDuration: useLibraryDuration ? 1 : 0,
            showThumbnailColumn: showThumbnailColumn ? 1 : 0,
            autoSubmitClearAll: autoSubmitClearAll ? 1 : 0,
            rememberFolderTreeStateGlobally: rememberFolderTreeGlobally ? 1 : 0,
          },
        });
      }

      // A one-off action, not a stored setting: untick it once it has been applied.
      setAutoSubmitClearAll(false);
      notify.success(t('Preferences saved'));
      return null;
    } catch (error) {
      console.error('Failed to save preferences:', error);
      return t('An error occurred while saving your preferences. Please try again.');
    }
  }, null);

  return (
    <PreferencesLayout>
      <div className="flex flex-1 flex-col gap-5 p-5">
        <h2 className="text-xl font-semibold text-gray-800">{t('Customisation')}</h2>

        {submitError && <InfoBanner type="danger">{submitError}</InfoBanner>}

        <form
          action={submitAction}
          className="flex flex-col gap-6 p-5 bg-white rounded-lg border border-gray-200"
        >
          <Checkbox
            id="useLibraryDuration"
            title={t('Force current Library duration?')}
            label={t(
              'Assign all Media items to Playlists based on their Library duration, and make it sticky so that changes in the library are not pulled into Layouts.',
            )}
            checked={useLibraryDuration}
            onChange={(e) => {
              setUseLibraryDuration(e.target.checked);
            }}
          />

          <Checkbox
            id="showThumbnailColumn"
            title={t('Auto show thumbnail column?')}
            label={t(
              'When requesting a screenshot from a display should the Thumbnail column be automatically shown if it is not visible?',
            )}
            checked={showThumbnailColumn}
            onChange={(e) => {
              setShowThumbnailColumn(e.target.checked);
            }}
          />

          <Checkbox
            id="autoSubmitClearAll"
            title={t('Clear all auto submit form choices?')}
            label={t('If you have selected to automatically submit any forms, tick here to reset.')}
            checked={autoSubmitClearAll}
            onChange={(e) => {
              setAutoSubmitClearAll(e.target.checked);
            }}
          />

          <Checkbox
            id="rememberFolderTreeStateGlobally"
            title={t('Remember Folder tree state globally?')}
            label={t(
              'When enabled the Folder tree state will be saved globally, each Page will remember the same state. If disabled, the Folder tree state will be saved per Page.',
            )}
            checked={rememberFolderTreeGlobally}
            onChange={(e) => {
              setRememberFolderTreeGlobally(e.target.checked);
            }}
          />

          <div className="flex justify-end">
            <Button type="submit" variant="primary" disabled={isPending}>
              {isPending ? t('Saving...') : t('Save')}
            </Button>
          </div>
        </form>
      </div>
    </PreferencesLayout>
  );
}
