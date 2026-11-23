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
import type { Connector, ConnectorFieldsResponse } from '@/types/connector';

export async function fetchConnectors(signal?: AbortSignal): Promise<Connector[]> {
  const response = await http.get<Connector[]>('/connectors', {
    params: { isVisible: 1 },
    signal,
  });
  return response.data;
}

export async function fetchConnectorFields(
  id: string,
  signal?: AbortSignal,
): Promise<ConnectorFieldsResponse> {
  const response = await http.get<ConnectorFieldsResponse>(`/connectors/${id}/fields`, { signal });
  return response.data;
}

export async function fetchConnectorProxy<T>(
  id: string,
  method: string,
  params?: Record<string, unknown>,
  signal?: AbortSignal,
): Promise<T> {
  const response = await http.get<T>(`/connectors/form/${id}/proxy/${method}`, { params, signal });
  return response.data;
}

export async function updateConnector(
  id: string,
  payload: Record<string, unknown>,
  signal?: AbortSignal,
): Promise<void> {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(payload)) {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  }
  await http.put(`/connectors/${id}`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    signal,
  });
}

export async function postConnectorProxy<T>(
  id: string,
  method: string,
  params?: Record<string, string | string[]>,
  signal?: AbortSignal,
): Promise<T> {
  const body = new URLSearchParams();
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (Array.isArray(value)) {
        for (const item of value) {
          body.append(key, item);
        }
      } else if (value !== undefined && value !== null) {
        body.append(key, value);
      }
    }
  }
  const response = await http.post<T>(`/connectors/form/${id}/proxy/${method}`, body.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    signal,
  });
  return response.data;
}
