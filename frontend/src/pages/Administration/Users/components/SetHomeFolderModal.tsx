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

import { isAxiosError } from 'axios';
import { useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import SelectFolder from '@/components/ui/forms/SelectFolder';
import Modal from '@/components/ui/modals/Modal';
import { setUserHomeFolder } from '@/services/userApi';
import type { User } from '@/types/user';

export interface SetHomeFolderModalProps {
  user: User;
  onClose: () => void;
  onSuccess: () => void;
}

export default function SetHomeFolderModal({ user, onClose, onSuccess }: SetHomeFolderModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [selectedFolderId, setSelectedFolderId] = useState<number>(user.homeFolderId ?? 1);
  const [selectedFolderText, setSelectedFolderText] = useState<string | null>(null);
  const [apiError, setApiError] = useState<string | undefined>();

  const handleSave = () => {
    startTransition(async () => {
      try {
        await setUserHomeFolder(user.userId, selectedFolderId);
        onSuccess();
        onClose();
      } catch (err: unknown) {
        if (isAxiosError(err) && err.response?.data?.message) {
          setApiError(err.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred.'));
        }
      }
    });
  };

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={t('Set Home Folder for {{name}}', { name: user.userName })}
      size="md"
      isPending={isPending}
      error={apiError}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary' as const,
          disabled: isPending,
        },
        {
          label: isPending ? t('Saving...') : t('Save'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      <div className="p-5 flex flex-col gap-2">
        <p className="text-sm text-gray-500">
          {t(
            'Select the home folder for this user. Content created by this user will be placed in this folder by default.',
          )}
        </p>
        <SelectFolder
          selectedId={selectedFolderId}
          selectedText={selectedFolderText}
          onSelect={(folder) => {
            setSelectedFolderId(folder?.id ?? 1);
            setSelectedFolderText(folder?.text ?? null);
          }}
        />
      </div>
    </Modal>
  );
}
