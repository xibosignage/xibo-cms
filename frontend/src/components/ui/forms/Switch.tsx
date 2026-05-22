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

import { useEffect, useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

interface SwitchProps {
  label?: string;
  checked: boolean;
  helpText?: string;
  optional?: boolean;
  disabled?: boolean;
  onChange: (checked: boolean) => void;
  hideOnOff?: boolean;
  size?: 'default' | 'sm';
}

export default function Switch({
  label,
  checked,
  helpText,
  optional = false,
  disabled = false,
  onChange,
  hideOnOff = false,
  size = 'default',
}: SwitchProps) {
  const { t } = useTranslation();
  const id = useId();
  const [mounted, setMounted] = useState(false);
  useEffect(() => {
    setMounted(true);
  }, []);

  return (
    <div className="flex flex-col gap-1 w-full">
      {label && (
        <label
          htmlFor={id}
          className="flex items-center justify-between text-sm font-semibold text-gray-500 leading-4.5"
        >
          <span>{label}</span>
          {optional && <span className="text-xs font-normal text-gray-500">{t('Optional')}</span>}
        </label>
      )}
      <div className="flex items-center gap-3">
        {!hideOnOff && (
          <span
            className={twMerge('text-sm font-medium', checked ? 'text-gray-400' : 'text-gray-700')}
          >
            {t('Off')}
          </span>
        )}
        <button
          id={id}
          type="button"
          role="switch"
          aria-checked={checked}
          disabled={disabled}
          onClick={() => onChange(!checked)}
          className={twMerge(
            'relative inline-flex border p-0.5 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
            size === 'sm' ? 'h-6.5 w-10' : 'h-9.5 w-15.5',
            checked ? 'bg-blue-100 border-blue-300' : 'bg-gray-100 border-gray-300',
          )}
        >
          <span
            className={twMerge(
              'inline-flex transform items-center justify-center rounded-full shadow-sm',
              mounted && 'transition-all duration-200 ease-in-out',
              size === 'sm' ? 'h-5 w-5' : 'h-8 w-8',
              checked
                ? twMerge(size === 'sm' ? 'translate-x-3.5' : 'translate-x-6', 'bg-xibo-blue-600')
                : 'translate-x-0 bg-gray-200 border-xibo-blue-200 border',
            )}
          >
            {checked ? (
              <svg
                className={twMerge(size === 'sm' ? 'h-2.5 w-2.5' : 'h-3.5 w-3.5', 'text-white')}
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                strokeWidth={3}
              >
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            ) : (
              <svg
                className={twMerge(
                  size === 'sm' ? 'h-2.5 w-2.5' : 'h-3.5 w-3.5',
                  'text-xibo-blue-500',
                )}
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                strokeWidth={3}
              >
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            )}
          </span>
        </button>
        {!hideOnOff && (
          <span
            className={twMerge('text-sm font-medium', checked ? 'text-blue-600' : 'text-gray-400')}
          >
            {t('On')}
          </span>
        )}
      </div>
      {helpText && <p className="text-xs text-gray-400 mt-1">{helpText}</p>}
    </div>
  );
}
