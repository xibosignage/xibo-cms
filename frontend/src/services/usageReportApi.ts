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
import type { Display } from '@/types/display';
import type { Layout } from '@/types/layout';

interface DateRangeParams {
  fromDate?: string;
  toDate?: string;
}

const toPhpDatetime = (iso: string): string =>
  new Date(iso).toISOString().slice(0, 19).replace('T', ' ');

export async function fetchMediaUsageDisplays(
  mediaId: number,
  params: DateRangeParams = {},
  signal?: AbortSignal,
): Promise<Display[]> {
  const query = new URLSearchParams();
  if (params.fromDate) {
    query.append('mediaEventFromDate', toPhpDatetime(params.fromDate));
  }
  if (params.toDate) {
    query.append('mediaEventToDate', toPhpDatetime(params.toDate));
  }

  const url = `/library/usage/${mediaId}${query.toString() ? `?${query}` : ''}`;
  const { data } = await http.get(url, { signal });
  return Array.isArray(data) ? data : [];
}

export async function fetchMediaUsageLayouts(
  mediaId: number,
  signal?: AbortSignal,
): Promise<Layout[]> {
  const { data } = await http.get(`/library/usage/layouts/${mediaId}`, { signal });
  return Array.isArray(data) ? data : [];
}

export async function fetchPlaylistUsageDisplays(
  playlistId: number,
  params: DateRangeParams = {},
  signal?: AbortSignal,
): Promise<Display[]> {
  const query = new URLSearchParams();
  if (params.fromDate) {
    query.append('playlistEventFromDate', toPhpDatetime(params.fromDate));
  }
  if (params.toDate) {
    query.append('playlistEventToDate', toPhpDatetime(params.toDate));
  }

  const url = `/playlist/usage/${playlistId}${query.toString() ? `?${query}` : ''}`;
  const { data } = await http.get(url, { signal });
  return Array.isArray(data) ? data : [];
}

export async function fetchPlaylistUsageLayouts(
  playlistId: number,
  signal?: AbortSignal,
): Promise<Layout[]> {
  const { data } = await http.get(`/playlist/usage/layouts/${playlistId}`, { signal });
  return Array.isArray(data) ? data : [];
}
