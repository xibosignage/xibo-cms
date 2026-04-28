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

import { ArrowLeft, ArrowRight, CalendarClock, Minus, Plus, Tablet } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '../Button';
import GeoScheduleMap from '../GeoScheduleMap';
import InfoBanner from '../InfoBanner';
import { notify } from '../Notification';
import Stepper from '../Stepper';
import Checkbox from '../forms/Checkbox';
import DatePickerInput from '../forms/DatePickerInput';
import NumberInput from '../forms/NumberInput';
import SelectDropdown from '../forms/SelectDropdown';
import TextInput from '../forms/TextInput';

import Modal, { type ModalAction } from './Modal';

import { useUserContext } from '@/context/UserContext';
import { DisplayGroupMultiSelect } from '@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect';
import {
  type DraftCriterion,
  type DraftReminder,
  type ScheduleEventDraft,
  type OptionalTab,
  type ScheduleFormErrors,
  type SelectOption,
  EVENT_TYPE_OPTIONS,
  CONDITION_OPTIONS,
  CRITERIA_TYPE_OPTIONS,
  RECURRENCE_TYPE_OPTIONS,
  REMINDER_TYPE_OPTIONS,
  REMINDER_OPTION_OPTIONS,
  WEEKDAYS,
  EMPTY_CRITERION,
  EMPTY_REMINDER,
  createInitialDraft,
  createDraftFromEvent,
  getContentFieldConfig,
  getContentHelpText,
  getContentValue,
  getPrefilledOption,
  getStepLabels,
  buildSteps,
} from '@/pages/Schedule/utils/scheduleEventDraft';
import { getScheduleEventSchema } from '@/schema/scheduleEvent';
import { fetchCampaigns } from '@/services/campaignApi';
import { fetchCommands } from '@/services/commandApi';
import { fetchDataset } from '@/services/datasetApi';
import { fetchDaypart } from '@/services/daypartApi';
import { createEvent, fetchEventById, updateEvent } from '@/services/eventApi';
import { fetchLayouts, fetchLayoutCodes } from '@/services/layoutsApi';
import { fetchMedia } from '@/services/mediaApi';
import { fetchPlaylist } from '@/services/playlistApi';
import { fetchResolution } from '@/services/resolutionApi';
import { fetchSyncGroups } from '@/services/syncGroupApi';
import { EventTypeId, type Event } from '@/types/event';
import { hasFeature } from '@/utils/permissions';

const DROPDOWN_PAGE_SIZE = 10;

type ScheduleModalMode = 'add' | 'schedule' | 'edit';

interface ScheduleEventModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSaved?: () => void;
  mode?: ScheduleModalMode;
  eventTypeId?: EventTypeId;
  contentId?: number;
  contentName?: string;
  event?: Event;
}

