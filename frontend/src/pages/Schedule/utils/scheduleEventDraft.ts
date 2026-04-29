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

export const EVENT_TYPE_OPTIONS: SelectOption[] = [
  { value: String(EventTypeId.Layout), label: 'Layout' },
  { value: String(EventTypeId.Command), label: 'Command' },
  { value: String(EventTypeId.Overlay), label: 'Overlay Layout' },
  { value: String(EventTypeId.Interrupt), label: 'Interrupt Layout' },
  { value: String(EventTypeId.Campaign), label: 'Campaign' },
  { value: String(EventTypeId.Action), label: 'Action' },
  { value: String(EventTypeId.Media), label: 'Media' },
  { value: String(EventTypeId.Playlist), label: 'Playlist' },
  { value: String(EventTypeId.Sync), label: 'Synchronised Event' },
  { value: String(EventTypeId.DataConnector), label: 'Data Connector' },
];

export const CONDITION_OPTIONS: SelectOption[] = [
  { value: 'set', label: 'Is set' },
  { value: 'lt', label: 'Less than' },
  { value: 'lte', label: 'Less than or equal to' },
  { value: 'eq', label: 'Equal to' },
  { value: 'neq', label: 'Not equal to' },
  { value: 'gte', label: 'Greater than or equal to' },
  { value: 'gt', label: 'Greater than' },
  { value: 'contains', label: 'Contains' },
  { value: 'ncontains', label: 'Not contains' },
];

export const CRITERIA_TYPE_OPTIONS: SelectOption[] = [
  { value: 'custom', label: 'Custom' },
  { value: 'weather', label: 'Weather' },
  { value: 'emergency_alert', label: 'Emergency Alerts' },
];

// Number comparison conditions shared by most weather metrics
const NUMBER_CONDITIONS: SelectOption[] = [
  { value: 'lt', label: 'Less than' },
  { value: 'lte', label: 'Less than or equal to' },
  { value: 'eq', label: 'Equal to' },
  { value: 'gte', label: 'Greater than or equal to' },
  { value: 'gt', label: 'Greater than' },
];

const EQ_ONLY_CONDITION: SelectOption[] = [{ value: 'eq', label: 'Equal to' }];

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

export const CRITERIA_TYPE_METRICS: Record<string, CriteriaTypeConfig> = {
  weather: {
    metrics: [
      {
        id: 'weather_condition',
        label: 'Weather Condition',
        conditions: EQ_ONLY_CONDITION,
        inputType: 'dropdown',
        values: [
          { value: 'thunderstorm', label: 'Thunderstorm' },
          { value: 'drizzle', label: 'Drizzle' },
          { value: 'rain', label: 'Rain' },
          { value: 'snow', label: 'Snow' },
          { value: 'clear', label: 'Clear' },
          { value: 'clouds', label: 'Clouds' },
        ],
      },
      {
        id: 'weather_temp_imperial',
        label: 'Temperature (Imperial)',
        conditions: NUMBER_CONDITIONS,
        inputType: 'number',
      },
      {
        id: 'weather_temp_metric',
        label: 'Temperature (Metric)',
        conditions: NUMBER_CONDITIONS,
        inputType: 'number',
      },
      {
        id: 'weather_feels_like_imperial',
        label: 'Apparent Temperature (Imperial)',
        conditions: NUMBER_CONDITIONS,
        inputType: 'number',
      },
      {
        id: 'weather_feels_like_metric',
        label: 'Apparent Temperature (Metric)',
        conditions: NUMBER_CONDITIONS,
        inputType: 'number',
      },
      {
        id: 'weather_wind_speed',
        label: 'Wind Speed',
        conditions: NUMBER_CONDITIONS,
        inputType: 'number',
      },
      {
        id: 'weather_wind_direction',
        label: 'Wind Direction',
        conditions: EQ_ONLY_CONDITION,
        inputType: 'dropdown',
        values: [
          { value: 'N', label: 'North' },
          { value: 'NE', label: 'Northeast' },
          { value: 'E', label: 'East' },
          { value: 'SE', label: 'Southeast' },
          { value: 'S', label: 'South' },
          { value: 'SW', label: 'Southwest' },
          { value: 'W', label: 'West' },
          { value: 'NW', label: 'Northwest' },
        ],
      },
      {
        id: 'weather_wind_degrees',
        label: 'Wind Direction (degrees)',
        conditions: NUMBER_CONDITIONS,
        inputType: 'number',
      },
      {
        id: 'weather_humidity',
        label: 'Humidity (Percent)',
        conditions: NUMBER_CONDITIONS,
        inputType: 'number',
      },
      {
        id: 'weather_pressure',
        label: 'Pressure',
        conditions: NUMBER_CONDITIONS,
        inputType: 'number',
      },
      {
        id: 'weather_visibility',
        label: 'Visibility (metres)',
        conditions: NUMBER_CONDITIONS,
        inputType: 'number',
      },
    ],
  },
  emergency_alert: {
    metrics: [
      {
        id: 'emergency_alert_status',
        label: 'Status',
        conditions: EQ_ONLY_CONDITION,
        inputType: 'dropdown',
        values: [
          { value: 'actual_alerts', label: 'Actual Alerts' },
          { value: 'test_alerts', label: 'Test Alerts' },
          { value: 'no_alerts', label: 'No Alerts' },
        ],
      },
      {
        id: 'emergency_alert_category',
        label: 'Category',
        conditions: EQ_ONLY_CONDITION,
        inputType: 'dropdown',
        values: [
          { value: 'Geo', label: 'Geo' },
          { value: 'Met', label: 'Met' },
          { value: 'Safety', label: 'Safety' },
          { value: 'Security', label: 'Security' },
          { value: 'Rescue', label: 'Rescue' },
          { value: 'Fire', label: 'Fire' },
          { value: 'Health', label: 'Health' },
          { value: 'Env', label: 'Env' },
          { value: 'Transport', label: 'Transport' },
          { value: 'Infra', label: 'Infra' },
          { value: 'CBRNE', label: 'CBRNE' },
          { value: 'Other', label: 'Other' },
        ],
      },
    ],
  },
};

