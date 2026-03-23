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
import type { Dataset } from '@/types/dataset';
import { incrementName } from '@/utils/stringUtils';

interface CopyDatasetModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (newName: string, newDescription: string, newCode: string, copyRows: boolean) => void;
  dataset: Dataset | null;
  isLoading?: boolean;
  existingNames: string[];
}

export default function CopyDatasetModal({
  isOpen,
  onClose,
  onConfirm,
  dataset,
  isLoading,
  existingNames,
}: CopyDatasetModalProps) {
  const { t } = useTranslation();
  const [newName, setNewName] = useState('');
  const [newDescription, setNewDescription] = useState('');
  const [newCode, setNewCode] = useState('');
  const [copyRows, setCopyRows] = useState(false);
  const [error, setError] = useState<string | undefined>();

  useEffect(() => {
    if (isOpen) {
      if (dataset) {
        setNewName(incrementName(dataset.dataSet));
        setNewDescription(dataset.description || '');
        setNewCode(dataset.code || '');
        setCopyRows(false);
      } else {
        setNewName('');
        setNewDescription('');
        setNewCode('');
        setCopyRows(false);
      }
    }

    setError(undefined);
  }, [dataset, isOpen]);

  const handleSave = () => {
    const trimmed = newName.trim();

    if (!trimmed) {
      setError(t('Name is required'));
      return;
    }

    const nameExists = existingNames.some(
      (dataSet) => dataSet.toLowerCase() === trimmed.toLowerCase(),
    );

    if (nameExists) {
      setError(t('A dataset item with this name already exists'));
      return;
    }

    setError(undefined);
    onConfirm(trimmed, newDescription, newCode, copyRows);
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Dataset')}
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
          label={t('Name')}
          helpText={t('A name for this DataSet')}
          error={error}
          onChange={(val) => {
            setNewName(val);
            if (error) {
              setError(undefined);
            }
          }}
        />

        <TextInput
          name="newDescription"
          value={newDescription}
          label={t('Description')}
          helpText={t('An optional description')}
          onChange={(val) => {
            setNewDescription(val);
            if (error) {
              setError(undefined);
            }
          }}
        />

        <TextInput
          name="newCode"
          value={newCode}
          label={t('Code')}
          helpText={t(
            'A code which can be used to lookup this DataSet - usually for an API application',
          )}
          onChange={(val) => {
            setNewCode(val);
            if (error) {
              setError(undefined);
            }
          }}
        />

        <Checkbox
          id="copyRows"
          className="items-center"
          title={t('Copy rows?')}
          label={t('Should we copy all the row data from the original dataSet?')}
          checked={copyRows}
          classNameLabel="text-xs"
          onChange={(e) => {
            setCopyRows(!!e.target.checked);
          }}
        />
      </div>
    </Modal>
  );
}