export default function ScheduleEventModal({
  isOpen,
  onClose,
  onSaved,
  mode = 'add',
  eventTypeId: prefilledEventTypeId,
  contentId,
  contentName,
  event,
}: ScheduleEventModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const timezone = user?.settings?.defaultTimezone ?? 'UTC';

  const canGeoSchedule = hasFeature(user, 'schedule.geoLocation');
  const canSetReminders = hasFeature(user, 'schedule.reminders');
  const canSetCriteria = hasFeature(user, 'schedule.criteria');

  const isEditMode = mode === 'edit' && !!event;
  const initialIsSyncType =
    (prefilledEventTypeId ?? (isEditMode ? event?.eventTypeId : null)) === EventTypeId.Sync;
  const initialStepLabels = getStepLabels(initialIsSyncType ? EventTypeId.Sync : null);
  const initialStep = isEditMode ? 0 : contentId ? 1 : 0;
  const initialMaxStep = isEditMode ? initialStepLabels.length - 1 : initialStep;
  const [currentStep, setCurrentStep] = useState(initialStep);
  const [maxReachedStep, setMaxReachedStep] = useState(initialMaxStep);

  const goToStep = (step: number) => {
    setCurrentStep(step);
    setMaxReachedStep((prev) => Math.max(prev, step));
  };
  const [draft, setDraft] = useState<ScheduleEventDraft>(() =>
    isEditMode ? createDraftFromEvent(event) : createInitialDraft(prefilledEventTypeId, contentId),
  );
  const [optionalTab, setOptionalTab] = useState<OptionalTab>('general');

  const [contentOptions, setContentOptions] = useState<SelectOption[]>([]);
  const [isLoadingContent, setIsLoadingContent] = useState(false);
  const [showDisplayBanner, setShowDisplayBanner] = useState(false);
  const [daypartOptions, setDaypartOptions] = useState<SelectOption[]>([]);
  const [resolutionOptions, setResolutionOptions] = useState<SelectOption[]>([]);
  const [layoutCodeOptions, setLayoutCodeOptions] = useState<SelectOption[]>([]);
  const [commandOptions, setCommandOptions] = useState<SelectOption[]>([]);

  const [pagination, setPagination] = useState({
    content: { totalCount: 0, isLoading: false, isLoadingMore: false },
    daypart: { totalCount: 0, isLoading: false, isLoadingMore: false },
    command: { totalCount: 0, isLoading: false, isLoadingMore: false },
    layoutCode: { totalCount: 0, isLoading: false, isLoadingMore: false },
  });

  const [alwaysDayPartId, setAlwaysDayPartId] = useState<string>('');
  const [customDayPartId, setCustomDayPartId] = useState<string>('');

  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [formErrors, setFormErrors] = useState<ScheduleFormErrors>({});

  const isMediaType = draft.eventTypeId === EventTypeId.Media;
  const isPlaylistType = draft.eventTypeId === EventTypeId.Playlist;
  const isCommandType = draft.eventTypeId === EventTypeId.Command;
  const isActionType = draft.eventTypeId === EventTypeId.Action;
  const isInterruptType = draft.eventTypeId === EventTypeId.Interrupt;
  const isSyncType = draft.eventTypeId === EventTypeId.Sync;
  const isDataConnectorType = draft.eventTypeId === EventTypeId.DataConnector;
  const stepLabels = getStepLabels(draft.eventTypeId);
  const steps = buildSteps(currentStep, maxReachedStep, t, stepLabels);
  const isFirstStep = currentStep === 0;
  const isLastStep = currentStep === stepLabels.length - 1;

  // Step index mapping: Sync skips Displays step
  const displayStepIndex = isSyncType ? -1 : 1;
  const timeStepIndex = isSyncType ? 1 : 2;
  const optionalStepIndex = isSyncType ? 2 : 3;

  const hasDisplays = draft.displaySpecificGroupIds.length > 0 || draft.displayGroupIds.length > 0;
  const hasContent = (() => {
    if (!draft.eventTypeId) return false;
    if (
      [
        EventTypeId.Layout,
        EventTypeId.Overlay,
        EventTypeId.Interrupt,
        EventTypeId.Campaign,
      ].includes(draft.eventTypeId)
    )
      return !!draft.campaignId;
    if (draft.eventTypeId === EventTypeId.Command) return !!draft.commandId;
    if (draft.eventTypeId === EventTypeId.Media) return !!(draft.mediaId || draft.campaignId);
    if (draft.eventTypeId === EventTypeId.Playlist) return !!(draft.playlistId || draft.campaignId);
    if (draft.eventTypeId === EventTypeId.Sync) return !!draft.syncGroupId;
    if (draft.eventTypeId === EventTypeId.DataConnector) return !!draft.dataSetId;
    if (draft.eventTypeId === EventTypeId.Action) {
      if (!draft.actionType || !draft.actionTriggerCode) return false;
      if (draft.actionType === 'navLayout') return !!draft.actionLayoutCode;
      if (draft.actionType === 'command') return !!draft.commandId;
      return false;
    }
    return true;
  })();
  const canFinish = (currentStep >= 1 || isEditMode) && (hasDisplays || isSyncType);

  const isCommandEvent = draft.eventTypeId === EventTypeId.Command;
  const isAlwaysDaypart = draft.dayPartId === alwaysDayPartId;
  const isCustomDaypart = draft.dayPartId === customDayPartId;
  const isNamedDaypart = draft.dayPartId !== '' && !isAlwaysDaypart && !isCustomDaypart;
  const showRepeatReminder = !isAlwaysDaypart && draft.dayPartId !== '';

  const isTimeStepValid = (() => {
    if (isCustomDaypart && draft.useRelativeTime) {
      return draft.relativeHours > 0 || draft.relativeMinutes > 0 || draft.relativeSeconds > 0;
    }
    if (isCommandEvent) {
      return !!draft.fromDt;
    }
    if (isCustomDaypart && !draft.useRelativeTime) {
      return !!draft.fromDt && !!draft.toDt;
    }
    return true;
  })();
  const isStepValid =
    currentStep === 0
      ? hasContent
      : currentStep === displayStepIndex
        ? hasDisplays
        : currentStep === timeStepIndex
          ? isTimeStepValid
          : true;

  useEffect(() => {
    if (!isOpen) return;

    setPagination((prev) => ({ ...prev, daypart: { ...prev.daypart, isLoading: true } }));
    fetchDaypart({ start: 0, length: DROPDOWN_PAGE_SIZE })
      .then(({ rows, totalCount }) => {
        setDaypartOptions(rows.map((dp) => ({ value: String(dp.dayPartId), label: dp.name })));
        setPagination((prev) => ({ ...prev, daypart: { ...prev.daypart, totalCount } }));

        const always = rows.find((dp) => dp.isAlways === 1);
        const custom = rows.find((dp) => dp.isCustom === 1);
        if (always) {
          setAlwaysDayPartId(String(always.dayPartId));
          setDraft((prev) =>
            prev.dayPartId === '' ? { ...prev, dayPartId: String(always.dayPartId) } : prev,
          );
        }
        if (custom) setCustomDayPartId(String(custom.dayPartId));
      })
      .finally(() => {
        setPagination((prev) => ({ ...prev, daypart: { ...prev.daypart, isLoading: false } }));
      });

    fetchResolution({ start: 0, length: 100 }).then(({ rows }) => {
      setResolutionOptions(
        rows.map((r) => ({ value: String(r.resolutionId), label: r.resolution })),
      );
    });
  }, [isOpen]);

  // Fetch enriched event details in edit mode — the grid row doesn't carry geoLocation,
  // mediaId, playlistId, or other fields only returned by GET /schedule/{id}.
  useEffect(() => {
    if (!isEditMode || !event || !isOpen) {
      return;
    }

    fetchEventById(event.eventId)
      .then((enriched) => {
        setDraft(createDraftFromEvent(enriched));
      })
      .catch(() => {});
  }, [isOpen, isEditMode, event]);

  // Fall back to 'general' tab when the currently active tab is no longer visible
  useEffect(() => {
    if ((optionalTab === 'repeats' || optionalTab === 'reminder') && !canSetReminders) {
      setOptionalTab('general');
    }
    if (optionalTab === 'geoLocation' && !canGeoSchedule) {
      setOptionalTab('general');
    }
    if (optionalTab === 'criteria' && !canSetCriteria) {
      setOptionalTab('general');
    }
  }, [canGeoSchedule, canSetReminders, canSetCriteria, optionalTab]);

  // Command events are always forced to the Custom daypart (no toDt, no relative time)
  useEffect(() => {
    if (isCommandEvent && customDayPartId) {
      setDraft((prev) =>
        prev.dayPartId === customDayPartId ? prev : { ...prev, dayPartId: customDayPartId },
      );
    }
  }, [isCommandEvent, customDayPartId]);

  const fetchContentPage = async (
    eventType: EventTypeId,
    start: number,
  ): Promise<{ options: SelectOption[]; totalCount: number }> => {
    switch (eventType) {
      case EventTypeId.Layout:
      case EventTypeId.Overlay:
      case EventTypeId.Interrupt: {
        const { rows, totalCount } = await fetchLayouts({ start, length: DROPDOWN_PAGE_SIZE });
        return {
          options: rows.map((l) => ({ value: String(l.campaignId), label: l.layout })),
          totalCount,
        };
      }
      case EventTypeId.Command: {
        const { rows, totalCount } = await fetchCommands({ start, length: DROPDOWN_PAGE_SIZE });
        return {
          options: rows.map((c) => ({ value: String(c.commandId), label: c.command })),
          totalCount,
        };
      }
      case EventTypeId.Campaign: {
        const { rows, totalCount } = await fetchCampaigns({
          start,
          length: DROPDOWN_PAGE_SIZE,
        });
        return {
          options: rows.map((c) => ({ value: String(c.campaignId), label: c.campaign })),
          totalCount,
        };
      }
      case EventTypeId.Media: {
        const { rows, totalCount } = await fetchMedia({ start, length: DROPDOWN_PAGE_SIZE });
        return {
          options: rows
            .filter((m) => m.released === 1)
            .map((m) => ({ value: String(m.mediaId), label: m.name })),
          totalCount,
        };
      }
      case EventTypeId.Playlist: {
        const { rows, totalCount } = await fetchPlaylist({ start, length: DROPDOWN_PAGE_SIZE });
        return {
          options: rows.map((p) => ({ value: String(p.playlistId), label: p.name })),
          totalCount,
        };
      }
      case EventTypeId.Sync: {
        const { rows, totalCount } = await fetchSyncGroups({ start, length: DROPDOWN_PAGE_SIZE });
        return {
          options: rows.map((sg) => ({ value: String(sg.syncGroupId), label: sg.name })),
          totalCount,
        };
      }
      case EventTypeId.DataConnector: {
        const { rows, totalCount } = await fetchDataset({
          start,
          length: DROPDOWN_PAGE_SIZE,
          isRealTime: 1,
        });
        return {
          options: rows.map((ds) => ({ value: String(ds.dataSetId), label: ds.dataSet })),
          totalCount,
        };
      }
      default:
        return { options: [], totalCount: 0 };
    }
  };

  useEffect(() => {
    if (!isOpen || !draft.eventTypeId) {
      setContentOptions([]);
      setPagination((prev) => ({
        ...prev,
        content: { totalCount: 0, isLoading: false, isLoadingMore: false },
      }));
      setIsLoadingContent(false);
      return;
    }

    let cancelled = false;
    setIsLoadingContent(true);
    setContentOptions([]);
    setPagination((prev) => ({
      ...prev,
      content: { totalCount: 0, isLoading: false, isLoadingMore: false },
    }));

    fetchContentPage(draft.eventTypeId, 0)
      .then(({ options, totalCount }) => {
        if (!cancelled) {
          setContentOptions(options);
          setPagination((prev) => ({ ...prev, content: { ...prev.content, totalCount } }));
        }
      })
      .finally(() => {
        if (!cancelled) {
          setIsLoadingContent(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [isOpen, draft.eventTypeId]);

  type PaginationKey = keyof typeof pagination;

  const loadMore = (
    key: PaginationKey,
    fetchFn: () => Promise<SelectOption[]>,
    setOptions: React.Dispatch<React.SetStateAction<SelectOption[]>>,
  ) => {
    if (pagination[key].isLoadingMore) return;
    setPagination((prev) => ({ ...prev, [key]: { ...prev[key], isLoadingMore: true } }));
    fetchFn()
      .then((newOptions) => {
        setOptions((prev) => [...prev, ...newOptions]);
      })
      .finally(() => {
        setPagination((prev) => ({ ...prev, [key]: { ...prev[key], isLoadingMore: false } }));
      });
  };

  const hasMoreContent = contentOptions.length < pagination.content.totalCount;
  const hasMoreDayparts = daypartOptions.length < pagination.daypart.totalCount;
  const hasMoreCommands = commandOptions.length < pagination.command.totalCount;

  const loadMoreContent = () => {
    if (!draft.eventTypeId) return;
    loadMore(
      'content',
      () =>
        fetchContentPage(draft.eventTypeId!, contentOptions.length).then(({ options }) => options),
      setContentOptions,
    );
  };

  const loadMoreDayparts = () => {
    loadMore(
      'daypart',
      () =>
        fetchDaypart({ start: daypartOptions.length, length: DROPDOWN_PAGE_SIZE }).then(
          ({ rows }) => rows.map((dp) => ({ value: String(dp.dayPartId), label: dp.name })),
        ),
      setDaypartOptions,
    );
  };

  const loadMoreCommands = () => {
    loadMore(
      'command',
      () =>
        fetchCommands({ start: commandOptions.length, length: DROPDOWN_PAGE_SIZE }).then(
          ({ rows }) => rows.map((c) => ({ value: String(c.commandId), label: c.command })),
        ),
      setCommandOptions,
    );
  };

  useEffect(() => {
    if (!isOpen || draft.eventTypeId !== EventTypeId.Action) {
      setLayoutCodeOptions([]);
      setCommandOptions([]);
      return;
    }

    setPagination((prev) => ({
      ...prev,
      layoutCode: { ...prev.layoutCode, isLoading: true },
      command: { ...prev.command, isLoading: true },
    }));

    fetchLayoutCodes()
      .then((codes) => {
        setLayoutCodeOptions(
          codes.map((c) => ({ value: c.code, label: `${c.layout} (${c.code})` })),
        );
        setPagination((prev) => ({
          ...prev,
          layoutCode: { ...prev.layoutCode, totalCount: codes.length },
        }));
      })
      .catch((err) => {
        console.error('fetchLayoutCodes failed:', err);
      })
      .finally(() => {
        setPagination((prev) => ({
          ...prev,
          layoutCode: { ...prev.layoutCode, isLoading: false },
        }));
      });

    fetchCommands({ start: 0, length: DROPDOWN_PAGE_SIZE })
      .then(({ rows: commands, totalCount }) => {
        setCommandOptions(commands.map((c) => ({ value: String(c.commandId), label: c.command })));
        setPagination((prev) => ({ ...prev, command: { ...prev.command, totalCount } }));
      })
      .catch(() => {
        notify.error(t('Failed to load commands.'));
      })
      .finally(() => {
        setPagination((prev) => ({ ...prev, command: { ...prev.command, isLoading: false } }));
      });
  }, [isOpen, draft.eventTypeId]);

  const updateDraft = <K extends keyof ScheduleEventDraft>(
    key: K,
    value: ScheduleEventDraft[K],
  ) => {
    setDraft((prev) => ({ ...prev, [key]: value }));
  };

  const updateCriterion = (index: number, field: keyof DraftCriterion, value: string) => {
    setDraft((prev) => {
      const criteria = prev.criteria.map((c, i) =>
        i === index ? ({ ...c, [field]: value } as DraftCriterion) : c,
      );
      return { ...prev, criteria };
    });
  };

  const addCriterion = () => {
    setDraft((prev) => ({ ...prev, criteria: [...prev.criteria, { ...EMPTY_CRITERION }] }));
  };

  const removeCriterion = (index: number) => {
    setDraft((prev) => ({
      ...prev,
      criteria:
        prev.criteria.length > 1 ? prev.criteria.filter((_, i) => i !== index) : prev.criteria,
    }));
  };

  const updateReminder = (
    index: number,
    field: keyof DraftReminder,
    value: string | number | boolean,
  ) => {
    setDraft((prev) => {
      const reminders = prev.reminders.map((r, i) =>
        i === index ? ({ ...r, [field]: value } as DraftReminder) : r,
      );
      return { ...prev, reminders };
    });
  };

  const addReminder = () => {
    setDraft((prev) => ({ ...prev, reminders: [...prev.reminders, { ...EMPTY_REMINDER }] }));
  };

  const removeReminder = (index: number) => {
    setDraft((prev) => ({
      ...prev,
      reminders:
        prev.reminders.length > 1 ? prev.reminders.filter((_, i) => i !== index) : prev.reminders,
    }));
  };

  const toggleWeekday = (day: string) => {
    setDraft((prev) => {
      const current = prev.recurrenceRepeatsOn;
      const updated = current.includes(day) ? current.filter((d) => d !== day) : [...current, day];
      return { ...prev, recurrenceRepeatsOn: updated };
    });
  };

  const goNext = () => {
    if (!isLastStep) goToStep(currentStep + 1);
  };

  const goBack = () => {
    if (!isFirstStep) setCurrentStep((prev) => prev - 1);
  };

  const handleFinish = () => {
    setApiError(undefined);
    setFormErrors({});

    const schema = getScheduleEventSchema(t);
    const result = schema.safeParse(draft);

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors as Partial<
        Record<keyof ScheduleEventDraft, string[]>
      >;
      const mapped: ScheduleFormErrors = {};

      Object.entries(fieldErrors).forEach(([key, errors]) => {
        if (errors?.[0]) {
          mapped[key as keyof ScheduleFormErrors] = errors[0];
        }
      });

      setFormErrors(mapped);

      const allMessages = Object.values(mapped).filter(Boolean) as string[];
      setApiError(
        allMessages.length > 0
          ? allMessages.join(' · ')
          : t('Please correct the errors in the form before saving.'),
      );

      if (
        mapped.eventTypeId ||
        mapped.campaignId ||
        mapped.commandId ||
        mapped.mediaId ||
        mapped.playlistId ||
        mapped.syncGroupId ||
        mapped.syncDisplayLayouts ||
        mapped.dataSetId ||
        mapped.actionType ||
        mapped.actionTriggerCode ||
        mapped.actionLayoutCode
      ) {
        setCurrentStep(0);
      } else if (mapped.displayGroupIds && !isSyncType) {
        setCurrentStep(displayStepIndex);
      } else if (
        mapped.dayPartId ||
        mapped.relativeHours ||
        mapped.toDt ||
        mapped.fromDt ||
        mapped.shareOfVoice
      ) {
        setCurrentStep(timeStepIndex);
      }
      return;
    }

    const eventTypeId = draft.eventTypeId!;
    const displayGroupIds =
      eventTypeId === EventTypeId.Sync
        ? []
        : [...draft.displaySpecificGroupIds, ...draft.displayGroupIds];

    startTransition(async () => {
      try {
        const filteredCriteria = draft.criteria.filter((c) => c.type && c.metric);
        const filteredReminders = draft.reminders.filter((r) => r.value > 0);

        const payload = {
          eventTypeId,
          displayGroupIds,
          dayPartId: Number(draft.dayPartId),

          ...(eventTypeId === EventTypeId.Media
            ? draft.mediaId
              ? { mediaId: draft.mediaId }
              : draft.campaignId
                ? { fullScreenCampaignId: draft.campaignId }
                : {}
            : {}),
          ...(eventTypeId === EventTypeId.Playlist
            ? draft.playlistId
              ? { playlistId: draft.playlistId }
              : draft.campaignId
                ? { fullScreenCampaignId: draft.campaignId }
                : {}
            : {}),
          ...([
            EventTypeId.Layout,
            EventTypeId.Overlay,
            EventTypeId.Interrupt,
            EventTypeId.Campaign,
          ].includes(eventTypeId) && draft.campaignId
            ? { campaignId: draft.campaignId }
            : {}),
          ...(eventTypeId === EventTypeId.Command && draft.commandId
            ? { commandId: draft.commandId }
            : {}),
          ...(eventTypeId === EventTypeId.Sync && draft.syncGroupId
            ? {
                syncGroupId: draft.syncGroupId,
                syncDisplayLayouts: draft.syncDisplayLayouts,
              }
            : {}),
          ...(eventTypeId === EventTypeId.DataConnector && draft.dataSetId
            ? {
                dataSetId: draft.dataSetId,
                ...(draft.dataSetParams ? { dataSetParams: draft.dataSetParams } : {}),
              }
            : {}),
          ...(eventTypeId === EventTypeId.Action
            ? {
                actionType: draft.actionType,
                actionTriggerCode: draft.actionTriggerCode,
                ...(draft.actionType === 'navLayout'
                  ? { actionLayoutCode: draft.actionLayoutCode }
                  : {}),
                ...(draft.actionType === 'command' && draft.commandId
                  ? { commandId: draft.commandId }
                  : {}),
              }
            : {}),
          ...(eventTypeId === EventTypeId.Interrupt && draft.shareOfVoice > 0
            ? { shareOfVoice: draft.shareOfVoice }
            : {}),

          ...(isCustomDaypart && draft.useRelativeTime
            ? (() => {
                const now = new Date();
                const end = new Date(now);
                end.setHours(end.getHours() + draft.relativeHours);
                end.setMinutes(end.getMinutes() + draft.relativeMinutes);
                end.setSeconds(end.getSeconds() + draft.relativeSeconds);
                return { fromDt: now.toISOString(), toDt: end.toISOString() };
              })()
            : {
                ...(isCustomDaypart && draft.fromDt ? { fromDt: draft.fromDt } : {}),
                // Command events don't require or send toDt
                ...(!isCommandEvent && isCustomDaypart && draft.toDt ? { toDt: draft.toDt } : {}),
              }),

          ...(!isCustomDaypart && !isAlwaysDaypart && draft.fromDt ? { fromDt: draft.fromDt } : {}),

          ...(draft.name ? { name: draft.name } : {}),
          ...(draft.resolutionId ? { resolutionId: Number(draft.resolutionId) } : {}),
          ...(draft.layoutDuration ? { layoutDuration: draft.layoutDuration } : {}),
          ...(draft.backgroundColor ? { backgroundColor: draft.backgroundColor } : {}),
          displayOrder: draft.displayOrder,
          isPriority: draft.isPriority,
          maxPlaysPerHour: draft.maxPlaysPerHour,
          syncTimezone: draft.syncTimezone ? 1 : 0,

          ...(draft.recurrenceType ? { recurrenceType: draft.recurrenceType } : {}),
          ...(draft.recurrenceType ? { recurrenceDetail: draft.recurrenceDetail } : {}),
          ...(draft.recurrenceType === 'Week' && draft.recurrenceRepeatsOn.length > 0
            ? { recurrenceRepeatsOn: draft.recurrenceRepeatsOn.map(Number) }
            : {}),
          ...(draft.recurrenceType === 'Month'
            ? { recurrenceMonthlyRepeatsOn: draft.recurrenceMonthlyRepeatsOn }
            : {}),
          ...(draft.recurrenceType && draft.recurrenceRange
            ? { recurrenceRange: draft.recurrenceRange }
            : {}),

          ...(filteredReminders.length > 0
            ? {
                scheduleReminders: filteredReminders.map((r) => ({
                  reminder_value: r.value,
                  reminder_type: r.type,
                  reminder_option: r.option,
                  reminder_isEmailHidden: r.isEmail ? 1 : 0,
                })),
              }
            : {}),

          isGeoAware: draft.isGeoAware ? 1 : 0,
          ...(draft.isGeoAware && draft.geoLocation ? { geoLocation: draft.geoLocation } : {}),

          ...(filteredCriteria.length > 0 ? { criteria: filteredCriteria } : {}),
        };

        if (isEditMode) {
          await updateEvent(event.eventId, payload, timezone);
          notify.success(t('Updated Event'));
        } else {
          await createEvent(payload, timezone);
          notify.success(t('Added Event'));
        }
        onSaved?.();
        onClose();
      } catch (error) {
        setApiError(
          error instanceof Error ? error.message : t('An error occurred while saving the event.'),
        );
      }
    });
  };

  const handleClose = () => {
    setCurrentStep(initialStep);
    setMaxReachedStep(initialMaxStep);
    setDraft(
      isEditMode
        ? createDraftFromEvent(event)
        : createInitialDraft(prefilledEventTypeId, contentId),
    );
    setOptionalTab('general');
    setShowDisplayBanner(false);
    setFormErrors({});
    setApiError(undefined);
    onClose();
  };

  const actions: ModalAction[] = (() => {
    const result: ModalAction[] = [
      {
        label: t('Cancel'),
        onClick: handleClose,
        variant: 'secondary',
        className: 'mr-auto',
      },
    ];

    if (!isFirstStep) {
      result.push({
        label: t('Back'),
        onClick: goBack,
        variant: 'link',
        leftIcon: ArrowLeft,
      });
    }

    if (!isLastStep) {
      result.push({
        label: t('Next'),
        onClick: goNext,
        variant: canFinish ? 'link' : 'primary',
        rightIcon: canFinish ? ArrowRight : undefined,
        disabled: !isStepValid,
      });
    }

    if (canFinish) {
      result.push({
        label: isPending ? t('Saving...') : isEditMode ? t('Save') : t('Finish'),
        onClick: handleFinish,
        variant: 'primary',
        disabled: isPending || !isStepValid,
      });
    }

    return result;
  })();

  const getTabClass = (tab: OptionalTab) =>
    `px-4 py-2 text-sm font-medium border-b-2 ${
      optionalTab === tab
        ? 'border-xibo-blue-600 text-xibo-blue-600'
        : 'border-transparent text-gray-500 hover:text-gray-700'
    }`;

  const contentField = getContentFieldConfig(draft.eventTypeId, t);
  const contentHelpText = getContentHelpText(draft.eventTypeId, t);

  const prefilledOption = isEditMode
    ? getPrefilledOption(
        Number(getContentValue(draft)) || undefined,
        event.campaign || event.command || undefined,
      )
    : getPrefilledOption(contentId, contentName);
  const mergedContentOptions =
    prefilledOption && !contentOptions.some((o) => o.value === prefilledOption.value)
      ? [prefilledOption, ...contentOptions]
      : contentOptions;

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title={isEditMode ? t('Edit Event') : t('Schedule Event')}
      size="lg"
      scrollable={false}
      actions={actions}
      isPending={isPending}
      error={apiError}
      className="min-h-[90vh]"
    >
      <div className="flex flex-col flex-1 min-h-0 max-h-full">
        {/* Stepper */}
        <div className="shrink-0 px-8">
          <Stepper steps={steps} activeIndex={currentStep} onStepClick={goToStep} />
        </div>

        {/* Info Banners */}
        {currentStep === displayStepIndex && !isEditMode && hasDisplays && showDisplayBanner && (
          <div className="shrink-0 mx-8 mt-4">
            <InfoBanner type="success">
              {t("You're all set! Click")} <strong>{t('Finish')}</strong>{' '}
              {t("to create an 'Always Schedule', or")} <strong>{t('Next')}</strong>{' '}
              {t('to choose times.')}
            </InfoBanner>
          </div>
        )}
        {currentStep === timeStepIndex && !isEditMode && (
          <div className="shrink-0 mx-8 mt-4">
            <InfoBanner type="success">
              {t('Click')} <strong>{t('Finish')}</strong> {t('to complete schedule or click')}{' '}
              <strong>{t('Next')}</strong> {t('to customized settings.')}
            </InfoBanner>
          </div>
        )}
        {currentStep === optionalStepIndex && !isEditMode && (
          <div className="shrink-0 mx-8 mt-4">
            <InfoBanner type="success">
              {t("You're all set! Simply click")} <strong>{t('Finish')}</strong>{' '}
              {t('to complete schedule or optional criteria.')}
            </InfoBanner>
          </div>
        )}

        {/* Step Content */}
        <div className="flex flex-col flex-1 overflow-y-auto px-8 py-4">
          {/* Step 1: Content */}
          {currentStep === 0 && (
            <div className="space-y-4">
              <SelectDropdown
                label={t('Event Type')}
                value={draft.eventTypeId ? String(draft.eventTypeId) : ''}
                options={EVENT_TYPE_OPTIONS}
                onSelect={(value) => {
                  const newType = Number(value) as EventTypeId;
                  setDraft((prev) => ({
                    ...prev,
                    eventTypeId: newType,
                    campaignId: null,
                    commandId: null,
                    mediaId: null,
                    playlistId: null,
                    syncGroupId: null,
                    dataSetId: null,
                    dataSetParams: '',
                    syncDisplayLayouts: {},
                    actionType: '',
                    actionTriggerCode: '',
                    actionLayoutCode: '',
                    shareOfVoice: 0,
                  }));
                  setContentOptions([]);
                }}
                placeholder={t('Select Event Type')}
                error={formErrors.eventTypeId}
              />

              {contentField && (
                <SelectDropdown
                  label={contentField.label}
                  value={getContentValue(draft)}
                  options={mergedContentOptions}
                  onSelect={(value) => {
                    const typeId = draft.eventTypeId;
                    if (typeId === EventTypeId.Media) updateDraft('mediaId', Number(value));
                    else if (typeId === EventTypeId.Playlist)
                      updateDraft('playlistId', Number(value));
                    else if (typeId === EventTypeId.Command)
                      updateDraft('commandId', Number(value));
                    else if (typeId === EventTypeId.Sync) updateDraft('syncGroupId', Number(value));
                    else if (typeId === EventTypeId.DataConnector)
                      updateDraft('dataSetId', Number(value));
                    else updateDraft('campaignId', Number(value));
                  }}
                  placeholder={contentField.placeholder}
                  helpText={contentHelpText}
                  searchable
                  isLoading={isLoadingContent}
                  onLoadMore={loadMoreContent}
                  hasMore={hasMoreContent}
                  isLoadingMore={pagination.content.isLoadingMore}
                  error={
                    formErrors.campaignId ||
                    formErrors.commandId ||
                    formErrors.mediaId ||
                    formErrors.playlistId ||
                    formErrors.syncGroupId ||
                    formErrors.dataSetId
                  }
                />
              )}

              {!!draft.campaignId &&
                [
                  EventTypeId.Layout,
                  EventTypeId.Overlay,
                  EventTypeId.Interrupt,
                  EventTypeId.Campaign,
                ].includes(draft.eventTypeId!) && (
                  <div className="inline-flex flex-col gap-1 items-start">
                    <Button
                      variant="secondary"
                      className="mb-0"
                      rightIcon={Tablet}
                      onClick={() => window.open(`/campaign/${draft.campaignId}/preview`, '_blank')}
                    >
                      {t('Preview')}
                    </Button>
                    <span className="text-xs text-gray-400 mt-1 whitespace-pre-line">
                      {t('Preview your selection in a new tab')}
                    </span>
                  </div>
                )}

              {/* TODO: Sync per-display layout selection — deferred to next sprint */}

              {/* Data Connector: Parameters field */}
              {isDataConnectorType && draft.dataSetId && (
                <TextInput
                  name="dataSetParams"
                  label={t('Parameters')}
                  value={draft.dataSetParams}
                  placeholder={t('Enter Parameters')}
                  onChange={(value) => updateDraft('dataSetParams', value)}
                  helpText={t(
                    'Optionally provide any parameters to be used by the Data Connector.',
                  )}
                />
              )}

              {/* Action: Action Type, Trigger Code, Layout Code / Command */}
              {isActionType && (
                <>
                  <SelectDropdown
                    label={t('Action Type')}
                    value={draft.actionType}
                    options={[
                      { value: 'navLayout', label: t('Navigate to Layout') },
                      { value: 'command', label: t('Command') },
                    ]}
                    onSelect={(value) => updateDraft('actionType', value)}
                    placeholder={t('Select Action Type')}
                    error={formErrors.actionType}
                  />
                  <TextInput
                    name="actionTriggerCode"
                    label={t('Trigger Code')}
                    value={draft.actionTriggerCode}
                    placeholder={t('Enter Trigger Code')}
                    onChange={(value) => updateDraft('actionTriggerCode', value)}
                    helpText={t('Web hook trigger code for this Action')}
                    error={formErrors.actionTriggerCode}
                  />
                  {draft.actionType === 'navLayout' && (
                    <SelectDropdown
                      label={t('Layout Code')}
                      value={draft.actionLayoutCode}
                      options={layoutCodeOptions}
                      onSelect={(value) => updateDraft('actionLayoutCode', value)}
                      placeholder={t('Select Layout Code')}
                      helpText={t(
                        'Please select the Code identifier for the Layout that Player should navigate to when this Action is triggered.',
                      )}
                      searchable
                      isLoading={pagination.layoutCode.isLoading}
                      error={formErrors.actionLayoutCode}
                    />
                  )}
                  {draft.actionType === 'command' && (
                    <SelectDropdown
                      label={t('Command')}
                      value={draft.commandId ? String(draft.commandId) : ''}
                      options={commandOptions}
                      onSelect={(value) => updateDraft('commandId', Number(value))}
                      placeholder={t('Select Command')}
                      searchable
                      isLoading={pagination.command.isLoading}
                      onLoadMore={loadMoreCommands}
                      hasMore={hasMoreCommands}
                      isLoadingMore={pagination.command.isLoadingMore}
                      error={formErrors.commandId}
                    />
                  )}
                </>
              )}
            </div>
          )}

          {/* Step 2: Displays */}
          {currentStep === displayStepIndex && (
            <div className="space-y-4">
              <div className="flex flex-col gap-1 w-full">
                <label className="text-sm font-semibold text-gray-500 leading-5">
                  {t('Display')}
                </label>
                <DisplayGroupMultiSelect
                  triggerClassName="bg-white"
                  value={{
                    displaySpecificGroupIds: draft.displaySpecificGroupIds,
                    displayGroupIds: draft.displayGroupIds,
                  }}
                  onChange={({ displaySpecificGroupIds, displayGroupIds }) => {
                    setDraft((prev) => ({ ...prev, displaySpecificGroupIds, displayGroupIds }));
                    setShowDisplayBanner(
                      displaySpecificGroupIds.length > 0 || displayGroupIds.length > 0,
                    );
                  }}
                />
                {formErrors.displayGroupIds ? (
                  <p className="text-xs text-red-600 ml-2 mt-1">{formErrors.displayGroupIds}</p>
                ) : (
                  <span className="text-xs text-gray-400">
                    {t('Please select one or more')} <strong>{t('Displays/Groups')}</strong>{' '}
                    {t('for this event to be shown on.')}
                  </span>
                )}
              </div>
            </div>
          )}

          {/* Step: Time */}
          {currentStep === timeStepIndex && (
            <div className="space-y-4">
              {/* Command events are always forced to Custom daypart — hide the selector */}
              {!isCommandEvent && (
                <SelectDropdown
                  label={t('Dayparting')}
                  value={draft.dayPartId}
                  options={daypartOptions}
                  onSelect={(value) => updateDraft('dayPartId', value)}
                  placeholder={t('Select Daypart')}
                  helpText={t(
                    'Select how this event recurs. Choose Always for continuous playback or Custom to define specific times.',
                  )}
                  isLoading={pagination.daypart.isLoading}
                  onLoadMore={loadMoreDayparts}
                  hasMore={hasMoreDayparts}
                  isLoadingMore={pagination.daypart.isLoadingMore}
                  error={formErrors.dayPartId}
                />
              )}

              {/* Interrupt: Share of Voice */}
              {isInterruptType && (
                <div className="grid grid-cols-2 gap-4">
                  <NumberInput
                    name="shareOfVoice"
                    label={t('Share of Voice')}
                    value={draft.shareOfVoice}
                    onChange={(num) => updateDraft('shareOfVoice', num)}
                    helpText={t(
                      'The amount of time this Layout should be shown, in seconds per hour.',
                    )}
                    error={formErrors.shareOfVoice}
                  />
                  <div className="flex flex-col gap-1">
                    <label className="text-sm font-semibold text-gray-500">
                      {t('As a percentage')}
                    </label>
                    <input
                      type="text"
                      readOnly
                      value={((draft.shareOfVoice / 3600) * 100).toFixed(2)}
                      className="h-11.25 rounded-lg border border-gray-200 px-3 text-sm bg-gray-50 text-gray-500"
                    />
                  </div>
                </div>
              )}

              {/* Named daypart: Start Time only (date, no time picker) */}
              {isNamedDaypart && (
                <DatePickerInput
                  label={t('Start Time')}
                  value={draft.fromDt}
                  onChange={(value) => updateDraft('fromDt', value)}
                  helpText={t('Select the start time for this event.')}
                  showTimePicker={false}
                />
              )}

              {/* Custom daypart + Command: Start Time only (no End Time) */}
              {isCommandEvent && !draft.useRelativeTime && (
                <DatePickerInput
                  label={t('Start Time')}
                  value={draft.fromDt}
                  onChange={(value) => updateDraft('fromDt', value)}
                  error={formErrors.fromDt}
                />
              )}

              {/* Custom daypart (non-Command): Start + End Time (or relative time) */}
              {isCustomDaypart && !isCommandEvent && !draft.useRelativeTime && (
                <div className="grid grid-cols-2 gap-4">
                  <DatePickerInput
                    label={t('Start Time')}
                    value={draft.fromDt}
                    onChange={(value) => updateDraft('fromDt', value)}
                    error={formErrors.fromDt}
                  />
                  <DatePickerInput
                    label={t('End Time')}
                    value={draft.toDt}
                    onChange={(value) => updateDraft('toDt', value)}
                    error={formErrors.toDt}
                  />
                </div>
              )}

              {/* Use Relative Time — for Custom daypart (Command included) */}
              {isCustomDaypart && (
                <div className="bg-gray-50 py-3">
                  <Checkbox
                    id="useRelativeTime"
                    checked={draft.useRelativeTime}
                    onChange={(e) => updateDraft('useRelativeTime', e.target.checked)}
                    title={t('Use Relative Time')}
                    label={t('Duration-based offsets')}
                    className="px-3 py-2.5"
                  />

                  {/* Relative time fields */}
                  {draft.useRelativeTime && (
                    <div className="flex flex-col px-3 gap-2.5">
                      {(draft.relativeHours > 0 ||
                        draft.relativeMinutes > 0 ||
                        draft.relativeSeconds > 0) && (
                        <div className="flex items-center gap-2 text-sm  bg-gray-100 rounded-lg p-3">
                          <CalendarClock className="h-3.5 w-3.5 text-gray-500" />
                          <span className="font-medium text-sm text-gray-500">
                            {t('Event Schedule:')}
                          </span>
                          <span className="text-gray-800">
                            {(() => {
                              const now = new Date();
                              const end = new Date(now);
                              end.setHours(end.getHours() + draft.relativeHours);
                              end.setMinutes(end.getMinutes() + draft.relativeMinutes);
                              end.setSeconds(end.getSeconds() + draft.relativeSeconds);
                              return `${now.toLocaleString()} - ${end.toLocaleString()}`;
                            })()}
                          </span>
                        </div>
                      )}
                      <p className="text-xs text-gray-400">
                        {t("Total duration for this event in each Display's local time zone.")}
                      </p>
                      <div className="grid grid-cols-3 gap-3">
                        <NumberInput
                          name="relativeHours"
                          label={t('Hours')}
                          value={draft.relativeHours}
                          onChange={(num) => updateDraft('relativeHours', num)}
                          error={formErrors.relativeHours}
                        />
                        <NumberInput
                          name="relativeMinutes"
                          label={t('Minutes')}
                          value={draft.relativeMinutes}
                          onChange={(num) => updateDraft('relativeMinutes', num)}
                        />
                        <NumberInput
                          name="relativeSeconds"
                          label={t('Seconds')}
                          value={draft.relativeSeconds}
                          onChange={(num) => updateDraft('relativeSeconds', num)}
                        />
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* Step: Optional */}
          {currentStep === optionalStepIndex && (
            <div className="flex flex-col flex-1 space-y-4">
              {/* Sub-tabs */}
              <nav className="flex shrink-0 border-b border-gray-200">
                <button
                  type="button"
                  className={getTabClass('general')}
                  onClick={() => setOptionalTab('general')}
                >
                  {t('General')}
                </button>
                {showRepeatReminder && canSetReminders && (
                  <>
                    <button
                      type="button"
                      className={getTabClass('repeats')}
                      onClick={() => setOptionalTab('repeats')}
                    >
                      {t('Repeats')}
                    </button>
                    <button
                      type="button"
                      className={getTabClass('reminder')}
                      onClick={() => setOptionalTab('reminder')}
                    >
                      {t('Reminder')}
                    </button>
                  </>
                )}
                {canGeoSchedule && (
                  <button
                    type="button"
                    className={getTabClass('geoLocation')}
                    onClick={() => setOptionalTab('geoLocation')}
                  >
                    {t('Geo Location')}
                  </button>
                )}
                {canSetCriteria && (
                  <button
                    type="button"
                    className={getTabClass('criteria')}
                    onClick={() => setOptionalTab('criteria')}
                  >
                    {t('Criteria')}
                  </button>
                )}
              </nav>
              {/* General tab */}
              {optionalTab === 'general' && (
                <div className="space-y-4">
                  <TextInput
                    name="name"
                    label={t('Name')}
                    value={draft.name}
                    placeholder={t('Enter Name')}
                    onChange={(value) => updateDraft('name', value)}
                    helpText={t('Optional Name for this Event (1-50 characters)')}
                  />
                  {/* Duration in loop: Media only */}
                  {isMediaType && (
                    <NumberInput
                      name="layoutDuration"
                      label={t('Duration in loop')}
                      value={draft.layoutDuration}
                      onChange={(num) => updateDraft('layoutDuration', num)}
                      helpText={t(
                        'Set how long this item should be shown each time it appears in the schedule. Leave blank to use the Media Duration set in the Library.',
                      )}
                    />
                  )}
                  {/* Resolution + Background Colour: Media and Playlist */}
                  {(isMediaType || isPlaylistType) && (
                    <>
                      <SelectDropdown
                        label={t('Resolution')}
                        value={draft.resolutionId}
                        options={resolutionOptions}
                        onSelect={(value) => updateDraft('resolutionId', value)}
                        placeholder={t('Select Resolution')}
                        helpText={t(
                          'Optionally select a Resolution to use for the selected Media. Leave blank to match with an existing Resolution closest in size to the selected media.',
                        )}
                        clearable
                      />
                      <div className="flex flex-col gap-1.5">
                        <label className="text-sm font-semibold text-gray-500">
                          {t('Background Colour')}
                        </label>
                        <div className="flex items-center h-11.25 border border-gray-200 rounded-lg overflow-hidden">
                          <input
                            type="color"
                            value={draft.backgroundColor}
                            onChange={(e) => updateDraft('backgroundColor', e.target.value)}
                            className="h-full w-12 border-0 cursor-pointer"
                          />
                          <input
                            type="text"
                            value={draft.backgroundColor}
                            onChange={(e) => updateDraft('backgroundColor', e.target.value)}
                            className="flex-1 py-2 px-3 text-sm outline-none border-none"
                          />
                        </div>
                        <p className="text-xs text-gray-400">
                          {t(
                            'Optionally set a colour to use as a background for if the item selected does not fill the entire screen.',
                          )}
                        </p>
                      </div>
                    </>
                  )}
                  {/* Display Order: hidden for Action */}
                  {!isActionType && (
                    <NumberInput
                      name="displayOrder"
                      label={t('Display Order')}
                      value={draft.displayOrder}
                      onChange={(num) => updateDraft('displayOrder', num)}
                      helpText={t(
                        'Please select the order this event should appear in relation to others when there is more than one event scheduled.',
                      )}
                    />
                  )}
                  <NumberInput
                    name="isPriority"
                    label={t('Priority')}
                    value={draft.isPriority}
                    placeholder={t('Select')}
                    onChange={(num) => updateDraft('isPriority', num)}
                    helpText={t(
                      'Sets the event priority - events with the highest priority play in preference to lower priority events.',
                    )}
                  />
                  {/* Max plays/hour: hidden for Command, Action, DataConnector */}
                  {!(isCommandType || isActionType || isDataConnectorType) && (
                    <NumberInput
                      name="maxPlaysPerHour"
                      label={t('Maximum plays/hour')}
                      value={draft.maxPlaysPerHour}
                      placeholder={t('Select')}
                      onChange={(num) => updateDraft('maxPlaysPerHour', num)}
                      helpText={t(
                        'Limit the number of times this event will play per hour on each display. For unlimited plays set to 0.',
                      )}
                    />
                  )}
                  <Checkbox
                    id="syncTimezone"
                    checked={draft.syncTimezone}
                    onChange={(e) => updateDraft('syncTimezone', e.target.checked)}
                    title={t('Run at CMS Time?')}
                    label={t(
                      'When selected, your event will run according to the timezone set on the CMS, otherwise the event will run at Display local time.',
                    )}
                  />
                </div>
              )}

              {/* Repeats tab */}
              {optionalTab === 'repeats' && (
                <div className="space-y-4">
                  <SelectDropdown
                    label={t('Repeats')}
                    value={draft.recurrenceType}
                    options={RECURRENCE_TYPE_OPTIONS}
                    onSelect={(value) => updateDraft('recurrenceType', value)}
                    placeholder={t('None')}
                    helpText={t('Select the type of Repeat required for this Event.')}
                  />

                  {draft.recurrenceType !== '' && (
                    <>
                      <NumberInput
                        name="recurrenceDetail"
                        label={t('Repeat every')}
                        value={draft.recurrenceDetail}
                        onChange={(num) => updateDraft('recurrenceDetail', num)}
                        helpText={t('How often does this event repeat?')}
                      />

                      {draft.recurrenceType === 'Week' && (
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">
                            {t('Repeat on')}
                          </label>
                          <div className="flex gap-2">
                            {WEEKDAYS.map((day, index) => (
                              <button
                                key={day}
                                type="button"
                                className={`px-3 py-1.5 text-sm rounded-lg border ${
                                  draft.recurrenceRepeatsOn.includes(String(index + 1))
                                    ? 'bg-xibo-blue-600 text-white border-xibo-blue-600'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                }`}
                                onClick={() => toggleWeekday(String(index + 1))}
                              >
                                {t(day)}
                              </button>
                            ))}
                          </div>
                        </div>
                      )}

                      {draft.recurrenceType === 'Month' && (
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">
                            {t('Repeats on')}
                          </label>
                          <div className="flex gap-4">
                            <label className="flex items-center gap-2 cursor-pointer">
                              <input
                                type="radio"
                                name="monthlyRepeat"
                                checked={draft.recurrenceMonthlyRepeatsOn === 0}
                                onChange={() => updateDraft('recurrenceMonthlyRepeatsOn', 0)}
                                className="text-xibo-blue-600"
                              />
                              <span className="text-sm">{t('Day of month')}</span>
                            </label>
                            <label className="flex items-center gap-2 cursor-pointer">
                              <input
                                type="radio"
                                name="monthlyRepeat"
                                checked={draft.recurrenceMonthlyRepeatsOn === 1}
                                onChange={() => updateDraft('recurrenceMonthlyRepeatsOn', 1)}
                                className="text-xibo-blue-600"
                              />
                              <span className="text-sm">{t('Day of week')}</span>
                            </label>
                          </div>
                        </div>
                      )}

                      <DatePickerInput
                        label={t('Until')}
                        value={draft.recurrenceRange}
                        onChange={(value) => updateDraft('recurrenceRange', value)}
                        helpText={t('Optionally select the date this event should stop repeating.')}
                      />
                    </>
                  )}
                </div>
              )}

              {/* Reminder tab */}
              {optionalTab === 'reminder' && (
                <div className="space-y-4">
                  <p className="text-sm text-gray-500">
                    {t(
                      'Use the form fields below to create a set of reminders for this event. New fields can be added by clicking on the + icon at the end of the row. Use the tick box to receive a notification by email alternatively reminders will be shown in the message centre.',
                    )}
                  </p>

                  {draft.reminders.map((reminder, index) => (
                    <div
                      key={index}
                      className="grid  grid-cols-[auto_1fr_1fr_auto_auto] items-center gap-2"
                    >
                      <NumberInput
                        name={`reminder-value-${index}`}
                        value={reminder.value}
                        onChange={(num) => updateReminder(index, 'value', num)}
                        className="w-24"
                      />
                      <SelectDropdown
                        value={String(reminder.type)}
                        options={REMINDER_TYPE_OPTIONS}
                        onSelect={(value) => updateReminder(index, 'type', Number(value))}
                      />
                      <SelectDropdown
                        value={String(reminder.option)}
                        options={REMINDER_OPTION_OPTIONS}
                        onSelect={(value) => updateReminder(index, 'option', Number(value))}
                      />
                      <Checkbox
                        id={`reminder-email-${index}`}
                        label={t('Notify by email?')}
                        checked={reminder.isEmail}
                        onChange={(e) => updateReminder(index, 'isEmail', e.target.checked)}
                      />
                      <button
                        type="button"
                        className="flex items-center justify-center size-9 rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-100 shrink-0"
                        onClick={() =>
                          index === draft.reminders.length - 1
                            ? addReminder()
                            : removeReminder(index)
                        }
                      >
                        {index === draft.reminders.length - 1 ? (
                          <Plus size={16} />
                        ) : (
                          <Minus size={16} />
                        )}
                      </button>
                    </div>
                  ))}
                </div>
              )}
              {/* Geo Location tab */}
              {optionalTab === 'geoLocation' && (
                <div className="flex flex-col flex-1 space-y-4">
                  <Checkbox
                    id="isGeoAware"
                    checked={draft.isGeoAware}
                    onChange={(e) => {
                      const checked = e.target.checked;
                      if (!checked) {
                        updateDraft('geoLocation', '');
                      }
                      updateDraft('isGeoAware', checked);
                    }}
                    title={t('Geo Schedule')}
                    label={t(
                      'Should this event be location aware? Enable this checkbox and select an area by drawing a polygon or rectangle layer on the map below.',
                    )}
                  />
                  {draft.isGeoAware && (
                    <div className="flex flex-col flex-1 w-full">
                      <GeoScheduleMap
                        key={isEditMode ? String(event.eventId) : 'new'}
                        geoLocation={draft.geoLocation ?? ''}
                        onChange={(json) => updateDraft('geoLocation', json)}
                        defaultLat={Number(user?.settings?.DEFAULT_LAT ?? 51.5)}
                        defaultLng={Number(user?.settings?.DEFAULT_LONG ?? -0.104)}
                      />
                    </div>
                  )}
                </div>
              )}
              {/* Criteria tab */}
              {optionalTab === 'criteria' && (
                <div className="space-y-4 p-5 bg-slate-50">
                  <div className="text-sm">
                    <p className="font-semibold  text-gray-800">
                      {t('Set criteria to limit when this event is active.')}
                    </p>
                    <p className=" text-gray-500">
                      {t(
                        '*If you add multiple conditions, all must be true for the event to trigger. Leave blank to play at all times.',
                      )}
                    </p>
                  </div>
                  <div className="">
                    {/* Column headers */}
                    <div className="grid grid-cols-[1fr_1fr_1fr_1fr_auto] gap-2 text-sm font-medium text-gray-700 mb-1">
                      <span>{t('Type')}</span>
                      <span>{t('Metric')}</span>
                      <span>{t('Condition')}</span>
                      <span>{t('Value')}</span>
                      <span className="w-9" />
                    </div>

                    {/* Criteria rows */}
                    {draft.criteria.map((criterion, index) => (
                      <div
                        key={index}
                        className="grid grid-cols-[1fr_1fr_1fr_1fr_auto] gap-2 items-center mb-2.5"
                      >
                        <SelectDropdown
                          value={criterion.type}
                          options={CRITERIA_TYPE_OPTIONS}
                          onSelect={(value) => updateCriterion(index, 'type', value)}
                          placeholder={t('Select Type')}
                          className="w-full"
                        />
                        <TextInput
                          name={`metric-${index}`}
                          value={criterion.metric}
                          placeholder={t('Enter Metric')}
                          onChange={(value) => updateCriterion(index, 'metric', value)}
                          className="w-full"
                        />
                        <SelectDropdown
                          value={criterion.condition}
                          options={CONDITION_OPTIONS}
                          onSelect={(value) => updateCriterion(index, 'condition', value)}
                          placeholder={t('Is set')}
                          className="w-full"
                        />
                        <TextInput
                          name={`value-${index}`}
                          value={criterion.value}
                          placeholder={t('Enter Value')}
                          onChange={(value) => updateCriterion(index, 'value', value)}
                          className="w-full"
                        />
                        <button
                          type="button"
                          className="flex items-center justify-center size-9 rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-100"
                          onClick={() =>
                            index === draft.criteria.length - 1
                              ? addCriterion()
                              : removeCriterion(index)
                          }
                        >
                          {index === draft.criteria.length - 1 ? (
                            <Plus size={16} />
                          ) : (
                            <Minus size={16} />
                          )}
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </Modal>
  );
}
