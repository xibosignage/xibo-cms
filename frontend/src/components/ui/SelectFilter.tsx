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

import { ChevronDown, ChevronRight } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import DatePicker from './DatePicker';

import { useClickOutside } from '@/hooks/useClickOutside';
import { useKeydown } from '@/hooks/useKeydown';

export interface FilterOption {
  label: string;
  value: string | number | null;
}

type SelectFilterProps = {
  label: string;
  name: string;
  value: string;
  options: readonly FilterOption[];
  onChange: (name: string, value: string | number | null) => void;
  className?: string;
  shouldTranslateOptions?: boolean;
  showAllOption?: boolean;
  allLabel?: string;
};

export default function SelectFilter({
  label,
  name,
  value,
  options,
  onChange,
  className,
  shouldTranslateOptions = false,
  showAllOption = true,
  allLabel = 'All',
}: SelectFilterProps) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const [openDatePicker, setOpenDatePicker] = useState(false);

  const selectedOption = options.find((o) => o.value === value);

  useKeydown('Escape', () => setOpen(false), !!open);

  useClickOutside(ref, () => setOpen(false));

  const handleDateApply = (range: { from: Date; to: Date }) => {
    const from = range.from.toISOString();
    const to = range.to.toISOString();

    const value = `range:${from}|${to}`;

    onChange(name, value);
  };

  const getDisplayLabel = () => {
    if (typeof value === 'string' && value.startsWith('range:')) {
      const rangeValue = value.replace('range:', '');
      const [from, to] = rangeValue.split('|');

      if (from && to) {
        const fromDate = new Date(from).toLocaleDateString();
        const toDate = new Date(to).toLocaleDateString();

        return `${fromDate} - ${toDate}`;
      }

      return t('Custom Range');
    }

    if (selectedOption) {
      return shouldTranslateOptions ? t(selectedOption.label) : selectedOption.label;
    }

    if (showAllOption) {
      return allLabel;
    }

    if (options.length > 0) {
      const firstOption = options[0];
      if (firstOption) {
        return shouldTranslateOptions ? t(firstOption.label) : firstOption.label;
      }
    }

    return '';
  };

  return (
    <div
      className={twMerge(
        'flex flex-col gap-1 text-gray-500 w-full md:w-auto md:flex-1 md:basis-auto relative',
        className,
      )}
      ref={ref}
    >
      <label className="text-sm font-semibold">{t(label)}</label>
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        className="flex items-center justify-between bg-white rounded-lg border border-gray-200 pl-4 text-left"
      >
        <span>{getDisplayLabel()}</span>
        <div
          className={twMerge(
            'p-4 transition-all duration-200 ease-in',
            open ? 'rotate-180' : 'rotate-0',
          )}
        >
          <ChevronDown size={14} />
        </div>
      </button>
      <div
        className={twMerge(
          'absolute z-50 mt-1 min-w-full transition-all duration-200 ease-linear flex bg-xibo-white overflow-hidden rounded-lg border border-gray-200 shadow-lg',
          open
            ? 'max-h-[400px] opacity-100 top-[68px] right-0'
            : 'max-h-0 opacity-0 top-[60px] pointer-events-none',
        )}
      >
        <ul className={twMerge(openDatePicker ? 'w-[200px] border-r border-gray-200' : 'w-full')}>
          {showAllOption && (
            <li
              onClick={() => {
                onChange(name, '');
                setOpen(false);
              }}
              className="cursor-pointer px-4 py-2 hover:bg-gray-100 first:rounded-t-md"
            >
              {allLabel}
            </li>
          )}

          {options.map((option) => (
            <li
              key={String(option.value)}
              onClick={() => {
                onChange(name, option.value as string);
                setOpen(false);
              }}
              className={`cursor-pointer px-4 py-2 hover:bg-gray-100 ${
                option.value === value ||
                (!selectedOption && !showAllOption && option === options[0])
                  ? 'bg-gray-50 font-medium'
                  : ''
              }`}
            >
              {shouldTranslateOptions ? t(option.label) : option.label}
            </li>
          ))}
          <div className="flex border-b border-gray-200" />
          {name === 'lastModified' && (
            <li
              onClick={() => {
                setOpenDatePicker((prev) => !prev);
              }}
              className={
                'cursor-pointer px-4 py-2 hover:bg-gray-100 flex justify-between items-center'
              }
            >
              {t('Custom Range')} <ChevronRight size={14} className="text-gray-500" />
            </li>
          )}
        </ul>
        <div
          className={twMerge(
            'transition-all duration-1000 ease-out overflow-hidden',
            openDatePicker
              ? 'opacity-100 translate-y-0 max-w-[500px] max-h-[500px]'
              : 'opacity-0 -translate-y-2 max-w-0 max-h-0 pointer-events-none',
          )}
        >
          <div className="pb-4 box-border">
            <DatePicker
              onCancel={() => setOpenDatePicker(false)}
              onApply={(range) => {
                handleDateApply(range);
                setOpenDatePicker(false);
                setOpen(false);
              }}
            />
          </div>
        </div>
      </div>
    </div>
  );
}
