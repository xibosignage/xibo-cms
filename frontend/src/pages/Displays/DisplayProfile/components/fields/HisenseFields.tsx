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
import { Minus, Plus } from 'lucide-react';
import React, { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';

import { DynamicSettingField } from './DynamicSettingField';
import type { PicturePropertyDef } from './LgSsspFields';
import { getFieldMetaForType, isFieldMetaEnabled } from './fieldMetadata';

import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Slider from '@/components/ui/forms/Slider';
import TimePickerInput from '@/components/ui/forms/TimePickerInput';
import type { PlayerSoftware } from '@/services/playerSoftwareApi';
import type { Daypart } from '@/types/daypart';

export interface HisenseTimerRule {
  id: number;
  index: number;
  dayScope: number;
  time: string;
  manualWeeks: number[];
}

export interface HisensePictureRow {
  id: number;
  property: string;
  value: number;
}

export const HISENSE_PICTURE_PROPERTY_DEFS: Record<string, PicturePropertyDef> = {
  brightness: { name: 'Brightness', min: 0, max: 100 },
  contrast: { name: 'Contrast', min: 0, max: 100 },
  backlight: { name: 'Backlight', min: 0, max: 100 },
  saturation: { name: 'Saturation', min: 0, max: 100 },
  colourTemperature: { name: 'Colour Temperature', min: 0, max: 100 },
};

export const HISENSE_PICTURE_KEYS = Object.keys(HISENSE_PICTURE_PROPERTY_DEFS);

export const GAMMA_MODE_OPTIONS = [
  { value: '0', label: 'Standard' },
  { value: '1', label: 'Bias' },
  { value: '2', label: 'Darker' },
];

export function getHisensePictureSliderIndex(
  property: string,
  storedValue: string | number,
): number {
  const def = HISENSE_PICTURE_PROPERTY_DEFS[property];
  if (!def) return 0;
  if (def.labels && typeof storedValue === 'string') {
    const idx = def.labels.indexOf(storedValue.toLowerCase());
    return idx >= 0 ? idx : 0;
  }
  const n = Number(storedValue);
  return isNaN(n) ? 0 : n;
}

const MAX_RULES_PER_TYPE = 3;
const ROW_BTN_CLASS = 'h-8 w-8 min-w-8';

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function getDayScopeOptions(t: TFunction) {
  return [
    { value: '0', label: t('Off') },
    { value: '2', label: t('Every day') },
    { value: '3', label: t('Monday to Friday') },
    { value: '4', label: t('Monday to Saturday') },
    { value: '5', label: t('Saturday to Sunday') },
    { value: '6', label: t('Manual') },
  ];
}

/** Expands a rule's day scope into the concrete set of weekdays it covers (0=Sun..6=Sat). */
function expandHisenseDayScope(rule: HisenseTimerRule): Set<number> {
  switch (rule.dayScope) {
    case 2:
      return new Set([0, 1, 2, 3, 4, 5, 6]);
    case 3:
      return new Set([1, 2, 3, 4, 5]);
    case 4:
      return new Set([1, 2, 3, 4, 5, 6]);
    case 5:
      return new Set([6, 0]);
    case 6:
      return new Set(rule.manualWeeks);
    default:
      return new Set();
  }
}

/**
 * Finds the first pair of same-type (power-on/power-on or power-off/power-off) rules whose
 * day scopes overlap, and returns a description of the conflict, or null if there's none.
 */
export function findHisenseTimerOverlap(
  rules: HisenseTimerRule[],
  t: TFunction,
): React.ReactNode | null {
  const active = rules.filter((r) => r.dayScope !== 0);
  const groups: Array<[HisenseTimerRule[], string]> = [
    [active.filter((r) => r.index < 3), t('Power On')],
    [active.filter((r) => r.index >= 3), t('Power Off')],
  ];

  for (const [group, label] of groups) {
    for (const [i, ruleA] of group.entries()) {
      const daysA = expandHisenseDayScope(ruleA);
      for (const ruleB of group.slice(i + 1)) {
        const daysB = expandHisenseDayScope(ruleB);
        const overlapDay = [...daysA].find((d) => daysB.has(d));
        if (overlapDay !== undefined) {
          const dayLabel = DAY_LABELS[overlapDay] ?? '';
          return (
            <Trans
              i18nKey="{{label}}: two rules overlap on <strong>{{day}}</strong>."
              values={{ label, day: t(dayLabel) }}
              components={{ strong: <strong /> }}
            />
          );
        }
      }
    }
  }

  return null;
}

export function HisenseTimersInput({
  rules,
  onChange,
  t,
}: {
  rules: HisenseTimerRule[];
  onChange: (rules: HisenseTimerRule[]) => void;
  t: TFunction;
}) {
  const dayScopeOptions = getDayScopeOptions(t);
  const onRules = rules.filter((r) => r.index < 3);
  const offRules = rules.filter((r) => r.index >= 3);

  const nextId = rules.length > 0 ? Math.max(...rules.map((r) => r.id)) + 1 : 1;

  const getNextIndex = (type: 'on' | 'off') => {
    const used = new Set((type === 'on' ? onRules : offRules).map((r) => r.index));
    const base = type === 'on' ? 0 : 3;
    for (let i = base; i < base + MAX_RULES_PER_TYPE; i++) {
      if (!used.has(i)) return i;
    }
    return -1;
  };

  const addRule = (type: 'on' | 'off') => {
    const index = getNextIndex(type);
    if (index === -1) return;
    onChange([...rules, { id: nextId, index, dayScope: 2, time: '00:00', manualWeeks: [] }]);
  };

  const removeRule = (id: number) => onChange(rules.filter((r) => r.id !== id));

  const updateRule = (id: number, updates: Partial<HisenseTimerRule>) => {
    onChange(rules.map((r) => (r.id === id ? { ...r, ...updates } : r)));
  };

  const toggleManualDay = (id: number, day: number) => {
    const rule = rules.find((r) => r.id === id);
    if (!rule) return;
    const weeks = rule.manualWeeks.includes(day)
      ? rule.manualWeeks.filter((d) => d !== day)
      : [...rule.manualWeeks, day];
    updateRule(id, { manualWeeks: weeks });
  };

  const renderRuleRow = (rule: HisenseTimerRule) => (
    <div key={rule.id} className="space-y-2">
      <div className="flex gap-2 items-center">
        <SelectDropdown
          label=""
          className="flex-4"
          value={String(rule.dayScope)}
          options={dayScopeOptions}
          onSelect={(v) => updateRule(rule.id, { dayScope: Number(v) })}
        />
        <TimePickerInput
          label=""
          className="flex-3 min-w-0 gap-0"
          value={rule.time}
          timeFormat="HH:mm"
          onChange={(v) => updateRule(rule.id, { time: v })}
        />
        <Button
          className={ROW_BTN_CLASS}
          variant="secondary"
          onClick={() => removeRule(rule.id)}
          title={t('Remove')}
        >
          <Minus size={14} />
        </Button>
      </div>
      {rule.dayScope === 6 && (
        <div className="flex gap-4 pl-1 py-2">
          {DAY_LABELS.map((label, dayIndex) => (
            <Checkbox
              key={dayIndex}
              id={`manual-day-${rule.id}-${dayIndex}`}
              title={t(label)}
              checked={rule.manualWeeks.includes(dayIndex)}
              onChange={() => toggleManualDay(rule.id, dayIndex)}
              classNameInput="mr-2"
            />
          ))}
        </div>
      )}
    </div>
  );

  const renderSection = (type: 'on' | 'off', sectionRules: HisenseTimerRule[], label: string) => (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h4 className="text-sm font-semibold text-gray-700 uppercase">{label}</h4>
        <span className="text-xs text-gray-500">
          ({sectionRules.length} {t('of')} {MAX_RULES_PER_TYPE} {t('used')})
        </span>
      </div>
      {sectionRules.length > 0 && (
        <div className="flex gap-2 items-center text-xs font-semibold text-gray-500 uppercase px-1">
          <span className="flex-4">{t('Day Scope')}</span>
          <span className="flex-3">{t('Time')}</span>
          <span className="w-8 shrink-0" />
        </div>
      )}
      <div className="space-y-2">{sectionRules.map(renderRuleRow)}</div>
      <Button
        className="w-full"
        onClick={() => addRule(type)}
        disabled={sectionRules.length >= MAX_RULES_PER_TYPE}
        title={type === 'on' ? t('Add power-on rule') : t('Add power-off rule')}
        leftIcon={Plus}
      >
        {type === 'on' ? t('Add power-on rule') : t('Add power-off rule')}
      </Button>
    </div>
  );

  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700">
        {t(
          'This display supports up to 3 power-on and 3 power-off rules. Each rule can repeat across several days.',
        )}
      </div>
      {renderSection('on', onRules, t('Power On'))}
      {renderSection('off', offRules, t('Power Off'))}
    </div>
  );
}

