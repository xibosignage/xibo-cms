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
  useFloating,
  autoUpdate,
  offset,
  flip,
  shift,
  size,
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import DatePicker from './DatePicker';

import { useDateFormatter } from '@/hooks/useDateFormatter';
import { formatCmsDate, formatDateTime, toLocalDateKey } from '@/utils/date';

type DateFilterProps = {
  label: string;
  name: string;
  value: string;
  onChange: (name: string, value: string | null) => void;
  isJalali?: boolean;
  className?: string;
  showTimePicker?: boolean;
};

export default function DateFilter({
  label,
  name,
  value,
  onChange,
  isJalali = false,
  className,
  showTimePicker = true,
}: DateFilterProps) {
  const { t } = useTranslation();
  const { formatDate, dateFormat } = useDateFormatter();
  const [open, setOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open,
    onOpenChange: setOpen,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [
      offset(4),
      flip(),
      shift(),
      size({
        apply({ rects, elements }) {
          Object.assign(elements.floating.style, {
            minWidth: `${rects.reference.width}px`,
          });
        },
        padding: 8,
      }),
    ],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  const getDisplayLabel = () => {
    if (!value) return t('Any time');

    if (!showTimePicker) {
      return formatCmsDate(value, { format: dateFormat, timeZone: 'UTC' });
    }

    const date = new Date(value.replace(' ', 'T'));
    if (isNaN(date.getTime())) return value;
    return formatDate(date);
  };

  return (
    <div
      className={twMerge(
        'flex flex-col gap-1 text-gray-500 w-full md:w-auto md:flex-1 min-w-0',
        className,
      )}
    >
      <label className="text-sm font-semibold text-gray-500 leading-5">{label}</label>
      <button
        ref={refs.setReference}
        {...getReferenceProps()}
        type="button"
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
      <FloatingPortal>
        {open && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white overflow-hidden rounded-lg border border-gray-200 shadow-lg"
          >
            <div className="pb-4 box-border">
              <DatePicker
                mode="single"
                disableFutureDates
                isJalali={isJalali}
                showTimePicker={showTimePicker}
                onCancel={() => setOpen(false)}
                onApply={(v) => {
                  if (v.type === 'single') {
                    onChange(
                      name,
                      showTimePicker ? formatDateTime(v.date) : toLocalDateKey(v.date),
                    );
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
        )}
      </FloatingPortal>
    </div>
  );
}
