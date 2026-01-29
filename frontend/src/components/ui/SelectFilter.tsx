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

import { ChevronDown } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

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

  const selectedOption = options.find((o) => o.value === value);

  useKeydown('Escape', () => setOpen(false), !!open);

  useClickOutside(ref, () => setOpen(false));

  const getDisplayLabel = () => {
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
      <ul
        className={`absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg
    transition-all duration-200 ease-linear
    ${open ? 'max-h-96 opacity-100 top-[68px]' : 'max-h-0 opacity-0 top-[60px] pointer-events-none'}
  `}
      >
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
              option.value === value || (!selectedOption && !showAllOption && option === options[0])
                ? 'bg-gray-50 font-medium'
                : ''
            }`}
          >
            {shouldTranslateOptions ? t(option.label) : option.label}
          </li>
        ))}
      </ul>
    </div>
  );
}
