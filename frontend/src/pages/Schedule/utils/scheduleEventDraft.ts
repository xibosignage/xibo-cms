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

import {
  EventTypeId,
  ReminderType,
  ReminderOption,
  type CriteriaCondition,
  type Event,
} from '@/types/event';

//  Types ──

export interface DraftCriterion {
  type: string;
  metric: string;
  condition: CriteriaCondition | string;
  value: string;
}

export interface DraftReminder {
  value: number;
  type: ReminderType;
  option: ReminderOption;
  isEmail: boolean;
}

export interface ScheduleEventDraft {
  eventTypeId: EventTypeId | null;
  mediaId: number | null;
  campaignId: number | null;
  commandId: number | null;
  playlistId: number | null;
  syncGroupId: number | null;
  dataSetId: number | null;
  dataSetParams: string;
  syncDisplayLayouts: Record<number, number | null>;
  actionType: string;
  actionTriggerCode: string;
  actionLayoutCode: string;
  shareOfVoice: number;
  displaySpecificGroupIds: number[];
  displayGroupIds: number[];
  dayPartId: string;
  fromDt: string;
  toDt: string;
  useRelativeTime: boolean;
  relativeHours: number;
  relativeMinutes: number;
  relativeSeconds: number;
  name: string;
  layoutDuration: number;
  resolutionId: string;
  backgroundColor: string;
  displayOrder: number;
  isPriority: number;
  maxPlaysPerHour: number;
  syncTimezone: boolean;
  recurrenceType: string;
  recurrenceDetail: number;
  recurrenceRepeatsOn: string[];
  recurrenceMonthlyRepeatsOn: number;
  recurrenceRange: string;
  reminders: DraftReminder[];
  isGeoAware: boolean;
  geoLocation: string;
  criteria: DraftCriterion[];
}

export type OptionalTab = 'general' | 'repeats' | 'reminder' | 'geoLocation' | 'criteria';

export type ScheduleFormErrors = Partial<
  Record<
    Exclude<
      keyof ScheduleEventDraft,
      'displaySpecificGroupIds' | 'displayGroupIds' | 'syncDisplayLayouts'
    >,
    string
  > & {
    displayGroupIds: string;
    syncDisplayLayouts: string;
  }
>;

export interface SelectOption {
  label: string;
  value: string;
}

export const STEP_LABELS = ['Content', 'Displays', 'Time', 'Optional'] as const;

export const getEventTypeOptions = (t: TFunction): SelectOption[] => [
  { value: String(EventTypeId.Layout), label: t('Layout') },
  { value: String(EventTypeId.Command), label: t('Command') },
  { value: String(EventTypeId.Overlay), label: t('Overlay Layout') },
  { value: String(EventTypeId.Interrupt), label: t('Interrupt Layout') },
  { value: String(EventTypeId.Campaign), label: t('Campaign') },
  { value: String(EventTypeId.Action), label: t('Action') },
  { value: String(EventTypeId.Media), label: t('Media') },
  { value: String(EventTypeId.Playlist), label: t('Playlist') },
  { value: String(EventTypeId.Sync), label: t('Synchronised Event') },
  { value: String(EventTypeId.DataConnector), label: t('Data Connector') },
];

export const getConditionOptions = (t: TFunction): SelectOption[] => [
  { value: 'set', label: t('Is set') },
  { value: 'lt', label: t('Less than') },
  { value: 'lte', label: t('Less than or equal to') },
  { value: 'eq', label: t('Equal to') },
  { value: 'neq', label: t('Not equal to') },
  { value: 'gte', label: t('Greater than or equal to') },
  { value: 'gt', label: t('Greater than') },
  { value: 'contains', label: t('Contains') },
  { value: 'ncontains', label: t('Not contains') },
];

export const getCriteriaTypeOptions = (t: TFunction): SelectOption[] => [
  { value: 'custom', label: t('Custom') },
  { value: 'weather', label: t('Weather') },
  { value: 'emergency_alert', label: t('Emergency Alerts') },
];

const getNumberConditions = (t: TFunction): SelectOption[] => [
  { value: 'lt', label: t('Less than') },
  { value: 'lte', label: t('Less than or equal to') },
  { value: 'eq', label: t('Equal to') },
  { value: 'gte', label: t('Greater than or equal to') },
  { value: 'gt', label: t('Greater than') },
];

