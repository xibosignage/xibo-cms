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
.*/

import { CalendarCheck2Icon, ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import FormDatePicker from '../FormDatePicker';

export type ExpiryValue =
  | { type: 'preset'; value: string }
  | { type: 'range'; from: Date; to: Date };

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

  return (
    <div className="relative overflow-visible">
      <label className="text-xs font-semibold text-gray-500">{t('Expiry Date')}</label>

      <div
        className="w-full border border-gray-200 rounded-lg flex items-center"
        onClick={onToggle}
      >
        <span className="p-3 text-gray-500 border-r text-sm border-gray-200">
          <CalendarCheck2Icon size={16} />
        </span>

        <span className="p-3 flex-1 text-sm cursor-pointer">
          {!value
            ? t('Never Expire')
            : value.type === 'preset'
              ? t(value.value)
              : `${value.from.toLocaleDateString()} – ${value.to.toLocaleDateString()}`}
        </span>

        <button type="button" className="p-3 text-gray-500">
          <ChevronDown size={14} />
        </button>
      </div>

      {/* Dropdown */}
      <div
        className={`absolute top-[70px] w-full bg-white shadow-md rounded-lg overflow-clip transition-all duration-150 ease-in-out z-50
          ${isOpen ? 'opacity-100 max-h-[1000px]' : 'opacity-0 max-h-0'}
        `}
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
                onClick={() =>
                  onSelect({
                    type: 'preset',
                    value: option,
                  })
                }
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
              {t('Custom Range')}
              <ChevronRight size={14} className="text-gray-500" />
            </button>
          </div>
          {openDatePicker && (
            <FormDatePicker
              onCancel={() => setOpenDatePicker(false)}
              onApply={(range) => {
                onSelect({
                  type: 'range',
                  from: range.from,
                  to: range.to,
                });
                setOpenDatePicker(false);
              }}
            />
          )}
        </div>
      </div>
    </div>
  );
}
