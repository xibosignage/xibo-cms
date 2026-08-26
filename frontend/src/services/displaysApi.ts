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

import { withPublicPath } from '@/config/publicPath';
import http from '@/lib/api';
import type { Display } from '@/types/display';
import type { DisplayGroup } from '@/types/displayGroup';
import type {
  BandwidthResponse,
  DisconnectionEvent,
  DisplayManageData,
  DisplayScreenshot,
  PlayerFault,
} from '@/types/displayManage';
import type { DisplayNextSchedule, DisplayOverviewSummary } from '@/types/displayOverview';
import type { Layout } from '@/types/layout';
import type { Media } from '@/types/media';

export interface FetchDisplaysRequest {
  start: number;
  length: number;
  displayId?: number;
  display?: string;
  useRegexForName?: number;
  tags?: string;
  exactTags?: number;
  logicalOperator?: 'OR' | 'AND';
  logicalOperatorName?: 'OR' | 'AND';
  mediaInventoryStatus?: number | string;
  loggedIn?: number | string;
  // Server-side buckets for the Display Overview page's KPI tiles — mirror the
  // aggregate query behind GET /display/overview/summary so the card grid
  // never re-derives bucket membership client-side.
  needsAttention?: number | string;
  faults?: number | string;
  authorised?: number | string;
  xmrRegistered?: number | string;
  clientType?: string;
  displayGroupId?: number | string;
  displayGroupIds?: number[];
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

export async function fetchDisplayOverviewSummary(
  signal?: AbortSignal,
): Promise<DisplayOverviewSummary> {
  const response = await http.get<DisplayOverviewSummary>('/display/overview/summary', {
    signal,
  });
  return response.data;
}

export async function fetchDisplayNextSchedule(
  displayId: number,
  signal?: AbortSignal,
): Promise<DisplayNextSchedule | null> {
  const response = await http.get<DisplayNextSchedule | null>(
    `/display/${displayId}/schedule/next`,
    { signal },
  );
  return response.data;
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
          const parsed = JSON.parse(value) as unknown;
          if (Array.isArray(parsed)) {
            // Hisense format: flat array of indexed rules
            (
              parsed as Array<{
                index: number;
                dayScope: number;
                time: string;
                manualWeeks?: number[];
              }>
            ).forEach((rule, i) => {
              params.append(`timers[${i}][index]`, String(rule.index));
              params.append(`timers[${i}][type]`, String(rule.dayScope));
              params.append(`timers[${i}][time]`, rule.time);
              if (rule.manualWeeks) {
                rule.manualWeeks.forEach((day, j) => {
                  params.append(`timers[${i}][manualWeeks][${j}]`, String(day));
                });
              }
            });
          } else {
            // LG/SSSP format: day-keyed object
            const entries = Object.entries(
              parsed as Record<string, { on?: string; off?: string }>,
            ).filter(([day]) => day !== '');
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
          }
        } catch {
          params.append(key, value);
        }
      } else if (key === 'hisensePictureOptions') {
        // Decompose grouped hisense picture JSON into individual params
        try {
          const parsed = JSON.parse(value) as Record<string, number>;
          Object.entries(parsed).forEach(([prop, val]) => {
            if (prop !== '') {
              params.append(prop, String(val));
            }
          });
        } catch (e) {
          console.warn('Failed to parse hisensePictureOptions override JSON:', e);
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
  const response = await axios.get(withPublicPath('display/map'), {
    params,
    withCredentials: true,
  });
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

export interface AddDisplayViaCodePayload {
  userCode: string;
  /** Cached by the CMS against the code and applied when the Player registers. */
  displayName?: string;
  folderId?: number | null;
  displayGroupId?: number | null;
  authorised?: boolean;
}

/**
 * Submit an activation code. The CMS relays it to the authentication service; the Player picks the
 * CMS details up moments later and registers itself, which is what actually creates the display.
 *
 * The optional settings are not applied here - the CMS caches them against the code and applies
 * them itself when the Player registers. useConnectWatcher only exists so the UI can tell the
 * operator when that has happened.
 */
export async function addDisplayViaCode(payload: AddDisplayViaCodePayload): Promise<void> {
  const params = new URLSearchParams({ user_code: payload.userCode });

  if (payload.displayName) {
    params.set('displayName', payload.displayName);
  }
  if (payload.folderId) {
    params.set('folderId', String(payload.folderId));
  }
  if (payload.displayGroupId) {
    params.set('displayGroupId', String(payload.displayGroupId));
  }
  if (payload.authorised) {
    params.set('authorised', '1');
  }

  await http.post('/display/addViaCode', params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}

export interface LicenceUsage {
  /** 0 means unlimited. */
  maxLicensed: number;
  currentlyLicensed: number;
  /** null means unlimited. */
  available: number | null;
}

export async function fetchLicenceUsage(): Promise<LicenceUsage> {
  // The state's data is emitted at the top level, not wrapped in a "data" envelope.
  const response = await http.get('/display/licence/usage');
  return response.data;
}

export interface ConnectDetails {
  /** The address a Player must be pointed at, as the CMS itself resolves it. */
  cmsAddress: string;
  cmsKey: string;
}

/**
 * The CMS Address and Key an operator types into a Player when configuring it by hand.
 *
 * Read from the CMS rather than derived from window.location, because WHITELIST_HOSTS decides the
 * address a Player can actually reach, which is not necessarily the one the browser used.
 */
export async function fetchConnectDetails(): Promise<ConnectDetails> {
  const response = await http.get('/display/connect/details');
  return response.data;
}

export interface ConnectCode {
  /** Four characters, unambiguous alphabet, single use. */
  code: string;
  expiresInMinutes: number;
}

/**
 * Issue a one-time code identifying a Player about to be configured by hand.
 *
 * It is appended to the CMS key the operator copies into the Player. RegisterDisplay parses it,
 * which is what lets the CMS say a given registration belongs to this form rather than guessing
 * from whatever registered most recently.
 */
export async function fetchConnectCode(): Promise<ConnectCode> {
  const response = await http.post('/display/connect/code');
  return response.data;
}

export interface ConnectStatus {
  /** The code was never issued, or has passed its 30 minute life. */
  expired: boolean;
  connected: boolean;
  displayId: number | null;
  display: string | null;
}

/** Has the Player holding this code registered yet? An identity check, not a search. */
export async function fetchConnectStatus(code: string): Promise<ConnectStatus> {
  const response = await http.get('/display/connect/status', { params: { code } });
  return response.data;
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

export async function fetchScreenshotHistory(
  displayId: number | string,
  signal?: AbortSignal,
): Promise<DisplayScreenshot[]> {
  const response = await http.get(`/display/screenshot/${displayId}/history`, { signal });
  return response.data;
}

/** One image out of the history, rather than the display's current one. */
export async function fetchHistoryScreenshotBlob(
  displayId: number | string,
  screenshotId: number,
  signal?: AbortSignal,
): Promise<Blob> {
  const response = await http.get(`/display/screenshot/${displayId}/history/${screenshotId}`, {
    responseType: 'blob',
    signal,
  });
  return response.data;
}

/** Minutes between automatic screenshots, as an override on the display's profile. 0 turns it off. */
export async function setScreenShotInterval(
  displayId: number | string,
  screenShotRequestInterval: number,
): Promise<void> {
  const params = new URLSearchParams({
    screenShotRequestInterval: String(screenShotRequestInterval),
  });
  await http.put(`/display/screenshotinterval/${displayId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function fetchDisplayScreenshotBlob(
  displayId: number | string,
  signal?: AbortSignal,
): Promise<Blob> {
  const response = await http.get(`/display/screenshot/${displayId}`, {
    responseType: 'blob',
    signal,
  });
  return response.data;
}

export type DisplayStatusWindow = Record<string, string | number> | string | unknown[];

export async function fetchDisplayStatusWindow(
  displayId: number | string,
  signal?: AbortSignal,
): Promise<DisplayStatusWindow> {
  const response = await http.get(`/display/status/${displayId}`, { signal });
  return response.data;
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

export async function fetchDisplayManageData(
  displayId: number,
  signal?: AbortSignal,
): Promise<DisplayManageData> {
  const response = await http.get(`/display/manage/${displayId}`, { signal });
  return response.data;
}

export async function fetchPlayerFaults(
  displayId: number,
  signal?: AbortSignal,
  params?: { activeOnly?: number },
): Promise<PlayerFault[]> {
  const response = await http.get(`/display/faults/${displayId}`, { signal, params });
  return response.data;
}

export async function fetchBandwidthData(
  params: { displayId: number; fromDt: string; toDt: string },
  signal?: AbortSignal,
): Promise<BandwidthResponse> {
  const response = await http.get('/stats/data/bandwidth', { params, signal });
  return response.data;
}

/**
 * Disconnection events for a display over a period.
 *
 * `eventTypeIds` narrows the displayevent table, which also holds unrelated event types such as
 * command and app start. Omit it to get everything.
 */
export async function fetchDisconnectionEvents(
  params: { displayId: number; fromDt: string; toDt: string; eventTypeIds?: number[] },
  signal?: AbortSignal,
): Promise<DisconnectionEvent[]> {
  const query = new URLSearchParams({
    displayId: String(params.displayId),
    fromDt: params.fromDt,
    toDt: params.toDt,
    // This endpoint's paging otherwise defaults to LIMIT 0, 10 and truncates the results.
    disablePaging: '1',
  });

  params.eventTypeIds?.forEach((id) => query.append('eventTypeIds[]', String(id)));

  const response = await http.get(`/stats/timeDisconnected?${query.toString()}`, { signal });
  return response.data;
}
