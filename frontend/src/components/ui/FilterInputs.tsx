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

import SelectFilter from './SelectFilter';

export interface FilterOption {
  label: string;
  value: string;
}

export interface FilterConfigItem<T> {
  label: string;
  name: keyof T & string;
  className?: string;
  options: FilterOption[];
}

type FilterInputsProps<T> = {
  open: boolean;
  values: T;
  options: FilterConfigItem<T>[];
  onChange: (name: keyof T & string, value: string) => void;
};

export default function FilterInputs<T>({ open, options, values, onChange }: FilterInputsProps<T>) {
  return (
    <div
      aria-hidden={!open}
      className={`
        transition-all duration-300 ease-in-out w-full
        ${
          open
            ? 'max-h-[600px] opacity-100 visible mt-4 overflow-visible'
            : 'max-h-0 opacity-0 invisible mt-0 overflow-hidden'
        }
      `}
    >
      <div className="bg-slate-50 p-5 pt-7 flex flex-wrap justify-between md:gap-x-4 gap-x-0 rounded-lg border border-gray-100">
        {options.map((filter) => (
          <SelectFilter
            key={filter.name}
            label={filter.label}
            name={filter.name}
            value={values[filter.name] as string}
            onChange={(name, val) => onChange(name as keyof T & string, val)}
            options={filter.options}
            className={filter?.className}
          />
        ))}
      </div>
    </div>
  );
}
