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

import type { TFunction } from 'i18next';
import React from 'react';

import { DynamicSettingField } from './DynamicSettingField';
import { getFieldMetaForType } from './fieldMetadata';

import type { Daypart } from '@/types/daypart';

export interface WindowsFieldProps {
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
}

export function WindowsFields({
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
}: WindowsFieldProps) {
  const metaMap = getFieldMetaForType('windows', t);
  const fieldsForTab = Object.entries(metaMap).filter(([, meta]) => meta.tab === tab);

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
