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

import axios from 'axios';

import http from '@/lib/api';
import type { Display } from '@/types/display';
import type { DisplayGroup } from '@/types/displayGroup';
import type { Layout } from '@/types/layout';
import type { Media } from '@/types/media';

export interface FetchDisplaysRequest {
  start: number;
  length: number;
  keyword?: string;
  mediaInventoryStatus?: number | string;
  loggedIn?: number | string;
  authorised?: number | string;
  xmrRegistered?: number | string;
  clientType?: string;
  displayGroupId?: number | string;
  displayProfileId?: number | string;
  orientation?: string;
  commercialLicence?: number | string;
  isPlayerSupported?: number | string;
  clientCode?: string;
  customId?: string;
  macAddress?: string;
  clientAddress?: string;
  lastAccessed?: string;
  folderId?: number | null;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchDisplaysResponse {
  rows: Display[];
  totalCount: number;
}

export async function fetchDisplays(
  options: FetchDisplaysRequest = { start: 0, length: 10 },
): Promise<FetchDisplaysResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/display', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows, totalCount };
}

export interface UpdateDisplayRequest {
  display: string;
  description?: string;
  licensed?: number;
  incSchedule?: number;
  emailAlert?: number;
  alertTimeout?: number;
  latitude?: number | null;
  longitude?: number | null;
  timeZone?: string;
  address?: string;
  screenSize?: number | null;
  displayTypeId?: number | null;
  venueId?: number | null;
  isMobile?: number;
  isOutdoor?: number;
  bandwidthLimit?: number | null;
  costPerPlay?: number | null;
  impressionsPerPlay?: number | null;
  ref1?: string;
  ref2?: string;
  ref3?: string;
  ref4?: string;
  ref5?: string;
  customId?: string;
  displayProfileId?: number | null;
  defaultLayoutId?: number | null;
  license?: string;
  tags?: string;
  languages?: string[];
  wakeOnLanEnabled?: number;
  broadCastAddress?: string;
  secureOn?: string;
  wakeOnLanTime?: string;
  cidr?: string;
  teamViewerSerial?: string;
  webkeySerial?: string;
  auditingUntil?: string;
  clearCachedData?: number;
  rekeyXmr?: number;
  folderId?: number | null;
  overrideValues?: Record<string, string>;
}

