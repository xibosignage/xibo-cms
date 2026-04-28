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

import {
  TimersInput,
  PictureOptionsInput,
  LockOptionsInput,
  getPictureSliderIndex,
  type TimerRow,
  type PictureOptionRow,
  type LockOptionsState,
} from './LgSsspFields';
import type { FieldMeta } from './fieldMetadata';

import Checkbox from '@/components/ui/forms/Checkbox';
import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import TimePickerInput from '@/components/ui/forms/TimePickerInput';
import type { PlayerSoftware } from '@/services/playerSoftwareApi';
import type { Daypart } from '@/types/daypart';

export interface DynamicSettingFieldProps {
  meta: FieldMeta;
  value: string | number | null;
  onChange: (value: string | number | null) => void;
  contextData?: {
    dayparts?: Daypart[];
    daypartsHasMore?: boolean;
    onLoadMoreDayparts?: () => void;
    isLoadingMoreDayparts?: boolean;
    playerVersions?: PlayerSoftware[];
    playerVersionsHasMore?: boolean;
    onLoadMorePlayerVersions?: () => void;
    isLoadingMorePlayerVersions?: boolean;
    playerType?: string;
  };
}

function parseTimerRows(value: string | number | null): TimerRow[] {
  if (typeof value === 'string' && value.trim().length > 0) {
    try {
      const parsed = JSON.parse(value) as Record<string, { on: string; off: string }>;
      const rows = Object.entries(parsed).map(([day, val], i) => ({
        id: i,
        day,
        on: val.on || '',
        off: val.off || '',
      }));
      if (rows.length > 0) {
        return rows;
      }
    } catch (e) {
      console.warn('Failed to parse timers override JSON:', e);
    }
  }
  return [{ id: 0, day: '', on: '', off: '' }];
}

function TimersFieldWrapper({
  value,
  onChange,
}: {
  value: string | number | null;
  onChange: (v: string) => void;
}) {
  const { t } = useTranslation();
  const [rows, setRows] = useState<TimerRow[]>(() => parseTimerRows(value));

  const handleChange = (newRows: TimerRow[]) => {
    setRows(newRows);
    const out: Record<string, { on: string; off: string }> = {};
    newRows.forEach((r) => {
      if (r.day) {
        out[r.day] = { on: r.on, off: r.off };
      }
    });
    onChange(JSON.stringify(out));
  };

  return (
    <div className="min-w-100">
      <TimersInput timerRows={rows} t={t} onChange={handleChange} />
    </div>
  );
}

