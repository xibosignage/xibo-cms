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

import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import MediaInput from '@/components/ui/forms/MediaInput';
import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import {
  createDatasetRow,
  updateDatasetRow,
  type DynamicRowData,
  type DatasetRowValue,
} from '@/services/datasetApi';
import type { DatasetColumn } from '@/types/datasetColumn';

interface AddAndEditDataModalProps {
  type: 'add' | 'edit';
  isOpen: boolean;
  datasetId: string;
  columnsSchema: DatasetColumn[];
  rowData?: DynamicRowData | null;
  onClose: () => void;
  onSave: () => void;
  rowIdKey?: string;
}

const formatToSqlDateTime = (isoString: string): string => {
  if (!isoString) {
    return '';
  }

  const date = new Date(isoString);
  const pad = (num: number) => String(num).padStart(2, '0');

  const year = date.getFullYear();
  const month = pad(date.getMonth() + 1);
  const day = pad(date.getDate());
  const hours = pad(date.getHours());
  const minutes = pad(date.getMinutes());
  const seconds = pad(date.getSeconds());

  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
};

export function AddAndEditDataModal({
  type,
  isOpen,
  datasetId,
  columnsSchema,
  rowData,
  onClose,
  onSave,
  rowIdKey = 'id',
}: AddAndEditDataModalProps) {
  const { t } = useTranslation();

  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();

  const [draft, setDraft] = useState<Record<string, DatasetRowValue>>({});

  useEffect(() => {
    if (isOpen) {
      setApiError(undefined);

      const initialDraft: Record<string, DatasetRowValue> = {};

      columnsSchema.forEach((col) => {
        if (col.dataSetColumnTypeId === 1) {
          const columnId = String(col.dataSetColumnId);

          if (type === 'edit' && rowData) {
            initialDraft[columnId] = rowData[col.heading] ?? rowData[columnId] ?? '';
          } else {
            initialDraft[columnId] = '';
          }
        }
      });

      setDraft(initialDraft);
    }
  }, [isOpen, type, rowData, columnsSchema]);

  const updateDraft = (columnId: number, value: DatasetRowValue) => {
    setDraft((prev) => ({ ...prev, [String(columnId)]: value }));
  };

  const handleSave = () => {
    for (const col of columnsSchema) {
      if (col.dataSetColumnTypeId === 1 && col.isRequired) {
        const val = draft[String(col.dataSetColumnId)];
        if (val === undefined || val === null || val === '') {
          setApiError(t(`The field "{{heading}}" is required.`, { heading: col.heading }));
          return;
        }
      }
    }

    setApiError(undefined);

    startTransition(async () => {
      try {
        if (type === 'edit' && rowData) {
          const rowId = rowData[rowIdKey] as string | number;
          if (!rowId) {
            throw new Error(t('Row ID is missing. Cannot update.'));
          }
          await updateDatasetRow(datasetId, rowId, draft);
        } else {
          await createDatasetRow(datasetId, draft);
        }

        onSave();
        onClose();
      } catch (err: unknown) {
        const error = err as { response?: { data?: { message?: string } }; message?: string };
        setApiError(
          error.response?.data?.message || error.message || t('An unexpected error occurred.'),
        );
      }
    });
  };

  return (
    <Modal
      title={type === 'add' ? t('Add Data') : t('Edit Data')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        { label: isPending ? t('Saving…') : t('Save'), onClick: handleSave, disabled: isPending },
      ]}
    >
      <div className="flex flex-col px-8 pb-8 gap-4 overflow-y-auto">
        {columnsSchema
          .filter((col) => col.dataSetColumnTypeId === 1)
          .sort((a, b) => (a.columnOrder || 0) - (b.columnOrder || 0))
          .map((col) => {
            const isRequired = Boolean(col.isRequired);
            const label = `${col.heading} ${isRequired ? '*' : ''}`;
            const currentValue = draft[String(col.dataSetColumnId)];

            // Date Picker (dataTypeId === 3)
            if (col.dataTypeId === 3) {
              return (
                <DatePickerInput
                  key={col.dataSetColumnId}
                  label={label}
                  helpText={col.tooltip}
                  value={currentValue ? String(currentValue) : undefined}
                  onChange={(isoString) => {
                    if (isoString) {
                      const formattedDate = formatToSqlDateTime(isoString);
                      updateDraft(col.dataSetColumnId, formattedDate);
                    } else {
                      updateDraft(col.dataSetColumnId, '');
                    }
                  }}
                />
              );
            }

            // Library Image (dataTypeId === 5)
            if (col.dataTypeId === 5) {
              return (
                <MediaInput
                  key={col.dataSetColumnId}
                  label={label}
                  helpText={col.tooltip}
                  value={currentValue ? String(currentValue) : undefined}
                  onChange={(mediaId) => {
                    updateDraft(col.dataSetColumnId, mediaId);
                  }}
                />
              );
            }

            if (col.dataTypeId === 1 && col.listContent) {
              const options = col.listContent.split(',').map((opt) => {
                const trimmed = opt.trim();
                return { label: trimmed, value: trimmed };
              });

              return (
                <SelectDropdown
                  key={col.dataSetColumnId}
                  label={label}
                  value={String(currentValue || '')}
                  options={options}
                  helper={col.tooltip}
                  onSelect={(val) => {
                    updateDraft(col.dataSetColumnId, val);
                  }}
                />
              );
            }

            // Number Input (dataTypeId === 2)
            if (col.dataTypeId === 2) {
              return (
                <NumberInput
                  key={col.dataSetColumnId}
                  name={col.heading}
                  label={label}
                  helpText={col.tooltip}
                  value={Number(currentValue) || 0}
                  onChange={(val) => {
                    updateDraft(col.dataSetColumnId, val);
                  }}
                />
              );
            }

            // Default: String / HTML / External Image
            return (
              <TextInput
                key={col.dataSetColumnId}
                name={col.heading}
                label={label}
                helpText={col.tooltip}
                value={String(currentValue || '')}
                onChange={(val) => {
                  updateDraft(col.dataSetColumnId, val);
                }}
              />
            );
          })}
      </div>
    </Modal>
  );
}
