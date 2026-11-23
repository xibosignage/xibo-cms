/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { useEffect, useRef, type ChangeEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

interface CheckboxProps {
  id: string;
  label?: string;
  checked?: boolean;
  indeterminate?: boolean;
  disabled?: boolean;
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
  indeterminate,
  disabled,
  onChange,
  title,
}: CheckboxProps) {
  const { t } = useTranslation();
  const checkboxRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (checkboxRef.current) {
      checkboxRef.current.indeterminate = !!indeterminate;
    }
  }, [indeterminate]);

  return (
    <div className={twMerge('flex', disabled && 'opacity-50 cursor-not-allowed', className)}>
      <input
        ref={checkboxRef}
        type="checkbox"
        id={id}
        checked={checked ?? false}
        disabled={disabled}
        onChange={onChange}
        aria-label={title}
        title={indeterminate && !title ? t('Indeterminate State') : title}
        className={twMerge(
          'shrink-0 mt-0.5 border-gray-200 rounded-sm cursor-pointer text-xibo-blue-600 focus:ring-xibo-blue-500 checked:border-xibo-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:checked:bg-xibo-blue-500 dark:checked:border-xibo-blue-500 dark:focus:ring-offset-gray-800',
          classNameInput,
        )}
      />
      <div className={twMerge('flex flex-col', label ? 'ms-3' : 'ms-1')}>
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
