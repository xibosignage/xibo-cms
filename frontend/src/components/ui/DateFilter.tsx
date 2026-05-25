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

import DatePicker from './DatePicker';

import { useClickOutside } from '@/hooks/useClickOutside';
import { useKeydown } from '@/hooks/useKeydown';
import { formatDateTime } from '@/utils/date';

type DateFilterProps = {
  label: string;
  name: string;
  value: string;
  onChange: (name: string, value: string | null) => void;
  isJalali?: boolean;
  className?: string;
};

export default function DateFilter({
  label,
  name,
  value,
  onChange,
  isJalali = false,
  className,
}: DateFilterProps) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useKeydown('Escape', () => setOpen(false), !!open);
  useClickOutside(ref, () => setOpen(false));

  const getDisplayLabel = () => {
    if (!value) return t('Any time');
    const date = new Date(value.replace(' ', 'T'));
    if (isNaN(date.getTime())) return value;
    return date.toLocaleDateString();
  };

  return (
    <div
      className={twMerge(
        'flex flex-col gap-1 text-gray-500 w-full md:w-auto md:flex-1 min-w-0 relative',
        className,
      )}
      ref={ref}
    >
      <label className="text-sm font-semibold text-gray-500 leading-5">{label}</label>
      <button
        type="button"
        onClick={() => setOpen((p) => !p)}
        className="w-full h-11.25 flex items-center justify-between bg-white rounded-lg border border-gray-200 pl-4 text-left"
      >
        <span className="text-sm">{getDisplayLabel()}</span>
        <div
          className={twMerge(
            'px-4 transition-all duration-200 ease-in',
            open ? 'rotate-180' : 'rotate-0',
          )}
        >
          <ChevronDown size={14} />
        </div>
      </button>
      <div
        className={twMerge(
          'absolute z-50 mt-1 transition-all duration-200 ease-linear bg-xibo-white overflow-hidden rounded-lg border border-gray-200 shadow-lg',
          open
            ? 'max-h-175 opacity-100 top-17 right-0'
            : 'max-h-0 opacity-0 top-15 pointer-events-none',
        )}
      >
        <div className="pb-4 box-border">
          <DatePicker
            mode="single"
            disableFutureDates
            isJalali={isJalali}
            onCancel={() => setOpen(false)}
            onApply={(v) => {
              if (v.type === 'single') {
                onChange(name, formatDateTime(v.date));
              }
              setOpen(false);
            }}
          />
          {value && (
            <div className="px-4 pt-2">
              <button
                type="button"
                onClick={() => {
                  onChange(name, null);
                  setOpen(false);
                }}
                className="w-full text-sm text-gray-500 hover:text-gray-700 text-left"
              >
                {t('Clear')}
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
