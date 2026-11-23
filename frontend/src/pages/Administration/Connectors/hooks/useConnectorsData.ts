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

import { keepPreviousData, useQuery } from '@tanstack/react-query';

import type { ConnectorFilterInput } from '../ConnectorsConfig';

import { fetchConnectors } from '@/services/connectorApi';
import type { Connector } from '@/types/connector';

export const connectorQueryKeys = {
  all: ['connectors'] as const,
  list: () => [...connectorQueryKeys.all, 'list'] as const,
  fields: (id: string) => [...connectorQueryKeys.all, id, 'fields'] as const,
};

export function useConnectorsData(filters: ConnectorFilterInput, enabled = true) {
  const query = useQuery({
    queryKey: connectorQueryKeys.list(),
    queryFn: ({ signal }) => fetchConnectors(signal),
    staleTime: 1000 * 60,
    placeholderData: keepPreviousData,
    enabled,
  });

  const data = query.data ?? [];
  const filtered: Connector[] = data.filter((c) => {
    if (c.isHidden) {
      return false;
    }

    if (filters.name && !c.title.toLowerCase().includes(filters.name.toLowerCase())) {
      return false;
    }

    if (filters.enabled === 'enabled' && c.isEnabled !== 1) {
      return false;
    }

    if (filters.enabled === 'disabled' && c.isEnabled !== 0) {
      return false;
    }

    return true;
  });

  return { ...query, data: filtered };
}