const getEqOnlyCondition = (t: TFunction): SelectOption[] => [
  { value: 'eq', label: t('Equal to') },
];

export interface CriteriaMetricConfig {
  id: string;
  label: string;
  conditions: SelectOption[];
  inputType: 'text' | 'number' | 'dropdown';
  values?: SelectOption[];
}

export interface CriteriaTypeConfig {
  metrics: CriteriaMetricConfig[];
}

export function getCriteriaTypeMetrics(t: TFunction): Record<string, CriteriaTypeConfig> {
  return {
    weather: {
      metrics: [
        {
          id: 'weather_condition',
          label: t('Weather Condition'),
          conditions: getEqOnlyCondition(t),
          inputType: 'dropdown',
          values: [
            { value: 'thunderstorm', label: t('Thunderstorm') },
            { value: 'drizzle', label: t('Drizzle') },
            { value: 'rain', label: t('Rain') },
            { value: 'snow', label: t('Snow') },
            { value: 'clear', label: t('Clear') },
            { value: 'clouds', label: t('Clouds') },
          ],
        },
        {
          id: 'weather_temp_imperial',
          label: t('Temperature (Imperial)'),
          conditions: getNumberConditions(t),
          inputType: 'number',
        },
        {
          id: 'weather_temp_metric',
          label: t('Temperature (Metric)'),
          conditions: getNumberConditions(t),
          inputType: 'number',
        },
        {
          id: 'weather_feels_like_imperial',
          label: t('Apparent Temperature (Imperial)'),
          conditions: getNumberConditions(t),
          inputType: 'number',
        },
        {
          id: 'weather_feels_like_metric',
          label: t('Apparent Temperature (Metric)'),
          conditions: getNumberConditions(t),
          inputType: 'number',
        },
        {
          id: 'weather_wind_speed',
          label: t('Wind Speed'),
          conditions: getNumberConditions(t),
          inputType: 'number',
        },
        {
          id: 'weather_wind_direction',
          label: t('Wind Direction'),
          conditions: getEqOnlyCondition(t),
          inputType: 'dropdown',
          values: [
            { value: 'N', label: t('North') },
            { value: 'NE', label: t('Northeast') },
            { value: 'E', label: t('East') },
            { value: 'SE', label: t('Southeast') },
            { value: 'S', label: t('South') },
            { value: 'SW', label: t('Southwest') },
            { value: 'W', label: t('West') },
            { value: 'NW', label: t('Northwest') },
          ],
        },
        {
          id: 'weather_wind_degrees',
          label: t('Wind Direction (degrees)'),
          conditions: getNumberConditions(t),
          inputType: 'number',
        },
        {
          id: 'weather_humidity',
          label: t('Humidity (Percent)'),
          conditions: getNumberConditions(t),
          inputType: 'number',
        },
        {
          id: 'weather_pressure',
          label: t('Pressure'),
          conditions: getNumberConditions(t),
          inputType: 'number',
        },
        {
          id: 'weather_visibility',
          label: t('Visibility (metres)'),
          conditions: getNumberConditions(t),
          inputType: 'number',
        },
      ],
    },
    emergency_alert: {
      metrics: [
        {
          id: 'emergency_alert_status',
          label: t('Status'),
          conditions: getEqOnlyCondition(t),
          inputType: 'dropdown',
          values: [
            { value: 'actual_alerts', label: t('Actual Alerts') },
            { value: 'test_alerts', label: t('Test Alerts') },
            { value: 'no_alerts', label: t('No Alerts') },
          ],
        },
        {
          id: 'emergency_alert_category',
          label: t('Category'),
          conditions: getEqOnlyCondition(t),
          inputType: 'dropdown',
          values: [
            { value: 'Geo', label: t('Geo') },
            { value: 'Met', label: t('Met') },
            { value: 'Safety', label: t('Safety') },
            { value: 'Security', label: t('Security') },
            { value: 'Rescue', label: t('Rescue') },
            { value: 'Fire', label: t('Fire') },
            { value: 'Health', label: t('Health') },
            { value: 'Env', label: t('Env') },
            { value: 'Transport', label: t('Transport') },
            { value: 'Infra', label: t('Infra') },
            { value: 'CBRNE', label: t('CBRNE') },
            { value: 'Other', label: t('Other') },
          ],
        },
      ],
    },
  };
}

