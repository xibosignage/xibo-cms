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

import Checkbox from '@/components/ui/forms/Checkbox';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { copyUserGroup } from '@/services/userGroupApi';
import type { UserGroup } from '@/types/userGroup';

interface CopyUserGroupModalProps {
  userGroup: UserGroup;
  onClose: () => void;
  onSuccess: () => void;
}

export default function CopyUserGroupModal({
  userGroup,
  onClose,
  onSuccess,
}: CopyUserGroupModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();

  const [name, setName] = useState(`${userGroup.group} (Copy)`);
  const [copyMembers, setCopyMembers] = useState(false);
  const [copyFeatures, setCopyFeatures] = useState(false);
  const [nameError, setNameError] = useState('');
  const [apiError, setApiError] = useState<string | undefined>();

  const handleSave = () => {
    setNameError('');
    setApiError(undefined);

    if (!name.trim()) {
      setNameError(t('Group name is required'));
      return;
    }

    startTransition(async () => {
      try {
        await copyUserGroup(userGroup.groupId, {
          group: name.trim(),
          copyMembers: copyMembers ? 1 : 0,
          copyFeatures: copyFeatures ? 1 : 0,
        });

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
      title={t('Copy User Group')}
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
          label: isPending ? t('Copying...') : t('Copy'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      <div className="flex flex-col gap-4 p-5">
        <TextInput
          name="group"
          label={t('New Group Name')}
          value={name}
          onChange={setName}
          error={nameError}
        />

        <Checkbox
          id="copyMembers"
          title={t('Copy Members')}
          label={t('Copy the members from the original group')}
          checked={copyMembers}
          onChange={() => setCopyMembers((prev) => !prev)}
        />

        <Checkbox
          id="copyFeatures"
          title={t('Copy Features')}
          label={t('Copy the feature access from the original group')}
          checked={copyFeatures}
          onChange={() => setCopyFeatures((prev) => !prev)}
        />
      </div>
    </Modal>
  );
}
