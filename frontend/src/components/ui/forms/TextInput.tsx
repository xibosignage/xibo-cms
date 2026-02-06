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
  value: string;
  label?: string;
  placeholder?: string;
  helpText?: string;
  error?: string;
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  className?: string;
  disabled?: boolean;
}

export default function TextInput({
  name,
  value,
  onChange,
  className,
  label,
  placeholder,
  helpText,
  error,
  disabled = false,
}: TextInputProps) {
  const { t } = useTranslation();
  const generatedId = useId();

  return (
    <div className="flex flex-col gap-1 w-full">
      <label htmlFor={generatedId} className="text-sm font-semibold text-gray-500 leading-[18px]">
        {!label ? t('Text') : label}
      </label>
      <input
        id={generatedId}
        name={name}
        value={value}
        disabled={disabled}
        onChange={onChange}
        placeholder={placeholder || t('Add text')}
        className={twMerge(
          'p-3 rounded-lg text-sm font-normal text-gray-800 placeholder:text-gray-500 border-gray-200',
          'hover:border-gray-400',
          'focus:border-xibo-blue-600 focus:ring-xibo-blue-600/25 focus:ring-1',
          'disabled:border-gray-200 disabled:bg-gray-50 disabled:text-gray-300 disabled:pointer-events-none',
          error && 'border-red-500! ',
          className,
        )}
      />

      {error ? (
        <p className="text-xs text-red-600 ml-2 mt-1">{error}</p>
      ) : helpText ? (
        <p className="text-xs text-gray-500 ml-2 mt-1">{helpText}</p>
      ) : null}
    </div>
  );
}