export function getCriteriaMetricOptions(type: string, t: TFunction): SelectOption[] {
  const config = getCriteriaTypeMetrics(t)[type];
  if (!config) return [];
  return config.metrics.map((m) => ({ value: m.id, label: m.label }));
}

export function getCriteriaMetricConfig(
  type: string,
  metricId: string,
  t: TFunction,
): CriteriaMetricConfig | null {
  const config = getCriteriaTypeMetrics(t)[type];
  if (!config) return null;
  return config.metrics.find((m) => m.id === metricId) ?? null;
}

export const getRecurrenceTypeOptions = (t: TFunction): SelectOption[] => [
  { value: '', label: t('None') },
  { value: 'Minute', label: t('Minute') },
  { value: 'Hour', label: t('Hour') },
  { value: 'Day', label: t('Day') },
  { value: 'Week', label: t('Week') },
  { value: 'Month', label: t('Month') },
  { value: 'Year', label: t('Year') },
];

export const getReminderTypeOptions = (t: TFunction): SelectOption[] => [
  { value: String(ReminderType.Minute), label: t('Minute') },
  { value: String(ReminderType.Hour), label: t('Hour') },
  { value: String(ReminderType.Day), label: t('Day') },
  { value: String(ReminderType.Week), label: t('Week') },
  { value: String(ReminderType.Month), label: t('Month') },
];

export const getReminderOptionOptions = (t: TFunction): SelectOption[] => [
  { value: String(ReminderOption.BeforeStart), label: t('Before schedule starts') },
  { value: String(ReminderOption.AfterStart), label: t('After schedule starts') },
  { value: String(ReminderOption.BeforeEnd), label: t('Before schedule ends') },
  { value: String(ReminderOption.AfterEnd), label: t('After schedule ends') },
];

export const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as const;

export const EMPTY_CRITERION: DraftCriterion = {
  type: 'custom',
  metric: '',
  condition: 'set',
  value: '',
};

export const EMPTY_REMINDER: DraftReminder = {
  value: 0,
  type: ReminderType.Minute,
  option: ReminderOption.BeforeStart,
  isEmail: false,
};

export function createInitialDraft(
  eventTypeId?: EventTypeId,
  contentId?: number,
  prefilledDisplaySpecificGroupIds?: number[],
  prefilledDisplayGroupIds?: number[],
): ScheduleEventDraft {
  return {
    eventTypeId: eventTypeId ?? EventTypeId.Layout,
    mediaId: eventTypeId === EventTypeId.Media ? (contentId ?? null) : null,
    campaignId:
      eventTypeId &&
      [
        EventTypeId.Layout,
        EventTypeId.Overlay,
        EventTypeId.Interrupt,
        EventTypeId.Campaign,
      ].includes(eventTypeId)
        ? (contentId ?? null)
        : null,
    commandId: eventTypeId === EventTypeId.Command ? (contentId ?? null) : null,
    playlistId: eventTypeId === EventTypeId.Playlist ? (contentId ?? null) : null,
    syncGroupId: eventTypeId === EventTypeId.Sync ? (contentId ?? null) : null,
    dataSetId: eventTypeId === EventTypeId.DataConnector ? (contentId ?? null) : null,
    dataSetParams: '',
    syncDisplayLayouts: {},
    actionType: '',
    actionTriggerCode: '',
    actionLayoutCode: '',
    shareOfVoice: 0,
    displaySpecificGroupIds: prefilledDisplaySpecificGroupIds ?? [],
    displayGroupIds: prefilledDisplayGroupIds ?? [],
    dayPartId: '',
    fromDt: '',
    toDt: '',
    useRelativeTime: false,
    relativeHours: 0,
    relativeMinutes: 0,
    relativeSeconds: 0,
    name: '',
    layoutDuration: 0,
    resolutionId: '',
    backgroundColor: '#000000',
    displayOrder: 0,
    isPriority: 0,
    maxPlaysPerHour: 0,
    syncTimezone: false,
    recurrenceType: '',
    recurrenceDetail: 1,
    recurrenceRepeatsOn: [],
    recurrenceMonthlyRepeatsOn: 0,
    recurrenceRange: '',
    reminders: [{ ...EMPTY_REMINDER }],
    isGeoAware: false,
    geoLocation: '',
    criteria: [{ ...EMPTY_CRITERION }],
  };
}

