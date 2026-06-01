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
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

type StorageUnit = 'KiB' | 'MiB' | 'GiB';

const UNITS: StorageUnit[] = ['KiB', 'MiB', 'GiB'];

const UNIT_MULTIPLIERS: Record<StorageUnit, number> = {
  KiB: 1,
  MiB: 1024,
  GiB: 1024 * 1024,
};

function getDefaultUnit(kb: number): StorageUnit {
  if (kb >= 1024 * 1024 && kb % (1024 * 1024) === 0) return 'GiB';
  if (kb >= 1024 && kb % 1024 === 0) return 'MiB';
  return 'KiB';
}

export interface LibraryQuotaInputProps {
  value: number;
  onChange: (kb: number) => void;
  error?: string;
}

export default function LibraryQuotaInput({ value, onChange, error }: LibraryQuotaInputProps) {
  const { t } = useTranslation();
  const [unit, setUnit] = useState<StorageUnit>(() => getDefaultUnit(value));
  const [inputValue, setInputValue] = useState<string>(() => {
    const display = value === 0 ? 0 : Math.round(value / UNIT_MULTIPLIERS[getDefaultUnit(value)]);
    return String(display);
  });
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Sync input when value changes externally (e.g. edit mode load)
  useEffect(() => {
    const display = value === 0 ? 0 : Math.round(value / UNIT_MULTIPLIERS[unit]);
    setInputValue(String(display));
  }, [value, unit]);

  const handleValueChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const raw = e.target.value;
    setInputValue(raw);

    if (raw === '' || raw === '-') {
      onChange(0);
      return;
    }

    const num = parseInt(raw, 10);
    if (isNaN(num) || num < 0) {
      onChange(0);
    } else {
      onChange(num * UNIT_MULTIPLIERS[unit]);
    }
  };

  const handleUnitChange = (newUnit: StorageUnit) => {
    const currentNum = parseInt(inputValue, 10) || 0;
    setUnit(newUnit);
    onChange(currentNum * UNIT_MULTIPLIERS[newUnit]);
    setIsOpen(false);
  };

  // Close dropdown on outside click
  useEffect(() => {
    if (!isOpen) return;
    const handleClick = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, [isOpen]);

  return (
    <div className="flex flex-col gap-1 w-full">
      <label className="flex items-center justify-between text-sm font-semibold text-gray-500 leading-4.5">
        {t('Library Quota')}
      </label>
      <div className="flex items-center border border-gray-200 rounded-lg overflow-visible bg-white h-11.25 focus-within:border-xibo-blue-600 focus-within:ring-1 focus-within:ring-xibo-blue-600/25">
        <input
          type="number"
          value={inputValue}
          onChange={handleValueChange}
          min={0}
          className="flex-1 py-2 px-3 text-sm bg-transparent border-none outline-none focus:ring-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
        />
        <div
          ref={dropdownRef}
          className="relative flex items-center border-s border-gray-200 shrink-0"
        >
          <button
            type="button"
            onClick={() => setIsOpen((prev) => !prev)}
            className="flex items-center gap-1 py-2 px-3 text-sm text-gray-600 hover:bg-gray-50 transition-colors"
          >
            {unit}
            <ChevronDown
              size={12}
              className={`transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
            />
          </button>
          {isOpen && (
            <div className="absolute top-full right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 min-w-20 overflow-hidden">
              {UNITS.map((u) => (
                <button
                  key={u}
                  type="button"
                  onClick={() => handleUnitChange(u)}
                  className={`w-full text-left px-3 py-2 text-sm transition-colors ${
                    u === unit
                      ? 'bg-blue-50 text-blue-600 font-medium'
                      : 'text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  {u}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>
      <p className="text-xs text-gray-400">{t('0 = Unlimited storage')}</p>
      {error && <p className="text-xs text-red-600">{error}</p>}
    </div>
  );
}