export async function updateDisplay(
  displayId: number | string,
  data: UpdateDisplayRequest,
): Promise<Display> {
  const params = new URLSearchParams();
  const { overrideValues, languages, ...rest } = data;

  Object.entries(rest).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.append(key, String(value));
    }
  });

  if (languages && languages.length > 0) {
    languages.forEach((lang) => params.append('languages[]', lang));
  }

  if (overrideValues) {
    Object.entries(overrideValues).forEach(([key, value]) => {
      if (key === 'pictureOptions') {
        try {
          const parsed = JSON.parse(value) as Record<string, string | number>;
          const entries = Object.entries(parsed).filter(([prop]) => prop !== '');
          if (entries.length > 0) {
            entries.forEach(([property, val], i) => {
              params.append(`pictureControls[${i}][property]`, property);
              params.append(`pictureControls[${i}][value]`, String(val));
            });
          } else {
            params.append('pictureControls[0][property]', '');
            params.append('pictureControls[0][value]', '0');
          }
        } catch {
          params.append(key, value);
        }
      } else if (key === 'timers') {
        try {
          const parsed = JSON.parse(value) as Record<string, { on?: string; off?: string }>;
          const entries = Object.entries(parsed).filter(([day]) => day !== '');
          if (entries.length > 0) {
            entries.forEach(([day, times], i) => {
              params.append(`timers[${i}][day]`, day);
              params.append(`timers[${i}][on]`, times.on ?? '');
              params.append(`timers[${i}][off]`, times.off ?? '');
            });
          } else {
            params.append('timers[0][day]', '');
            params.append('timers[0][on]', '');
            params.append('timers[0][off]', '');
          }
        } catch {
          params.append(key, value);
        }
      } else if (key === 'lockOptions') {
        try {
          const parsed = JSON.parse(value) as {
            usblock?: boolean | null;
            osdlock?: boolean | null;
            keylock?: { local?: string; remote?: string };
          };
          params.append(
            'usblock',
            parsed.usblock === true ? 'true' : parsed.usblock === false ? 'false' : 'empty',
          );
          params.append(
            'osdlock',
            parsed.osdlock === true ? 'true' : parsed.osdlock === false ? 'false' : 'empty',
          );
          if (parsed.keylock?.local) {
            params.append('keylockLocal', parsed.keylock.local);
          }
          if (parsed.keylock?.remote) {
            params.append('keylockRemote', parsed.keylock.remote);
          }
        } catch {
          params.append(key, value);
        }
      } else {
        params.append(key, value);
      }
    });
  }

  const response = await http.put(`/display/${displayId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deleteDisplay(displayId: number | string): Promise<void> {
  await http.delete(`/display/${displayId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function toggleDisplayAuthorised(displayId: number | string): Promise<Display> {
  const response = await http.put(`/display/authorise/${displayId}`, null, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  return response.data;
}

export interface DisplayMapFeature {
  type: 'Feature';
  geometry: { type: 'Point'; coordinates: [number, number] };
  properties: {
    displayId: number;
    display: string;
    status: string;
    mediaInventoryStatus: number;
    loggedIn: number;
    orientation: string;
    displayProfile: string;
    resolution: string | null;
    lastAccessed: number | null;
    thumbnail?: string;
  };
}

export interface DisplayMapFeatureCollection {
  type: 'FeatureCollection';
  features: DisplayMapFeature[];
}

export async function fetchDisplaysMap(
  params: Record<string, unknown> = {},
): Promise<DisplayMapFeatureCollection> {
  const response = await axios.get('/display/map', { params, withCredentials: true });
  return response.data;
}

export interface DisplayVenue {
  venueId: number;
  venueName: string;
}

export async function fetchDisplayVenues(): Promise<DisplayVenue[]> {
  const response = await http.get('/displayvenue');
  return response.data;
}

export async function fetchDisplayLocales(): Promise<{ id: string; value: string }[]> {
  const response = await http.get('/display/locales');
  return response.data;
}

export async function addDisplayViaCode(userCode: string): Promise<void> {
  const params = new URLSearchParams({ user_code: userCode });
  await http.post('/display/addViaCode', params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}

export async function checkLicence(displayId: number | string): Promise<void> {
  await http.put(`/display/licenceCheck/${displayId}`, null, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function requestScreenShot(displayId: number | string): Promise<void> {
  await http.put(`/display/requestscreenshot/${displayId}`, null, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function collectNow(displayGroupId: number | string): Promise<void> {
  await http.post(`/displaygroup/${displayGroupId}/action/collectNow`, null, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function wakeOnLan(displayId: number | string): Promise<void> {
  await http.post(`/display/wol/${displayId}`, null, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function purgeAll(displayId: number | string): Promise<void> {
  await http.put(`/display/purgeAll/${displayId}`, null, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function triggerWebhook(
  displayGroupId: number | string,
  triggerCode: string,
): Promise<void> {
  const params = new URLSearchParams({ triggerCode });
  await http.post(`/displaygroup/${displayGroupId}/action/triggerWebhook`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function setDefaultLayout(displayId: number, layoutId: number): Promise<void> {
  const params = new URLSearchParams();
  params.append('layoutId', String(layoutId));
  await http.put(`/display/defaultlayout/${displayId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });
}

export interface MoveCmsData {
  newCmsAddress: string;
  newCmsKey: string;
  twoFactorCode: string;
}

export async function moveCms(displayId: number | string, data: MoveCmsData): Promise<void> {
  const params = new URLSearchParams({
    newCmsAddress: data.newCmsAddress,
    newCmsKey: data.newCmsKey,
    twoFactorCode: data.twoFactorCode,
  });
  await http.put(`/display/${displayId}/moveCms`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function moveCmsCancel(displayId: number | string): Promise<void> {
  await http.delete(`/display/${displayId}/moveCms`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function setBandwidthLimitMultiple(
  ids: number[],
  bandwidthLimitKb: number,
): Promise<void> {
  const params = new URLSearchParams({
    ids: ids.join(','),
    bandwidthLimit: String(bandwidthLimitKb),
    bandwidthLimitUnits: 'kb',
  });
  await http.put('/display/setBandwidthLimit/multi', params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function sendCommand(
  displayGroupId: number | string,
  commandId: number,
): Promise<void> {
  const params = new URLSearchParams({ commandId: String(commandId) });
  await http.post(`/displaygroup/${displayGroupId}/action/command`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function fetchDisplayGroupMedia(displayGroupId: number | string): Promise<Media[]> {
  const response = await http.get(`/displaygroup/${displayGroupId}/media`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  return response.data;
}

export async function assignMedia(
  displayGroupId: number | string,
  mediaIds: number[],
  unassignMediaIds: number[] = [],
): Promise<void> {
  const params = new URLSearchParams();
  mediaIds.forEach((id) => params.append('mediaId[]', String(id)));
  unassignMediaIds.forEach((id) => params.append('unassignMediaId[]', String(id)));
  await http.post(`/displaygroup/${displayGroupId}/media/assign`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function fetchDisplayGroupLayouts(displayGroupId: number | string): Promise<Layout[]> {
  const response = await http.get(`/displaygroup/${displayGroupId}/layouts`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  return response.data;
}

export async function assignLayouts(
  displayGroupId: number | string,
  layoutIds: number[],
  unassignLayoutIds: number[] = [],
): Promise<void> {
  const params = new URLSearchParams();
  layoutIds.forEach((id) => params.append('layoutId[]', String(id)));
  unassignLayoutIds.forEach((id) => params.append('unassignLayoutId[]', String(id)));
  await http.post(`/displaygroup/${displayGroupId}/layout/assign`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function fetchDisplayGroupMembership(
  displayGroupId: number | string,
): Promise<DisplayGroup[]> {
  const response = await http.get('/displaygroup', {
    params: { displayId: displayGroupId, isDisplaySpecific: 0 },
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  return response.data;
}

export async function assignDisplayGroups(
  displayId: number | string,
  displayGroupIds: number[],
  unassignDisplayGroupIds: number[] = [],
): Promise<void> {
  const params = new URLSearchParams();
  displayGroupIds.forEach((id) => params.append('displayGroupId[]', String(id)));
  unassignDisplayGroupIds.forEach((id) => params.append('unassignDisplayGroupId[]', String(id)));
  await http.post(`/display/${displayId}/displaygroup/assign`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}
