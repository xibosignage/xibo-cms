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

import type { TFunction } from 'i18next';
import React from 'react';

import { DynamicSettingField } from './DynamicSettingField';
import { getFieldMetaForType, isFieldMetaEnabled } from './fieldMetadata';

import type { Daypart } from '@/types/daypart';

export interface ChromeOsFieldProps {
  str: (key: string) => string;
  num: (key: string) => number;
  bool: (key: string) => boolean;
  setStr: (key: string) => (value: string) => void;
  setNum: (key: string) => (value: number) => void;
  setBool: (key: string) => (e: React.ChangeEvent<HTMLInputElement>) => void;
  t: TFunction;
  tab: string;
  dayparts: Daypart[];
  daypartsHasMore?: boolean;
  onLoadMoreDayparts?: () => void;
  isLoadingMoreDayparts?: boolean;
  onSearchDayparts?: (term: string) => void;
  resolveDaypartLabel?: (value: string) => Promise<string>;
  settings?: Record<string, unknown> | null;
}

export function ChromeOsFields({
  str,
  num,
  bool,
  setStr,
  setNum,
  setBool,
  t,
  tab,
  dayparts,
  daypartsHasMore,
  onLoadMoreDayparts,
  isLoadingMoreDayparts,
  onSearchDayparts,
  resolveDaypartLabel,
  settings,
}: ChromeOsFieldProps) {
  const metaMap = getFieldMetaForType('chromeOS', t);
  const fieldsForTab = Object.entries(metaMap).filter(
    ([, meta]) => meta.tab === tab && isFieldMetaEnabled(meta, settings, bool),
  );

  if (fieldsForTab.length === 0) {
    return null;
  }

  const getValue = (key: string, inputType: string) => {
    if (inputType === 'number') {
      return num(key);
    }
    if (inputType === 'checkbox') {
      return bool(key) ? 1 : 0;
    }
    if (key === 'screenShotSize') {
      const raw = str(key);
      return raw === '1' || raw === '2' ? raw : '1';
    }
    return str(key);
  };

  const handleChange = (key: string, inputType: string) => (val: string | number | null) => {
    if (inputType === 'number') {
      setNum(key)(Number(val));
    } else if (inputType === 'checkbox') {
      setBool(key)({
        target: { checked: val === 1 || val === 'on' },
      } as React.ChangeEvent<HTMLInputElement>);
    } else {
      setStr(key)(val !== null ? String(val) : '');
    }
  };

  const contextData = {
    dayparts,
    daypartsHasMore,
    onLoadMoreDayparts,
    isLoadingMoreDayparts,
    onSearchDayparts,
    resolveDaypartLabel,
  };

  return (
    <div className="flex flex-col gap-4">
      {fieldsForTab.map(([key, meta]) => (
        <DynamicSettingField
          key={key}
          meta={meta}
          value={getValue(key, meta.inputType)}
          onChange={handleChange(key, meta.inputType)}
          contextData={contextData}
        />
      ))}
    </div>
  );
}
