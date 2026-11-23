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

import type { StatsChartType } from '@/pages/Reporting/Reports/shared/types';

export interface ReportMeta {
  route: string;
  chartType: StatsChartType | null;
}

export const REPORT_META: Record<string, ReportMeta> = {
  summaryReport: { route: '/reporting/summary', chartType: 'line' },
  proofofplayReport: { route: '/reporting/proof-of-play', chartType: null },
  distributionReport: { route: '/reporting/distribution', chartType: 'bar' },
  bandwidth: { route: '/reporting/bandwidth', chartType: null },
  timeconnected: { route: '/reporting/time-connected', chartType: null },
  timedisconnectedsummary: { route: '/reporting/time-connected-summary', chartType: null },
  displayalerts: { route: '/reporting/display-alerts', chartType: null },
  libraryusage: { route: '/reporting/library-usage', chartType: null },
  sessionhistory: { route: '/reporting/session-history', chartType: null },
  apirequests: { route: '/reporting/api-requests', chartType: null },
};