export function HisensePictureInput({
  rows,
  onChange,
  t,
}: {
  rows: HisensePictureRow[];
  onChange: (rows: HisensePictureRow[]) => void;
  t: TFunction;
}) {
  const addRow = () => {
    const nextId = rows.length > 0 ? Math.max(...rows.map((r) => r.id)) + 1 : 1;
    onChange([...rows, { id: nextId, property: '', value: 0 }]);
  };
  const removeRow = (id: number) => onChange(rows.filter((r) => r.id !== id));
  const updateRowProperty = (id: number, property: string) =>
    onChange(rows.map((r) => (r.id === id ? { ...r, property, value: 0 } : r)));
  const updateRowValue = (id: number, value: number) =>
    onChange(rows.map((r) => (r.id === id ? { ...r, value } : r)));

  const allPropertyKeys = Object.keys(HISENSE_PICTURE_PROPERTY_DEFS);
  const usedProperties = new Set(rows.map((r) => r.property).filter(Boolean));
  const allUsed = usedProperties.size >= allPropertyKeys.length;

  const getSliderLabels = (property: string) => {
    if (property === 'colourTemperature') {
      return { left: t('Warm'), right: t('Cool') };
    }
    const def = HISENSE_PICTURE_PROPERTY_DEFS[property];
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
    const def = HISENSE_PICTURE_PROPERTY_DEFS[property];
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
      {rows.map((row) => {
        const def = row.property ? HISENSE_PICTURE_PROPERTY_DEFS[row.property] : null;
        const sliderLabels = row.property ? getSliderLabels(row.property) : null;
        const availableOptions = Object.entries(HISENSE_PICTURE_PROPERTY_DEFS)
          .filter(([key]) => key === row.property || !usedProperties.has(key))
          .map(([key, d]) => ({ value: key, label: t(d.name) }));
        return (
          <div key={row.id} className="flex gap-3 items-center">
            <SelectDropdown
              label=""
              className="w-40 min-w-40 max-w-40 truncate"
              value={row.property}
              placeholder={t('Select property')}
              options={availableOptions}
              onSelect={(v) => updateRowProperty(row.id, v)}
            />
            <div className="flex-1 min-w-0 h-11.25 flex items-center">
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
            <Button
              className={ROW_BTN_CLASS}
              variant="secondary"
              onClick={() => removeRow(row.id)}
              title={t('Remove')}
            >
              <Minus size={14} />
            </Button>
          </div>
        );
      })}
      <Button className="w-full" onClick={addRow} disabled={allUsed} title={t('Add')}>
        <Plus size={14} />
      </Button>
    </div>
  );
}