export function DynamicSettingField({
  meta,
  value,
  onChange,
  contextData = {},
}: DynamicSettingFieldProps) {
  const { t } = useTranslation();
  const { playerType } = contextData;

  if (meta.inputType === 'text') {
    return (
      <TextInput
        name={meta.label}
        label={meta.label}
        helpText={meta.helpText}
        placeholder=" "
        value={value ? String(value) : ''}
        onChange={onChange}
      />
    );
  }
  if (meta.inputType === 'number') {
    return (
      <NumberInput
        name={meta.label}
        label={meta.label}
        helpText={meta.helpText}
        value={Number(value ?? 0)}
        onChange={onChange}
      />
    );
  }
  if (meta.inputType === 'checkbox') {
    return (
      <Checkbox
        id={meta.label.replace(/\s+/g, '')}
        title={meta.label}
        label={meta.helpText}
        checked={value === 1 || value === '1' || value === 'on'}
        onChange={(e) => onChange(e.target.checked ? 1 : 0)}
      />
    );
  }
  if (meta.inputType === 'dropdown') {
    return (
      <SelectDropdown
        label={meta.label}
        helpText={meta.helpText}
        value={value ? String(value) : ''}
        options={meta.options ?? []}
        onSelect={onChange}
      />
    );
  }
  if (meta.inputType === 'time') {
    return (
      <TimePickerInput
        label={meta.label}
        helpText={meta.helpText}
        value={value ? String(value) : ''}
        onChange={onChange}
      />
    );
  }
  if (meta.inputType === 'datepicker') {
    const parseDateValue = (raw: string | number | null) => {
      if (!raw || raw === '0') {
        return '';
      }
      const ts = Number(raw);
      if (!isNaN(ts) && ts > 0) {
        return new Date(ts * 1000).toISOString();
      }
      const d = new Date(raw);
      return isNaN(d.getTime()) ? '' : d.toISOString();
    };

    const handleDateChange = (iso: string) => {
      if (!iso) {
        return onChange('');
      }
      const d = new Date(iso);
      const formatted = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`;
      onChange(formatted);
    };

    return (
      <DatePickerInput
        label={meta.label}
        helpText={meta.helpText}
        value={parseDateValue(value)}
        onChange={handleDateChange}
      />
    );
  }
  if (meta.inputType === 'daypart') {
    return (
      <SelectDropdown
        label={meta.label}
        helpText={meta.helpText}
        value={value ? String(value) : ''}
        options={
          contextData.dayparts?.map((d) => ({ value: String(d.dayPartId), label: d.name })) || []
        }
        placeholder=" "
        onSelect={onChange}
        hasMore={contextData.daypartsHasMore}
        onLoadMore={contextData.onLoadMoreDayparts}
        isLoadingMore={contextData.isLoadingMoreDayparts}
      />
    );
  }
  if (meta.inputType === 'player-version') {
    return (
      <SelectDropdown
        label={meta.label}
        helpText={meta.helpText}
        value={value ? String(value) : ''}
        options={
          contextData.playerVersions?.map((v) => ({
            value: String(v.versionId),
            label: v.playerShowVersion,
          })) || []
        }
        placeholder=" "
        onSelect={onChange}
        hasMore={contextData.playerVersionsHasMore}
        onLoadMore={contextData.onLoadMorePlayerVersions}
        isLoadingMore={contextData.isLoadingMorePlayerVersions}
      />
    );
  }
  if (meta.inputType === 'timers') {
    return <TimersFieldWrapper value={value} onChange={(v) => onChange(v)} />;
  }

  if (meta.inputType === 'picture-options') {
    let parsedPicture: PictureOptionRow[] = [];
    if (typeof value === 'string' && value.trim().length > 0) {
      try {
        const parsed = JSON.parse(value) as Record<string, string | number>;
        parsedPicture = Object.entries(parsed).map(([property, val], i) => ({
          id: i,
          property,
          value: getPictureSliderIndex(property, val),
        }));
      } catch (e) {
        console.warn('Failed to parse picture-options override JSON:', e);
      }
    }
    if (parsedPicture.length === 0) {
      parsedPicture = [{ id: 0, property: '', value: 0 }];
    }

    return (
      <div className="min-w-100">
        <PictureOptionsInput
          pictureOptionRows={parsedPicture}
          t={t}
          onChange={(newRows) => {
            const out: Record<string, number> = {};
            newRows.forEach((r) => {
              if (r.property) {
                out[r.property] = r.value;
              }
            });
            onChange(JSON.stringify(out));
          }}
        />
      </div>
    );
  }

  if (meta.inputType === 'lock-options') {
    let parsedLock: LockOptionsState = {
      usblock: 'empty',
      osdlock: 'empty',
      keylockLocal: '',
      keylockRemote: '',
    };
    if (typeof value === 'string' && value.trim().length > 0) {
      try {
        const parsed = JSON.parse(value) as {
          usblock?: boolean | null;
          osdlock?: boolean | null;
          keylock?: { local?: string; remote?: string };
        };
        parsedLock = {
          usblock: parsed.usblock != null ? (parsed.usblock ? 'true' : 'false') : 'empty',
          osdlock: parsed.osdlock != null ? (parsed.osdlock ? 'true' : 'false') : 'empty',
          keylockLocal: parsed.keylock?.local || '',
          keylockRemote: parsed.keylock?.remote || '',
        };
      } catch (e) {
        console.warn('Failed to parse lock-options override JSON:', e);
      }
    }

    return (
      <div className="min-w-75">
        <LockOptionsInput
          state={parsedLock}
          t={t}
          playerType={playerType}
          onChange={(newState) => {
            const out = {
              usblock: newState.usblock === 'empty' ? null : newState.usblock === 'true',
              osdlock: newState.osdlock === 'empty' ? null : newState.osdlock === 'true',
              keylock: { local: newState.keylockLocal, remote: newState.keylockRemote },
            };
            onChange(JSON.stringify(out));
          }}
        />
      </div>
    );
  }

  return null;
}
