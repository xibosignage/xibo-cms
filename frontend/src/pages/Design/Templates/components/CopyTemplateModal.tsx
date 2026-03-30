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

import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getTemplateSchema } from '@/schema/templates';
import type { Template } from '@/types/templates';
import { incrementName } from '@/utils/stringUtils';

interface CopyTemplateModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (newName: string, description: string, copyMediaFiles: boolean) => void;
  template: Template | null;
  isLoading?: boolean;
  existingNames: string[];
}

type CopyTemplateFormErrors = Partial<Record<'name' | 'description', string>>;

export default function CopyTemplateModal({
  isOpen = true,
  onClose,
  onConfirm,
  template,
  isLoading,
  existingNames,
}: CopyTemplateModalProps) {
  const { t } = useTranslation();
  const [newName, setNewName] = useState('');
  const [description, setDescription] = useState('');
  const [copyMediaFiles, setCopyMediaFiles] = useState(false);
  const [formErrors, setFormErrors] = useState<CopyTemplateFormErrors>({});

  useEffect(() => {
    if (template && isOpen) {
      setNewName(incrementName(template.layout));
      setDescription(template.description ?? '');
      setCopyMediaFiles(false);
    }

    setFormErrors({});
  }, [template, isOpen]);

  const handleSave = () => {
    const schema = getTemplateSchema(t).pick({
      name: true,
      description: true,
    });

    const result = schema.safeParse({
      name: newName,
      description,
    });

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors;

      const mappedErrors: CopyTemplateFormErrors = {};

      Object.entries(fieldErrors).forEach(([key, value]) => {
        if (value?.[0]) {
          mappedErrors[key as keyof CopyTemplateFormErrors] = value[0];
        }
      });

      setFormErrors(mappedErrors);
      return;
    }

    setFormErrors({});

    const trimmed = newName.trim();
    const nameExists = existingNames.some((name) => name.toLowerCase() === trimmed.toLowerCase());

    if (nameExists) {
      setFormErrors({
        name: t('A template with this name already exists'),
      });
      return;
    }

    onConfirm(trimmed, description, copyMediaFiles);
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Template')}
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
          helpText={t('The Name for the copy (1 - 100 characters)')}
          error={formErrors.name}
          onChange={(val) => {
            setNewName(val);
            if (formErrors.name) {
              setFormErrors((prev) => ({ ...prev, name: undefined }));
            }
          }}
        />
        <TextInput
          name="description"
          label={t('Description')}
          value={description}
          placeholder={t('Add description')}
          helpText={t('Optional description for this template')}
          onChange={(value) => setDescription(value)}
          multiline
          rows={3}
          error={formErrors.description}
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
