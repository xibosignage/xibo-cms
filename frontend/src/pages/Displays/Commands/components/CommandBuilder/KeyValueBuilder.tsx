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
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import TextInput from '@/components/ui/forms/TextInput';

export interface KeyValueEntry {
  key: string;
  value: string;
}

interface KeyValueBuilderProps {
  entries: KeyValueEntry[];
  onChange: (entries: KeyValueEntry[]) => void;
  label?: string;
  keyPlaceholder?: string;
  valuePlaceholder?: string;
}

export default function KeyValueBuilder({
  entries: propEntries,
  onChange,
  label,
  keyPlaceholder,
  valuePlaceholder,
}: KeyValueBuilderProps) {
  const { t } = useTranslation();
  const [entries, setEntries] = useState(propEntries);
  const internalChange = useRef(false);

  const propEntriesKey = JSON.stringify(propEntries);
  useEffect(() => {
    if (internalChange.current) {
      internalChange.current = false;
      return;
    }
    setEntries(propEntries);
  }, [propEntriesKey]);

  const addEntry = () => {
    setEntries((prev) => [...prev, { key: '', value: '' }]);
  };

  const removeEntry = (index: number) => {
    const updated = entries.filter((_, i) => i !== index);
    setEntries(updated);
    internalChange.current = true;
    onChange(updated);
  };

  const updateEntry = (index: number, field: 'key' | 'value', val: string) => {
    const updated = entries.map((entry, i) => (i === index ? { ...entry, [field]: val } : entry));
    setEntries(updated);
    internalChange.current = true;
    onChange(updated);
  };

  return (
    <div className="flex flex-col gap-2">
      {label && (
        <div className="flex items-center justify-between">
          <label className="text-sm font-semibold text-gray-500 leading-4.5">{label}</label>
          <button
            type="button"
            onClick={addEntry}
            className="h-6 w-6 bg-xibo-blue-100 text-xibo-blue-600 rounded-lg cursor-pointer hover:bg-xibo-blue-200 text-xs flex items-center justify-center"
          >
            <Plus className="h-3 w-3" />
          </button>
        </div>
      )}
      {entries.map((entry, index) => (
        <div key={index} className="flex items-center gap-2">
          <TextInput
            name={`kv-key-${index}`}
            placeholder={keyPlaceholder ?? t('Key')}
            value={entry.key}
            onChange={(val) => updateEntry(index, 'key', val)}
            wrapperClassName="flex-1"
          />
          <TextInput
            name={`kv-value-${index}`}
            placeholder={valuePlaceholder ?? t('Value')}
            value={entry.value}
            onChange={(val) => updateEntry(index, 'value', val)}
            wrapperClassName="flex-1"
          />
          <button
            type="button"
            onClick={() => removeEntry(index)}
            className="shrink-0 p-2 h-6 w-6 bg-red-100 text-red-600 rounded-lg cursor-pointer hover:bg-red-200 text-xs center"
          >
            <Minus className="h-4 w-4" />
          </button>
        </div>
      ))}
    </div>
  );
}
