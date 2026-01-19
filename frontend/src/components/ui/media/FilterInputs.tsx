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

import SelectFilter from '../SelectFilter';

import type { FilterInput } from '@/pages/Library/Media/Media';

export const FILTER_CONFIG = [
  {
    label: 'Type',
    name: 'type',
    className: '',
    options: [
      { label: 'Image', value: 'image' },
      { label: 'Video', value: 'video' },
      { label: 'Audio', value: 'audio' },
      { label: 'PDF', value: 'pdf' },
      { label: 'Archive', value: 'archive' },
      { label: 'Other', value: 'other' },
    ],
  },
  {
    label: 'Owner',
    name: 'owner',
    className: '',
    options: [
      { label: 'Owner 1', value: 'owner-1' },
      { label: 'Owner 2', value: 'owner-2' },
      { label: 'Owner 3', value: 'owner-3' },
      { label: 'Owner 4', value: 'owner-4' },
    ],
  },
  {
    label: 'User Group',
    name: 'userGroup',
    className: 'md:w-auto w-[100%]',
    options: [
      { label: 'Group 1', value: 'group-1' },
      { label: 'Group 2', value: 'group-2' },
      { label: 'Group 3', value: 'group-3' },
      { label: 'Group 4', value: 'group-4' },
    ],
  },
  {
    label: 'Orientation',
    name: 'orientation',
    className: '',
    options: [
      { label: 'Portrait', value: 'portrait' },
      { label: 'Landscape', value: 'landscape' },
      { label: 'Square', value: 'square' },
    ],
  },
  {
    label: 'Retired',
    name: 'retired',
    className: 'max-w-[100px]',
    options: [
      { label: 'Any', value: '' },
      { label: 'Yes', value: 'yes' },
      { label: 'No', value: 'no' },
    ],
  },
  {
    label: 'Last Modified',
    name: 'lastModified',
    className: '',
    options: [
      { label: 'Any time', value: '' },
      { label: 'Today', value: 'today' },
      { label: 'Last 7 days', value: '7d' },
      { label: 'Last 30 days', value: '30d' },
      { label: 'This year', value: '1y' },
    ],
  },
] as const;

type FilterInputsProps = {
  open: boolean;
  values: FilterInput;
  onChange: (name: string, value: string) => void;
};

export default function FilterInputs({ open, values, onChange }: FilterInputsProps) {
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
        {FILTER_CONFIG.map((filter) => (
          <SelectFilter
            key={filter.name}
            label={filter.label}
            name={filter.name}
            value={values[filter.name as keyof FilterInput]}
            onChange={onChange}
            options={filter.options}
            className={filter?.className}
          />
        ))}
      </div>
    </div>
  );
}
