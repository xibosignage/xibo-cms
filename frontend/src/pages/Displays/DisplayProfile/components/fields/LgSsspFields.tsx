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

import type {TFunction} from 'i18next';
import {Minus, Plus} from 'lucide-react';
import React from 'react';

import {DynamicSettingField} from './DynamicSettingField';
import {getFieldMetaForType} from './fieldMetadata';

import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Slider from '@/components/ui/forms/Slider';
import TimePickerInput from '@/components/ui/forms/TimePickerInput';
import type {PlayerSoftware} from '@/services/playerSoftwareApi';
import type {Daypart} from '@/types/daypart';

export interface TimerRow {
  id: number;
  day: string;
  on: string;
  off: string;
}

export interface PictureOptionRow {
  id: number;
  property: string;
  value: number;
}

export interface LockOptionsState {
  usblock: string;
  osdlock: string;
  keylockLocal: string;
  keylockRemote: string;
}

export interface PicturePropertyDef {
  name: string;
  min: number;
  max: number;
  labels?: string[];
}

export const PICTURE_PROPERTY_DEFS: Record<string, PicturePropertyDef> = {
  backlight: { name: 'Backlight', min: 0, max: 100 },
  contrast: { name: 'Contrast', min: 0, max: 100 },
  brightness: { name: 'Brightness', min: 0, max: 100 },
  sharpness: { name: 'Sharpness', min: 0, max: 50 },
  hSharpness: { name: 'Horizontal Sharpness', min: 0, max: 50 },
  vSharpness: { name: 'Vertical Sharpness', min: 0, max: 50 },
  color: { name: 'Color', min: 0, max: 100 },
  tint: { name: 'Tint', min: 0, max: 100 },
  colorTemperature: { name: 'Color Temperature', min: 0, max: 100 },
  dynamicContrast: {
    name: 'Dynamic Contrast',
    min: 0,
    max: 3,
    labels: ['off', 'low', 'medium', 'high'],
  },
  superResolution: {
    name: 'Super Resolution',
    min: 0,
    max: 3,
    labels: ['off', 'low', 'medium', 'high'],
  },
  colorGamut: { name: 'Color Gamut', min: 0, max: 1, labels: ['normal', 'extended'] },
  dynamicColor: { name: 'Dynamic Color', min: 0, max: 3, labels: ['off', 'low', 'medium', 'high'] },
  noiseReduction: {
    name: 'Noise Reduction',
    min: 0,
    max: 4,
    labels: ['auto', 'off', 'low', 'medium', 'high'],
  },
  mpegNoiseReduction: {
    name: 'MPEG Noise Reduction',
    min: 0,
    max: 4,
    labels: ['auto', 'off', 'low', 'medium', 'high'],
  },
  blackLevel: { name: 'Black Level', min: 0, max: 1, labels: ['low', 'high'] },
  gamma: { name: 'Gamma', min: 0, max: 3, labels: ['low', 'medium', 'high', 'high2'] },
};

export function getPictureSliderIndex(property: string, storedValue: string | number): number {
  const def = PICTURE_PROPERTY_DEFS[property];
  if (!def) return 0;
  if (def.labels && typeof storedValue === 'string') {
    const idx = def.labels.indexOf(storedValue.toLowerCase());
    return idx >= 0 ? idx : 0;
  }
  const n = Number(storedValue);
  return isNaN(n) ? 0 : n;
}

const ROW_BTN_CLASS = 'h-8 w-8 min-w-8';

