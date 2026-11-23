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
import type { Template } from '@/types/templates';

export interface FetchTemplateRequest {
  start: number;
  length: number;
  template?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  folderId?: number;

  userId?: string;
  ownerUserGroupId?: string;
  lastModified?: string;
  tags?: string;

  useRegexForName?: number;
  logicalOperatorName?: 'OR' | 'AND';
  exactTags?: number;
  logicalOperator?: 'OR' | 'AND';
}

export interface FetchTemplateResponse {
  rows: Template[];
  totalCount: number;
}

export async function fetchTemplates(
  options: FetchTemplateRequest = { start: 0, length: 10 },
): Promise<FetchTemplateResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/template', {
    params: queryParams,
    signal,
  });

  let rows = response.data;

  // TODO: Temporary fix for API returning string instead of array, should be removed once backend is fixed
  if (typeof rows === 'string') {
    const jsonStart = rows.indexOf('[');
    const jsonEnd = rows.lastIndexOf(']');

    if (jsonStart !== -1 && jsonEnd !== -1) {
      try {
        rows = JSON.parse(rows.slice(jsonStart, jsonEnd + 1));
      } catch {
        rows = [];
      }
    } else {
      rows = [];
    }
  }

  if (!Array.isArray(rows)) {
    rows = [];
  }

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : rows.length;

  return {
    rows,
    totalCount,
  };
}

export interface CreateTemplatePayload {
  name: string;
  description?: string;
  tags?: string;
  retired?: number;
  folderId?: number | null;
  enableStat?: number;
  code?: string | null;
}

export async function createTemplate(payload: CreateTemplatePayload) {
  const formData = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      formData.append(key, String(value));
    }
  });

  const response = await http.post('/template', formData, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  return response.data;
}

export async function updateTemplate(templateId: number, payload: CreateTemplatePayload) {
  const formData = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      formData.append(key, String(value));
    }
  });

  const response = await http.put(`/layout/${templateId}`, formData, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  return response.data;
}