export interface HisenseFieldProps {
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
  playerVersions: PlayerSoftware[];
  playerVersionsHasMore?: boolean;
  onLoadMorePlayerVersions?: () => void;
  isLoadingMorePlayerVersions?: boolean;
  onSearchPlayerVersions?: (term: string) => void;
  hisenseTimerRules: HisenseTimerRule[];
  onHisenseTimerRulesChange: (rules: HisenseTimerRule[]) => void;
  hisensePictureRows: HisensePictureRow[];
  onHisensePictureRowsChange: (rows: HisensePictureRow[]) => void;
  settings?: Record<string, unknown> | null;
}

export function HisenseFields({
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
  playerVersions,
  playerVersionsHasMore,
  onLoadMorePlayerVersions,
  isLoadingMorePlayerVersions,
  onSearchPlayerVersions,
  hisenseTimerRules,
  onHisenseTimerRulesChange,
  hisensePictureRows,
  onHisensePictureRowsChange,
  settings,
}: HisenseFieldProps) {
  if (tab === 'timers') {
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
        {!bool('disableTimerManagement') && (
          <HisenseTimersInput
            rules={hisenseTimerRules}
            onChange={onHisenseTimerRulesChange}
            t={t}
          />
        )}
      </div>
    );
  }

  if (tab === 'pictureOptions') {
    return (
      <div className="flex flex-col gap-4">
        <HisensePictureInput
          rows={hisensePictureRows}
          onChange={onHisensePictureRowsChange}
          t={t}
        />
        <SelectDropdown
          label={t('Gamma Mode')}
          value={str('gammaMode')}
          options={GAMMA_MODE_OPTIONS.map((o) => ({ ...o, label: t(o.label) }))}
          onSelect={setStr('gammaMode')}
        />
        <Checkbox
          id="dynamicContrast"
          title={t('Dynamic Contrast')}
          label={t('Automatically adjusts contrast levels to enhance picture quality')}
          checked={num('dynamicContrast') === 1}
          onChange={(e) => setNum('dynamicContrast')(e.target.checked ? 1 : 0)}
        />
      </div>
    );
  }

  const metaMap = getFieldMetaForType('hisense', t);
  const fieldsForTab = Object.entries(metaMap).filter(
    ([, meta]) =>
      meta.tab === tab &&
      !['timers', 'picture-options', 'hisense-timers', 'hisense-picture-options'].includes(
        meta.inputType,
      ) &&
      isFieldMetaEnabled(meta, settings, bool),
  );

  if (fieldsForTab.length === 0) {
    return null;
  }

  const getValue = (key: string, inputType: string) => {
    if (inputType === 'number') return num(key);
    if (inputType === 'checkbox') return bool(key) ? 1 : 0;
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
    playerVersions,
    playerVersionsHasMore,
    onLoadMorePlayerVersions,
    isLoadingMorePlayerVersions,
    onSearchPlayerVersions,
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

function parseHisenseTimerRules(value: string | number | null): HisenseTimerRule[] {
  if (typeof value === 'string' && value.trim().length > 0) {
    try {
      const parsed = JSON.parse(value) as Array<{
        index: number;
        type: number;
        hour: number;
        minute: number;
        manualWeeks?: number[];
      }>;
      if (Array.isArray(parsed) && parsed.length > 0) {
        return parsed.map((entry, i) => ({
          id: i,
          index: entry.index,
          dayScope: entry.type,
          time: `${String(entry.hour).padStart(2, '0')}:${String(entry.minute).padStart(2, '0')}`,
          manualWeeks: entry.manualWeeks ?? [],
        }));
      }
    } catch (e) {
      console.warn('Failed to parse hisense timers override JSON:', e);
    }
  }
  return [];
}

export function HisenseTimersFieldWrapper({
  value,
  onChange,
}: {
  value: string | number | null;
  onChange: (v: string) => void;
}) {
  const { t } = useTranslation();
  const [rules, setRules] = useState<HisenseTimerRule[]>(() => parseHisenseTimerRules(value));

  const handleChange = (newRules: HisenseTimerRule[]) => {
    setRules(newRules);
    const out = newRules
      .filter((r) => r.dayScope !== 0)
      .map(({ index, dayScope, time, manualWeeks }) => ({
        index,
        dayScope,
        time,
        manualWeeks,
      }));
    onChange(JSON.stringify(out));
  };

  return (
    <div className="min-w-100">
      <HisenseTimersInput rules={rules} onChange={handleChange} t={t} />
    </div>
  );
}

export function HisensePictureOptionsFieldWrapper({
  value,
  onChange,
}: {
  value: string | number | null;
  onChange: (v: string) => void;
}) {
  const { t } = useTranslation();

  const parsed: Record<string, number> = (() => {
    if (typeof value === 'string' && value.trim().length > 0) {
      try {
        return JSON.parse(value) as Record<string, number>;
      } catch {
        return {};
      }
    }
    return {};
  })();

  const sliderKeys = Object.keys(HISENSE_PICTURE_PROPERTY_DEFS);

  const initRows: HisensePictureRow[] = sliderKeys
    .filter((key) => parsed[key] !== undefined)
    .map((key, i) => ({
      id: i,
      property: key,
      value: getHisensePictureSliderIndex(key, parsed[key] ?? 0),
    }));
  if (initRows.length === 0) {
    initRows.push({ id: 0, property: '', value: 0 });
  }

  const [rows, setRows] = useState<HisensePictureRow[]>(initRows);
  const [gammaMode, setGammaMode] = useState<string>(
    parsed.gammaMode !== undefined ? String(parsed.gammaMode) : '',
  );
  const [dynamicContrast, setDynamicContrast] = useState<boolean>(parsed.dynamicContrast === 1);

  const emitChange = (newRows: HisensePictureRow[], gm: string, dc: boolean) => {
    const out: Record<string, number> = {};
    newRows.forEach((r) => {
      if (r.property) {
        out[r.property] = r.value;
      }
    });
    if (gm !== '') {
      out.gammaMode = Number(gm);
    }
    out.dynamicContrast = dc ? 1 : 0;
    onChange(JSON.stringify(out));
  };

  return (
    <div className="min-w-100 space-y-4">
      <HisensePictureInput
        rows={rows}
        onChange={(newRows) => {
          setRows(newRows);
          emitChange(newRows, gammaMode, dynamicContrast);
        }}
        t={t}
      />
      <SelectDropdown
        label={t('Gamma Mode')}
        value={gammaMode}
        options={GAMMA_MODE_OPTIONS.map((o) => ({ ...o, label: t(o.label) }))}
        onSelect={(v) => {
          setGammaMode(v);
          emitChange(rows, v, dynamicContrast);
        }}
      />
      <Checkbox
        id="dynamicContrast-override"
        title={t('Dynamic Contrast')}
        label={t('Automatically adjusts contrast levels to enhance picture quality')}
        checked={dynamicContrast}
        onChange={(e) => {
          setDynamicContrast(e.target.checked);
          emitChange(rows, gammaMode, e.target.checked);
        }}
      />
    </div>
  );
}
