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

interface SliderProps {
  min: number;
  max: number;
  value: number;
  onChange: (value: number) => void;
  step?: number;
  label?: string;
  leftLabel?: string;
  rightLabel?: string;
  displayValue?: string;
  disabled?: boolean;
  optional?: boolean;
}

export default function Slider({
  min,
  max,
  value,
  onChange,
  step = 1,
  label,
  leftLabel,
  rightLabel,
  displayValue,
  disabled = false,
  optional = false,
}: SliderProps) {
  const id = useId();
  const { t } = useTranslation();

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
      <input
        id={id}
        type="range"
        min={min}
        max={max}
        step={step}
        value={value}
        disabled={disabled}
        onChange={(e) => onChange(Number(e.target.value))}
        className="w-full accent-xibo-blue-500 disabled:opacity-50 disabled:pointer-events-none"
      />
      {(leftLabel !== undefined || rightLabel !== undefined || displayValue !== undefined) && (
        <div className="flex justify-between text-xs text-gray-500">
          <span>{leftLabel ?? ''}</span>
          {displayValue !== undefined && (
            <span className="font-medium text-gray-700">{displayValue}</span>
          )}
          <span>{rightLabel ?? ''}</span>
        </div>
      )}
    </div>
  );
}
