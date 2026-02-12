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
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';
export type SelectOption = {
  label: string;
  value: string;
};

interface SelectDropdownProps {
  label: string;
  value?: string;
  placeholder?: string;
  options: SelectOption[];
  isOpen: boolean;
  searchable?: boolean;
  searchPlaceholder?: string;
  onToggle: () => void;
  onSelect: (value: string) => void;
  helper?: string;
  addLeftLabel?: boolean;
  leftLabelContent?: ReactNode;
  optionLabel?: string;
  addOptionAvatar?: boolean;
  className?: string;
}

export default function SelectDropdown({
  label,
  value,
  placeholder = 'Select',
  options,
  isOpen,
  onToggle,
  onSelect,
  helper,
  addLeftLabel,
  leftLabelContent,
  optionLabel,
  className,
  addOptionAvatar,
}: SelectDropdownProps) {
  const { t } = useTranslation();

  const selectedLabel = options.find((o) => o.value === value)?.label ?? '';

  return (
    <div className={twMerge('relative overflow-visible', className)}>
      <label className="text-xs font-semibold text-gray-500">{t(label)}</label>
      <div
        className="w-full border bg-white border-gray-200 rounded-lg flex items-center cursor-pointer max-h-11.25"
        onClick={onToggle}
      >
        {addLeftLabel && (
          <div className="p-3 border-r text-sm border-gray-200 text-gray-500">
            {leftLabelContent || t('My files')}
          </div>
        )}
        <span className="p-3 flex-1 text-sm capitalize">{selectedLabel || t(placeholder)}</span>
        <span className="p-3 text-gray-500">
          <ChevronDown size={14} />
        </span>
      </div>

      <div
        className={`absolute top-17.5 w-full bg-white shadow-md rounded-lg overflow-clip transition-all duration-150 border border-gray-200 ease-in-out z-50
          ${isOpen ? 'opacity-100 max-h-75' : 'opacity-0 max-h-0'}
        `}
      >
        {optionLabel && (
          <span className="bg-gray-100 p-2 text-sm font-semibold text-gray-500 uppercase w-full flex">
            {t(optionLabel)}
          </span>
        )}
        <div className="flex flex-col p-2 text-sm">
          {options.map((option) => (
            <button
              key={option.value}
              type="button"
              className="text-left p-2 rounded-lg hover:bg-gray-100 font-medium flex gap-2 items-center cursor-pointer"
              onClick={() => onSelect(option.value)}
            >
              {addOptionAvatar && (
                <div className="bg-xibo-blue-100 h-6.5 w-6.5 text-[12px] center rounded-full text-xibo-blue-800 font-semibold">
                  {option.label.slice(0, 1)}
                </div>
              )}
              {t(option.label)}
            </button>
          ))}
        </div>
      </div>
      <span className="text-xs text-gray-400 leading-snug flex mt-1">{helper}</span>
    </div>
  );
}
