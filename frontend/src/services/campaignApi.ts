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
import type { Campaign } from '@/types/campaign';

export interface FetchCampaignRequest {
  start?: number;
  length?: number;

  name?: string;
  keyword?: string;
  sortBy?: string;
  sortDir?: string;

  folderId?: number;
  retired?: number;

  tags?: string;

  isLayoutSpecific?: number;
  hasLayouts?: number;
  layoutId?: number;
  type?: string;
  cyclePlaybackEnabled?: number;

  signal?: AbortSignal;
}

export interface FetchCampaignResponse {
  rows: Campaign[];
  totalCount: number;
}

export async function fetchCampaigns(
  options: FetchCampaignRequest = {},
): Promise<FetchCampaignResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/campaign', {
    params: queryParams,
    signal,
  });

  const rows = response.data;

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : rows.length;

  return {
    rows,
    totalCount,
  };
}

export interface CreateCampaignPayload {
  name: string;
  type?: string;
  folderId?: number | null;
  tags?: string;
  cyclePlaybackEnabled: boolean;
  playCount?: number;
  listPlayOrder?: 'round' | 'block';
  targetType?: string;
  target?: number;
}

export async function createCampaign(payload: CreateCampaignPayload) {
  const formData = new URLSearchParams();

  formData.append('name', payload.name);

  if (payload.type) {
    formData.append('type', payload.type);
  }

  if (payload.folderId) {
    formData.append('folderId', String(payload.folderId));
  }

  if (payload.tags) {
    formData.append('tags', payload.tags);
  }

  formData.append('cyclePlaybackEnabled', payload.cyclePlaybackEnabled ? '1' : '0');

  if (payload.type === 'ad') {
    if (payload.targetType) {
      formData.append('targetType', payload.targetType);
    }
    if (payload.target != null) {
      formData.append('target', String(payload.target));
    }
  } else if (payload.cyclePlaybackEnabled) {
    formData.append('playCount', String(payload.playCount ?? 1));
  } else if (payload.listPlayOrder) {
    formData.append('listPlayOrder', payload.listPlayOrder);
  }

  const response = await http.post('/campaign', formData, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  return response.data;
}

export interface UpdateCampaignPayload {
  name: string;
  folderId?: number | null;
  tags?: string;
  cyclePlaybackEnabled: number;
  playCount?: number;
  listPlayOrder?: string;
  ref1?: string;
  ref2?: string;
  ref3?: string;
  ref4?: string;
  ref5?: string;
}

export async function updateCampaign(
  campaignId: number,
  payload: UpdateCampaignPayload,
): Promise<Campaign> {
  const params = new URLSearchParams();
  params.append('name', payload.name);
  if (payload.folderId != null) params.append('folderId', String(payload.folderId));
  if (payload.tags !== undefined) params.append('tags', payload.tags);
  params.append('cyclePlaybackEnabled', String(payload.cyclePlaybackEnabled));
  if (payload.playCount !== undefined) params.append('playCount', String(payload.playCount));
  if (payload.listPlayOrder !== undefined) params.append('listPlayOrder', payload.listPlayOrder);
  if (payload.ref1 !== undefined) params.append('ref1', payload.ref1);
  if (payload.ref2 !== undefined) params.append('ref2', payload.ref2);
  if (payload.ref3 !== undefined) params.append('ref3', payload.ref3);
  if (payload.ref4 !== undefined) params.append('ref4', payload.ref4);
  if (payload.ref5 !== undefined) params.append('ref5', payload.ref5);

  const { data } = await http.put(`/campaign/${campaignId}`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return data;
}

export interface CopyCampaignPayload {
  name: string;
}

export async function copyCampaign(campaignId: number, payload: CopyCampaignPayload) {
  const formData = new URLSearchParams();

  formData.append('name', payload.name);

  const response = await http.post(`/campaign/${campaignId}/copy`, formData, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  return response.data;
}

export async function deleteCampaign(campaignId: number) {
  const response = await http.delete(`/campaign/${campaignId}`);
  return response.data;
}
