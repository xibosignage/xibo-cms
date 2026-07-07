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

import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { Media } from '@/types/media';
import type { Tag } from '@/types/tag';
import { incrementName } from '@/utils/stringUtils';

interface CopyMediaModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (newName: string, tags: Tag[]) => void;
  media: Media | null;
  isLoading?: boolean;
  existingNames: string[];
}

export default function CopyMediaModal({
  isOpen = true,
  onClose,
  onConfirm,
  media,
  isLoading,
  existingNames,
}: CopyMediaModalProps) {
  const { t } = useTranslation();
  const [newName, setNewName] = useState('');
  const [newTags, setNewTags] = useState([] as Tag[]);
  const [error, setError] = useState<string | undefined>();

  useEffect(() => {
    if (media && isOpen) {
      setNewName(incrementName(media.name));
      setNewTags(media.tags ?? []);
    }
  }, [media, isOpen]);

  const handleSave = () => {
    const trimmed = newName.trim();

    if (!trimmed) {
      setError(t('Name is required'));
      return;
    }

    const nameExists = existingNames.some((name) => name.toLowerCase() === trimmed.toLowerCase());

    if (nameExists) {
      setError(t('A media item with this name already exists'));
      return;
    }

    setError(undefined);
    onConfirm(trimmed, newTags);
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Media')}
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
          name="name"
          value={newName}
          label={t('New name')}
          error={error}
          onChange={(val) => {
            setNewName(val);
            if (error) setError(undefined);
          }}
        />

        <TagInput onChange={setNewTags} value={newTags}></TagInput>
      </div>
    </Modal>
  );
}
