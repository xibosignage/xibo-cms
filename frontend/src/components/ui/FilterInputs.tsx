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

import { t } from 'i18next';

import Button from './Button';
import InputFilter from './InputFilter';
import type { FilterOption } from './SelectFilter';
import SelectFilter from './SelectFilter';
import SelectDropdown from './forms/SelectDropdown';
import type { SelectOption } from './forms/SelectDropdown';
import TagInput from './forms/TagInput';

import type { Tag } from '@/types/tag';

export interface FilterConfigItem<T> {
  label: string;
  name: keyof T & string;
  placeholder?: string;
  type?: 'select' | 'text' | 'number' | 'tags' | 'paged-select';
  className?: string;
  options?: FilterOption[];
  shouldTranslateOptions?: boolean;
  showAllOption?: boolean;
  allLabel?: string;
  allowCustomRange?: boolean;
  isJalali?: boolean;
  onLoadMore?: () => void;
  hasMore?: boolean;
  isLoadingMore?: boolean;
  isLoading?: boolean;
}

type FilterValue = string | number | null | Tag[];

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
        {' '}
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
              <InputFilter
                key={filter.name}
                label={filter.label}
                placeholder={filter.placeholder}
                name={filter.name}
                type={filterType}
                value={values[filter.name] as string | number}
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

          if (filterType === 'paged-select') {
            const pagedOptions: SelectOption[] = (filter.options ?? []).map((o) => ({
              label: o.label,
              value: String(o.value ?? ''),
            }));
            const currentValue = values[filter.name];
            const stringValue =
              currentValue !== null && currentValue !== undefined ? String(currentValue) : '';

            return (
              <SelectDropdown
                key={filter.name}
                label={filter.label}
                value={stringValue}
                options={pagedOptions}
                onSelect={(val) =>
                  onChange(filter.name as keyof T & string, val === '' ? null : Number(val))
                }
                searchable
                clearable
                placeholder={filter.placeholder ?? 'All'}
                onLoadMore={filter.onLoadMore}
                hasMore={filter.hasMore}
                isLoadingMore={filter.isLoadingMore}
                isLoading={filter.isLoading}
                className={`w-full md:w-auto md:flex-1 min-w-0 ${filter.className ?? ''}`}
              />
            );
          }

          return (
            <SelectFilter
              key={filter.name}
              label={filter.label}
              name={filter.name}
              value={values[filter.name] as string}
              onChange={(name, val) => onChange(name as keyof T & string, val)}
              options={filter.options ?? []}
              className={filter?.className}
              shouldTranslateOptions={filter.shouldTranslateOptions}
              showAllOption={filter.showAllOption}
              allLabel={filter.allLabel}
              allowCustomRange={filter.allowCustomRange}
              isJalali={filter.isJalali}
            />
          );
        })}
      </div>
    </div>
  );
}
