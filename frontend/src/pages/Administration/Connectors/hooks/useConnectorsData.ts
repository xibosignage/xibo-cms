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
