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

import Checkbox from '@/components/ui/forms/Checkbox';
import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getDatasetColumnSchema } from '@/schema/dataset';
import { createDatasetColumn, updateDatasetColumn } from '@/services/datasetApi';
import type { DatasetColumn, DataTypeId, DataSetColumnTypeId } from '@/types/datasetColumn';

type DatasetColumnFormErrors = {
  heading?: string;
};

export interface UpdateDatasetColumnRequest {
  heading: string;
  dataSetColumnTypeId: DataSetColumnTypeId;
  dataTypeId: DataTypeId;
  listContent: string;
  remoteField: string;
  columnOrder: number;
  tooltip: string;
  formula: string;
  showFilter: boolean;
  dateFormat: string;
  showSort: boolean;
  isRequired: boolean;
}

interface AddAndEditDatasetColumnModalProps {
  type: 'add' | 'edit';
  isOpen?: boolean;
  datasetId: string;
  datasetSourceId?: '1' | '2';
  column?: DatasetColumn | null;
  onClose: () => void;
  onSave: () => void;
  columnTypes?: { id: number; name: string }[];
  dataTypes?: { id: number; name: string }[];
}

const DEFAULT_DRAFT: UpdateDatasetColumnRequest = {
  heading: '',
  dataSetColumnTypeId: 1,
  dataTypeId: 1,
  listContent: '',
  remoteField: '',
  columnOrder: 0,
  tooltip: '',
  formula: '',
  showFilter: false,
  dateFormat: '',
  showSort: false,
  isRequired: false,
};

