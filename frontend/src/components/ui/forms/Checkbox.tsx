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

import { type ChangeEvent } from 'react';
import { twMerge } from 'tailwind-merge';

interface CheckboxProps {
  id: string;
  label?: string;
  checked?: boolean;
  className?: string;
  classNameLabel?: string;
  classNameInput?: string;
  onChange: (event: ChangeEvent<HTMLInputElement>) => void;
  title?: string;
}

export default function Checkbox({
  id,
  label,
  className,
  classNameLabel,
  classNameInput,
  checked,
  onChange,
  title,
}: CheckboxProps) {
  return (
    <div className={twMerge('flex', className)}>
      <input
        type="checkbox"
        id={id}
        checked={checked ?? false}
        onChange={onChange}
        className={twMerge(
          'shrink-0 mt-0.5 border-gray-200 rounded-sm cursor-pointer text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800',
          classNameInput,
        )}
      />
      <div className={twMerge('flex flex-col', label ? 'ms-3' : '')}>
        <span className="text-sm font-semibold">{title}</span>
        <label
          htmlFor={id}
          className={twMerge(
            'text-sm cursor-pointer text-gray-500 dark:text-neutral-400',
            classNameLabel,
          )}
        >
          {label}
        </label>
      </div>
    </div>
  );
}
