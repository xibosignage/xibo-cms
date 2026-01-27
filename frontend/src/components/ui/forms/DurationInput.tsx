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

import { ChevronDown, ChevronUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface DurationInputProps {
  value: number;
  onChange: (seconds: number) => void;
}

function formatSeconds(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;

  return [h, m, s].map((v) => String(v).padStart(2, '0')).join(':');
}

function parseTimeToSeconds(value: string): number {
  const parts = value.split(':').map(Number);
  if (parts.some(isNaN)) return 0;

  const [h = 0, m = 0, s = 0] = parts;
  return h * 3600 + m * 60 + s;
}

export default function DurationInput({ value, onChange }: DurationInputProps) {
  const { t } = useTranslation();
  const [displayValue, setDisplayValue] = useState(formatSeconds(value));
  const STEP = 1;

  const increment = () => {
    const seconds = parseTimeToSeconds(displayValue);
    const next = seconds + STEP;
    onChange(next);
    setDisplayValue(formatSeconds(next));
  };

  const decrement = () => {
    const seconds = parseTimeToSeconds(displayValue);
    const next = Math.max(0, seconds - STEP);
    onChange(next);
    setDisplayValue(formatSeconds(next));
  };

  useEffect(() => {
    setDisplayValue(formatSeconds(value));
  }, [value]);

  return (
    <div className="relative flex flex-col">
      <label className="text-xs font-semibold text-gray-500">{t('Duration')}</label>

      <input
        type="text"
        inputMode="numeric"
        placeholder="00:00:00"
        className="border-gray-200 text-sm rounded-lg px-3 py-2 pr-9 tracking-wider"
        value={displayValue}
        onChange={(e) => {
          const val = e.target.value.replace(/[^\d:]/g, '');
          setDisplayValue(val);
        }}
        onBlur={() => {
          const seconds = parseTimeToSeconds(displayValue);
          onChange(seconds);
          setDisplayValue(formatSeconds(seconds));
        }}
      />
      <div className="absolute mb-0.5 mr-1.5 bottom-0 right-0 flex flex-col">
        <button
          type="button"
          onClick={increment}
          className="h-4 w-6 flex items-center justify-center rounded-t text-gray-500 cursor-pointer"
          aria-label="Increase duration"
        >
          <ChevronUp size={14} />
        </button>

        <button
          type="button"
          onClick={decrement}
          className="h-4 w-6 flex items-center justify-center rounded-b text-gray-500 cursor-pointer"
          aria-label="Decrease duration"
        >
          <ChevronDown size={14} />
        </button>
      </div>
    </div>
  );
}
