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
import type { Application, ApplicationScope } from '@/types/application';

export interface FetchApplicationRequest {
  start?: number;
  length?: number;
  name?: string;
  logicalOperatorName?: 'AND' | 'OR';
  useRegexForName?: boolean;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchApplicationResponse {
  rows: Application[];
  totalCount: number;
}

export async function fetchApplications(
  options: FetchApplicationRequest = {},
): Promise<FetchApplicationResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/application', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : rows.length;

  return { rows, totalCount };
}

export async function fetchApplicationDetails(key: string): Promise<Application> {
  const { data } = await http.get(`/application/${key}`);
  return data;
}

export async function fetchScopes(): Promise<ApplicationScope[]> {
  const { data } = await http.get('/application/scope');
  return data;
}

export async function createApplication(name: string): Promise<Application> {
  const formData = new URLSearchParams();
  formData.append('name', name);

  const response = await http.post('/application', formData, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return response.data;
}

export interface UpdateApplicationPayload {
  name: string;
  authCode: number;
  clientCredentials: number;
  isConfidential: number;
  resetKeys?: number;
  redirectUris?: string[];
  description?: string;
  logo?: string;
  coverImage?: string;
  companyName?: string;
  termsUrl?: string;
  privacyUrl?: string;
  selectedScopeIds?: string[];
  newOwnerId?: number;
}

export async function updateApplication(
  key: string,
  payload: UpdateApplicationPayload,
): Promise<Application> {
  const params = new URLSearchParams();
  params.append('name', payload.name);
  params.append('authCode', String(payload.authCode));
  params.append('clientCredentials', String(payload.clientCredentials));
  params.append('isConfidential', String(payload.isConfidential));

  if (payload.resetKeys) {
    params.append('resetKeys', '1');
  }

  if (payload.redirectUris) {
    for (const uri of payload.redirectUris) {
      params.append('redirectUri[]', uri);
    }
  }

  if (payload.description !== undefined) {
    params.append('description', payload.description);
  }
  if (payload.logo !== undefined) {
    params.append('logo', payload.logo);
  }
  if (payload.coverImage !== undefined) {
    params.append('coverImage', payload.coverImage);
  }
  if (payload.companyName !== undefined) {
    params.append('companyName', payload.companyName);
  }
  if (payload.termsUrl !== undefined) {
    params.append('termsUrl', payload.termsUrl);
  }
  if (payload.privacyUrl !== undefined) {
    params.append('privacyUrl', payload.privacyUrl);
  }

  if (payload.selectedScopeIds) {
    for (const scopeId of payload.selectedScopeIds) {
      params.append(`scope_${scopeId}`, '1');
    }
  }

  if (payload.newOwnerId !== undefined) {
    params.append('userId', String(payload.newOwnerId));
  }

  const { data } = await http.put(`/application/${key}`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return data;
}

export async function deleteApplication(key: string): Promise<void> {
  await http.delete(`/application/${key}`);
}
