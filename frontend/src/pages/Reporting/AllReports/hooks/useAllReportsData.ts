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
import type { TFunction } from 'i18next';
import { useTranslation } from 'react-i18next';

import { useUserContext } from '@/context/UserContext';
import { fetchReports } from '@/services/reportApi';
import type { Report, ReportsByCategory } from '@/types/report';
import { UserType } from '@/types/user';

const reportsQueryKeys = {
  all: ['reports'] as const,
  list: () => [...reportsQueryKeys.all, 'list'] as const,
};

const SSP_REPORT_CATEGORY = 'Proof of Play';

function withSspActivityReport(data: ReportsByCategory, t: TFunction): ReportsByCategory {
  const sspReport: Report = {
    name: 'ssp-activity',
    description: t('SSP Activity Report'),
    type: 'Report',
    output_type: 'both',
    color: '',
    lucide_icon: 'FileBarChart2',
    sort_order: 99,
    hidden: 0,
    category: SSP_REPORT_CATEGORY,
    feature: 'report.view',
    adminOnly: 1,
    url: '/reporting/ssp-activity',
  };

  const existing = data[SSP_REPORT_CATEGORY] ?? [];
  if (existing.some((report) => report.name === sspReport.name)) {
    return data;
  }
  return { ...data, [SSP_REPORT_CATEGORY]: [...existing, sspReport] };
}

export const useAllReportsData = (enabled: boolean) => {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const isSuperAdmin = user?.userTypeId === UserType.SuperAdmin;

  return useQuery<ReportsByCategory, Error, ReportsByCategory>({
    queryKey: reportsQueryKeys.list(),
    queryFn: ({ signal }) => fetchReports(signal),
    select: (data) => (isSuperAdmin ? withSspActivityReport(data, t) : data),
    staleTime: 60_000,
    enabled,
  });
};
