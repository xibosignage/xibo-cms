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

import { useActionState, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import { saveUserPreferencesBulk } from '@/services/userApi';

interface PreferencesModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function PreferencesModal({ isOpen, onClose }: PreferencesModalProps) {
  const { t } = useTranslation();
  const { user, updateUser } = useUserContext();

  const [useLibraryDuration, setUseLibraryDuration] = useState<boolean>(
    Boolean(user?.settings?.useLibraryDuration ?? false),
  );
  const [showThumbnailColumn, setShowThumbnailColumn] = useState<boolean>(
    Boolean(user?.settings?.showThumbnailColumn ?? true),
  );
  const [autoSubmitClearAll, setAutoSubmitClearAll] = useState<boolean>(false);
  const [isAlwaysUseManualAddUserForm, setIsAlwaysUseManualAddUserForm] = useState<boolean>(
    Boolean(user?.settings?.isAlwaysUseManualAddUserForm ?? false),
  );
  const [rememberFolderTreeGlobally, setRememberFolderTreeGlobally] = useState<boolean>(
    Boolean(user?.settings?.rememberFolderTreeStateGlobally ?? true),
  );

  useEffect(() => {
    if (isOpen && user) {
      setUseLibraryDuration(Boolean(user.settings?.useLibraryDuration ?? false));
      setShowThumbnailColumn(Boolean(user.settings?.showThumbnailColumn ?? true));
      setAutoSubmitClearAll(false);
      setIsAlwaysUseManualAddUserForm(
        Boolean(user.settings?.isAlwaysUseManualAddUserForm ?? false),
      );
      setRememberFolderTreeGlobally(
        Boolean(user.settings?.rememberFolderTreeStateGlobally ?? true),
      );
    }
  }, [isOpen, user]);

  const [submitError, submitAction, isPending] = useActionState(async () => {
    try {
      const payload = {
        navigationMenuPosition: 'vertical',
        useLibraryDuration: useLibraryDuration ? 'on' : 'off',
        showThumbnailColumn: showThumbnailColumn ? 'on' : 'off',
        autoSubmitClearAll: autoSubmitClearAll ? 'on' : 'off',
        isAlwaysUseManualAddUserForm: isAlwaysUseManualAddUserForm ? 'on' : 'off',
        rememberFolderTreeStateGlobally: rememberFolderTreeGlobally ? 'on' : 'off',
      };

      await saveUserPreferencesBulk(payload);

      if (user) {
        updateUser({
          settings: {
            ...user.settings,
            useLibraryDuration: useLibraryDuration ? 1 : 0,
            showThumbnailColumn: showThumbnailColumn ? 1 : 0,
            autoSubmitClearAll: autoSubmitClearAll ? 1 : 0,
            isAlwaysUseManualAddUserForm: isAlwaysUseManualAddUserForm ? 1 : 0,
            rememberFolderTreeStateGlobally: rememberFolderTreeGlobally ? 1 : 0,
          },
        });
      }

      onClose();
      return null;
    } catch (error) {
      console.error('Failed to save preferences:', error);
      return t('An error occurred while saving your preferences. Please try again.');
    }
  }, null);

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={t('Preferences')}
      isPending={isPending}
      error={submitError || undefined}
      actions={[
        { label: t('Cancel'), variant: 'secondary', onClick: onClose, disabled: isPending },
        {
          label: t('Save'),
          variant: 'primary',
          isSubmit: true,
          formId: 'user-preferences-form',
          disabled: isPending,
        },
      ]}
    >
      <div className="p-8">
        <form id="user-preferences-form" action={submitAction} className="flex flex-col gap-6">
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
            id="isAlwaysUseManualAddUserForm"
            title={t('Always use manual Add User form?')}
            label={t(
              'If selected the manual Add User form will always open when you click Add User, otherwise the onboarding form will open.',
            )}
            checked={isAlwaysUseManualAddUserForm}
            onChange={(e) => {
              setIsAlwaysUseManualAddUserForm(e.target.checked);
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
        </form>
      </div>
    </Modal>
  );
}
