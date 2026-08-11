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

import { Equal, Regex } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Button from './Button';
import DateFilter from './DateFilter';
import DateRangeFilter from './DateRangeFilter';
import InputFilter from './InputFilter';
import AndOrButton from './forms/AndOrButton';
import Checkbox from './forms/Checkbox';
import SelectDropdown from './forms/SelectDropdown';
import type { SelectOption } from './forms/SelectDropdown';
import TagInput from './forms/TagInput';
import TextInput from './forms/TextInput';

import type { FilterOption } from '@/types/filter';
import type { Tag } from '@/types/tag';
import { isValidRegex } from '@/utils/regex';

export type { FilterOption };

export interface FilterConfigItem<T> {
  label: string;
  name: keyof T & string;
  placeholder?: string;
  type?: 'select' | 'text' | 'number' | 'tags' | 'date-range' | 'date' | 'checkbox';
  className?: string;
  options?: FilterOption[];
  clearable?: boolean;
  searchable?: boolean;
  onLoadMore?: () => void;
  hasMore?: boolean;
  isLoadingMore?: boolean;
  isLoading?: boolean;
  onSearch?: (term: string) => void;
  isJalali?: boolean;
  initialLabel?: string;
  resolveLabel?: (value: string) => Promise<string>;
  showAndOr?: boolean;
  andOrKey?: keyof T & string;
  showRegex?: boolean;
  regexKey?: keyof T & string;
  showExactTags?: boolean;
  exactTagsKey?: keyof T & string;
  showTimePicker?: boolean;
}

type FilterValue = string | number | boolean | null | Tag[];

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

type DebouncedTextWithControlsProps = {
  name: string;
  label: string;
  placeholder?: string;
  externalValue: string;
  onChange: (name: string, value: string) => void;
  showAndOr?: boolean;
  andOr?: 'AND' | 'OR';
  onAndOrChange?: (value: 'AND' | 'OR') => void;
  showRegex?: boolean;
  isRegex?: boolean;
  onRegexChange?: (value: boolean) => void;
};

function DebouncedTextWithControls({
  name,
  label,
  placeholder,
  externalValue,
  onChange,
  showAndOr,
  andOr = 'OR',
  onAndOrChange,
  showRegex,
  isRegex = false,
  onRegexChange,
}: DebouncedTextWithControlsProps) {
  const { t } = useTranslation();
  const [localValue, setLocalValue] = useState<string>(String(externalValue ?? ''));
  const [regexError, setRegexError] = useState<string>('');
  const debounceRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    setLocalValue(String(externalValue ?? ''));
  }, [externalValue]);

  useEffect(() => {
    if (!isRegex) {
      setRegexError('');
    }
  }, [isRegex]);

  const handleChange = (val: string) => {
    setLocalValue(val);

    if (isRegex && val && !isValidRegex(val)) {
      setRegexError(t('Invalid regular expression'));
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
      return;
    }
    setRegexError('');

    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }
    debounceRef.current = setTimeout(() => {
      onChange(name, val);
    }, 300);
  };

  const prefix = showAndOr ? (
    <AndOrButton value={andOr} onChange={(val) => onAndOrChange?.(val)} />
  ) : undefined;

  const suffix = showRegex ? (
    <div
      className="px-3 py-2 flex items-center cursor-pointer justify-center"
      onClick={() => {
        if (!isRegex && localValue && !isValidRegex(localValue)) {
          setRegexError(t('Invalid regular expression'));
          return;
        }
        onRegexChange?.(!isRegex);
      }}
      title={t('Use RegEx pattern matching')}
    >
      <Button
        variant="tertiary"
        leftIcon={Regex}
        className={twMerge(
          'p-1.5',
          isRegex
            ? 'bg-xibo-blue-600 text-white hover:bg-xibo-blue-700 hover:text-white'
            : 'text-xibo-blue-600 hover:text-xibo-blue-800',
        )}
      ></Button>
    </div>
  ) : undefined;

  return (
    <TextInput
      name={name}
      label={label}
      placeholder={placeholder}
      value={localValue}
      onChange={handleChange}
      prefix={prefix}
      suffix={suffix}
      error={regexError || undefined}
    />
  );
}

type FilterInputsProps<T> = {
  isOpen: boolean;
  values: T;
  options: FilterConfigItem<T>[];
  onChange: (name: keyof T & string, value: FilterValue) => void;
  onReset?: () => void;
  onApply?: () => void;
};

