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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { CopyDisplayGroupFormData } from '@/pages/Displays/DisplayGroup/hooks/useDisplayGroupActions';
import type { DisplayGroup } from '@/types/displayGroup';
import { incrementName } from '@/utils/stringUtils';

interface CopyDisplayGroupModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (data: CopyDisplayGroupFormData) => void;
  displayGroup: DisplayGroup | null;
  isLoading?: boolean;
  existingNames: string[];
}

export default function CopyDisplayGroupModal({
  isOpen = true,
  onClose,
  onConfirm,
  displayGroup,
  isLoading,
  existingNames,
}: CopyDisplayGroupModalProps) {
  const { t } = useTranslation();
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [copyMembers, setCopyMembers] = useState(false);
  const [copyAssignments, setCopyAssignments] = useState(false);
  const [copyTags, setCopyTags] = useState(false);
  const [error, setError] = useState<string | undefined>();

  useEffect(() => {
    if (displayGroup && isOpen) {
      setName(incrementName(displayGroup.displayGroup));
      setDescription(displayGroup.description ?? '');
    }
    setError(undefined);
    setCopyMembers(false);
    setCopyAssignments(false);
    setCopyTags(false);
  }, [displayGroup, isOpen]);

  const handleSave = () => {
    const trimmed = name.trim();

    if (!trimmed) {
      setError(t('Name is required'));
      return;
    }

    const nameExists = existingNames.some((n) => n.toLowerCase() === trimmed.toLowerCase());
    if (nameExists) {
      setError(t('A display group with this name already exists'));
      return;
    }

    setError(undefined);
    onConfirm({ name: trimmed, description, copyMembers, copyAssignments, copyTags });
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Display Group')}
      onClose={onClose}
      size="md"
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: isLoading ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isLoading,
        },
      ]}
    >
      <div className="px-8 pb-8 space-y-4">
        <TextInput
          name="name"
          value={name}
          label={t('Name')}
          helpText={t('The Name for the Display Group')}
          error={error}
          onChange={(val) => {
            setName(val);
            if (error) setError(undefined);
          }}
        />

        <TextInput
          name="description"
          value={description}
          label={t('Description')}
          helpText={t('The description for the Display Group')}
          onChange={setDescription}
          multiline
          rows={3}
        />

        <div className="space-y-3 pt-1">
          <div>
            <Checkbox
              id="copyMembers"
              label={t('Copy Members?')}
              checked={copyMembers}
              onChange={(e) => setCopyMembers(e.target.checked)}
            />
            <p className="ms-7 text-sm text-gray-500">
              {t('Should we copy all members to the new Display Group?')}
            </p>
          </div>

          <div>
            <Checkbox
              id="copyAssignments"
              label={t('Copy Assignments?')}
              checked={copyAssignments}
              onChange={(e) => setCopyAssignments(e.target.checked)}
            />
            <p className="ms-7 text-sm text-gray-500">
              {t('Should we copy all file and layout assignments to the new Display Group?')}
            </p>
          </div>

          <div>
            <Checkbox
              id="copyTags"
              label={t('Copy Tags?')}
              checked={copyTags}
              onChange={(e) => setCopyTags(e.target.checked)}
            />
            <p className="ms-7 text-sm text-gray-500">
              {t('Should we copy all tags to the new Display Group?')}
            </p>
          </div>
        </div>
      </div>
    </Modal>
  );
}
