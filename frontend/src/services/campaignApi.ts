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
import type { Tag } from '@/types/tag';

export interface FetchCampaignRequest {
  name?: string;
  folderId?: number;
  retired?: number;
  signal?: AbortSignal;
}

export interface FetchCampaignResponse {
  rows: Campaign[];
  totalCount: number;
}

export async function fetchCampaignsList(
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

export interface FetchCampaignTableRequest {
  start: number;
  length: number;

  keyword?: string;
  sortBy?: string;
  sortDir?: string;

  folderId?: number;
  retired?: number;

  tags?: string;

  isLayoutSpecific?: number;
  layoutId?: number;
  type?: string;
  cyclePlaybackEnabled?: number;

  signal?: AbortSignal;
}

export interface FetchCampaignTableResponse {
  rows: Campaign[];
  totalCount: number;
}

export async function fetchCampaigns(
  options: FetchCampaignTableRequest = { start: 0, length: 10 },
): Promise<FetchCampaignTableResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/campaign', {
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

export interface CreateCampaignPayload {
  name: string;
  folderId?: number | null;
  tags?: Tag[];
  cyclePlaybackEnabled: boolean;
  playCount?: number;
  listPlayOrder?: 'round' | 'block';
}

export async function createCampaign(payload: CreateCampaignPayload) {
  const formData = new URLSearchParams();

  formData.append('name', payload.name);

  if (payload.folderId) {
    formData.append('folderId', String(payload.folderId));
  }

  // ✅ transform tags here
  if (payload.tags && payload.tags.length > 0) {
    const tags = payload.tags.map((t) => (t.value ? `${t.tag}|${t.value}` : t.tag)).join(',');

    formData.append('tags', tags);
  }

  formData.append('cyclePlaybackEnabled', payload.cyclePlaybackEnabled ? '1' : '0');

  if (payload.cyclePlaybackEnabled) {
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
