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
import { isAxiosError } from 'axios';

import { fetchReports } from '@/services/reportApi';
import type { ReportsByCategory } from '@/types/report';

const reportsQueryKeys = {
  all: ['reports'] as const,
  list: () => [...reportsQueryKeys.all, 'list'] as const,
};

export const useAllReportsData = (enabled: boolean) => {
  return useQuery<ReportsByCategory>({
    queryKey: reportsQueryKeys.list(),
    queryFn: ({ signal }) => fetchReports(signal),
    staleTime: 60_000,
    enabled,
    throwOnError: (error: unknown) => isAxiosError(error) && (error.response?.status ?? 0) >= 500,
  });
};
