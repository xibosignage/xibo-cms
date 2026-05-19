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

import {
  autoUpdate,
  flip,
  FloatingPortal,
  offset,
  shift,
  useClick,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { ChevronDown, Equal, Regex } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Button from './Button';
import DateFilter from './DateFilter';
import DateRangeFilter from './DateRangeFilter';
import InputFilter from './InputFilter';
import SelectDropdown from './forms/SelectDropdown';
import type { SelectOption } from './forms/SelectDropdown';
import TagInput from './forms/TagInput';
import TextInput from './forms/TextInput';

import type { FilterOption } from '@/types/filter';
import type { Tag } from '@/types/tag';

export type { FilterOption };

export interface FilterConfigItem<T> {
  label: string;
  name: keyof T & string;
  placeholder?: string;
  type?: 'select' | 'text' | 'number' | 'tags' | 'date-range' | 'date';
  className?: string;
  options?: FilterOption[];
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

function AndOrButton({
  value,
  onChange,
}: {
  value: 'AND' | 'OR';
  onChange: (value: 'AND' | 'OR') => void;
}) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [offset(4), flip(), shift()],
  });
  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  return (
    <>
      <button
        type="button"
        ref={refs.setReference}
        {...getReferenceProps()}
        title={value === 'AND' ? t('Match ALL entered terms') : t('Match ANY entered terms')}
        className="flex items-center gap-2 p-3 h-full text-sm font-semibold text-gray-500 cursor-pointer bg-transparent border-none"
      >
        {value}
        <ChevronDown
          size={12}
          className={twMerge('transition-transform duration-200', isOpen && 'rotate-180')}
        />
      </button>
      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white shadow-md rounded-lg border border-gray-200 flex flex-col overflow-clip"
          >
            {(['AND', 'OR'] as const).map((opt) => (
              <button
                key={opt}
                type="button"
                onClick={() => {
                  onChange(opt);
                  setIsOpen(false);
                }}
                className={twMerge(
                  'p-3 text-sm font-semibold text-left hover:bg-gray-100 cursor-pointer',
                  value === opt && 'text-xibo-blue-600',
                )}
              >
                {opt}
              </button>
            ))}
          </div>
        )}
      </FloatingPortal>
    </>
  );
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

    if (isRegex && val) {
      try {
        new RegExp(val);
        setRegexError('');
      } catch {
        setRegexError(t('Invalid regular expression'));
        if (debounceRef.current) {
          clearTimeout(debounceRef.current);
        }
        return;
      }
    } else {
      setRegexError('');
    }

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
      onClick={() => onRegexChange && onRegexChange(!isRegex)}
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
      <div className="relative bg-slate-50 p-5 pt-7 grid grid-cols-[repeat(auto-fit,minmax(15rem,1fr))] gap-4 items-end">
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
              initialLabel={filter.initialLabel}
              resolveLabel={filter.resolveLabel}
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
