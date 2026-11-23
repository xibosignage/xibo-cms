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

import type { Report, ReportsByCategory } from '@/types/report';

// Shared available-reports fixtures for the Reporting suites.
export const buildReport = (overrides: Partial<Report> = {}): Report => ({
  name: 'proofofplayReport',
  description: 'Proof of Play',
  type: 'Report',
  output_type: '',
  color: '',
  lucide_icon: '',
  sort_order: 0,
  hidden: 0,
  category: 'Proof of Play',
  feature: '',
  adminOnly: 0,
  url: '/reporting/proof-of-play',
  ...overrides,
});

export const mockReport = buildReport();

export const bandwidthReport = buildReport({
  name: 'bandwidth',
  description: 'Display Statistics: Bandwidth',
  type: 'Chart',
  category: 'Display',
  url: '/reporting/bandwidth',
});

// Two categories, so consumers must flatten across them.
export const REPORTS_BY_CATEGORY: ReportsByCategory = {
  'Proof of Play': [mockReport],
  Display: [bandwidthReport],
};

// What the filter-options hooks derive from REPORTS_BY_CATEGORY.
export const REPORT_DESCRIPTION_MAP: Record<string, string> = {
  [mockReport.name]: mockReport.description,
  [bandwidthReport.name]: bandwidthReport.description,
};
