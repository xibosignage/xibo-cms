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

import http from '@/lib/api';
import type { DisplayProfile, DisplayProfileType } from '@/types/displayProfile';

export interface FetchDisplayProfileRequest {
  start: number;
  length: number;
  keyword?: string;
  type?: DisplayProfileType;
  embed?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchDisplayProfileResponse {
  rows: DisplayProfile[];
  totalCount: number;
}

export async function fetchDisplayProfile(
  options: FetchDisplayProfileRequest = { start: 0, length: 10 },
): Promise<FetchDisplayProfileResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/displayprofile', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows, totalCount };
}

export async function fetchDisplayProfileById(displayProfileId: number): Promise<DisplayProfile> {
  const response = await http.get('/displayprofile', {
    params: {
      displayProfileId,
      embed: 'config,commands,configWithDefault',
    },
  });

  return response.data[0];
}

export interface CreateDisplayProfileRequest {
  name: string;
  type: DisplayProfileType;
  isDefault: number;
}

export async function createDisplayProfile(
  data: CreateDisplayProfileRequest,
): Promise<DisplayProfile> {
  const params = new URLSearchParams();
  params.append('name', data.name);
  params.append('type', data.type);
  params.append('isDefault', data.isDefault ? 'on' : 'off');

  const response = await http.post('/displayprofile', params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export interface TimerEntry {
  day: string;
  on: string; // HH:mm
  off: string; // HH:mm
}

export interface PictureControlEntry {
  property: string;
  value: number;
}

export interface LockOptionsPayload {
  usblock: string; // 'true' | 'false' | 'empty'
  osdlock: string; // 'true' | 'false' | 'empty'
  keylockLocal: string;
  keylockRemote: string;
}

export interface UpdateDisplayProfileRequest {
  name: string;
  isDefault: number;
  config: Record<string, string | number | null>;
  timers?: TimerEntry[];
  pictureControls?: PictureControlEntry[];
  lockOptions?: LockOptionsPayload;
}

export async function updateDisplayProfile(
  displayProfileId: number | string,
  data: UpdateDisplayProfileRequest,
): Promise<DisplayProfile> {
  const params = new URLSearchParams();
  params.append('name', data.name);
  params.append('isDefault', data.isDefault ? 'on' : 'off');

  for (const [key, value] of Object.entries(data.config)) {
    if (value !== null && value !== undefined) {
      params.append(key, String(value));
    }
  }

  if (data.timers !== undefined) {
    const timersToSend = data.timers.length > 0 ? data.timers : [{ day: '', on: '', off: '' }];
    timersToSend.forEach((timer, i) => {
      params.append(`timers[${i}][day]`, timer.day);
      params.append(`timers[${i}][on]`, timer.on);
      params.append(`timers[${i}][off]`, timer.off);
    });
  }

  if (data.pictureControls !== undefined) {
    const controlsToSend =
      data.pictureControls.length > 0 ? data.pictureControls : [{ property: '', value: 0 }];
    controlsToSend.forEach((ctrl, i) => {
      params.append(`pictureControls[${i}][property]`, ctrl.property);
      params.append(`pictureControls[${i}][value]`, String(ctrl.value));
    });
  }

  if (data.lockOptions) {
    params.append('usblock', data.lockOptions.usblock);
    params.append('osdlock', data.lockOptions.osdlock);
    if (data.lockOptions.keylockLocal) {
      params.append('keylockLocal', data.lockOptions.keylockLocal);
    }
    if (data.lockOptions.keylockRemote) {
      params.append('keylockRemote', data.lockOptions.keylockRemote);
    }
  }

  const response = await http.put(`/displayprofile/${displayProfileId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function copyDisplayProfile(
  displayProfileId: number | string,
  name: string,
): Promise<DisplayProfile> {
  const params = new URLSearchParams();
  params.append('name', name);

  const response = await http.post(`/displayprofile/${displayProfileId}/copy`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deleteDisplayProfile(displayProfileId: number | string): Promise<void> {
  await http.delete(`/displayprofile/${displayProfileId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
