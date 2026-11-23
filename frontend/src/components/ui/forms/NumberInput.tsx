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

import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

interface NumberInputProps {
  name?: string;
  value: number | undefined;
  label?: string;
  placeholder?: string;
  helpText?: string;
  error?: string;
  onChange: (num: number) => void;
  onClear?: () => void;
  className?: string;
  disabled?: boolean;
  min?: number;
  max?: number;
  step?: number | string;
  optional?: boolean;
  ariaLabel?: string;
}

export default function NumberInput({
  name,
  value,
  onChange,
  onClear,
  className,
  label,
  placeholder,
  helpText,
  error,
  disabled = false,
  min,
  max,
  step,
  optional = false,
  ariaLabel,
}: NumberInputProps) {
  const { t } = useTranslation();
  const generatedId = useId();
  const isInline = !label && !helpText && !error;

  const input = (
    <input
      id={generatedId}
      type="number"
      name={name}
      aria-label={label ? undefined : ariaLabel}
      value={value ?? ''}
      disabled={disabled}
      min={min}
      max={max}
      step={step}
      onChange={(e) => {
        if (e.target.value === '') {
          if (onClear) {
            onClear();
          } else {
            onChange(0);
          }
          return;
        }

        const numericValue = e.target.valueAsNumber;

        if (!Number.isNaN(numericValue)) {
          const clamped = min != null ? Math.max(min, numericValue) : numericValue;
          onChange(max != null ? Math.min(max, clamped) : clamped);
        } else {
          onChange(0);
        }
      }}
      placeholder={placeholder ?? t('Add number')}
      className={twMerge(
        'h-11.25 px-3 rounded-lg text-sm font-normal text-gray-800 placeholder:text-gray-500 border-gray-200',
        'hover:border-gray-400',
        'focus:border-xibo-blue-600 focus:ring-xibo-blue-600/25 focus:ring-1',
        'disabled:border-gray-200 disabled:bg-gray-50 disabled:text-gray-300 disabled:pointer-events-none',
        error && 'border-red-500! ',
        className,
      )}
    />
  );

  if (isInline) {
    return input;
  }

  return (
    <div className="flex flex-col gap-1 w-full">
      {label && (
        <label
          htmlFor={generatedId}
          className="flex items-center justify-between text-sm font-semibold text-gray-500 leading-4.5"
        >
          <span>{label}</span>
          {optional && <span className="text-xs font-normal text-gray-500">{t('Optional')}</span>}
        </label>
      )}
      {input}
      {error ? (
        <p className="text-xs text-red-600 ml-2 mt-1">{error}</p>
      ) : helpText ? (
        <p className="text-xs text-gray-400 mt-1">{helpText}</p>
      ) : null}
    </div>
  );
}
