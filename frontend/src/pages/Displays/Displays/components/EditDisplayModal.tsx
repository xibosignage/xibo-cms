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

import {
  FloatingPortal,
  autoUpdate,
  flip,
  offset,
  shift,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import type { TFunction } from 'i18next';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import BandwidthInput from '@/components/ui/forms/BandwidthInput';
import Checkbox from '@/components/ui/forms/Checkbox';
import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import MultiSelectDropdown from '@/components/ui/forms/MultiSelectDropdown';
import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import TimezoneSelect from '@/components/ui/forms/TimezoneSelect';
import Modal from '@/components/ui/modals/Modal';
import { useDebounce } from '@/hooks/useDebounce';
import { DynamicSettingField } from '@/pages/Displays/DisplayProfile/components/fields/DynamicSettingField';
import { PICTURE_PROPERTY_DEFS } from '@/pages/Displays/DisplayProfile/components/fields/LgSsspFields';
import type { FieldMeta } from '@/pages/Displays/DisplayProfile/components/fields/fieldMetadata';
import { getFieldMetaForType } from '@/pages/Displays/DisplayProfile/components/fields/fieldMetadata';
import { getEditDisplaySchema } from '@/schema/display';
import { fetchDaypart } from '@/services/daypartApi';
import { fetchDisplayProfileById, fetchDisplayProfile } from '@/services/displayProfileApi';
import { updateDisplay, fetchDisplayVenues, fetchDisplayLocales } from '@/services/displaysApi';
import type { DisplayVenue } from '@/services/displaysApi';
import { fetchLayouts } from '@/services/layoutsApi';
import { fetchPlayerSoftware } from '@/services/playerSoftwareApi';
import type { PlayerSoftware } from '@/services/playerSoftwareApi';
import type { Daypart } from '@/types/daypart';
import type { Display } from '@/types/display';
import type {
  DisplayProfile,
  DisplayProfileConfigItem,
  DisplayProfileConfigDefaultItem,
} from '@/types/displayProfile';
import type { Layout } from '@/types/layout';
import type { Tag } from '@/types/tag';

type ActiveTab =
  | 'general'
  | 'details'
  | 'reference'
  | 'maintenance'
  | 'wol'
  | 'settings'
  | 'remote'
  | 'advanced';

function tabClass(activeTab: ActiveTab, tab: ActiveTab): string {
  const isActive = activeTab === tab;
  return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
    isActive ? 'border-blue-600 text-blue-500' : 'border-gray-200 text-gray-500 hover:text-blue-600'
  }`;
}

function formatSettingName(name: string): string {
  return name
    .replace(/([A-Z])/g, ' $1')
    .replace(/^./, (s) => s.toUpperCase())
    .trim();
}

function getApiErrorMessage(err: unknown, fallback: string): string {
  const e = err as { response?: { data?: { message?: string } } };
  return e.response?.data?.message ?? (err instanceof Error ? err.message : fallback);
}

function configArrayToFlat(
  config: DisplayProfileConfigItem[] | undefined,
): Record<string, string | number | null> {
  if (!config) {
    return {};
  }
  return Object.fromEntries(config.map((item) => [item.name, item.value]));
}

function defaultsToFlat(
  configDefault: DisplayProfileConfigDefaultItem[] | undefined,
): Record<string, string | number | null> {
  if (!configDefault) {
    return {};
  }
  return Object.fromEntries(configDefault.map((item) => [item.name, item.default]));
}

function summarizeTimers(str: string, t: TFunction): string {
  try {
    const parsed = JSON.parse(str) as Record<string, { on?: string; off?: string }>;
    const days = Object.entries(parsed);
    if (days.length === 0) {
      return '—';
    }
    const abbrev: Record<string, string> = {
      monday: t('Mon'),
      tuesday: t('Tue'),
      wednesday: t('Wed'),
      thursday: t('Thu'),
      friday: t('Fri'),
      saturday: t('Sat'),
      sunday: t('Sun'),
    };
    if (days.length <= 3) {
      return days
        .map(([day, val]) => `${abbrev[day] ?? day} ${val.on ?? ''}–${val.off ?? ''}`)
        .join(', ');
    }
    return `${days.length} ${t('days')}`;
  } catch {
    return str ? t('Configured') : '—';
  }
}

function summarizePictureOptions(str: string, t: TFunction): string {
  try {
    const parsed = JSON.parse(str) as Record<string, unknown>;
    const entries = Object.entries(parsed);
    if (entries.length === 0) {
      return '—';
    }
    return entries
      .map(([key, val]) => `${PICTURE_PROPERTY_DEFS[key]?.name ?? key}: ${String(val)}`)
      .join(', ');
  } catch {
    return str ? t('Configured') : '—';
  }
}

function summarizeLockOptions(str: string, t: TFunction): string {
  try {
    const parsed = JSON.parse(str) as {
      usblock?: boolean | null;
      osdlock?: boolean | null;
      keylock?: { local?: string; remote?: string };
    };
    const parts: string[] = [];
    const boolStr = (v: boolean | null | undefined) => (v == null ? null : v ? t('On') : t('Off'));
    const keylockLabels: Record<string, string> = {
      allowall: t('Allow All'),
      blockall: t('Block All'),
      poweronly: t('Power Only'),
    };
    const usb = boolStr(parsed.usblock);
    if (usb != null) {
      parts.push(`${t('USB')}: ${usb}`);
    }
    const osd = boolStr(parsed.osdlock);
    if (osd != null) {
      parts.push(`${t('OSD')}: ${osd}`);
    }
    const local = parsed.keylock?.local;
    if (local) {
      parts.push(`${t('Local')}: ${keylockLabels[local] ?? local}`);
    }
    const remote = parsed.keylock?.remote;
    if (remote) {
      parts.push(`${t('Remote')}: ${keylockLabels[remote] ?? remote}`);
    }
    return parts.length > 0 ? parts.join(' · ') : '—';
  } catch {
    return str ? t('Configured') : '—';
  }
}

function resolveLabel(
  raw: string | number | null | undefined,
  meta: FieldMeta,
  playerVersions: PlayerSoftware[],
  dayparts: Daypart[],
  t: TFunction,
): string {
  if (raw === null || raw === undefined) {
    return '—';
  }
  const str = String(raw);
  if (meta.options) {
    return meta.options.find((o) => o.value === str)?.label ?? str;
  }
  if (meta.inputType === 'player-version') {
    return playerVersions.find((v) => String(v.versionId) === str)?.playerShowVersion ?? str;
  }
  if (meta.inputType === 'daypart') {
    return dayparts.find((d) => String(d.dayPartId) === str)?.name ?? str;
  }
  if (meta.inputType === 'timers') {
    return summarizeTimers(str, t);
  }
  if (meta.inputType === 'picture-options') {
    return summarizePictureOptions(str, t);
  }
  if (meta.inputType === 'lock-options') {
    return summarizeLockOptions(str, t);
  }
  if (meta.inputType === 'checkbox') {
    return str === '1' || str === 'true' ? t('True') : t('False');
  }
  return str;
}

interface OverrideCellProps {
  name: string;
  meta: FieldMeta;
  overrideVal: string | undefined;
  profileVal: string | number | null;
  dayparts: Daypart[];
  playerVersions: PlayerSoftware[];
  playerType: string | undefined;
  onCommit: (name: string, value: string) => void;
  onRemove: (name: string) => void;
}

function OverrideCell({
  name,
  meta,
  overrideVal,
  profileVal,
  dayparts,
  playerVersions,
  playerType,
  onCommit,
  onRemove,
}: OverrideCellProps) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const [editValue, setEditValue] = useState('');

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [offset(4), flip(), shift()],
  });

  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([dismiss]);

  const handleOpen = () => {
    const initial =
      overrideVal !== undefined
        ? overrideVal
        : profileVal !== null && profileVal !== undefined
          ? String(profileVal)
          : '';
    setEditValue(initial);
    setIsOpen(true);
  };

  const handleCommit = () => {
    onCommit(name, editValue);
    setIsOpen(false);
  };

  const displayText =
    overrideVal !== undefined
      ? resolveLabel(overrideVal, meta, playerVersions, dayparts, t)
      : undefined;

  return (
    <div className="flex items-center gap-2">
      <Button
        variant="link"
        ref={refs.setReference}
        {...getReferenceProps()}
        onClick={handleOpen}
        className={overrideVal !== undefined ? 'text-amber-600 hover:text-amber-700' : undefined}
      >
        {displayText ?? t('Override')}
      </Button>
      {overrideVal !== undefined && (
        <Button
          variant="link"
          onClick={() => onRemove(name)}
          title={t('Remove override')}
          className="text-red-400 hover:text-red-600"
        >
          ✕
        </Button>
      )}

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className={`z-9999 bg-white shadow-xl rounded-lg border border-gray-100 overflow-hidden flex flex-col min-w-72 ${['timers', 'picture-options'].includes(meta.inputType) ? 'min-w-120 max-w-160' : 'max-w-96'}`}
          >
            <span className="bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-500 uppercase">
              {t('Override')}: {meta.label}
            </span>
            <div className="p-4">
              <DynamicSettingField
                meta={meta}
                value={editValue}
                onChange={(val) => setEditValue(val !== null ? String(val) : '')}
                contextData={{ dayparts, playerVersions, playerType }}
              />
            </div>
            <div className="flex justify-end gap-2 px-4 py-3 border-t border-gray-100">
              <Button
                variant="secondary"
                className="px-3 py-1.5 text-xs min-w-0"
                onClick={() => setIsOpen(false)}
              >
                {t('Cancel')}
              </Button>
              <Button
                variant="primary"
                className="px-3 py-1.5 text-xs min-w-0"
                onClick={handleCommit}
              >
                {t('Apply')}
              </Button>
            </div>
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}

interface EditDraft {
  display: string;
  license: string;
  description: string;
  folderId: number | null;
  tags: Tag[];
  licensed: number;
  defaultLayoutId: number | null;
  latitude: number | null;
  longitude: number | null;
  timeZone: string;
  languages: string[];
  displayTypeId: number | null;
  venueId: number | null;
  address: string;
  screenSize: number | null;
  isMobile: number;
  isOutdoor: number;
  costPerPlay: number | null;
  impressionsPerPlay: number | null;
  ref1: string;
  ref2: string;
  ref3: string;
  ref4: string;
  ref5: string;
  customId: string;
  emailAlert: number;
  alertTimeout: number;
  wakeOnLanEnabled: number;
  broadCastAddress: string;
  secureOn: string;
  wakeOnLanTime: string;
  cidr: string;
  displayProfileId: number | null;
  teamViewerSerial: string;
  webkeySerial: string;
  incSchedule: number;
  auditingUntil: string;
  bandwidthLimit: number | null;
  clearCachedData: number;
  rekeyXmr: number;
}

interface EditDisplayModalProps {
  isOpen?: boolean;
  data: Display | null;
  onClose: () => void;
  onSave: (updated: Display) => void;
}

const LAYOUT_PAGE_SIZE = 10;

export default function EditDisplayModal({
  isOpen = true,
  data,
  onClose,
  onSave,
}: EditDisplayModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();

  const displayTypes: SelectOption[] = [
    { value: '', label: '' },
    { value: '1', label: t('Billboard') },
    { value: '2', label: t('Kiosk') },
    { value: '3', label: t('LED Matrix / LED Video Wall') },
    { value: '4', label: t('Monitor / Other') },
    { value: '5', label: t('Projector') },
    { value: '6', label: t('Shelf-edge Display') },
    { value: '7', label: t('Smart Mirror') },
    { value: '8', label: t('TV / Panel') },
    { value: '9', label: t('Tablet') },
    { value: '10', label: t('Totem') },
  ];
  const [activeTab, setActiveTab] = useState<ActiveTab>('general');
  const [apiError, setApiError] = useState<string | undefined>();
  const [nameError, setNameError] = useState<string | undefined>();

  const [draft, setDraft] = useState<EditDraft>({
    display: '',
    license: '',
    description: '',
    folderId: null,
    tags: [],
    licensed: 0,
    defaultLayoutId: null,
    latitude: null,
    longitude: null,
    timeZone: '',
    languages: [],
    displayTypeId: null,
    venueId: null,
    address: '',
    screenSize: null,
    isMobile: 0,
    isOutdoor: 0,
    costPerPlay: null,
    impressionsPerPlay: null,
    ref1: '',
    ref2: '',
    ref3: '',
    ref4: '',
    ref5: '',
    customId: '',
    emailAlert: 0,
    alertTimeout: 0,
    wakeOnLanEnabled: 0,
    broadCastAddress: '',
    secureOn: '',
    wakeOnLanTime: '',
    cidr: '',
    displayProfileId: null,
    teamViewerSerial: '',
    webkeySerial: '',
    incSchedule: 0,
    auditingUntil: '',
    bandwidthLimit: null,
    clearCachedData: 1,
    rekeyXmr: 0,
  });

  const [profiles, setProfiles] = useState<DisplayProfile[]>([]);
  const [activeProfile, setActiveProfile] = useState<DisplayProfile | null>(null);
  const [dayparts, setDayparts] = useState<Daypart[]>([]);
  const [playerVersions, setPlayerVersions] = useState<PlayerSoftware[]>([]);
  const [profileFlat, setProfileFlat] = useState<Record<string, string | number | null>>({});
  const [profileDefaults, setProfileDefaults] = useState<Record<string, string | number | null>>(
    {},
  );
  const [isLoadingProfile, setIsLoadingProfile] = useState(false);
  const [overrides, setOverrides] = useState<Record<string, string>>({});

  const [layouts, setLayouts] = useState<Layout[]>([]);
  const [layoutPage, setLayoutPage] = useState(0);
  const [hasMoreLayouts, setHasMoreLayouts] = useState(false);
  const [isLoadingLayouts, setIsLoadingLayouts] = useState(false);
  const [isLoadingMoreLayouts, setIsLoadingMoreLayouts] = useState(false);
  const [layoutSearch, setLayoutSearch] = useState('');
  const debouncedLayoutSearch = useDebounce(layoutSearch, 300);

  const [venues, setVenues] = useState<DisplayVenue[]>([]);
  const [localeLanguages, setLocaleLanguages] = useState<{ value: string; label: string }[]>([]);

  useEffect(() => {
    fetchDisplayLocales()
      .then((langs) => setLocaleLanguages(langs.map((l) => ({ value: l.id, label: l.value }))))
      .catch(() => setLocaleLanguages([]));
  }, []);

  useEffect(() => {
    if (!isOpen || !data) {
      return;
    }

    setActiveTab('general');
    setApiError(undefined);
    setNameError(undefined);
    setActiveProfile(null);
    setProfileFlat({});
    setProfileDefaults({});
    setDayparts([]);
    setPlayerVersions([]);

    setDraft({
      display: data.display ?? '',
      license: data.license ?? '',
      description: data.description ?? '',
      folderId: data.folderId ?? null,
      tags: data.tags ?? [],
      licensed: data.licensed ?? 0,
      defaultLayoutId: data.defaultLayoutId ?? null,
      latitude: data.latitude ?? null,
      longitude: data.longitude ?? null,
      timeZone: data.timeZone ?? '',
      languages: data.languages
        ? data.languages
            .split(',')
            .map((l) => l.trim())
            .filter(Boolean)
        : [],
      displayTypeId: data.displayTypeId ?? null,
      venueId: data.venueId ?? null,
      address: data.address ?? '',
      screenSize: data.screenSize ?? null,
      isMobile: data.isMobile ?? 0,
      isOutdoor: data.isOutdoor ?? 0,
      costPerPlay: data.costPerPlay ?? null,
      impressionsPerPlay: data.impressionsPerPlay ?? null,
      ref1: data.ref1 ?? '',
      ref2: data.ref2 ?? '',
      ref3: data.ref3 ?? '',
      ref4: data.ref4 ?? '',
      ref5: data.ref5 ?? '',
      customId: data.customId ?? '',
      emailAlert: data.emailAlert ?? 0,
      alertTimeout: data.alertTimeout ?? 0,
      wakeOnLanEnabled: data.wakeOnLanEnabled ?? 0,
      broadCastAddress: data.broadCastAddress ?? '',
      secureOn: data.secureOn ?? '',
      wakeOnLanTime: data.wakeOnLanTime ?? '',
      cidr: data.cidr ?? '',
      displayProfileId: data.displayProfileId ?? null,
      teamViewerSerial: data.teamViewerSerial ?? '',
      webkeySerial: data.webkeySerial ?? '',
      incSchedule: data.incSchedule ?? 0,
      auditingUntil: (() => {
        const val = data.auditingUntil as number | string | null | undefined;
        if (!val) {
          return '';
        }
        if (typeof val === 'number') {
          return val > 0 ? new Date(val * 1000).toISOString() : '';
        }
        return new Date(val).toISOString();
      })(),
      bandwidthLimit: data.bandwidthLimit ?? null,
      clearCachedData: 1,
      rekeyXmr: 0,
    });

    const initOverrides: Record<string, string> = {};
    if (Array.isArray(data.overrideConfig)) {
      data.overrideConfig.forEach((item) => {
        const rec = item as Record<string, unknown>;
        const name = rec['name'] as string | undefined;
        const value = rec['value'];
        if (name && value !== undefined && value !== null && value !== '') {
          const str = String(value);
          try {
            const parsed: unknown = JSON.parse(str);
            if (
              typeof parsed === 'object' &&
              parsed !== null &&
              !Array.isArray(parsed) &&
              Object.keys(parsed).length === 0
            ) {
              return;
            }
          } catch {
            // Not JSON
          }
          initOverrides[name] = str;
        }
      });
    }
    setOverrides(initOverrides);

    fetchDisplayProfile({
      start: 0,
      length: 200,
      ...(data.clientType ? { type: data.clientType as never } : {}),
    })
      .then((res) => setProfiles(res.rows))
      .catch(() => setProfiles([]));

    fetchDaypart({ start: 0, length: 100, isAlways: 0, isCustom: 0 })
      .then((res) => setDayparts(res.rows))
      .catch(() => setDayparts([]));

    const playerVersionType =
      data.clientType === 'chromeOS'
        ? 'chromeOS'
        : data.clientType === 'android' || data.clientType === 'lg' || data.clientType === 'sssp'
          ? data.clientType
          : null;
    if (playerVersionType) {
      fetchPlayerSoftware({ playerType: playerVersionType, start: 0, length: 100 })
        .then((res) => setPlayerVersions(res.rows))
        .catch(() => setPlayerVersions([]));
    }

    fetchDisplayVenues()
      .then((v) => setVenues(v))
      .catch(() => setVenues([]));

    setLayoutSearch('');
  }, [isOpen, data]);

  useEffect(() => {
    if (!isOpen || !data) {
      return;
    }
    setIsLoadingLayouts(true);
    setLayouts([]);
    setLayoutPage(0);
    fetchLayouts({
      start: 0,
      length: LAYOUT_PAGE_SIZE,
      retired: 0,
      layout: debouncedLayoutSearch || undefined,
    })
      .then((res) => {
        setLayouts(res.rows);
        setHasMoreLayouts(res.rows.length === LAYOUT_PAGE_SIZE);
      })
      .catch(() => {
        setLayouts([]);
        setHasMoreLayouts(false);
      })
      .finally(() => setIsLoadingLayouts(false));
  }, [isOpen, data, debouncedLayoutSearch]);

  useEffect(() => {
    setIsLoadingProfile(true);
    const clientType = data?.clientType as string | null | undefined;

    const load: Promise<DisplayProfile | null> = draft.displayProfileId
      ? fetchDisplayProfileById(draft.displayProfileId)
      : clientType
        ? fetchDisplayProfile({
            start: 0,
            length: 200,
            type: clientType as never,
            embed: 'config,commands,configWithDefault',
          }).then((res) => res.rows.find((p) => p.isDefault === 1) ?? res.rows[0] ?? null)
        : Promise.resolve(null);

    load
      .then((profile) => {
        setActiveProfile(profile);
        setProfileFlat(profile ? configArrayToFlat(profile.config) : {});
        setProfileDefaults(profile ? defaultsToFlat(profile.configDefault) : {});
      })
      .catch(() => {
        setActiveProfile(null);
        setProfileFlat({});
        setProfileDefaults({});
      })
      .finally(() => setIsLoadingProfile(false));
  }, [draft.displayProfileId, data?.clientType]);

  const handleLoadMoreLayouts = () => {
    if (isLoadingMoreLayouts || !hasMoreLayouts) {
      return;
    }
    const nextPage = layoutPage + 1;
    setIsLoadingMoreLayouts(true);
    fetchLayouts({
      start: nextPage * LAYOUT_PAGE_SIZE,
      length: LAYOUT_PAGE_SIZE,
      retired: 0,
      layout: debouncedLayoutSearch || undefined,
    })
      .then((res) => {
        setLayouts((prev) => [...prev, ...res.rows]);
        setLayoutPage(nextPage);
        setHasMoreLayouts(res.rows.length === LAYOUT_PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreLayouts(false));
  };

  const handleSave = () => {
    if (!data) {
      return;
    }

    startTransition(async () => {
      const schema = getEditDisplaySchema(t);
      const result = schema.safeParse({
        display: draft.display,
        description: draft.description || undefined,
        latitude: draft.latitude ?? undefined,
        longitude: draft.longitude ?? undefined,
        bandwidthLimit: draft.bandwidthLimit ?? undefined,
        costPerPlay: draft.costPerPlay ?? undefined,
        impressionsPerPlay: draft.impressionsPerPlay ?? undefined,
      });

      if (!result.success) {
        setApiError(undefined);
        setNameError(result.error.flatten().fieldErrors.display?.[0]);
        return;
      }

      setNameError(undefined);

      const tagString =
        draft.tags.length > 0
          ? draft.tags.map((tg) => (tg.value ? `${tg.tag}|${tg.value}` : tg.tag)).join(',')
          : undefined;

      try {
        const updated = await updateDisplay(data.displayId, {
          display: draft.display,
          license: draft.license,
          folderId: draft.folderId,
          description: draft.description || undefined,
          licensed: draft.licensed,
          incSchedule: draft.incSchedule,
          emailAlert: draft.emailAlert,
          alertTimeout: draft.alertTimeout,
          latitude: draft.latitude,
          longitude: draft.longitude,
          timeZone: draft.timeZone || undefined,
          languages: draft.languages.length > 0 ? draft.languages : undefined,
          displayTypeId: draft.displayTypeId,
          venueId: draft.venueId,
          address: draft.address || undefined,
          screenSize: draft.screenSize,
          isMobile: draft.isMobile,
          isOutdoor: draft.isOutdoor,
          bandwidthLimit: draft.bandwidthLimit,
          costPerPlay: draft.costPerPlay,
          impressionsPerPlay: draft.impressionsPerPlay,
          tags: tagString,
          ref1: draft.ref1 || undefined,
          ref2: draft.ref2 || undefined,
          ref3: draft.ref3 || undefined,
          ref4: draft.ref4 || undefined,
          ref5: draft.ref5 || undefined,
          customId: draft.customId || undefined,
          displayProfileId: draft.displayProfileId,
          defaultLayoutId: draft.defaultLayoutId,
          wakeOnLanEnabled: draft.wakeOnLanEnabled,
          broadCastAddress: draft.broadCastAddress || undefined,
          secureOn: draft.secureOn || undefined,
          wakeOnLanTime: draft.wakeOnLanTime || undefined,
          cidr: draft.cidr || undefined,
          teamViewerSerial: draft.teamViewerSerial || undefined,
          webkeySerial: draft.webkeySerial || undefined,
          auditingUntil: (() => {
            if (!draft.auditingUntil) {
              return undefined;
            }
            const d = new Date(draft.auditingUntil);
            const p = (n: number) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
          })(),
          clearCachedData: draft.clearCachedData,
          rekeyXmr: draft.rekeyXmr,
          overrideValues: Object.keys(overrides).length > 0 ? overrides : undefined,
        });
        onSave({ ...data, ...updated });
        onClose();
      } catch (err: unknown) {
        setApiError(getApiErrorMessage(err, t('An unexpected error occurred while saving.')));
      }
    });
  };

  const set = <K extends keyof EditDraft>(key: K, value: EditDraft[K]) =>
    setDraft((prev) => ({ ...prev, [key]: value }));

  const tab = (name: ActiveTab) => tabClass(activeTab, name);
  const title = data ? `${t('Edit')} "${data.display}"` : t('Edit Display');

  const profileSettingNames = activeProfile?.configDefault?.map((d) => d.name) ?? [];
  const getProfileValue = (name: string): string | number | null =>
    profileFlat[name] !== undefined ? profileFlat[name] : (profileDefaults[name] ?? null);

  const fieldMeta = getFieldMetaForType(data?.clientType, t);

  const commitOverride = (name: string, value: string) => {
    if (value.trim() === '') {
      setOverrides((prev) => {
        const next = { ...prev };
        delete next[name];
        return next;
      });
    } else {
      setOverrides((prev) => ({ ...prev, [name]: value }));
    }
  };

  const removeOverride = (name: string) => {
    setOverrides((prev) => {
      const next = { ...prev };
      delete next[name];
      return next;
    });
  };

  const layoutOptions: SelectOption[] = [
    ...(layoutSearch ? [] : [{ value: '', label: t('Global default') }]),
    ...layouts.map((l) => ({ value: String(l.layoutId), label: l.layout })),
  ];

  if (
    !layoutSearch &&
    draft.defaultLayoutId &&
    !layouts.some((l) => l.layoutId === draft.defaultLayoutId)
  ) {
    layoutOptions.push({
      value: String(draft.defaultLayoutId),
      label: data?.defaultLayout ?? String(draft.defaultLayoutId),
    });
  }

  const venueOptions: SelectOption[] = [
    { value: '', label: '' },
    ...venues.map((v) => ({ value: String(v.venueId), label: v.venueName })),
  ];

  return (
    <Modal
      title={title}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable={false}
      error={apiError}
      size="lg"
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible px-4">
        <nav
          className="flex px-4 overflow-x-auto shrink-0 border-b border-gray-200"
          aria-label="Tabs"
        >
          <button type="button" className={tab('general')} onClick={() => setActiveTab('general')}>
            {t('General')}
          </button>
          <button type="button" className={tab('details')} onClick={() => setActiveTab('details')}>
            {t('Details')}
          </button>
          <button
            type="button"
            className={tab('reference')}
            onClick={() => setActiveTab('reference')}
          >
            {t('Reference')}
          </button>
          <button
            type="button"
            className={tab('maintenance')}
            onClick={() => setActiveTab('maintenance')}
          >
            {t('Maintenance')}
          </button>
          <button type="button" className={tab('wol')} onClick={() => setActiveTab('wol')}>
            {t('Wake on LAN')}
          </button>
          <button
            type="button"
            className={tab('settings')}
            onClick={() => setActiveTab('settings')}
          >
            {t('Settings')}
          </button>
          <button type="button" className={tab('remote')} onClick={() => setActiveTab('remote')}>
            {t('Remote')}
          </button>
          <button
            type="button"
            className={tab('advanced')}
            onClick={() => setActiveTab('advanced')}
          >
            {t('Advanced')}
          </button>
        </nav>

        <div className="flex-1 overflow-y-auto py-4 px-8 space-y-4">
          {activeTab === 'general' && (
            <>
              <div className="relative z-20">
                <SelectFolder
                  selectedId={draft.folderId}
                  onSelect={(folder) => set('folderId', folder ? Number(folder.id) : null)}
                />
              </div>
              <TextInput
                name="display"
                label={t('Display')}
                helpText={t('The Name of the Display - (1 - 50 characters).')}
                placeholder={t('Enter name')}
                value={draft.display}
                onChange={(v) => set('display', v)}
                error={nameError}
              />
              <TextInput
                name="license"
                label={t("Display's Hardware Key")}
                helpText={t('A unique identifier for this display.')}
                placeholder=" "
                value={draft.license}
                onChange={(v) => set('license', v)}
              />
              <TextInput
                name="description"
                label={t('Description')}
                helpText={t('A description - (1 - 254 characters).')}
                placeholder=" "
                value={draft.description}
                onChange={(v) => set('description', v)}
              />
              <TagInput
                label={t('Tags')}
                helpText={t(
                  'Tags for this Display - Comma separated string of Tags or Tag|Value format. If you choose a Tag that has associated values, they will be shown for selection below.',
                )}
                placeholder=" "
                value={draft.tags}
                onChange={(tags) => set('tags', tags)}
              />
              <SelectDropdown
                label={t('Authorise display?')}
                helpText={t('Use one of the available slots for this display?')}
                value={String(draft.licensed)}
                options={[
                  { value: '0', label: t('No') },
                  { value: '1', label: t('Yes') },
                ]}
                onSelect={(v) => set('licensed', Number(v))}
              />
              <SelectDropdown
                label={t('Default Layout')}
                helpText={t(
                  'Set the Default Layout to use when no other content is scheduled to this Display. This will override the global Default Layout as set in CMS Administrator Settings. If left blank a global Default Layout will be automatically set for this Display.',
                )}
                value={draft.defaultLayoutId ? String(draft.defaultLayoutId) : ''}
                placeholder={t('Global default')}
                options={layoutOptions}
                onSelect={(v) => set('defaultLayoutId', v ? Number(v) : null)}
                isLoading={isLoadingLayouts}
                onLoadMore={handleLoadMoreLayouts}
                hasMore={hasMoreLayouts}
                isLoadingMore={isLoadingMoreLayouts}
                searchable
                searchPlaceholder={t('Search layouts...')}
                onSearch={(v) => setLayoutSearch(v)}
              />
            </>
          )}

          {activeTab === 'details' && (
            <>
              <NumberInput
                name="latitude"
                label={t('Latitude')}
                helpText={t('The Latitude of this display')}
                placeholder=" "
                value={draft.latitude ?? undefined}
                onChange={(v) => set('latitude', v || null)}
              />
              <NumberInput
                name="longitude"
                label={t('Longitude')}
                helpText={t('The Longitude of this Display')}
                placeholder=" "
                value={draft.longitude ?? undefined}
                onChange={(v) => set('longitude', v || null)}
              />
              <TimezoneSelect
                value={draft.timeZone}
                onChange={(v) => set('timeZone', v)}
                helpText={t('The timezone for this display, or empty to use the CMS timezone')}
              />

              <p className="text-gray-500 mt-2">
                {t(
                  'Configure further details for integration with 3rd parties such as DOOH providers:',
                )}
              </p>

              <MultiSelectDropdown
                label={t('Languages')}
                helpText={t(
                  'The languages that the audience viewing this Display are likely to understand.',
                )}
                value={draft.languages}
                options={localeLanguages}
                onChange={(langs) => set('languages', langs)}
                placeholder=" "
                searchPlaceholder={t('Search languages…')}
                selectAllOption
                selectAllText={t('Select all')}
              />
              <SelectDropdown
                label={t('Display Type')}
                helpText={t('The Type of this Display')}
                placeholder=" "
                value={draft.displayTypeId ? String(draft.displayTypeId) : ''}
                options={displayTypes}
                onSelect={(v) => set('displayTypeId', v ? Number(v) : null)}
              />
              <SelectDropdown
                label={t('Venue')}
                helpText={t('The Location/Venue of this display')}
                value={draft.venueId ? String(draft.venueId) : ''}
                placeholder=" "
                options={venueOptions}
                onSelect={(v) => set('venueId', v ? Number(v) : null)}
                searchable
                searchPlaceholder={t('Search venues…')}
              />
              <TextInput
                name="address"
                label={t('Address')}
                helpText={t('The Address of this Display')}
                placeholder=" "
                value={draft.address}
                onChange={(v) => set('address', v)}
              />
              <NumberInput
                name="screenSize"
                label={t('Screen size')}
                helpText={t('The Screen size of this Display')}
                placeholder=" "
                value={draft.screenSize ?? undefined}
                onChange={(v) => set('screenSize', v || null)}
              />
              <Checkbox
                id="isMobile"
                title={t('Is mobile?')}
                label={t('Is this display mobile?')}
                checked={draft.isMobile === 1}
                onChange={(e) => set('isMobile', e.target.checked ? 1 : 0)}
              />
              <Checkbox
                id="isOutdoor"
                title={t('Is outdoor?')}
                label={t('Is your display located outdoors?')}
                checked={draft.isOutdoor === 1}
                onChange={(e) => set('isOutdoor', e.target.checked ? 1 : 0)}
              />
              <NumberInput
                name="costPerPlay"
                label={t('Cost per play')}
                helpText={t('The cost per play')}
                placeholder=" "
                value={draft.costPerPlay ?? undefined}
                onChange={(v) => set('costPerPlay', v || null)}
              />
              <NumberInput
                name="impressionsPerPlay"
                label={t('Impressions per play')}
                helpText={t('The impressions per play')}
                placeholder=" "
                value={draft.impressionsPerPlay ?? undefined}
                onChange={(v) => set('impressionsPerPlay', v || null)}
              />
            </>
          )}

          {activeTab === 'reference' && (
            <>
              <p className="text-sm text-gray-500">{t('Add reference fields if needed')}</p>
              {(['ref1', 'ref2', 'ref3', 'ref4', 'ref5'] as const).map((field, i) => (
                <TextInput
                  key={field}
                  name={field}
                  label={t('Reference {{n}}', { n: i + 1 })}
                  placeholder=" "
                  value={draft[field]}
                  onChange={(v) => set(field, v)}
                />
              ))}
              <TextInput
                name="customId"
                label={t('Custom ID')}
                placeholder=" "
                value={draft.customId}
                onChange={(v) => set('customId', v)}
              />
            </>
          )}

          {activeTab === 'maintenance' && (
            <>
              <SelectDropdown
                label={t('Email Alerts')}
                helpText={t(
                  'Do you want to be notified by email if there is a problem with this display?',
                )}
                value={String(draft.emailAlert)}
                options={[
                  { value: '0', label: t('No') },
                  { value: '1', label: t('Yes') },
                ]}
                onSelect={(v) => set('emailAlert', Number(v))}
              />
              <Checkbox
                id="alertTimeout"
                title={t('Use the Global Timeout?')}
                label={t(
                  'Should this display be tested against the global time out or the Player collection interval?',
                )}
                checked={draft.alertTimeout === 1}
                onChange={(e) => set('alertTimeout', e.target.checked ? 1 : 0)}
              />
            </>
          )}

          {activeTab === 'wol' && (
            <>
              <Checkbox
                id="wakeOnLanEnabled"
                title={t('Enable Wake on LAN')}
                label={t(
                  'Wake on Lan requires the correct network configuration to route the magic packet to the display PC',
                )}
                checked={draft.wakeOnLanEnabled === 1}
                onChange={(e) => set('wakeOnLanEnabled', e.target.checked ? 1 : 0)}
              />
              <TextInput
                name="broadCastAddress"
                label={t('Broadcast Address')}
                helpText={t("The IP address of the remote host's broadcast address (or gateway)")}
                placeholder=" "
                value={draft.broadCastAddress}
                onChange={(v) => set('broadCastAddress', v)}
              />
              <TextInput
                name="secureOn"
                label={t('Wake on LAN SecureOn')}
                helpText={t(
                  "Enter a hexadecimal password of a SecureOn enabled NIC. Pattern: 'xx-xx-xx-xx-xx-xx'. Leave empty if SecureOn is not used.",
                )}
                placeholder=" "
                value={draft.secureOn}
                onChange={(v) => set('secureOn', v)}
              />
              <TextInput
                name="wakeOnLanTime"
                label={t('Wake on LAN Time')}
                helpText={t(
                  'The time this display should receive the WOL command, using 24hr clock - e.g. 19:00. Maintenance must be enabled.',
                )}
                placeholder="HH:MM"
                value={draft.wakeOnLanTime}
                onChange={(v) => set('wakeOnLanTime', v)}
              />
              <TextInput
                name="cidr"
                label={t('Wake on LAN CIDR')}
                helpText={t(
                  'Enter a number within 0–32. Leave empty if no subnet mask should be used (CIDR = 0).',
                )}
                placeholder=" "
                value={draft.cidr}
                onChange={(v) => set('cidr', v)}
              />
            </>
          )}

          {activeTab === 'settings' && (
            <>
              <SelectDropdown
                label={t('Settings Profile')}
                helpText={t(
                  'What display profile should this display use? To use the default profile leave this empty.',
                )}
                value={draft.displayProfileId ? String(draft.displayProfileId) : ''}
                placeholder={t('None (use default)')}
                clearable
                options={profiles.map((p) => ({
                  value: String(p.displayProfileId),
                  label: p.name,
                }))}
                onSelect={(v) => set('displayProfileId', v ? Number(v) : null)}
              />

              <p className="text-sm text-gray-500 mt-2">
                {t(
                  'The settings for this display are shown below. They are taken from the active Display Profile for this Display, which can be changed in Display Settings. If you have altered the Settings Profile above, you will need to save and re-show the form.',
                )}
                {activeProfile && (
                  <span className="ml-1 font-medium text-gray-700">
                    ({!draft.displayProfileId && `${t('Default')}: `}
                    {activeProfile.name})
                  </span>
                )}
              </p>

              {isLoadingProfile ? (
                <p className="text-sm text-gray-400">{t('Loading profile settings…')}</p>
              ) : activeProfile && profileSettingNames.length > 0 ? (
                <div className="overflow-x-auto rounded border border-gray-200">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 border-b border-gray-200">
                      <tr>
                        <th className="text-left px-3 py-2 font-semibold text-gray-600 w-1/3">
                          {t('Setting')}
                        </th>
                        <th className="text-left px-3 py-2 font-semibold text-gray-600 w-1/3">
                          {t('Profile')}
                        </th>
                        <th className="text-left px-3 py-2 font-semibold text-gray-600 w-1/3">
                          {t('Override')}
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {profileSettingNames.map((name) => {
                        const profileVal = getProfileValue(name);
                        const overrideVal = overrides[name];

                        const meta: FieldMeta = fieldMeta[name] ?? {
                          label: formatSettingName(name),
                          tab: 'general',
                          inputType: typeof profileVal === 'number' ? 'number' : 'text',
                        };

                        const profileDisplay = resolveLabel(
                          profileVal,
                          meta,
                          playerVersions,
                          dayparts,
                          t,
                        );

                        return (
                          <tr
                            key={name}
                            className="border-b border-gray-100 last:border-0 hover:bg-gray-50"
                          >
                            <td className="px-3 py-2 font-medium text-gray-700">
                              {meta.label ?? formatSettingName(name)}
                            </td>
                            <td className="px-3 py-2 text-gray-500">{profileDisplay}</td>
                            <td className="px-3 py-2">
                              <OverrideCell
                                name={name}
                                meta={meta}
                                overrideVal={overrideVal}
                                profileVal={profileVal}
                                dayparts={dayparts}
                                playerVersions={playerVersions}
                                playerType={data?.clientType ?? undefined}
                                onCommit={commitOverride}
                                onRemove={removeOverride}
                              />
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              ) : (
                !isLoadingProfile && (
                  <p className="text-sm text-gray-400">
                    {t('No settings available for this profile.')}
                  </p>
                )
              )}
            </>
          )}

          {activeTab === 'remote' && (
            <>
              <TextInput
                name="teamViewerSerial"
                label={t('TeamViewer Serial')}
                helpText={t(
                  'If TeamViewer is installed on the device, enter the serial number here.',
                )}
                placeholder=" "
                value={draft.teamViewerSerial}
                onChange={(v) => set('teamViewerSerial', v)}
              />
              <TextInput
                name="webkeySerial"
                label={t('Webkey Serial')}
                helpText={t('If Webkey is installed on the device, enter the serial number here.')}
                placeholder=" "
                value={draft.webkeySerial}
                onChange={(v) => set('webkeySerial', v)}
              />
            </>
          )}

          {activeTab === 'advanced' && (
            <>
              <SelectDropdown
                label={t('Interleave Default')}
                helpText={t('Whether to always put the default layout into the cycle.')}
                value={String(draft.incSchedule)}
                options={[
                  { value: '0', label: t('No') },
                  { value: '1', label: t('Yes') },
                ]}
                onSelect={(v) => set('incSchedule', Number(v))}
              />
              <DatePickerInput
                label={t('Auditing until')}
                helpText={t(
                  'Collect auditing from this Player. Should only be used if there is a problem with the display.',
                )}
                value={draft.auditingUntil}
                onChange={(iso) => set('auditingUntil', iso)}
              />
              <BandwidthInput
                valueKb={draft.bandwidthLimit}
                onChange={(kb) => set('bandwidthLimit', kb)}
                helpText={t('The bandwidth limit that should be applied. Enter 0 for no limit.')}
              />
              <Checkbox
                id="clearCachedData"
                title={t('Clear Cached Data')}
                label={t('Remove any cached data for this display.')}
                checked={draft.clearCachedData === 1}
                onChange={(e) => set('clearCachedData', e.target.checked ? 1 : 0)}
              />
              <Checkbox
                id="rekeyXmr"
                title={t('Reconfigure XMR')}
                label={t('Remove the XMR configuration for this Player and send a rekey action.')}
                checked={draft.rekeyXmr === 1}
                onChange={(e) => set('rekeyXmr', e.target.checked ? 1 : 0)}
              />
            </>
          )}
        </div>
      </div>
    </Modal>
  );
}
