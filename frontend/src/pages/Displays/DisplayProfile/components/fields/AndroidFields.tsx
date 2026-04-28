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

import NumberInput from '@/components/ui/forms/NumberInput';
import type { PlayerSoftware } from '@/services/playerSoftwareApi';
import type { Daypart } from '@/types/daypart';

export interface AndroidFieldProps {
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
  playerVersions: PlayerSoftware[];
  playerVersionsHasMore?: boolean;
  onLoadMorePlayerVersions?: () => void;
  isLoadingMorePlayerVersions?: boolean;
}

export function AndroidFields({
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
  playerVersions,
  playerVersionsHasMore,
  onLoadMorePlayerVersions,
  isLoadingMorePlayerVersions,
}: AndroidFieldProps) {
  // Fetch the schema mapping for Android profiles
  const metaMap = getFieldMetaForType('android', t);

  // Filter fields so we only render the ones that belong on the current active tab
  const fieldsForTab = Object.entries(metaMap).filter(([, meta]) => meta.tab === tab);

  if (fieldsForTab.length === 0) {
    return null;
  }

  // Adapter functions to map the unified DynamicSettingField values
  // back to the legacy typed draft state setters
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

  // Bundle context data for domain-specific custom dropdowns
  const contextData = {
    dayparts,
    daypartsHasMore,
    onLoadMoreDayparts,
    isLoadingMoreDayparts,
    playerVersions,
    playerVersionsHasMore,
    onLoadMorePlayerVersions,
    isLoadingMorePlayerVersions,
  };

  return (
    <div className="flex flex-col gap-4">
      {fieldsForTab.map(([key, meta]) => {
        // Handle the custom composite UI for screen dimensions
        if (key === 'screenDimensions') {
          const raw = str('screenDimensions');
          const parts = raw ? raw.split(',') : [];
          const dims: [number, number, number, number] = [
            parseInt(parts[0] ?? '0', 10) || 0,
            parseInt(parts[1] ?? '0', 10) || 0,
            parseInt(parts[2] ?? '0', 10) || 0,
            parseInt(parts[3] ?? '0', 10) || 0,
          ];

          const setDim = (index: number) => (value: number) => {
            const current = str('screenDimensions')
              ? str('screenDimensions').split(',')
              : ['0', '0', '0', '0'];
            while (current.length < 4) {
              current.push('0');
            }
            current[index] = String(value);
            setStr('screenDimensions')(current.join(','));
          };

          return (
            <div key={key}>
              <label className="text-sm font-semibold text-gray-500">
                {t('Screen Dimensions')}
              </label>
              <div className="grid grid-cols-4 gap-2 mt-1">
                <NumberInput
                  name="screenDimTop"
                  label={t('Top')}
                  value={dims[0]}
                  onChange={setDim(0)}
                />
                <NumberInput
                  name="screenDimLeft"
                  label={t('Left')}
                  value={dims[1]}
                  onChange={setDim(1)}
                />
                <NumberInput
                  name="screenDimWidth"
                  label={t('Width')}
                  value={dims[2]}
                  onChange={setDim(2)}
                />
                <NumberInput
                  name="screenDimHeight"
                  label={t('Height')}
                  value={dims[3]}
                  onChange={setDim(3)}
                />
              </div>
              <span className="text-xs text-gray-400 leading-snug flex mt-1 whitespace-pre-line">
                {meta.helpText}
              </span>
            </div>
          );
        }

        // Render standard fields via the schema
        return (
          <DynamicSettingField
            key={key}
            meta={meta}
            value={getValue(key, meta.inputType)}
            onChange={handleChange(key, meta.inputType)}
            contextData={contextData}
          />
        );
      })}
    </div>
  );
}
