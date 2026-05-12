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

import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from './Button';
import DateRangeFilter from './DateRangeFilter';
import InputFilter from './InputFilter';
import SelectDropdown from './forms/SelectDropdown';
import type { SelectOption } from './forms/SelectDropdown';
import TagInput from './forms/TagInput';

import type { FilterOption } from '@/types/filter';
import type { Tag } from '@/types/tag';

export type { FilterOption };

export interface FilterConfigItem<T> {
  label: string;
  name: keyof T & string;
  placeholder?: string;
  type?: 'select' | 'text' | 'number' | 'tags' | 'date-range';
  className?: string;
  options?: FilterOption[];
  onLoadMore?: () => void;
  hasMore?: boolean;
  isLoadingMore?: boolean;
  isLoading?: boolean;
  onSearch?: (term: string) => void;
  isJalali?: boolean;
}

type FilterValue = string | number | null | Tag[];

type DebouncedInputFilterProps = {
  name: string;
  label: string;
  placeholder?: string;
  type: 'text' | 'number';
  externalValue: string | number;
  onChange: (name: string, value: string | number | null) => void;
  className?: string;
};

function DebouncedInputFilter({ externalValue, onChange, ...props }: DebouncedInputFilterProps) {
  const [localValue, setLocalValue] = useState<string | number>(externalValue ?? '');
  const debounceRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    setLocalValue(externalValue ?? '');
  }, [externalValue]);

  const handleChange = (name: string, val: string | number | null) => {
    setLocalValue(val ?? '');
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }
    debounceRef.current = setTimeout(() => {
      onChange(name, val);
    }, 300);
  };

  return <InputFilter {...props} value={localValue} onChange={handleChange} />;
}

type FilterInputsProps<T> = {
  isOpen: boolean;
  values: T;
  options: FilterConfigItem<T>[];
  onChange: (name: keyof T & string, value: FilterValue) => void;
  onReset?: () => void;
};

export default function FilterInputs<T>({
  isOpen,
  options,
  values,
  onChange,
  onReset,
}: FilterInputsProps<T>) {
  const { t } = useTranslation();
  return (
    <div
      aria-hidden={!isOpen}
      className={`
        transition-all duration-300 ease-in-out w-full
        ${
          isOpen
            ? 'max-h-150 opacity-100 visible mt-4 overflow-visible'
            : 'max-h-0 opacity-0 invisible mt-0 overflow-hidden'
        }
      `}
    >
      <div className="relative bg-slate-50 p-5 pt-7 grid grid-cols-[repeat(auto-fit,minmax(10rem,1fr))] gap-4 items-end">
        {onReset && (
          <Button
            variant="tertiary"
            className="absolute right-1 top-1 focus:outline-0"
            onClick={onReset}
          >
            {t('Reset')}
          </Button>
        )}
        {options.map((filter) => {
          const filterType = filter.type || 'select';

          if (filterType === 'text' || filterType === 'number') {
            return (
              <DebouncedInputFilter
                key={filter.name}
                label={filter.label}
                placeholder={filter.placeholder}
                name={filter.name}
                type={filterType}
                externalValue={values[filter.name] as string | number}
                onChange={(name, val) => onChange(name as keyof T & string, val)}
                className={filter.className}
              />
            );
          }

          if (filterType === 'tags') {
            return (
              <TagInput
                key={filter.name}
                label={filter.label}
                value={(values[filter.name] as Tag[]) || []}
                onChange={(tags) => onChange(filter.name, tags)}
                className={filter.className}
                placeholder={filter.placeholder}
              />
            );
          }

          if (filterType === 'date-range') {
            return (
              <DateRangeFilter
                key={filter.name}
                label={filter.label}
                name={filter.name}
                value={(values[filter.name] as string) ?? ''}
                options={filter.options ?? []}
                onChange={(name, val) => onChange(name as keyof T & string, val)}
                isJalali={filter.isJalali}
                className={filter.className}
              />
            );
          }

          const allOpts = filter.options ?? [];
          const clearOpt = allOpts.find((o) => o.value === null || o.value === '');
          const realOpts = allOpts.filter((o) => o.value !== null && o.value !== '');
          const placeholder = clearOpt?.label ?? filter.placeholder ?? t('All');

          const selectOptions: SelectOption[] = realOpts.map((o) => ({
            label: o.label,
            value: String(o.value),
          }));

          const raw = values[filter.name];
          const currentStr = raw != null && raw !== '' ? String(raw) : '';

          return (
            <SelectDropdown
              key={filter.name}
              label={filter.label}
              value={currentStr}
              options={selectOptions}
              searchable
              clearable
              placeholder={placeholder}
              onSelect={(val) => {
                if (!val) {
                  onChange(filter.name, null);
                } else {
                  const orig = realOpts.find((o) => String(o.value) === val);
                  onChange(filter.name, orig !== undefined ? orig.value : val);
                }
              }}
              onSearch={filter.onSearch}
              onLoadMore={filter.onLoadMore}
              hasMore={filter.hasMore}
              isLoadingMore={filter.isLoadingMore}
              isLoading={filter.isLoading}
              className={`w-full md:w-auto md:flex-1 min-w-0 ${filter.className ?? ''}`}
            />
          );
        })}
      </div>
    </div>
  );
}