export function AddAndEditDatasetColumnModal({
  type,
  isOpen = true,
  datasetId,
  datasetSourceId = '1',
  column,
  onClose,
  onSave,
  columnTypes = [
    { id: 1, name: 'Value' },
    { id: 2, name: 'Formula' },
    { id: 3, name: 'Remote' },
  ],
  dataTypes = [
    { id: 1, name: 'String' },
    { id: 2, name: 'Number' },
    { id: 3, name: 'Date' },
    { id: 4, name: 'External Image' },
    { id: 5, name: 'Library Image' },
    { id: 6, name: 'HTML' },
  ],
}: AddAndEditDatasetColumnModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [formErrors, setFormErrors] = useState<DatasetColumnFormErrors>({});

  const [draft, setDraft] = useState<UpdateDatasetColumnRequest>({ ...DEFAULT_DRAFT });

  useEffect(() => {
    if (type === 'edit' && column) {
      setDraft({
        heading: column.heading ?? '',
        dataSetColumnTypeId: (column.dataSetColumnTypeId ?? 1) as DataSetColumnTypeId,
        dataTypeId: column.dataTypeId ?? 1,
        listContent: column.listContent ?? '',
        remoteField: column.remoteField ?? '',
        columnOrder: column.columnOrder ?? 0,
        tooltip: column.tooltip ?? '',
        formula: column.formula ?? '',
        showFilter: Boolean(column.showFilter),
        dateFormat: column.dateFormat ?? '',
        showSort: Boolean(column.showSort),
        isRequired: Boolean(column.isRequired),
      });
    } else {
      setDraft({ ...DEFAULT_DRAFT });
    }
    setApiError(undefined);
  }, [column, type, isOpen]);

  const updateDraft = <K extends keyof UpdateDatasetColumnRequest>(
    field: K,
    value: UpdateDatasetColumnRequest[K],
  ) => {
    setDraft((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = () => {
    const schema = getDatasetColumnSchema(t);
    const result = schema.safeParse(draft);

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors;
      setFormErrors({
        heading: fieldErrors.heading?.[0],
      });
      return;
    }

    setFormErrors({});
    setApiError(undefined);

    startTransition(async () => {
      try {
        if (type === 'edit' && column) {
          await updateDatasetColumn(datasetId, column.dataSetColumnId, draft);
        } else {
          await createDatasetColumn(datasetId, draft);
        }

        onSave();
        onClose();
      } catch (err: unknown) {
        const apiError = err as { response?: { data?: { message?: string } } };
        setApiError(apiError.response?.data?.message || t('An unexpected error occurred.'));
      }
    });
  };

  const clearError = (field: keyof DatasetColumnFormErrors) => {
    setFormErrors((prev) => ({ ...prev, [field]: undefined }));
  };

  const isValueType = draft.dataSetColumnTypeId === 1;
  const isFormulaType = draft.dataSetColumnTypeId === 2;
  const isRemoteType = draft.dataSetColumnTypeId === 3;
  const isDateFormat = draft.dataTypeId === 3;

  return (
    <Modal
      title={type === 'add' ? t('Add Column') : t('Edit Column')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        { label: isPending ? t('Saving…') : t('Save'), onClick: handleSave, disabled: isPending },
      ]}
    >
      <div className="flex flex-col px-8 pb-8 gap-3 overflow-y-auto">
        <TextInput
          name="heading"
          label={t('Heading')}
          placeholder={t('Enter heading')}
          helpText={t('The heading for this Column. You cannot use a column name with spaces.')}
          value={draft.heading}
          error={formErrors.heading}
          onChange={(val) => {
            updateDraft('heading', val);
            clearError('heading');
          }}
        />

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <SelectDropdown
            label={t('Data Type')}
            value={String(draft.dataTypeId)}
            options={dataTypes.map((d) => ({ label: t(d.name), value: String(d.id) }))}
            helpText={t('The DataType of the Intended Data')}
            onSelect={(val) => {
              updateDraft('dataTypeId', parseInt(val, 10) as DataTypeId);
            }}
          />

          <SelectDropdown
            label={t('Column Type')}
            value={String(draft.dataSetColumnTypeId)}
            options={columnTypes.map((c) => ({ label: t(c.name), value: String(c.id) }))}
            helpText={t('Select the Column Type')}
            onSelect={(val) => {
              updateDraft('dataSetColumnTypeId', parseInt(val, 10) as DataSetColumnTypeId);
            }}
          />
        </div>

        <NumberInput
          name="columnOrder"
          label={t('Column Order')}
          helpText={t('The order this column should be displayed in when entering data')}
          value={draft.columnOrder}
          onChange={(val) => updateDraft('columnOrder', val)}
        />

        {isValueType && (
          <TextInput
            name="listContent"
            label={t('List Content')}
            helpText={t('A comma separated list of items to present in a combo box')}
            value={draft.listContent}
            onChange={(val) => updateDraft('listContent', val)}
          />
        )}

        {isValueType && (
          <TextInput
            name="tooltip"
            label={t('Tooltip')}
            helpText={t('Optional message to be displayed under the input when entering data')}
            value={draft.tooltip}
            onChange={(val) => updateDraft('tooltip', val)}
          />
        )}

        {isRemoteType && (
          <TextInput
            name="remoteField"
            label={t('Remote Data Path')}
            helpText={
              datasetSourceId === '1'
                ? t(
                    'Give the JSON-path in the remote data for the value that you want to fill this column. This path should be relative to the DataRoot configured on the DataSet.',
                  )
                : t(
                    'Provide Column number relative to the spreadsheet, numeration starts from 0 ie to get values from Column A from spreadsheet to this column enter 0',
                  )
            }
            value={draft.remoteField}
            onChange={(val) => updateDraft('remoteField', val)}
          />
        )}

        {isDateFormat && isRemoteType && (
          <TextInput
            name="dateFormat"
            label={t('Date Format')}
            helpText={t('Enter a PHP date format to parse the dates from the source.')}
            value={draft.dateFormat}
            onChange={(val) => updateDraft('dateFormat', val)}
          />
        )}

        {isFormulaType && (
          <TextInput
            name="formula"
            label={t('Formula')}
            helpText={t("Enter a MySQL statement suitable to use in a 'SELECT' statement")}
            value={draft.formula}
            multiline
            rows={2}
            onChange={(val) => updateDraft('formula', val)}
          />
        )}

        <Checkbox
          id="showFilter"
          label={t('Show as a filter option on the Data Entry Page?')}
          className="px-3 py-2.5"
          title={t('Filter?')}
          checked={draft.showFilter}
          onChange={(e) => updateDraft('showFilter', e.target.checked)}
        />

        <Checkbox
          id="showSort"
          label={t(
            'Enable sorting on the Data Entry Page? We recommend that the number of sortable columns is kept to a minimum.',
          )}
          className="px-3 py-2.5"
          title={t('Sort?')}
          checked={draft.showSort}
          onChange={(e) => updateDraft('showSort', e.target.checked)}
        />

        {isValueType && (
          <Checkbox
            id="isRequired"
            label={t('Should the value for this Column be required?')}
            className="px-3 py-2.5"
            title={t('Required?')}
            checked={draft.isRequired}
            onChange={(e) => updateDraft('isRequired', e.target.checked)}
          />
        )}

        {isFormulaType && (
          <>
            <div className="bg-blue-50 text-blue-800 text-sm p-3 rounded-lg flex flex-col gap-2">
              <p>
                {t(
                  'Two substitutions are available for Formula columns: [DisplayId] and [DisplayGeoLocation]. They will be substituted at run time with the Display ID / Display Geo Location (MySQL GEOMETRY).',
                )}
              </p>
            </div>

            <div className="bg-blue-50 text-blue-800 text-sm p-3 rounded-lg flex flex-col gap-2">
              <p>
                {t(
                  'Client side formula is also available for Formula columns : $dateFormat(columnName,format,language), for example $dateFormat(date,l,de), would return textual representation of a day in German language from the full date in date column',
                )}
              </p>
            </div>
          </>
        )}
      </div>
    </Modal>
  );
}
