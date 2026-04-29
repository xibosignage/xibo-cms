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

import { isAxiosError } from 'axios';

import http from '@/lib/api';
import type { Event } from '@/types/event';
import { formatDateTime } from '@/utils/date';

export interface FetchEventRequest {
  start: number;
  length: number;
  name?: string;
  eventTypeId?: number | null;
  campaignId?: number | null;
  displaySpecificGroupIds?: number[];
  displayGroupIds?: number[];
  geoAware?: number | null;
  recurring?: number | null;
  directSchedule?: number | null;
  sharedSchedule?: number | null;
  fromDt?: string;
  toDt?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchEventResponse {
  rows: Event[];
  totalCount: number;
}

export async function fetchEvent(
  options: FetchEventRequest = { start: 0, length: 10 },
): Promise<FetchEventResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/schedule', {
    params: queryParams,
    signal,
  });

  const rows = response.data;

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return {
    rows,
    totalCount,
  };
}

export async function fetchEventById(eventId: number): Promise<Event> {
  const response = await http.get<Event>(`/schedule/${eventId}`);
  return response.data;
}

export async function deleteEvent(eventId: number | string): Promise<void> {
  await http.delete(`/schedule/${eventId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function deleteEventOccurrence(
  eventId: number | string,
  eventStart: number,
  eventEnd: number,
): Promise<void> {
  await http.delete(`/schedulerecurrence/${eventId}`, {
    params: { eventStart, eventEnd },
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export interface CloneEventRequest {
  eventId: number | string;
  name: string;
  signal?: AbortSignal;
}

export async function cloneEvent({ eventId, name }: CloneEventRequest): Promise<Event> {
  const params = new URLSearchParams();

  params.append('name', name);

  const response = await http.post(`/schedule/copy/${eventId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export interface CreateEventRequest {
  eventTypeId: number;
  displayGroupIds: number[];
  dayPartId: number;

  campaignId?: number;
  fullScreenCampaignId?: number;
  commandId?: number;
  mediaId?: number;
  playlistId?: number;
  syncGroupId?: number;
  dataSetId?: number;
  dataSetParams?: string;
  syncDisplayLayouts?: Record<number, number | null>;
  actionType?: string;
  actionTriggerCode?: string;
  actionLayoutCode?: string;

  fromDt?: string;
  toDt?: string;
  recurrenceType?: string;
  recurrenceDetail?: number;
  recurrenceRepeatsOn?: number[];
  recurrenceMonthlyRepeatsOn?: number;
  recurrenceRange?: string;
  syncTimezone?: number;

  name?: string;
  resolutionId?: number;
  displayOrder?: number;
  isPriority?: number;
  maxPlaysPerHour?: number;

  shareOfVoice?: number;

  isGeoAware?: number;
  geoLocation?: string;

  backgroundColor?: string;
  layoutDuration?: number;

  criteria?: {
    type: string;
    metric: string;
    condition: string;
    value: string;
  }[];

  scheduleReminders?: {
    reminder_value: number;
    reminder_type: number;
    reminder_option: number;
    reminder_isEmailHidden: number;
  }[];
}

export async function createEvent(data: CreateEventRequest, timezone = 'UTC'): Promise<Event> {
  const params = new URLSearchParams();

  params.append('eventTypeId', String(data.eventTypeId));
  params.append('dayPartId', String(data.dayPartId));

  data.displayGroupIds.forEach((id) => params.append('displayGroupIds[]', String(id)));

  if (data.campaignId) params.append('campaignId', String(data.campaignId));
  if (data.fullScreenCampaignId) {
    params.append('fullScreenCampaignId', String(data.fullScreenCampaignId));
  }
  if (data.commandId) params.append('commandId', String(data.commandId));
  if (data.mediaId) params.append('mediaId', String(data.mediaId));
  if (data.playlistId) params.append('playlistId', String(data.playlistId));
  if (data.syncGroupId) params.append('syncGroupId', String(data.syncGroupId));
  if (data.dataSetId) params.append('dataSetId', String(data.dataSetId));
  if (data.dataSetParams) params.append('dataSetParams', data.dataSetParams);
  if (data.syncDisplayLayouts) {
    Object.entries(data.syncDisplayLayouts).forEach(([displayId, layoutId]) => {
      if (layoutId) params.append(`layoutId_${displayId}`, String(layoutId));
    });
  }
  if (data.actionType) params.append('actionType', data.actionType);
  if (data.actionTriggerCode) params.append('actionTriggerCode', data.actionTriggerCode);
  if (data.actionLayoutCode) params.append('actionLayoutCode', data.actionLayoutCode);

  if (data.fromDt) params.append('fromDt', formatDateTime(new Date(data.fromDt), timezone));
  if (data.toDt) params.append('toDt', formatDateTime(new Date(data.toDt), timezone));
  if (data.recurrenceType) params.append('recurrenceType', data.recurrenceType);
  if (data.recurrenceDetail) params.append('recurrenceDetail', String(data.recurrenceDetail));
  if (data.recurrenceRepeatsOn) {
    data.recurrenceRepeatsOn.forEach((day) => params.append('recurrenceRepeatsOn[]', String(day)));
  }
  if (data.recurrenceMonthlyRepeatsOn != null) {
    params.append('recurrenceMonthlyRepeatsOn', String(data.recurrenceMonthlyRepeatsOn));
  }
  if (data.recurrenceRange) {
    params.append('recurrenceRange', formatDateTime(new Date(data.recurrenceRange), timezone));
  }
  if (data.syncTimezone != null) params.append('syncTimezone', String(data.syncTimezone));

  if (data.name) params.append('name', data.name);
  if (data.resolutionId) params.append('resolutionId', String(data.resolutionId));
  if (data.displayOrder != null) params.append('displayOrder', String(data.displayOrder));
  if (data.isPriority != null) params.append('isPriority', String(data.isPriority));
  if (data.maxPlaysPerHour != null) {
    params.append('maxPlaysPerHour', String(data.maxPlaysPerHour));
  }
  if (data.shareOfVoice != null) params.append('shareOfVoice', String(data.shareOfVoice));

  if (data.isGeoAware != null) params.append('isGeoAware', String(data.isGeoAware));
  if (data.geoLocation) params.append('geoLocation', data.geoLocation);

  if (data.backgroundColor) params.append('backgroundColor', data.backgroundColor);
  if (data.layoutDuration != null) params.append('layoutDuration', String(data.layoutDuration));

  if (data.criteria && data.criteria.length > 0) {
    data.criteria.forEach((c, i) => {
      params.append(`criteria[${i}][metric]`, c.metric);
      params.append(`criteria[${i}][type]`, c.type);
      params.append(`criteria[${i}][condition]`, c.condition);
      params.append(`criteria[${i}][value]`, c.value);
    });
  }

  if (data.scheduleReminders && data.scheduleReminders.length > 0) {
    data.scheduleReminders.forEach((r, i) => {
      params.append(`reminder_value[${i}]`, String(r.reminder_value));
      params.append(`reminder_type[${i}]`, String(r.reminder_type));
      params.append(`reminder_option[${i}]`, String(r.reminder_option));
      params.append(`reminder_isEmailHidden[${i}]`, String(r.reminder_isEmailHidden));
    });
  }

  try {
    const response = await http.post('/schedule', params.toString(), {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    return response.data;
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.message) {
      throw new Error(error.response.data.message);
    }
    throw error;
  }
}

export async function updateEvent(
  eventId: number | string,
  data: CreateEventRequest,
  timezone = 'UTC',
): Promise<Event> {
  const params = new URLSearchParams();

  params.append('eventTypeId', String(data.eventTypeId));
  params.append('dayPartId', String(data.dayPartId));

  data.displayGroupIds.forEach((id) => params.append('displayGroupIds[]', String(id)));

  if (data.campaignId) params.append('campaignId', String(data.campaignId));
  if (data.fullScreenCampaignId) {
    params.append('fullScreenCampaignId', String(data.fullScreenCampaignId));
  }
  if (data.commandId) params.append('commandId', String(data.commandId));
  if (data.mediaId) params.append('mediaId', String(data.mediaId));
  if (data.playlistId) params.append('playlistId', String(data.playlistId));
  if (data.syncGroupId) params.append('syncGroupId', String(data.syncGroupId));
  if (data.dataSetId) params.append('dataSetId', String(data.dataSetId));
  if (data.dataSetParams) params.append('dataSetParams', data.dataSetParams);
  if (data.syncDisplayLayouts) {
    Object.entries(data.syncDisplayLayouts).forEach(([displayId, layoutId]) => {
      if (layoutId) params.append(`layoutId_${displayId}`, String(layoutId));
    });
  }
  if (data.actionType) params.append('actionType', data.actionType);
  if (data.actionTriggerCode) params.append('actionTriggerCode', data.actionTriggerCode);
  if (data.actionLayoutCode) params.append('actionLayoutCode', data.actionLayoutCode);

  if (data.fromDt) params.append('fromDt', formatDateTime(new Date(data.fromDt), timezone));
  if (data.toDt) params.append('toDt', formatDateTime(new Date(data.toDt), timezone));
  if (data.recurrenceType) params.append('recurrenceType', data.recurrenceType);
  if (data.recurrenceDetail) params.append('recurrenceDetail', String(data.recurrenceDetail));
  if (data.recurrenceRepeatsOn) {
    data.recurrenceRepeatsOn.forEach((day) => params.append('recurrenceRepeatsOn[]', String(day)));
  }
  if (data.recurrenceMonthlyRepeatsOn != null) {
    params.append('recurrenceMonthlyRepeatsOn', String(data.recurrenceMonthlyRepeatsOn));
  }
  if (data.recurrenceRange) {
    params.append('recurrenceRange', formatDateTime(new Date(data.recurrenceRange), timezone));
  }
  if (data.syncTimezone != null) params.append('syncTimezone', String(data.syncTimezone));

  if (data.name) params.append('name', data.name);
  if (data.resolutionId) params.append('resolutionId', String(data.resolutionId));
  if (data.displayOrder != null) params.append('displayOrder', String(data.displayOrder));
  if (data.isPriority != null) params.append('isPriority', String(data.isPriority));
  if (data.maxPlaysPerHour != null) {
    params.append('maxPlaysPerHour', String(data.maxPlaysPerHour));
  }
  if (data.shareOfVoice != null) params.append('shareOfVoice', String(data.shareOfVoice));

  if (data.isGeoAware != null) params.append('isGeoAware', String(data.isGeoAware));
  if (data.geoLocation) params.append('geoLocation', data.geoLocation);

  if (data.backgroundColor) params.append('backgroundColor', data.backgroundColor);
  if (data.layoutDuration != null) params.append('layoutDuration', String(data.layoutDuration));

  if (data.criteria && data.criteria.length > 0) {
    data.criteria.forEach((c, i) => {
      params.append(`criteria[${i}][metric]`, c.metric);
      params.append(`criteria[${i}][type]`, c.type);
      params.append(`criteria[${i}][condition]`, c.condition);
      params.append(`criteria[${i}][value]`, c.value);
    });
  }

  if (data.scheduleReminders && data.scheduleReminders.length > 0) {
    data.scheduleReminders.forEach((r, i) => {
      params.append(`reminder_scheduleReminderId[${i}]`, '0');
      params.append(`reminder_value[${i}]`, String(r.reminder_value));
      params.append(`reminder_type[${i}]`, String(r.reminder_type));
      params.append(`reminder_option[${i}]`, String(r.reminder_option));
      params.append(`reminder_isEmailHidden[${i}]`, String(r.reminder_isEmailHidden));
    });
  }

  try {
    const response = await http.put(`/schedule/${eventId}`, params.toString(), {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    return response.data;
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.message) {
      throw new Error(error.response.data.message);
    }
    throw error;
  }
}