export function TimersInput({
  timerRows,
  onChange,
  t,
}: {
  timerRows: TimerRow[];
  onChange: (rows: TimerRow[]) => void;
  t: TFunction;
}) {
  const addRow = () => {
    const nextId = timerRows.length > 0 ? Math.max(...timerRows.map((r) => r.id)) + 1 : 1;
    onChange([...timerRows, { id: nextId, day: '', on: '', off: '' }]);
  };
  const removeRow = (id: number) => onChange(timerRows.filter((r) => r.id !== id));
  const updateRow = (id: number, field: keyof Omit<TimerRow, 'id'>, value: string) => {
    onChange(timerRows.map((r) => (r.id === id ? { ...r, [field]: value } : r)));
  };

  const dayOptions = [
    { value: 'monday', label: t('Monday') },
    { value: 'tuesday', label: t('Tuesday') },
    { value: 'wednesday', label: t('Wednesday') },
    { value: 'thursday', label: t('Thursday') },
    { value: 'friday', label: t('Friday') },
    { value: 'saturday', label: t('Saturday') },
    { value: 'sunday', label: t('Sunday') },
  ];

  return (
    <div className="space-y-4">
      <div
        className="rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700"
        dangerouslySetInnerHTML={{ __html: t(`Use the form fields to create On/Off timings.`) }}
      />
      {timerRows.length > 0 && (
        <div className="flex gap-2 items-center text-xs font-semibold text-gray-500 uppercase px-1">
          <span className="flex-5">{t('Day')}</span>
          <span className="flex-3">{t('On')}</span>
          <span className="flex-3">{t('Off')}</span>
          <span className="w-17 shrink-0" />
        </div>
      )}
      <div className="space-y-2">
        {timerRows.map((row) => (
          <div key={row.id} className="flex gap-2 items-center">
            <SelectDropdown
              label=""
              className="flex-3"
              value={row.day}
              placeholder={t('Select day')}
              options={dayOptions}
              onSelect={(v) => updateRow(row.id, 'day', v)}
            />
            <TimePickerInput
              label=""
              className="flex-3 min-w-0 gap-0"
              value={row.on}
              timeFormat="HH:mm"
              onChange={(v) => updateRow(row.id, 'on', v)}
            />
            <TimePickerInput
              label=""
              className="flex-3 min-w-0 gap-0"
              value={row.off}
              timeFormat="HH:mm"
              onChange={(v) => updateRow(row.id, 'off', v)}
            />
            <Button
              className={ROW_BTN_CLASS}
              variant="secondary"
              onClick={() => removeRow(row.id)}
              title={t('Remove')}
            >
              <Minus size={14} />
            </Button>
          </div>
        ))}
      </div>
      <Button className="w-full" onClick={addRow} title={t('Add')}>
        <Plus size={14} />
      </Button>
    </div>
  );
}

export function PictureOptionsInput({
  pictureOptionRows,
  onChange,
  t,
}: {
  pictureOptionRows: PictureOptionRow[];
  onChange: (rows: PictureOptionRow[]) => void;
  t: TFunction;
}) {
  const addRow = () => {
    const nextId =
      pictureOptionRows.length > 0 ? Math.max(...pictureOptionRows.map((r) => r.id)) + 1 : 1;
    onChange([...pictureOptionRows, { id: nextId, property: '', value: 0 }]);
  };
  const removeRow = (id: number) => onChange(pictureOptionRows.filter((r) => r.id !== id));
  const updateRowProperty = (id: number, property: string) =>
    onChange(pictureOptionRows.map((r) => (r.id === id ? { ...r, property, value: 0 } : r)));
  const updateRowValue = (id: number, value: number) =>
    onChange(pictureOptionRows.map((r) => (r.id === id ? { ...r, value } : r)));

  const propertyOptions = Object.entries(PICTURE_PROPERTY_DEFS).map(([key, def]) => ({
    value: key,
    label: t(def.name),
  }));

  const getSliderLabels = (property: string) => {
    if (property === 'tint') {
      return { left: t('Red'), right: t('Green') };
    }
    if (property === 'colorTemperature') {
      return { left: t('Warm'), right: t('Cool') };
    }
    const def = PICTURE_PROPERTY_DEFS[property];
    if (!def) {
      return { left: '0', right: '0' };
    }
    if (def.labels) {
      return {
        left: t(def.labels[def.min] ?? String(def.min)),
        right: t(def.labels[def.max] ?? String(def.max)),
      };
    }
    return { left: String(def.min), right: String(def.max) };
  };

  const getDisplayValue = (property: string, value: number) => {
    const def = PICTURE_PROPERTY_DEFS[property];
    if (!def) {
      return String(value);
    }
    if (def.labels) {
      return t(def.labels[value] ?? String(value));
    }
    return String(value);
  };

  return (
    <div className="space-y-3">
      {pictureOptionRows.map((row) => {
        const def = row.property ? PICTURE_PROPERTY_DEFS[row.property] : null;
        const sliderLabels = row.property ? getSliderLabels(row.property) : null;
        return (
          <div key={row.id} className="flex gap-3 items-center">
            <SelectDropdown
              label=""
              className="flex-4"
              value={row.property}
              placeholder={t('Select property')}
              options={propertyOptions}
              onSelect={(v) => updateRowProperty(row.id, v)}
            />
            <div className="flex-6 h-11.25 flex items-center">
              {def && sliderLabels ? (
                <Slider
                  min={def.min}
                  max={def.max}
                  value={row.value}
                  onChange={(v) => updateRowValue(row.id, v)}
                  leftLabel={sliderLabels.left}
                  rightLabel={sliderLabels.right}
                  displayValue={getDisplayValue(row.property, row.value)}
                />
              ) : (
                <p className="text-sm text-gray-400 pt-2">
                  {t('Select a property to display inputs')}
                </p>
              )}
            </div>
            <Button className={ROW_BTN_CLASS} variant="secondary" onClick={() => removeRow(row.id)}>
              <Minus size={14} />
            </Button>
          </div>
        );
      })}
      <Button className="w-full" onClick={addRow} title={t('Add')}>
        <Plus size={14} />
      </Button>
    </div>
  );
}

