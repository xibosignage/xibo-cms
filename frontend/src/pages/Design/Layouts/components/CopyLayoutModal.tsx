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
import type { Layout } from '@/types/layout';
import { incrementName } from '@/utils/stringUtils';

interface CopyLayoutModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (newName: string, description: string, copyMediaFiles: boolean) => void;
  layout: Layout | null;
  isLoading?: boolean;
  existingNames: string[];
}

export default function CopyLayoutModal({
  isOpen,
  onClose,
  onConfirm,
  layout,
  isLoading,
  existingNames,
}: CopyLayoutModalProps) {
  const { t } = useTranslation();
  const [newName, setNewName] = useState('');
  const [description, setDescription] = useState('');
  const [copyMediaFiles, setCopyMediaFiles] = useState(false);
  const [error, setError] = useState<string | undefined>();

  useEffect(() => {
    if (layout && isOpen) {
      setNewName(incrementName(layout.name || layout.layout));
      setDescription(layout.description ?? '');
      setCopyMediaFiles(false);
    }

    setError(undefined);
  }, [layout, isOpen]);

  const handleSave = () => {
    const trimmed = newName.trim();

    if (!trimmed) {
      setError(t('Name is required'));
      return;
    }

    const nameExists = existingNames.some((name) => name.toLowerCase() === trimmed.toLowerCase());

    if (nameExists) {
      setError(t('A layout with this name already exists'));
      return;
    }

    setError(undefined);
    onConfirm(trimmed, description, copyMediaFiles);
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Layout')}
      onClose={onClose}
      size="sm"
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
          name="newName"
          value={newName}
          label={t('New name')}
          helpText={t('The Name for the copy (1 - 50 characters)')}
          error={error}
          onChange={(val) => {
            setNewName(val);
            if (error) {
              setError(undefined);
            }
          }}
        />
        <TextInput
          name="description"
          label={t('Description')}
          value={description}
          placeholder={t('Add description')}
          helpText={t('Optional description for this layout')}
          onChange={(value) => setDescription(value)}
          multiline
          rows={3}
        />
        <Checkbox
          id="copyMediaFiles"
          className="items-center"
          title={t('Make new copies of all media?')}
          label={t(
            'This will duplicate all media that is currently assigned to the item being copied.',
          )}
          checked={copyMediaFiles}
          onChange={(e) => {
            setCopyMediaFiles(!!e.target.checked);
          }}
        />
      </div>
    </Modal>
  );
}
