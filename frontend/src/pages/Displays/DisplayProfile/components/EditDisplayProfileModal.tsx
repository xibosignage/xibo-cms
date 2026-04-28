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

import { useEffect, useRef, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { AndroidFields } from './fields/AndroidFields';
import { ChromeOsFields } from './fields/ChromeOsFields';
import { getPictureSliderIndex, LgSsspFields } from './fields/LgSsspFields';
import type { LockOptionsState, PictureOptionRow, TimerRow } from './fields/LgSsspFields';
import { LinuxFields } from './fields/LinuxFields';
import { WindowsFields } from './fields/WindowsFields';
import { CHECKBOX_FIELDS_BY_TYPE } from './fields/fieldMetadata';

import Checkbox from '@/components/ui/forms/Checkbox';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getEditDisplayProfileSchema } from '@/schema/displayProfile';
import { fetchDaypart } from '@/services/daypartApi';
import { fetchDisplayProfileById, updateDisplayProfile } from '@/services/displayProfileApi';
import { fetchPlayerSoftware } from '@/services/playerSoftwareApi';
import type { PlayerSoftware } from '@/services/playerSoftwareApi';
import type { Daypart } from '@/types/daypart';
import type { DisplayProfile } from '@/types/displayProfile';

const PAGE_SIZE = 10;

type ConfigValue = string | number | null;
type FlatConfig = Record<string, ConfigValue>;

function getApiErrorMessage(err: unknown, fallback: string): string {
  const e = err as { response?: { data?: { message?: string } } };
  return e.response?.data?.message ?? (err instanceof Error ? err.message : fallback);
}

interface EditDraft {
  name: string;
  isDefault: number;
  config: FlatConfig;
}

type ActiveTab =
  | 'general'
  | 'network'
  | 'location'
  | 'troubleshooting'
  | 'advanced'
  | 'timers'
  | 'pictureOptions'
  | 'lockSettings';

