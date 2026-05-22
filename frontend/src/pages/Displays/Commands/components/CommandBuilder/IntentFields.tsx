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

import { Minus, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import type { IntentExtra } from './commandStringUtils';

import Button from '@/components/ui/Button';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';

const TYPE_OPTIONS = [
  { value: 'activity', label: 'activity' },
  { value: 'service', label: 'service' },
  { value: 'broadcast', label: 'broadcast' },
];

const EXTRA_TYPE_OPTIONS = [
  { value: 'string', label: 'string' },
  { value: 'int', label: 'int' },
  { value: 'bool', label: 'bool' },
  { value: 'intArray', label: 'intArray' },
];

interface IntentFieldsProps {
  intentType: string;
  name: string;
  extras: IntentExtra[];
  onTypeChange: (type: string) => void;
  onNameChange: (name: string) => void;
  onExtrasChange: (extras: IntentExtra[]) => void;
}

export default function IntentFields({
  intentType,
  name,
  extras,
  onTypeChange,
  onNameChange,
  onExtrasChange,
}: IntentFieldsProps) {
  const { t } = useTranslation();

  const addExtra = () => {
    onExtrasChange([...extras, { name: '', type: 'string', value: '' }]);
  };

  const removeExtra = (index: number) => {
    onExtrasChange(extras.filter((_, i) => i !== index));
  };

  const updateExtra = (index: number, field: keyof IntentExtra, value: string) => {
    const updated = extras.map((extra, i) => (i === index ? { ...extra, [field]: value } : extra));
    onExtrasChange(updated as IntentExtra[]);
  };

  return (
    <div className="flex flex-col gap-4">
      <SelectDropdown
        label={t('Type')}
        value={intentType}
        options={TYPE_OPTIONS}
        onSelect={(val) => onTypeChange(val || 'activity')}
      />

      <TextInput
        name="intentName"
        label={t('Intent')}
        placeholder={t('Enter intent name')}
        value={name}
        onChange={onNameChange}
      />

      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <label className="text-sm font-medium text-gray-700">{t('Extras')}</label>
          <Button
            variant="iconLink"
            onClick={addExtra}
            className="h-6 text-xibo-blue-600 w-6 bg-xibo-blue-100 text-xs"
          >
            <Plus className="h-3 w-3" />
          </Button>
        </div>

        {extras.map((extra, index) => (
          <div key={index} className="flex items-center gap-2">
            <TextInput
              name={`extra-name-${index}`}
              placeholder={t('Name')}
              value={extra.name}
              onChange={(val) => updateExtra(index, 'name', val)}
              className="w-41"
            />
            <SelectDropdown
              value={extra.type}
              options={EXTRA_TYPE_OPTIONS}
              onSelect={(val) => updateExtra(index, 'type', val)}
              className="w-41"
            />
            <TextInput
              name={`extra-value-${index}`}
              placeholder={t('Value')}
              value={extra.value}
              onChange={(val) => updateExtra(index, 'value', val)}
              wrapperClassName="flex-1"
            />
            <button
              type="button"
              onClick={() => removeExtra(index)}
              className="shrink-0 p-2 h-6 w-6 bg-red-100 text-red-600 rounded-lg cursor-pointer hover:bg-red-200 text-xs center"
            >
              <Minus className="h-4 w-4" />
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