export function LockOptionsInput({
  state,
  onChange,
  playerType,
  t,
}: {
  state: LockOptionsState;
  onChange: (s: LockOptionsState) => void;
  playerType?: string;
  t: TFunction;
}) {
  const boolOptions = [
    { value: 'empty', label: t('Not set') },
    { value: 'true', label: t('True') },
    { value: 'false', label: t('False') },
  ];
  const keylockBaseOptions = [
    { value: '', label: t('Not set') },
    { value: 'allowall', label: t('Allow All') },
    { value: 'blockall', label: t('Block All') },
  ];
  const keylockOptions =
    playerType === 'lg'
      ? [...keylockBaseOptions, { value: 'poweronly', label: t('Power Only') }]
      : keylockBaseOptions;

  const updateLock = (field: keyof LockOptionsState, value: string) =>
    onChange({ ...state, [field]: value });

  return (
    <div className="flex flex-col gap-4">
      {playerType === 'lg' && (
        <SelectDropdown
          label={t('USB Lock')}
          value={state.usblock}
          options={boolOptions}
          onSelect={(v) => updateLock('usblock', v)}
        />
      )}
      <SelectDropdown
        label={t('OSD Lock')}
        value={state.osdlock}
        options={boolOptions}
        onSelect={(v) => updateLock('osdlock', v)}
      />
      <SelectDropdown
        label={t('Keylock (local)')}
        value={state.keylockLocal}
        options={keylockOptions}
        onSelect={(v) => updateLock('keylockLocal', v)}
      />
      <SelectDropdown
        label={t('Keylock (remote)')}
        value={state.keylockRemote}
        options={keylockOptions}
        onSelect={(v) => updateLock('keylockRemote', v)}
      />
    </div>
  );
}

export interface LgSsspFieldProps {
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
  playerType?: string;
  playerVersions: PlayerSoftware[];
  playerVersionsHasMore?: boolean;
  onLoadMorePlayerVersions?: () => void;
  isLoadingMorePlayerVersions?: boolean;
  timerRows?: TimerRow[];
  onTimerRowsChange?: (rows: TimerRow[]) => void;
  pictureOptionRows?: PictureOptionRow[];
  onPictureOptionRowsChange?: (rows: PictureOptionRow[]) => void;
  lockOptionsState?: LockOptionsState;
  onLockOptionsStateChange?: (state: LockOptionsState) => void;
}

export function LgSsspFields({
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
  playerType,
  playerVersions,
  playerVersionsHasMore,
  onLoadMorePlayerVersions,
  isLoadingMorePlayerVersions,
  timerRows = [{ id: 0, day: '', on: '', off: '' }],
  onTimerRowsChange,
  pictureOptionRows = [{ id: 0, property: '', value: 0 }],
  onPictureOptionRowsChange,
  lockOptionsState = { usblock: 'empty', osdlock: 'empty', keylockLocal: '', keylockRemote: '' },
  onLockOptionsStateChange,
}: LgSsspFieldProps) {
  if (tab === 'timers' && onTimerRowsChange) {
    return (
      <div className="flex flex-col gap-4">
        <Checkbox
          id="disableTimerManagement"
          title={t('Disable managing on/off timers')}
          label={t(
            'When disabled on/off timers can be controlled on the screen and will not be modified by the CMS',
          )}
          checked={bool('disableTimerManagement')}
          onChange={setBool('disableTimerManagement')}
        />
        <TimersInput timerRows={timerRows} onChange={onTimerRowsChange} t={t} />
      </div>
    );
  }

  if (tab === 'pictureOptions' && onPictureOptionRowsChange) {
    return (
      <PictureOptionsInput
        pictureOptionRows={pictureOptionRows}
        onChange={onPictureOptionRowsChange}
        t={t}
      />
    );
  }

  if (tab === 'lockSettings' && onLockOptionsStateChange) {
    return (
      <LockOptionsInput
        state={lockOptionsState}
        onChange={onLockOptionsStateChange}
        playerType={playerType}
        t={t}
      />
    );
  }

  const metaMap = getFieldMetaForType(playerType, t);
  const fieldsForTab = Object.entries(metaMap).filter(
    ([, meta]) =>
      meta.tab === tab && !['timers', 'pictureOptions', 'lockOptions'].includes(meta.inputType),
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
    playerVersions,
    playerVersionsHasMore,
    onLoadMorePlayerVersions,
    isLoadingMorePlayerVersions,
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