export function getCriteriaMetricOptions(type: string): SelectOption[] {
  const config = CRITERIA_TYPE_METRICS[type];
  if (!config) return [];
  return config.metrics.map((m) => ({ value: m.id, label: m.label }));
}

export function getCriteriaMetricConfig(
  type: string,
  metricId: string,
): CriteriaMetricConfig | null {
  const config = CRITERIA_TYPE_METRICS[type];
  if (!config) return null;
  return config.metrics.find((m) => m.id === metricId) ?? null;
}

export const RECURRENCE_TYPE_OPTIONS: SelectOption[] = [
  { value: '', label: 'None' },
  { value: 'Minute', label: 'Minute' },
  { value: 'Hour', label: 'Hour' },
  { value: 'Day', label: 'Day' },
  { value: 'Week', label: 'Week' },
  { value: 'Month', label: 'Month' },
  { value: 'Year', label: 'Year' },
];

export const REMINDER_TYPE_OPTIONS: SelectOption[] = [
  { value: String(ReminderType.Minute), label: 'Minute' },
  { value: String(ReminderType.Hour), label: 'Hour' },
  { value: String(ReminderType.Day), label: 'Day' },
  { value: String(ReminderType.Week), label: 'Week' },
  { value: String(ReminderType.Month), label: 'Month' },
];

export const REMINDER_OPTION_OPTIONS: SelectOption[] = [
  { value: String(ReminderOption.BeforeStart), label: 'Before schedule starts' },
  { value: String(ReminderOption.AfterStart), label: 'After schedule starts' },
  { value: String(ReminderOption.BeforeEnd), label: 'Before schedule ends' },
  { value: String(ReminderOption.AfterEnd), label: 'After schedule ends' },
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
    displaySpecificGroupIds: [],
    displayGroupIds: [],
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
    shareOfVoice: scheduleEvent.shareOfVoice ?? 0,
    displaySpecificGroupIds: scheduleEvent.displayGroups
      .filter((dg) => dg.isDisplaySpecific === 1)
      .map((dg) => dg.displayGroupId),
    displayGroupIds: scheduleEvent.displayGroups
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
    layoutDuration: scheduleEvent.layoutDuration ?? 0,
    resolutionId: scheduleEvent.resolutionId ? String(scheduleEvent.resolutionId) : '',
    backgroundColor: scheduleEvent.backgroundColor ?? '#000000',
    displayOrder: scheduleEvent.displayOrder ?? 0,
    isPriority: scheduleEvent.isPriority ?? 0,
    maxPlaysPerHour: scheduleEvent.maxPlaysPerHour ?? 0,
    syncTimezone: scheduleEvent.syncTimezone === 1,
    recurrenceType: scheduleEvent.recurrenceType ?? '',
    recurrenceDetail: scheduleEvent.recurrenceDetail ?? 1,
    recurrenceRepeatsOn: scheduleEvent.recurrenceRepeatsOn
      ? scheduleEvent.recurrenceRepeatsOn.split(',')
      : [],
    recurrenceMonthlyRepeatsOn: scheduleEvent.recurrenceMonthlyRepeatsOn ?? 0,
    recurrenceRange: scheduleEvent.recurrenceRange
      ? new Date(scheduleEvent.recurrenceRange * 1000).toISOString()
      : '',
    reminders:
      scheduleEvent.scheduleReminders.length > 0
        ? scheduleEvent.scheduleReminders.map((r) => ({
            value: r.value,
            type: r.type,
            option: r.option,
            isEmail: r.isEmail === 1,
          }))
        : [{ ...EMPTY_REMINDER }],
    isGeoAware: scheduleEvent.isGeoAware === 1,
    geoLocation: scheduleEvent.geoLocation ?? '',
    criteria:
      scheduleEvent.criteria.length > 0
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
