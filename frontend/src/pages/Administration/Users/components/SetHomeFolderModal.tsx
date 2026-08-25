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

import { notify } from '@/components/ui/Notification';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import Modal from '@/components/ui/modals/Modal';
import { setUserHomeFolder } from '@/services/userApi';
import type { User } from '@/types/user';

export interface SetHomeFolderModalProps {
  users: User[];
  onClose: () => void;
  onSuccess: () => void;
}

export default function SetHomeFolderModal({ users, onClose, onSuccess }: SetHomeFolderModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [selectedFolderId, setSelectedFolderId] = useState<number>(users[0]?.homeFolderId ?? 1);
  const [selectedFolderText, setSelectedFolderText] = useState<string | null>(null);
  const [apiError, setApiError] = useState<string | undefined>();

  const handleSave = () => {
    startTransition(async () => {
      const results = await Promise.allSettled(
        users.map((u) => setUserHomeFolder(u.userId, selectedFolderId)),
      );

      const failed = results.filter((r) => r.status === 'rejected');
      if (failed.length > 0) {
        const firstRejected = failed[0] as PromiseRejectedResult;
        const reason = firstRejected.reason;
        const message =
          isAxiosError(reason) && reason.response?.data?.message
            ? reason.response.data.message
            : t('{{count}} user(s) could not be updated.', { count: failed.length });
        setApiError(message);
        onSuccess();
        return;
      }

      notify.success(t('{{count}} user(s) updated successfully.', { count: users.length }));
      onSuccess();
      onClose();
    });
  };

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={
        users.length > 1
          ? t('Set Home Folder for {{count}} users', { count: users.length })
          : t('Set Home Folder for {{name}}', { name: users[0]?.userName })
      }
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
          {users.length > 1
            ? t(
                'Select the home folder for the selected users. Content created by these users will be placed in this folder by default.',
              )
            : t(
                'Select the home folder for this user. Content created by this user will be placed in this folder by default.',
              )}
        </p>
        <SelectFolder
          selectedId={selectedFolderId}
          selectedText={selectedFolderText}
          enforceViewPermission={false}
          onSelect={(folder) => {
            setSelectedFolderId(folder?.id ?? 1);
            setSelectedFolderText(folder?.text ?? null);
          }}
        />
      </div>
    </Modal>
  );
}
