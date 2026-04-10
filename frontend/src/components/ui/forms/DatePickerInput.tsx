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
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { Calendar, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import DatePicker from '@/components/ui/DatePicker';

interface DatePickerInputProps {
  label: string;
  value?: string;
  onChange: (value: string) => void;
  helpText?: string;
  disablePastDates?: boolean;
  disableFutureDates?: boolean;
}

export default function DatePickerInput({
  label,
  value,
  onChange,
  helpText,
  disablePastDates = false,
  disableFutureDates = false,
}: DatePickerInputProps) {
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

  const displayValue = value ? new Date(value).toLocaleString() : '';

  return (
    <div className="flex flex-col gap-1.5 relative">
      <label className="text-sm font-semibold text-gray-500">{label}</label>

      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        className="flex relative cursor-pointer h-11.25"
      >
        <button
          type="button"
          className="flex items-center justify-center bg-white border border-gray-200 border-r-0 rounded-l-lg px-3 hover:bg-gray-200 transition-colors"
        >
          <Calendar size={18} className="text-gray-500" />
        </button>

        <input
          type="text"
          readOnly
          value={displayValue}
          placeholder={t('Select date')}
          className={twMerge(
            'flex-1 py-2 px-3 bg-white border border-gray-200 text-sm cursor-pointer outline-none focus:border-xibo-blue-500',
            !value ? 'rounded-r-lg' : '',
          )}
        />

        {value && (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onChange('');
            }}
            className="flex items-center justify-center bg-white border border-gray-200 border-l-0 rounded-r-lg px-3 hover:bg-gray-200 transition-colors z-10 relative"
          >
            <X size={18} className="text-gray-500" />
          </button>
        )}
      </div>

      {helpText && <p className="text-xs text-gray-400">{helpText}</p>}

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white border border-gray-200 rounded-lg shadow-xl"
          >
            <DatePicker
              mode="single"
              value={value ? { date: new Date(value) } : undefined}
              disablePastDates={disablePastDates}
              disableFutureDates={disableFutureDates}
              onApply={(selection) => {
                if (selection && selection.type === 'single' && selection.date) {
                  onChange(selection.date.toISOString());
                }
                setIsOpen(false);
              }}
              onCancel={() => {
                setIsOpen(false);
              }}
            />
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}