function tabClass(activeTab: ActiveTab, tab: ActiveTab): string {
  const isActive = activeTab === tab;
  return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
    isActive ? 'border-blue-600 text-blue-500' : 'border-gray-200 text-gray-500 hover:text-blue-600'
  }`;
}

function configArrayToFlat(config: DisplayProfile['config']): FlatConfig {
  if (!config) {
    return {};
  }
  return Object.fromEntries(config.map((item) => [item.name, item.value]));
}

function defaultsToFlat(configDefault: DisplayProfile['configDefault']): FlatConfig {
  if (!configDefault) {
    return {};
  }
  return Object.fromEntries(configDefault.map((item) => [item.name, item.default]));
}

interface EditDisplayProfileModalProps {
  isOpen?: boolean;
  data: DisplayProfile | null;
  onClose: () => void;
  onSave: (updated: DisplayProfile) => void;
}

export default function EditDisplayProfileModal({
  isOpen = true,
  data,
  onClose,
  onSave,
}: EditDisplayProfileModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [isLoading, setIsLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<ActiveTab>('general');
  const [apiError, setApiError] = useState<string | undefined>();
  const [nameError, setNameError] = useState<string | undefined>();

  const [draft, setDraft] = useState<EditDraft>({ name: '', isDefault: 0, config: {} });
  const [configDefaults, setConfigDefaults] = useState<FlatConfig>({});

  const [timerRows, setTimerRows] = useState<TimerRow[]>([{ id: 0, day: '', on: '', off: '' }]);
  const [pictureOptionRows, setPictureOptionRows] = useState<PictureOptionRow[]>([
    { id: 0, property: '', value: 0 },
  ]);
  const [lockOptionsState, setLockOptionsState] = useState<LockOptionsState>({
    usblock: 'empty',
    osdlock: 'empty',
    keylockLocal: '',
    keylockRemote: '',
  });

  const [dayparts, setDayparts] = useState<Daypart[]>([]);
  const [daypartsTotalCount, setDaypartsTotalCount] = useState(0);
  const [isLoadingMoreDayparts, setIsLoadingMoreDayparts] = useState(false);
  const daypartsPageRef = useRef(0);

  const [playerVersions, setPlayerVersions] = useState<PlayerSoftware[]>([]);
  const [playerVersionsTotalCount, setPlayerVersionsTotalCount] = useState(0);
  const [isLoadingMorePlayerVersions, setIsLoadingMorePlayerVersions] = useState(false);
  const playerVersionsPageRef = useRef(0);
  const playerVersionTypeRef = useRef<string | null>(null);

  useEffect(() => {
    if (!isOpen || !data) {
      return;
    }

    setActiveTab('general');
    setApiError(undefined);
    setNameError(undefined);
    setIsLoading(true);

    setDayparts([]);
    setDaypartsTotalCount(0);
    daypartsPageRef.current = 0;
    setPlayerVersions([]);
    setPlayerVersionsTotalCount(0);
    playerVersionsPageRef.current = 0;
    setTimerRows([{ id: 0, day: '', on: '', off: '' }]);
    setPictureOptionRows([{ id: 0, property: '', value: 0 }]);
    setLockOptionsState({
      usblock: 'empty',
      osdlock: 'empty',
      keylockLocal: '',
      keylockRemote: '',
    });

    const isLgSsspType = data.type === 'lg' || data.type === 'sssp';

    const profilePromise = fetchDisplayProfileById(data.displayProfileId).then((full) => {
      setConfigDefaults(defaultsToFlat(full.configDefault));
      setDraft({
        name: full.name,
        isDefault: full.isDefault,
        config: configArrayToFlat(full.config),
      });

      if (isLgSsspType) {
        const flatConfig = configArrayToFlat(full.config);

        // Parse timers
        const timersJson = flatConfig['timers'];
        let parsedTimers: TimerRow[] = [];
        if (timersJson && typeof timersJson === 'string') {
          try {
            const parsed = JSON.parse(timersJson) as Record<string, { on: string; off: string }>;
            parsedTimers = Object.entries(parsed).map(([day, val], i) => ({
              id: i,
              day,
              on: val.on || '',
              off: val.off || '',
            }));
          } catch (e) {
            console.warn('Failed to parse timers config', e);
          }
        }
        setTimerRows(
          parsedTimers.length > 0 ? parsedTimers : [{ id: 0, day: '', on: '', off: '' }],
        );

        const pictureJson = flatConfig['pictureOptions'];
        let parsedPicture: PictureOptionRow[] = [];
        if (pictureJson && typeof pictureJson === 'string') {
          try {
            const parsed = JSON.parse(pictureJson) as Record<string, string | number>;
            parsedPicture = Object.entries(parsed).map(([property, val], i) => ({
              id: i,
              property,
              value: getPictureSliderIndex(property, val),
            }));
          } catch (e) {
            console.warn('Failed to parse pictureOptions config', e);
          }
        }
        setPictureOptionRows(
          parsedPicture.length > 0 ? parsedPicture : [{ id: 0, property: '', value: 0 }],
        );

        const lockJson = flatConfig['lockOptions'];
        let parsedLock: LockOptionsState = {
          usblock: 'empty',
          osdlock: 'empty',
          keylockLocal: '',
          keylockRemote: '',
        };
        if (lockJson && typeof lockJson === 'string') {
          try {
            const parsed = JSON.parse(lockJson) as {
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
            console.warn('Failed to parse lockOptions config', e);
          }
        }
        setLockOptionsState(parsedLock);
      }
    });

    const daypartPromise = fetchDaypart({ start: 0, length: PAGE_SIZE, isAlways: 0, isCustom: 0 })
      .then((res) => {
        setDayparts(res.rows);
        setDaypartsTotalCount(res.totalCount);
        daypartsPageRef.current = 1;
      })
      .catch(() => setDayparts([]));

    const playerVersionType =
      data.type === 'chromeOS'
        ? 'chromeOS'
        : data.type === 'android' || data.type === 'lg' || data.type === 'sssp'
          ? data.type
          : null;

    playerVersionTypeRef.current = playerVersionType;

    const playerVersionPromise = playerVersionType
      ? fetchPlayerSoftware({ playerType: playerVersionType, start: 0, length: PAGE_SIZE })
          .then((res) => {
            setPlayerVersions(res.rows);
            setPlayerVersionsTotalCount(res.totalCount);
            playerVersionsPageRef.current = 1;
          })
          .catch(() => setPlayerVersions([]))
      : Promise.resolve();

    Promise.all([profilePromise, daypartPromise, playerVersionPromise])
      .catch((err: unknown) => {
        setApiError(getApiErrorMessage(err, t('Failed to load profile settings.')));
      })
      .finally(() => setIsLoading(false));
  }, [isOpen, data, t]);

  const handleLoadMoreDayparts = () => {
    if (isLoadingMoreDayparts || dayparts.length >= daypartsTotalCount) {
      return;
    }
    setIsLoadingMoreDayparts(true);
    fetchDaypart({
      start: daypartsPageRef.current * PAGE_SIZE,
      length: PAGE_SIZE,
      isAlways: 0,
      isCustom: 0,
    })
      .then((res) => {
        setDayparts((prev) => [...prev, ...res.rows]);
        setDaypartsTotalCount(res.totalCount);
        daypartsPageRef.current += 1;
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreDayparts(false));
  };

  const handleLoadMorePlayerVersions = () => {
    const playerVersionType = playerVersionTypeRef.current;
    if (
      !playerVersionType ||
      isLoadingMorePlayerVersions ||
      playerVersions.length >= playerVersionsTotalCount
    ) {
      return;
    }
    setIsLoadingMorePlayerVersions(true);
    fetchPlayerSoftware({
      playerType: playerVersionType,
      start: playerVersionsPageRef.current * PAGE_SIZE,
      length: PAGE_SIZE,
    })
      .then((res) => {
        setPlayerVersions((prev) => [...prev, ...res.rows]);
        setPlayerVersionsTotalCount(res.totalCount);
        playerVersionsPageRef.current += 1;
      })
      .catch(() => {})
      .finally(() => setIsLoadingMorePlayerVersions(false));
  };

  const str = (key: string): string => String(draft.config[key] ?? configDefaults[key] ?? '');
  const num = (key: string): number => Number(draft.config[key] ?? configDefaults[key] ?? 0);
  const bool = (key: string): boolean => {
    const v = draft.config[key] ?? configDefaults[key];
    return v === 1 || v === '1' || v === 'on';
  };

  const set = (key: string, value: ConfigValue) =>
    setDraft((prev) => ({ ...prev, config: { ...prev.config, [key]: value } }));

  const setStr = (key: string) => (value: string) => set(key, value);
  const setNum = (key: string) => (value: number) => set(key, value);
  const setBool = (key: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
    set(key, e.target.checked ? 1 : 0);

  const handleSave = () => {
    if (!data) {
      return;
    }

    startTransition(async () => {
      const schema = getEditDisplayProfileSchema(t);
      const result = schema.safeParse({ name: draft.name, isDefault: draft.isDefault });

      if (!result.success) {
        setApiError(undefined);
        setNameError(result.error.flatten().fieldErrors.name?.[0]);
        return;
      }

      setNameError(undefined);
      const checkboxFields = CHECKBOX_FIELDS_BY_TYPE[data.type] ?? new Set();
      const isLgSsspType = data.type === 'lg' || data.type === 'sssp';
      const skipKeys = isLgSsspType
        ? new Set(['timers', 'pictureOptions', 'lockOptions'])
        : new Set<string>();

      const configPayload: Record<string, string | number | null> = {};
      for (const [key, value] of Object.entries(draft.config)) {
        if (skipKeys.has(key)) continue;
        if (checkboxFields.has(key)) {
          configPayload[key] = value === 1 || value === '1' || value === 'on' ? 'on' : 'off';
        } else if (key === 'elevateLogsUntil') {
          const ts = Number(value);
          if (!value || value === '0' || value === 0 || isNaN(ts) || ts <= 0) {
            configPayload[key] = '';
          } else if (
            typeof value === 'number' ||
            (typeof value === 'string' && String(Math.floor(ts)) === value.trim())
          ) {
            const d = new Date(ts * 1000);
            const pad = (n: number) => String(n).padStart(2, '0');
            configPayload[key] =
              `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` +
              ` ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
          } else {
            configPayload[key] = value;
          }
        } else {
          configPayload[key] = value;
        }
      }

      try {
        const updated = await updateDisplayProfile(data.displayProfileId, {
          name: draft.name,
          isDefault: draft.isDefault,
          config: configPayload,
          ...(isLgSsspType && {
            timers: timerRows
              .filter((r) => r.day !== '')
              .map(({ day, on, off }) => ({ day, on, off })),
            pictureControls: pictureOptionRows
              .filter((r) => r.property !== '')
              .map(({ property, value }) => ({ property, value })),
            lockOptions: lockOptionsState,
          }),
        });
        onSave({ ...data, ...updated });
        onClose();
      } catch (err: unknown) {
        setApiError(getApiErrorMessage(err, t('An unexpected error occurred while saving.')));
      }
    });
  };

  const tab = (t: ActiveTab) => tabClass(activeTab, t);

  const isLgSssp = data?.type === 'lg' || data?.type === 'sssp';
  const hasNetworkTab =
    data?.type === 'android' || data?.type === 'windows' || data?.type === 'linux';
  const hasLocationTab =
    data?.type === 'android' || data?.type === 'windows' || data?.type === 'linux';
  const hasTroubleshootingTab =
    data?.type === 'android' || data?.type === 'windows' || data?.type === 'linux';
  const hasTimersTab = isLgSssp;
  const hasPictureOptionsTab = isLgSssp;
  const hasLockSettingsTab = isLgSssp;
  const hasAdvancedTab =
    data?.type === 'android' ||
    data?.type === 'chromeOS' ||
    data?.type === 'windows' ||
    data?.type === 'linux' ||
    data?.type === 'lg' ||
    data?.type === 'sssp';

  const fieldProps = {
    str,
    num,
    bool,
    setStr,
    setNum,
    setBool,
    t,
    tab: activeTab,
    dayparts,
    daypartsHasMore: dayparts.length < daypartsTotalCount,
    onLoadMoreDayparts: handleLoadMoreDayparts,
    isLoadingMoreDayparts,
    playerVersions,
    playerVersionsHasMore: playerVersions.length < playerVersionsTotalCount,
    onLoadMorePlayerVersions: handleLoadMorePlayerVersions,
    isLoadingMorePlayerVersions,
    timerRows,
    onTimerRowsChange: setTimerRows,
    pictureOptionRows,
    onPictureOptionRowsChange: setPictureOptionRows,
    lockOptionsState,
    onLockOptionsStateChange: setLockOptionsState,
  };

  const renderTypeFields = () => {
    switch (data?.type) {
      case 'android':
        return <AndroidFields {...fieldProps} />;
      case 'windows':
        return <WindowsFields {...fieldProps} />;
      case 'linux':
        return <LinuxFields {...fieldProps} />;
      case 'lg':
      case 'sssp':
        return <LgSsspFields {...fieldProps} playerType={data?.type} />;
      case 'chromeOS':
        return <ChromeOsFields {...fieldProps} />;
      default:
        return null;
    }
  };

  const title = data ? `${t('Edit')} "${data.name}"` : t('Edit Display Profile');

  return (
    <Modal
      title={title}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending || isLoading}
      scrollable={false}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending || isLoading,
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible px-4">
        <nav className="flex px-4 overflow-x-auto shrink-0" aria-label="Tabs">
          <button type="button" className={tab('general')} onClick={() => setActiveTab('general')}>
            {t('General')}
          </button>
          {hasNetworkTab && (
            <button
              type="button"
              className={tab('network')}
              onClick={() => setActiveTab('network')}
            >
              {t('Network')}
            </button>
          )}
          {hasLocationTab && (
            <button
              type="button"
              className={tab('location')}
              onClick={() => setActiveTab('location')}
            >
              {t('Location')}
            </button>
          )}
          {hasTroubleshootingTab && (
            <button
              type="button"
              className={tab('troubleshooting')}
              onClick={() => setActiveTab('troubleshooting')}
            >
              {t('Troubleshooting')}
            </button>
          )}
          {hasTimersTab && (
            <button type="button" className={tab('timers')} onClick={() => setActiveTab('timers')}>
              {t('On/Off Timers')}
            </button>
          )}
          {hasPictureOptionsTab && (
            <button
              type="button"
              className={tab('pictureOptions')}
              onClick={() => setActiveTab('pictureOptions')}
            >
              {t('Picture Settings')}
            </button>
          )}
          {hasLockSettingsTab && (
            <button
              type="button"
              className={tab('lockSettings')}
              onClick={() => setActiveTab('lockSettings')}
            >
              {t('Lock Settings')}
            </button>
          )}
          {hasAdvancedTab && (
            <button
              type="button"
              className={tab('advanced')}
              onClick={() => setActiveTab('advanced')}
            >
              {t('Advanced')}
            </button>
          )}
        </nav>

        <div className="flex-1 overflow-y-auto py-4 px-8 space-y-4">
          {isLoading ? (
            <div className="flex items-center justify-center h-32 text-gray-400">
              {t('Loading settings…')}
            </div>
          ) : (
            <>
              {/* General */}
              {activeTab === 'general' && (
                <>
                  <TextInput
                    name="name"
                    label={t('Name')}
                    helpText={t('The Name of the Profile - (1 - 50 characters)')}
                    placeholder={t('Enter name')}
                    value={draft.name}
                    onChange={(name) => setDraft((prev) => ({ ...prev, name }))}
                    error={nameError}
                  />
                  <Checkbox
                    id="isDefault"
                    title={t('Default Profile?')}
                    label={t(
                      'Is this the default profile for all Displays of this type? Only 1 profile can be the default.',
                    )}
                    checked={draft.isDefault === 1}
                    onChange={(e) =>
                      setDraft((prev) => ({ ...prev, isDefault: e.target.checked ? 1 : 0 }))
                    }
                  />
                </>
              )}

              {/* Type-specific fields */}
              {renderTypeFields()}
            </>
          )}
        </div>
      </div>
    </Modal>
  );
}
