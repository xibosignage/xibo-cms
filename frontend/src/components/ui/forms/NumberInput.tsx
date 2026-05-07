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
  className?: string;
  disabled?: boolean;
  min?: number;
  max?: number;
  step?: number | string;
  optional?: boolean;
}

export default function NumberInput({
  name,
  value,
  onChange,
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
}: NumberInputProps) {
  const { t } = useTranslation();
  const generatedId = useId();
  const isInline = !label && !helpText && !error;

  const input = (
    <input
      id={generatedId}
      type="number"
      name={name}
      value={value ?? ''}
      disabled={disabled}
      min={min}
      max={max}
      step={step}
      onChange={(e) => {
        const numericValue = e.target.valueAsNumber;

        if (!Number.isNaN(numericValue)) {
          const clamped = min != null ? Math.max(min, numericValue) : numericValue;
          onChange(max != null ? Math.min(max, clamped) : clamped);
        } else {
          onChange(0);
        }
      }}
      placeholder={placeholder || t('Add text')}
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
