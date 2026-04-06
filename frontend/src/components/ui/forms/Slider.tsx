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
}: SliderProps) {
  const id = useId();

  return (
    <div className="flex flex-col gap-1 w-full">
      {label && (
        <label htmlFor={id} className="text-sm font-semibold text-gray-500 leading-4.5">
          {label}
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
        className="w-full accent-blue-500 disabled:opacity-50 disabled:pointer-events-none"
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
