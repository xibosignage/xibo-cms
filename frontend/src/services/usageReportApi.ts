/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
