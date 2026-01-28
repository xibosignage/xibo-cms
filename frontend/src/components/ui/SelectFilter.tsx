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

type Option = {
  label: string;
  value: string;
};

type SelectFilterProps = {
  label: string;
  name: string;
  value: string;
  options: readonly Option[];
  onChange: (name: string, value: string) => void;
  className?: string;
};

export default function SelectFilter({
  label,
  name,
  value,
  options,
  onChange,
  className,
}: SelectFilterProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  const selectedOption = options.find((o) => o.value === value);

  // Close when clicking outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div
      className={`flex flex-col gap-y-1 relative text-gray-500 md:flex-1 md:basis-auto basis-[calc(50%-0.5rem)] ${className}`}
      ref={ref}
    >
      <label className="text-sm font-medium">{label}</label>
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        className="flex items-center justify-between rounded-lg border border-gray-200 pl-4 text-left"
      >
        <span>{selectedOption?.label || 'All'}</span>
        <div
          className={`p-4 ${open ? 'rotate-180' : 'rotate-0'} transition-all duration-200 ease-in`}
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
        <li
          onClick={() => {
            onChange(name, '');
            setOpen(false);
          }}
          className="cursor-pointer px-4 py-2 hover:bg-gray-100 first:rounded-t-md"
        >
          All
        </li>

        {options.map((option) => (
          <li
            key={option.value}
            onClick={() => {
              onChange(name, option.value);
              setOpen(false);
            }}
            className={`cursor-pointer px-4 py-2 hover:bg-gray-100 ${
              option.value === value ? 'bg-gray-50 font-medium' : ''
            }`}
          >
            {option.label}
          </li>
        ))}
      </ul>
    </div>
  );
}
