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

import { buildReport, mockReport } from '../../../__tests__/fixtures/report';

import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { ReportsByCategory } from '@/types/report';
import type { User } from '@/types/user';

// Four categories, incl. one whose only report is hidden.
export const summaryChart = buildReport({
  name: 'summaryReport',
  description: 'Summary by Layout, Media or Event',
  type: 'Chart',
  url: '/reporting/summary',
});
export const hiddenPop = buildReport({
  name: 'hiddenPop',
  description: 'Hidden Proof of Play',
  hidden: 1,
});
export const sessionHistory = buildReport({
  name: 'sessionhistory',
  description: 'Session History',
  category: 'Audit',
  url: '/reporting/session-history',
});
// No url → the classic report form outside the app.
export const legacyCustom = buildReport({
  name: 'customReport',
  description: 'Custom Legacy Report',
  category: 'Library',
  url: undefined,
});
export const exportReport = buildReport({
  name: 'statsExport',
  description: 'Export Stats',
  type: 'Export',
  category: 'Library',
  url: undefined,
});
export const onlyHidden = buildReport({
  name: 'hiddenDisplay',
  description: 'Hidden Display Report',
  category: 'Display',
  hidden: 1,
});

export const ALL_REPORTS: ReportsByCategory = {
  'Proof of Play': [mockReport, summaryChart, hiddenPop],
  Audit: [sessionHistory],
  Library: [legacyCustom, exportReport],
  Display: [onlyHidden],
};

// The SSP report as the server sends it, to check it isn't added twice.
export const SSP_FROM_SERVER = buildReport({
  name: 'ssp-activity',
  description: 'SSP Activity Report',
  url: '/reporting/ssp-activity',
});

const UTC_SETTINGS = {
  defaultTimezone: 'UTC',
  defaultLanguage: 'en',
  DATE_FORMAT_JS: 'YYYY-MM-DD',
  TIME_FORMAT_JS: 'HH:mm',
};

// Sees the SSP Activity card (super admins only).
export const superAdmin: User = buildCurrentUser(PERSONAS.superAdmin, { settings: UTC_SETTINGS });

export const plainUser: User = buildCurrentUser(PERSONAS.user, {
  features: { 'report.view': true },
  settings: UTC_SETTINGS,
});

export const queryKeys = {
  allReportsPage: ['userPref', 'all_reports_page'] as const,
  reportsList: ['reports', 'list'] as const,
};
