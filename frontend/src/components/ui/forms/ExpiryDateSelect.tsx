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
import { CalendarCheck2Icon, ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import DatePicker from '../DatePicker';

import type { ExpiryValue } from '@/utils/date';

interface ExpiryDateSelectProps {
  value?: ExpiryValue;
  options: string[];
  onSelect: (value: ExpiryValue) => void;
}

export default function ExpiryDateSelect({ value, options, onSelect }: ExpiryDateSelectProps) {
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState(false);
  const [openDatePicker, setOpenDatePicker] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: (open) => {
      setIsOpen(open);
      if (!open) {
        setOpenDatePicker(false);
      }
    },
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
      }),
    ],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  return (
    <div className="relative overflow-visible">
      <label className="text-sm font-semibold text-gray-500">{t('Expiry Date')}</label>

      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        className="w-full border border-gray-200 rounded-lg flex items-center cursor-pointer bg-white"
      >
        <span className="p-3 text-gray-500 border-r text-sm border-gray-200">
          <CalendarCheck2Icon size={16} />
        </span>
        <span className="p-3 flex-1 text-sm">
          {value && value.type === 'never'
            ? t('Never Expire')
            : value?.type === 'preset'
              ? t(value.value)
              : value?.date.toLocaleString()}
        </span>
        <button
          type="button"
          className={twMerge(
            'p-3 text-gray-500 transition-transform duration-300 ease-in-out',
            isOpen ? 'rotate-180' : 'rotate-0',
          )}
        >
          <ChevronDown size={14} />
        </button>
      </div>
      <span className="text-xs text-gray-400 leading-snug flex mt-1">
        {t('Set when this media is removed from all Layouts and widgets.')}
      </span>

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white shadow-xl rounded-lg border border-gray-100 overflow-hidden flex flex-col"
          >
            <span className="bg-gray-100 p-2 text-sm font-semibold text-gray-500 uppercase flex w-full">
              {t('Select Expiry Date')}
            </span>

            <div className="flex">
              <div className="flex flex-col text-sm p-3 flex-1 border-r border-gray-200">
                {options.map((option) => (
                  <button
                    key={option}
                    type="button"
                    className="text-left p-2 rounded-lg hover:bg-gray-100 font-medium cursor-pointer"
                    onClick={() => {
                      if (option === 'Never Expire') {
                        onSelect({ type: 'never' });
                      } else {
                        onSelect({
                          type: 'preset',
                          value: option,
                        });
                      }
                      setIsOpen(false);
                    }}
                  >
                    {t(option)}
                  </button>
                ))}

                <div className="flex border-b border-gray-200 my-1" />

                <button
                  type="button"
                  className="text-left p-2 rounded-lg hover:bg-gray-100 font-medium cursor-pointer flex justify-between items-center"
                  onClick={() => setOpenDatePicker((prev) => !prev)}
                >
                  {t('Choose Date')}
                  <ChevronRight size={14} className="text-gray-500" />
                </button>
              </div>

              {openDatePicker && (
                <DatePicker
                  mode="single"
                  value={{ date: value?.type === 'datePicked' ? value.date : undefined }}
                  disablePastDates={true}
                  onCancel={() => {
                    setOpenDatePicker(false);
                  }}
                  onApply={(val) => {
                    if (val.type === 'single') {
                      onSelect({
                        type: 'datePicked',
                        date: val.date,
                      });
                    }
                    setIsOpen(false);
                  }}
                />
              )}
            </div>
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}
