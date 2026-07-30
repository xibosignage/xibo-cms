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

import { useQuery } from '@tanstack/react-query';
import type { AxiosError } from 'axios';

import { fetchAgendaEvents } from '@/services/eventApi';
import type { FetchAgendaEventsRequest } from '@/services/eventApi';

export const agendaQueryKeys = {
  all: ['agenda'] as const,
  list: (params: Omit<FetchAgendaEventsRequest, 'signal'>) =>
    [...agendaQueryKeys.all, 'list', params] as const,
};

export const useAgendaData = (
  params: Omit<FetchAgendaEventsRequest, 'signal'>,
  enabled: boolean,
) => {
  return useQuery({
    queryKey: agendaQueryKeys.list(params),
    queryFn: ({ signal }) => fetchAgendaEvents({ ...params, signal }),
    enabled,
    staleTime: 1000 * 30,
    throwOnError: (error: AxiosError) => (error.response?.status ?? 0) >= 500,
  });
};
