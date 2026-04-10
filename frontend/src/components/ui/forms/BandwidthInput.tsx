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

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import SelectDropdown from './SelectDropdown';

import NumberInput from '@/components/ui/forms/NumberInput';

type BandwidthUnit = 'kb' | 'mb' | 'gb';

function detectUnit(kb: number | null): BandwidthUnit {
  if (!kb || kb === 0) {
    return 'kb';
  }
  if (kb % (1024 * 1024) === 0) {
    return 'gb';
  }
  if (kb % 1024 === 0) {
    return 'mb';
  }
  return 'kb';
}

function toDisplayValue(kb: number | null, unit: BandwidthUnit): number {
  if (!kb) {
    return 0;
  }
  if (unit === 'gb') {
    return kb / (1024 * 1024);
  }
  if (unit === 'mb') {
    return kb / 1024;
  }
  return kb;
}

function toKb(value: number, unit: BandwidthUnit): number {
  if (unit === 'gb') {
    return value * 1024 * 1024;
  }
  if (unit === 'mb') {
    return value * 1024;
  }
  return value;
}

interface BandwidthInputProps {
  valueKb: number | null;
  onChange: (kb: number | null) => void;
  label?: string;
  helpText?: string;
}

export default function BandwidthInput({
  valueKb,
  onChange,
  label,
  helpText,
}: BandwidthInputProps) {
  const { t } = useTranslation();
  const [unit, setUnit] = useState<BandwidthUnit>(() => detectUnit(valueKb));

  const displayValue = toDisplayValue(valueKb, unit);

  const handleValueChange = (v: number) => {
    onChange(v ? toKb(v, unit) : null);
  };

  const handleUnitChange = (newUnit: BandwidthUnit) => {
    setUnit(newUnit);
    if (valueKb) {
      onChange(toKb(toDisplayValue(valueKb, newUnit), newUnit));
    }
  };

  return (
    <div className="flex flex-col gap-1">
      <label className="text-sm font-semibold text-gray-500">{label ?? t('Bandwidth limit')}</label>
      <div className="flex gap-2">
        <div className="flex-1">
          <NumberInput name="bandwidthLimit" value={displayValue} onChange={handleValueChange} />
        </div>
        <SelectDropdown
          value={unit}
          onSelect={(value) => handleUnitChange(value as BandwidthUnit)}
          options={[
            {
              label: 'KiB',
              value: 'kb',
            },
            {
              label: 'MiB',
              value: 'mb',
            },
            {
              label: 'GiB',
              value: 'gb',
            },
          ]}
        ></SelectDropdown>
      </div>
      {helpText && <span className="text-xs text-gray-400">{helpText}</span>}
    </div>
  );
}
