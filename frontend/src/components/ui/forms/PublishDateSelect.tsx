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
import { CalendarClock, ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import DatePicker from '../DatePicker';

import { useUserContext } from '@/context/UserContext';

export type PublishValue = { type: 'now' } | { type: 'scheduled'; date: Date };

interface PublishDateSelectProps {
  value?: PublishValue;
  onSelect: (value: PublishValue) => void;
}

export default function PublishDateSelect({ value, onSelect }: PublishDateSelectProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const timeZone = user?.settings?.defaultTimezone;

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
          <CalendarClock size={16} />
        </span>
        <span className="p-3 flex-1 text-sm">
          {value?.type === 'now'
            ? t('Publish Now')
            : value?.type === 'scheduled'
              ? value.date.toLocaleString(undefined, timeZone ? { timeZone } : undefined)
              : t('Select publish option')}
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

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white shadow-xl rounded-lg border border-gray-100 overflow-hidden flex flex-col"
          >
            <div className="flex">
              <div className="flex flex-col text-sm p-3 flex-1">
                {/* Publish Now */}
                <button
                  type="button"
                  className="text-left p-2 rounded-lg hover:bg-gray-100 font-medium"
                  onClick={() => {
                    onSelect({ type: 'now' });
                    setIsOpen(false);
                  }}
                >
                  {t('Publish Now')}
                </button>

                {/* TODO: Schedule publish date is still not implemented in the BE */}
                <button
                  type="button"
                  className="text-left p-2 rounded-lg hover:bg-gray-100 font-medium flex justify-between items-center"
                  onClick={() => setOpenDatePicker((prev) => !prev)}
                >
                  {t('Custom Date')}
                  <ChevronRight size={14} className="text-gray-500" />
                </button>
              </div>
              {openDatePicker && (
                <DatePicker
                  mode="single"
                  value={{
                    date: value?.type === 'scheduled' ? value.date : undefined,
                  }}
                  disablePastDates={true}
                  onCancel={() => {
                    setOpenDatePicker(false);
                  }}
                  onApply={(val) => {
                    if (val.type === 'single') {
                      onSelect({
                        type: 'scheduled',
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
