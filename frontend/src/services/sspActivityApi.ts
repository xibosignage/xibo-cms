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

import { fetchConnectorProxy, fetchConnectors } from '@/services/connectorApi';

const SSP_CONNECTOR_CLASS = 'XiboSspConnector';

export interface SspActivityRow {
  scheduledAt: string | null;
  campaignId: string | null;
  displayId: number | null;
  isPlayed: boolean;
  isErrored: boolean;
  impressions: number;
  impressionDate: string | null;
  impressionActual: number;
  errors: number;
  errorDate: string | null;
  errorCode: string | null;
}

export interface SspActivityResponse {
  success?: boolean;
  message?: string;
  data?: SspActivityRow[];
  recordsTotal?: number;
}

export interface SspActivityRequest {
  connectorId: number;
  displayId: number;
  partnerId?: string;
  activityFromDt?: string;
  activityToDt?: string;
  signal?: AbortSignal;
}

export type SspPartnersResponse = Record<string, { name: string }>;

export async function resolveSspConnectorId(signal?: AbortSignal): Promise<number | null> {
  const connectors = await fetchConnectors(signal);
  const ssp = connectors.find((c) => c.className.endsWith(SSP_CONNECTOR_CLASS));
  return ssp?.connectorId ?? null;
}

export async function fetchSspActivity(req: SspActivityRequest): Promise<SspActivityResponse> {
  return fetchConnectorProxy<SspActivityResponse>(
    String(req.connectorId),
    'activity',
    {
      displayId: req.displayId,
      partnerId: req.partnerId || undefined,
      activityFromDt: req.activityFromDt,
      activityToDt: req.activityToDt,
    },
    req.signal,
  );
}

export async function fetchSspPartners(
  connectorId: number,
  signal?: AbortSignal,
): Promise<SspPartnersResponse> {
  return fetchConnectorProxy<SspPartnersResponse>(
    String(connectorId),
    'getAvailablePartnersFilter',
    undefined,
    signal,
  );
}