export function createDraftFromEvent(scheduleEvent: Event): ScheduleEventDraft {
  return {
    eventTypeId: scheduleEvent.eventTypeId,
    mediaId: scheduleEvent.mediaId ?? null,
    campaignId: scheduleEvent.fullScreenCampaignId ?? scheduleEvent.campaignId ?? null,
    commandId: scheduleEvent.commandId ?? null,
    playlistId: scheduleEvent.playlistId ?? null,
    syncGroupId: scheduleEvent.syncGroupId ?? null,
    dataSetId: scheduleEvent.dataSetId ?? null,
    dataSetParams: scheduleEvent.dataSetParams ?? '',
    syncDisplayLayouts: {},
    actionType: scheduleEvent.actionType ?? '',
    actionTriggerCode: scheduleEvent.actionTriggerCode ?? '',
    actionLayoutCode: scheduleEvent.actionLayoutCode ?? '',
    shareOfVoice: Number(scheduleEvent.shareOfVoice ?? 0),
    displaySpecificGroupIds: (scheduleEvent.displayGroups ?? [])
      .filter((dg) => dg.isDisplaySpecific === 1)
      .map((dg) => dg.displayGroupId),
    displayGroupIds: (scheduleEvent.displayGroups ?? [])
      .filter((dg) => dg.isDisplaySpecific !== 1)
      .map((dg) => dg.displayGroupId),
    dayPartId: String(scheduleEvent.dayPartId),
    fromDt: scheduleEvent.fromDt ? new Date(scheduleEvent.fromDt * 1000).toISOString() : '',
    toDt: scheduleEvent.toDt ? new Date(scheduleEvent.toDt * 1000).toISOString() : '',
    useRelativeTime: false,
    relativeHours: 0,
    relativeMinutes: 0,
    relativeSeconds: 0,
    name: scheduleEvent.name ?? '',
    layoutDuration: Number(scheduleEvent.layoutDuration ?? 0),
    resolutionId: scheduleEvent.resolutionId ? String(scheduleEvent.resolutionId) : '',
    backgroundColor: scheduleEvent.backgroundColor ?? '#000000',
    displayOrder: Number(scheduleEvent.displayOrder ?? 0),
    isPriority: Number(scheduleEvent.isPriority ?? 0),
    maxPlaysPerHour: Number(scheduleEvent.maxPlaysPerHour ?? 0),
    syncTimezone: scheduleEvent.syncTimezone === 1,
    recurrenceType: scheduleEvent.recurrenceType ?? '',
    recurrenceDetail: Number(scheduleEvent.recurrenceDetail ?? 1),
    recurrenceRepeatsOn: scheduleEvent.recurrenceRepeatsOn
      ? scheduleEvent.recurrenceRepeatsOn.split(',')
      : [],
    recurrenceMonthlyRepeatsOn: Number(scheduleEvent.recurrenceMonthlyRepeatsOn ?? 0),
    recurrenceRange: scheduleEvent.recurrenceRange
      ? new Date(scheduleEvent.recurrenceRange * 1000).toISOString()
      : '',
    reminders:
      (scheduleEvent.scheduleReminders ?? []).length > 0
        ? scheduleEvent.scheduleReminders.map((r) => ({
            value: Number(r.value),
            type: Number(r.type),
            option: Number(r.option),
            isEmail: r.isEmail === 1,
          }))
        : [{ ...EMPTY_REMINDER }],
    isGeoAware: scheduleEvent.isGeoAware === 1,
    geoLocation: scheduleEvent.geoLocation ?? '',
    criteria:
      (scheduleEvent.criteria ?? []).length > 0
        ? scheduleEvent.criteria.map((c) => ({
            type: c.type,
            metric: c.metric,
            condition: c.condition,
            value: c.value,
          }))
        : [{ ...EMPTY_CRITERION }],
  };
}

