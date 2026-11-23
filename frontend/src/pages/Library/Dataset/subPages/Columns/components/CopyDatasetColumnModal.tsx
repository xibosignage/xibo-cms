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
import type { DatasetColumn } from '@/types/datasetColumn';
import { incrementName } from '@/utils/stringUtils';

interface CopyDatasetColumnModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (newHeading: string) => void;
  column: DatasetColumn | null;
  isLoading?: boolean;
  existingNames: string[];
}

export default function CopyDatasetColumnModal({
  isOpen = true,
  onClose,
  onConfirm,
  column,
  isLoading,
  existingNames,
}: CopyDatasetColumnModalProps) {
  const { t } = useTranslation();
  const [newHeading, setNewHeading] = useState('');
  const [error, setError] = useState<string | undefined>();

  useEffect(() => {
    if (isOpen && column) {
      setNewHeading(incrementName(column.heading));
    } else {
      setNewHeading('');
    }
    setError(undefined);
  }, [column, isOpen]);

  const handleSave = () => {
    const trimmed = newHeading.trim();

    if (!trimmed) {
      setError(t('Heading is required'));
      return;
    }

    if (/\s/.test(trimmed)) {
      setError(t('You cannot use a column name with spaces.'));
      return;
    }

    const nameExists = existingNames.some(
      (existing) => existing.toLowerCase() === trimmed.toLowerCase(),
    );

    if (nameExists) {
      setError(t('A column with this heading already exists'));
      return;
    }

    setError(undefined);
    onConfirm(trimmed);
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Column')}
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
          name="newHeading"
          value={newHeading}
          label={t('New Heading')}
          helpText={t('A heading for the copied Column. No spaces allowed.')}
          error={error}
          onChange={(val) => {
            setNewHeading(val);
            if (error) setError(undefined);
          }}
        />
      </div>
    </Modal>
  );
}
