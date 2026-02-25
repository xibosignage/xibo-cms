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

import { CalendarCheck2Icon, ChevronDown, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import DatePicker from '../DatePicker';

import type { ExpiryValue } from '@/utils/date';

interface ExpiryDateSelectProps {
  value?: ExpiryValue;
  options: string[];
  isOpen: boolean;
  onToggle: () => void;
  onSelect: (value: ExpiryValue) => void;
}

export default function ExpiryDateSelect({
  value,
  options,
  isOpen,
  onToggle,
  onSelect,
}: ExpiryDateSelectProps) {
  const { t } = useTranslation();
  const [openDatePicker, setOpenDatePicker] = useState(false);
  const dropdownRef = useRef<HTMLDivElement | null>(null);
  const containerRef = useRef<HTMLDivElement | null>(null);

  const updatePosition = useCallback(() => {
    if (!isOpen || !containerRef.current || !dropdownRef.current) return;

    const containerRect = containerRef.current.getBoundingClientRect();
    const dropdownRect = dropdownRef.current.getBoundingClientRect();

    const viewportHeight = window.innerHeight;
    const gap = 4;

    const spaceBelow = viewportHeight - containerRect.bottom - gap;
    const spaceAbove = containerRect.top - gap;

    const contentHeight = dropdownRect.height || 240;

    let top = containerRect.bottom + gap;
    let transformOrigin = 'top';

    if (spaceBelow < contentHeight && spaceAbove > spaceBelow) {
      top = containerRect.top - contentHeight - gap;
      transformOrigin = 'bottom';
    }

    Object.assign(dropdownRef.current.style, {
      top: `${top}px`,
      left: `${containerRect.left}px`,
      width: `${containerRect.width}px`,
      transformOrigin,
      opacity: '1',
    });
  }, [isOpen]);

  useLayoutEffect(() => {
    if (!isOpen) return;

    requestAnimationFrame(() => updatePosition());

    const resizeObserver = new ResizeObserver(() => updatePosition());
    if (dropdownRef.current) resizeObserver.observe(dropdownRef.current);

    return () => resizeObserver.disconnect();
  }, [isOpen, updatePosition]);

  useEffect(() => {
    if (!isOpen) return;

    const closeOnScroll = () => onToggle();

    window.addEventListener('wheel', closeOnScroll, { passive: true });

    return () => window.removeEventListener('wheel', closeOnScroll);
  }, [isOpen, onToggle]);

  return (
    <div className="relative overflow-visible">
      <label className="text-xs font-semibold text-gray-500">{t('Expiry Date')}</label>
      <div
        ref={containerRef}
        className="w-full border border-gray-200 rounded-lg flex items-center"
        onClick={onToggle}
      >
        <span className="p-3 text-gray-500 border-r text-sm border-gray-200">
          <CalendarCheck2Icon size={16} />
        </span>
        <span className="p-3 flex-1 text-sm cursor-pointer">
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

      {/* Dropdown */}
      {isOpen &&
        createPortal(
          <div
            ref={dropdownRef}
            style={{
              position: 'fixed',
              zIndex: 9999,
              opacity: 0,
            }}
            className="bg-white shadow-xl rounded-lg border border-gray-100 overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-100"
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
                    }}
                  >
                    {t(option)}
                  </button>
                ))}
                <div className="flex border-b border-gray-200" />
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
                  onCancel={() => setOpenDatePicker(false)}
                  onApply={(val) => {
                    if (val.type === 'single') {
                      onSelect({
                        type: 'datePicked',
                        date: val.date,
                      });
                    }
                    setOpenDatePicker(false);
                  }}
                />
              )}
            </div>
          </div>,
          document.body,
        )}
    </div>
  );
}