export function getContentFieldConfig(eventTypeId: EventTypeId | null, t: (key: string) => string) {
  switch (eventTypeId) {
    case EventTypeId.Layout:
    case EventTypeId.Overlay:
    case EventTypeId.Interrupt:
      return { label: t('Layout'), placeholder: t('Select Layout') };
    case EventTypeId.Command:
      return { label: t('Command'), placeholder: t('Select Command') };
    case EventTypeId.Campaign:
      return { label: t('Campaign'), placeholder: t('Select Campaign') };
    case EventTypeId.Media:
      return { label: t('Media'), placeholder: t('Select Media') };
    case EventTypeId.Playlist:
      return { label: t('Playlist'), placeholder: t('Select Playlist') };
    case EventTypeId.Sync:
      return { label: t('Sync Group'), placeholder: t('Select Sync Group') };
    case EventTypeId.DataConnector:
      return { label: t('DataSet'), placeholder: t('Select DataSet') };
    default:
      return null;
  }
}

export function getContentHelpText(
  eventTypeId: EventTypeId | null,
  t: (key: string) => string,
): string {
  switch (eventTypeId) {
    case EventTypeId.Layout:
      return t('Select a Layout to schedule.');
    case EventTypeId.Overlay:
      return t('Select an Overlay Layout to schedule.');
    case EventTypeId.Interrupt:
      return t('Select an Interrupt Layout to schedule.');
    case EventTypeId.Command:
      return t('Select a Command to execute on the selected Displays.');
    case EventTypeId.Campaign:
      return t('Select a Campaign to schedule.');
    case EventTypeId.Media:
      return t(
        'Select a Media item to use. The selected media will be shown full screen for this event.',
      );
    case EventTypeId.Playlist:
      return t(
        'Select a Playlist to use. The selected playlist will be shown full screen for this event.',
      );
    case EventTypeId.Sync:
      return t('Select a Sync Group to schedule content across synchronised displays.');
    case EventTypeId.DataConnector:
      return t('Select a Real-Time DataSet to connect to this schedule.');
    default:
      return '';
  }
}

export function getContentValue(draft: ScheduleEventDraft): string {
  switch (draft.eventTypeId) {
    case EventTypeId.Media:
      return draft.mediaId
        ? String(draft.mediaId)
        : draft.campaignId
          ? String(draft.campaignId)
          : '';
    case EventTypeId.Playlist:
      return draft.playlistId
        ? String(draft.playlistId)
        : draft.campaignId
          ? String(draft.campaignId)
          : '';
    case EventTypeId.Command:
      return draft.commandId ? String(draft.commandId) : '';
    case EventTypeId.Layout:
    case EventTypeId.Overlay:
    case EventTypeId.Interrupt:
    case EventTypeId.Campaign:
      return draft.campaignId ? String(draft.campaignId) : '';
    case EventTypeId.Sync:
      return draft.syncGroupId ? String(draft.syncGroupId) : '';
    case EventTypeId.DataConnector:
      return draft.dataSetId ? String(draft.dataSetId) : '';
    default:
      return '';
  }
}

export function getPrefilledOption(contentId?: number, contentName?: string): SelectOption | null {
  if (contentId) {
    return { value: String(contentId), label: contentName || `#${contentId}` };
  }
  return null;
}

export const SYNC_STEP_LABELS = ['Content', 'Time', 'Optional'] as const;

export function getStepLabels(eventTypeId: EventTypeId | null): readonly string[] {
  if (eventTypeId === EventTypeId.Sync) {
    return SYNC_STEP_LABELS;
  }
  return STEP_LABELS;
}

export function buildSteps(
  currentStep: number,
  maxReachedStep: number,
  t: (key: string) => string,
  stepLabels: readonly string[] = STEP_LABELS,
) {
  const isLastStep = currentStep === stepLabels.length - 1;

  return stepLabels.map((label, index) => ({
    label: t(label),
    status:
      isLastStep || index < currentStep
        ? ('completed' as const)
        : index === currentStep
          ? ('active' as const)
          : index <= maxReachedStep
            ? ('reachable' as const)
            : ('inactive' as const),
  }));
}
