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

interface TextInputProps {
  name: string;
  value?: string;
  label?: string;
  placeholder?: string;
  helpText?: string;
  error?: string;
  onChange?: (value: string) => void;
  className?: string;
  labelClassName?: string;
  wrapperClassName?: string;
  disabled?: boolean;
  prefix?: React.ReactNode;
  suffix?: React.ReactNode;
  multiline?: boolean;
  rows?: number;
  type?: React.HTMLInputTypeAttribute;
}

export default function TextInput({
  name,
  value,
  onChange,
  className,
  labelClassName,
  wrapperClassName,
  label,
  placeholder,
  helpText,
  error,
  disabled = false,
  prefix,
  suffix,
  multiline = false,
  rows,
  type,
}: TextInputProps) {
  const { t } = useTranslation();
  const generatedId = useId();

  return (
    <div className={twMerge('flex flex-col gap-1 w-full', wrapperClassName)}>
      {label && (
        <label
          htmlFor={generatedId}
          className={twMerge('text-sm font-semibold text-gray-500 leading-4.5', labelClassName)}
        >
          {label}
        </label>
      )}
      <div
        className={twMerge(
          'flex items-stretch rounded-lg bg-white border border-gray-200 overflow-hidden transition-colors',
          'focus-within:border-xibo-blue-600 focus-within:ring-1 focus-within:ring-xibo-blue-600/25',
          disabled && 'bg-gray-50',
          error && 'border-red-500 focus-within:border-red-500 focus-within:ring-red-500/25',
        )}
      >
        {prefix && (
          <div className="flex items-center border-e border-gray-200 shrink-0">{prefix}</div>
        )}

        {multiline ? (
          <textarea
            id={generatedId}
            name={name}
            value={value}
            disabled={disabled}
            rows={rows}
            onChange={(e) => onChange && onChange(e.target.value)}
            placeholder={placeholder || t('Add text')}
            className={twMerge(
              'flex-1 p-3 text-sm font-normal text-gray-800 placeholder:text-gray-500',
              'bg-transparent border-none outline-none focus:ring-0 resize-none',
              className,
            )}
          />
        ) : (
          <input
            id={generatedId}
            name={name}
            value={value}
            disabled={disabled}
            onChange={(e) => onChange && onChange(e.target.value)}
            placeholder={placeholder || t('Add text')}
            type={type || 'text'}
            className={twMerge(
              'flex-1 p-3 text-sm font-normal text-gray-800 placeholder:text-gray-500',
              'bg-transparent border-none outline-none focus:ring-0',
              className,
            )}
          />
        )}

        {suffix && (
          <div className="flex items-center border-s border-gray-200 shrink-0">{suffix}</div>
        )}
      </div>

      {error ? (
        <p className="text-xs text-red-600 ml-2 mt-1">{error}</p>
      ) : helpText ? (
        <p className="text-xs text-gray-500 mt-1 whitespace-pre-line">{helpText}</p>
      ) : null}
    </div>
  );
}
