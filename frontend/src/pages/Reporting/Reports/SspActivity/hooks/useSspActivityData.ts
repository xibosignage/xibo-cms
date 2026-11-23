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

import { useQuery } from '@tanstack/react-query';
import { t } from 'i18next';

import type { SspActivityFilter } from '../SspActivityConfig';

import {
  fetchSspActivity,
  fetchSspPartners,
  resolveSspConnectorId,
  type SspActivityRow,
} from '@/services/sspActivityApi';
import { formatDateTime } from '@/utils/date';

function toCmsDateTime(value: string): string | undefined {
  if (!value) {
    return undefined;
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? undefined : formatDateTime(date);
}

export const sspActivityQueryKeys = {
  all: ['ssp-activity'] as const,
  connectorId: () => [...sspActivityQueryKeys.all, 'connector-id'] as const,
  partners: (connectorId: number | null) =>
    [...sspActivityQueryKeys.all, 'partners', connectorId] as const,
  report: (params: Record<string, unknown>) =>
    [...sspActivityQueryKeys.all, 'report', params] as const,
};

export function useSspConnectorId() {
  return useQuery({
    queryKey: sspActivityQueryKeys.connectorId(),
    queryFn: ({ signal }) => resolveSspConnectorId(signal),
    staleTime: 1000 * 60 * 10,
    gcTime: 1000 * 60 * 30,
  });
}

export function useSspPartners(connectorId: number | null) {
  return useQuery({
    queryKey: sspActivityQueryKeys.partners(connectorId),
    queryFn: ({ signal }) => fetchSspPartners(connectorId as number, signal),
    enabled: connectorId != null,
    staleTime: 1000 * 60 * 10,
    gcTime: 1000 * 60 * 30,
  });
}

interface UseSspActivityDataParams {
  connectorId: number | null;
  filter: SspActivityFilter;
  enabled: boolean;
}

export interface SspActivityData {
  rows: SspActivityRow[];
  message: string | null;
}

export function useSspActivityData({ connectorId, filter, enabled }: UseSspActivityDataParams) {
  return useQuery<SspActivityData>({
    queryKey: sspActivityQueryKeys.report({ connectorId, ...filter }),

    queryFn: async ({ signal }) => {
      const response = await fetchSspActivity({
        connectorId: connectorId as number,
        displayId: filter.displayId as number,
        partnerId: filter.partnerId,
        activityFromDt: toCmsDateTime(filter.activityFromDt),
        activityToDt: toCmsDateTime(filter.activityToDt),
        signal,
      });

      if (response.success === false) {
        throw new Error(response.message || t('The SSP service returned an error.'));
      }

      return {
        rows: response.data ?? [],
        message: response.message ?? null,
      };
    },

    enabled: enabled && connectorId != null && filter.displayId != null,
    staleTime: 0,
    gcTime: 1000 * 60 * 5,
  });
}
