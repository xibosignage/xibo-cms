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

import { useTranslation } from 'react-i18next';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';

function buildTimezoneOptions(): SelectOption[] {
  const intlAny = Intl as unknown as { supportedValuesOf?: (key: string) => string[] };
  const tzIds: string[] = intlAny.supportedValuesOf ? intlAny.supportedValuesOf('timeZone') : [];

  const now = Date.now();

  function getOffsetSeconds(tz: string): number {
    const parts = new Intl.DateTimeFormat('en', {
      timeZone: tz,
      timeZoneName: 'shortOffset',
    }).formatToParts(now);
    const offsetStr = parts.find((p) => p.type === 'timeZoneName')?.value ?? 'GMT';
    const match = offsetStr.match(/GMT([+-])(\d+)(?::(\d+))?/);
    if (!match) {
      return 0;
    }
    const sign = match[1] === '+' ? 1 : -1;
    const hours = parseInt(match[2] ?? '0', 10);
    const minutes = parseInt(match[3] ?? '0', 10);
    return sign * (hours * 3600 + minutes * 60);
  }

  function formatGmtOffset(offsetSeconds: number): string {
    if (offsetSeconds === 0) {
      return 'GMT';
    }
    const sign = offsetSeconds > 0 ? '+' : '-';
    const abs = Math.abs(offsetSeconds);
    const hours = Math.floor(abs / 3600);
    const minutes = Math.floor((abs % 3600) / 60);
    return `GMT${sign}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
  }

  function formatTimezoneName(tz: string): string {
    return tz.replace(/\//g, ', ').replace(/_/g, ' ').replace(/St /g, 'St. ');
  }

  const withOffsets = tzIds.map((tz) => {
    const offsetSecs = getOffsetSeconds(tz);
    return {
      value: tz,
      offset: offsetSecs,
      label: `(${formatGmtOffset(offsetSecs)}) ${formatTimezoneName(tz)}`,
    };
  });

  withOffsets.sort((a, b) => a.offset - b.offset);

  return [{ value: '', label: '' }, ...withOffsets.map(({ value, label }) => ({ value, label }))];
}

const TIMEZONE_OPTIONS = buildTimezoneOptions();

interface TimezoneSelectProps {
  label?: string;
  value: string;
  onChange: (value: string) => void;
  helpText?: string;
  placeholder?: string;
}

export default function TimezoneSelect({
  label,
  value,
  onChange,
  helpText,
  placeholder = ' ',
}: TimezoneSelectProps) {
  const { t } = useTranslation();

  return (
    <SelectDropdown
      label={label ?? t('Timezone')}
      value={value}
      placeholder={placeholder}
      options={TIMEZONE_OPTIONS}
      onSelect={onChange}
      helpText={helpText}
      searchable
      searchPlaceholder={t('Search timezones…')}
    />
  );
}
