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
import type { Tag, TagUsageEntry } from '@/types/tag';

export interface FetchTagsRequest {
  start?: number;
  length?: number;
  tagId?: number;
  keyword?: string;
  tag?: string;
  useRegexForName?: boolean;
  logicalOperatorName?: 'AND' | 'OR';
  allTags?: number;
  isSystem?: number;
  haveOptions?: number;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchTagsResponse {
  rows: Tag[];
  totalCount: number;
}

export async function fetchTags(options: FetchTagsRequest = {}): Promise<FetchTagsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/tag', {
    params: queryParams,
    signal,
  });

  const rows = response.data as Tag[];
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : rows.length;

  return { rows, totalCount };
}

export interface CreateTagPayload {
  name: string;
  options: string;
  isRequired: number;
}

export async function createTag(payload: CreateTagPayload): Promise<Tag> {
  const params = new URLSearchParams();
  params.append('name', payload.name);
  params.append('options', payload.options);
  params.append('isRequired', String(payload.isRequired));

  const { data } = await http.post('/tag', params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return data;
}

export interface UpdateTagPayload {
  name: string;
  options: string;
  isRequired: number;
}

export async function updateTag(id: number, payload: UpdateTagPayload): Promise<Tag> {
  const params = new URLSearchParams();
  params.append('name', payload.name);
  params.append('options', payload.options);
  params.append('isRequired', String(payload.isRequired));

  const { data } = await http.put(`/tag/${id}`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return data;
}

export async function deleteTag(id: number): Promise<void> {
  await http.delete(`/tag/${id}`);
}

export async function fetchTagUsage(
  id: number,
  signal?: AbortSignal,
): Promise<{ rows: TagUsageEntry[]; totalCount: number }> {
  const response = await http.get(`/tag/usage/${id}`, { signal });
  const rows = response.data as TagUsageEntry[];
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : rows.length;
  return { rows, totalCount };
}
