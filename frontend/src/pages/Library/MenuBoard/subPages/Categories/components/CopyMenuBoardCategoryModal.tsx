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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { MenuBoardCategory } from '@/types/menuBoardCategory';
import { incrementName } from '@/utils/stringUtils';

interface CopyMenuBoardCategoryModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (name: string, description: string, code: string) => void;
  category: MenuBoardCategory | null;
  isLoading?: boolean;
  existingNames: string[];
}

export default function CopyMenuBoardCategoryModal({
  isOpen = true,
  onClose,
  onConfirm,
  category,
  isLoading,
  existingNames,
}: CopyMenuBoardCategoryModalProps) {
  const { t } = useTranslation();
  const [newName, setNewName] = useState('');
  const [newDescription, setNewDescription] = useState('');
  const [newCode, setNewCode] = useState('');
  const [error, setError] = useState<string | undefined>();

  useEffect(() => {
    if (isOpen) {
      if (category) {
        setNewName(incrementName(category.name));
        setNewDescription(category.description ?? '');
        setNewCode(category.code ?? '');
      } else {
        setNewName('');
        setNewDescription('');
        setNewCode('');
      }
    }
    setError(undefined);
  }, [category, isOpen]);

  const handleSave = () => {
    const trimmed = newName.trim();

    if (!trimmed) {
      setError(t('Name is required'));
      return;
    }

    if (existingNames.some((n) => n.toLowerCase() === trimmed.toLowerCase())) {
      setError(t('A category with this name already exists'));
      return;
    }

    setError(undefined);
    onConfirm(trimmed, newDescription, newCode);
  };

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Category')}
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
      <div className="px-8 pb-8 space-y-4 pt-4">
        <TextInput
          name="newName"
          value={newName}
          label={t('Name')}
          helpText={t('The Name for this Menu Board Category.')}
          error={error}
          onChange={(val) => {
            setNewName(val);
            if (error) {
              setError(undefined);
            }
          }}
        />

        <TextInput
          name="newCode"
          value={newCode}
          label={t('Code')}
          helpText={t('The Code identifier for this Menu Board Category.')}
          optional
          onChange={(val) => {
            setNewCode(val);
          }}
        />

        <TextInput
          name="newDescription"
          value={newDescription}
          label={t('Description')}
          helpText={t('The description for this Menu Board Category.')}
          optional
          multiline
          onChange={(val) => {
            setNewDescription(val);
          }}
        />
      </div>
    </Modal>
  );
}