export default function FilterInputs<T>({
  isOpen,
  options,
  values,
  onChange,
  onReset,
  onApply,
}: FilterInputsProps<T>) {
  const { t } = useTranslation();
  return (
    <div
      aria-hidden={!isOpen}
      className={`
        transition-all duration-300 ease-in-out w-full relative
        ${
          isOpen
            ? 'opacity-100 visible mt-4 shrink-0 max-h-[min(37.5rem,50vh)] overflow-auto'
            : 'max-h-0 opacity-0 invisible mt-0 overflow-hidden'
        }
      `}
    >
      <div className="relative bg-slate-50 p-5 pt-7 flex flex-col gap-4">
        {onReset && (
          <Button
            variant="tertiary"
            className="absolute right-1 top-1 focus:outline-0"
            onClick={onReset}
          >
            {t('Reset')}
          </Button>
        )}
        <div className="grid grid-cols-[repeat(auto-fit,minmax(15rem,1fr))] gap-4 items-end">
          {options.map((filter) => {
            const filterType = filter.type || 'select';

            if (filterType === 'text' || filterType === 'number') {
              const hasControls = filterType === 'text' && (filter.showAndOr || filter.showRegex);

              if (hasControls) {
                return (
                  <DebouncedTextWithControls
                    key={filter.name}
                    name={filter.name}
                    label={filter.label}
                    placeholder={filter.placeholder}
                    externalValue={values[filter.name] as string}
                    onChange={(name, val) => onChange(name as keyof T & string, val)}
                    showAndOr={filter.showAndOr}
                    andOr={
                      filter.andOrKey ? ((values[filter.andOrKey] as 'AND' | 'OR') ?? 'OR') : 'OR'
                    }
                    onAndOrChange={(val) => {
                      if (filter.andOrKey) {
                        onChange(filter.andOrKey, val);
                      }
                    }}
                    showRegex={filter.showRegex}
                    isRegex={
                      filter.regexKey ? ((values[filter.regexKey] as boolean) ?? false) : false
                    }
                    onRegexChange={(val) => {
                      if (filter.regexKey) {
                        onChange(filter.regexKey, val);
                      }
                    }}
                  />
                );
              }

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
              const andOr = filter.andOrKey
                ? ((values[filter.andOrKey] as 'AND' | 'OR') ?? 'OR')
                : 'OR';
              const exactTags = filter.exactTagsKey
                ? ((values[filter.exactTagsKey] as boolean) ?? false)
                : false;

              const prefix = filter.showAndOr ? (
                <AndOrButton
                  value={andOr}
                  onChange={(val) => filter.andOrKey && onChange(filter.andOrKey, val)}
                />
              ) : undefined;

              const suffix = filter.showExactTags ? (
                <div
                  className="px-3 py-2 flex items-center cursor-pointer justify-center"
                  onClick={() => filter.exactTagsKey && onChange(filter.exactTagsKey, !exactTags)}
                  title={t('Match exact characters only')}
                >
                  <Button
                    variant="tertiary"
                    leftIcon={Equal}
                    className={twMerge(
                      'p-1.5',
                      exactTags
                        ? 'bg-xibo-blue-600 text-white hover:bg-xibo-blue-700 hover:text-white'
                        : 'text-xibo-blue-600 hover:text-xibo-blue-800',
                    )}
                  ></Button>
                </div>
              ) : undefined;

              return (
                <TagInput
                  key={filter.name}
                  label={filter.label}
                  value={(values[filter.name] as Tag[]) || []}
                  onChange={(tags) => onChange(filter.name, tags)}
                  className={filter.className}
                  placeholder={filter.placeholder}
                  prefix={prefix}
                  suffix={suffix}
                  allowValues={false}
                  compact
                />
              );
            }

            if (filterType === 'date') {
              return (
                <DateFilter
                  key={filter.name}
                  label={filter.label}
                  name={filter.name}
                  value={(values[filter.name] as string) ?? ''}
                  onChange={(name, val) => onChange(name as keyof T & string, val)}
                  isJalali={filter.isJalali}
                  className={filter.className}
                  showTimePicker={filter.showTimePicker}
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

            if (filterType === 'checkbox') {
              return (
                <div
                  key={filter.name}
                  className={twMerge('flex items-center h-11.25', filter.className)}
                >
                  <Checkbox
                    id={filter.name}
                    label={filter.label}
                    checked={Boolean(values[filter.name])}
                    onChange={(e) => onChange(filter.name, e.target.checked)}
                  />
                </div>
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

            const sharedProps = {
              label: filter.label,
              value: currentStr,
              initialLabel: filter.initialLabel,
              resolveLabel: filter.resolveLabel,
              options: selectOptions,
              searchable: filter.searchable ?? true,
              clearable: filter.clearable ?? true,
              placeholder,
              onSelect: (val: string) => {
                if (!val) {
                  onChange(filter.name, null);
                } else {
                  const orig = realOpts.find((o) => String(o.value) === val);
                  onChange(filter.name, orig !== undefined ? orig.value : val);
                }
              },
              isLoading: filter.isLoading,
              className: `w-full md:w-auto md:flex-1 min-w-0 ${filter.className ?? ''}`,
            };

            if (filter.onLoadMore && filter.onSearch) {
              return (
                <SelectDropdown
                  key={filter.name}
                  {...sharedProps}
                  onLoadMore={filter.onLoadMore}
                  hasMore={filter.hasMore ?? false}
                  isLoadingMore={filter.isLoadingMore ?? false}
                  onSearch={filter.onSearch}
                />
              );
            }

            return <SelectDropdown key={filter.name} {...sharedProps} onSearch={filter.onSearch} />;
          })}
        </div>
        {onApply && (
          <div className="flex justify-end sticky bottom-0 bg-slate-50 pt-2 pb-1">
            <Button variant="secondary" className="font-semibold h-11.25" onClick={onApply}>
              {t('Apply Filter')}
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
