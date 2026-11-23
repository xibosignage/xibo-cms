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

// =============================================================================
// Contract: who may open each Reporting page
// =============================================================================

import { describe, test, expect } from 'vitest';

import { APP_ROUTES, generateTabNavigation } from '@/config/appRoutes';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';
import { filterRoutesByUser } from '@/utils/permissions';

const allShown = () => 'shown' as const;

const reporting = APP_ROUTES.find((route) => route.path === 'reporting')!;

// A plain user with only the named Reporting features on.
const userWith = (...features: string[]): User =>
  buildCurrentUser(PERSONAS.user, {
    features: Object.fromEntries(features.map((f) => [f, true])),
  });

const allowedPaths = (user: User) =>
  filterRoutesByUser(reporting.subLinks ?? [], user, allShown)
    .map((route) => route.path)
    .sort();

const DISPLAY_REPORTS = ['bandwidth', 'display-alerts', 'time-connected', 'time-connected-summary'];
const POP_REPORTS = ['distribution', 'proof-of-play', 'summary'];
const ADMIN_ONLY = ['api-requests', 'library-usage', 'session-history', 'ssp-activity'];

describe('Reporting routes - access', () => {
  test('the route table still has every report this contract checks', () => {
    const paths = (reporting.subLinks ?? []).map((route) => route.path);

    expect(paths).toEqual(
      expect.arrayContaining([...DISPLAY_REPORTS, ...POP_REPORTS, ...ADMIN_ONLY]),
    );
  });

  test('a super admin can open every Reporting page', () => {
    const superAdmin = buildCurrentUser(PERSONAS.superAdmin);

    expect(allowedPaths(superAdmin)).toEqual(
      (reporting.subLinks ?? []).map((route) => route.path).sort(),
    );
  });

  test('"Display reporting" opens exactly the four display reports', () => {
    expect(allowedPaths(userWith('displays.reporting'))).toEqual(DISPLAY_REPORTS);
  });

  test('"Proof of Play" opens exactly the three Proof of Play reports', () => {
    expect(allowedPaths(userWith('proof-of-play'))).toEqual(POP_REPORTS);
  });

  test('admin-only reports stay closed to a user with every Reporting feature', () => {
    const everything = userWith(
      'report.view',
      'report.scheduling',
      'report.saving',
      'displays.reporting',
      'proof-of-play',
      'admin',
    );

    expect(allowedPaths(everything).filter((path) => ADMIN_ONLY.includes(path))).toEqual([]);
  });

  test('a user with no Reporting features can open nothing', () => {
    expect(allowedPaths(userWith())).toEqual([]);
  });
});

describe('Reporting routes - tabs', () => {
  test('the three tabs each follow their own feature', () => {
    const tabs = (user: User) =>
      generateTabNavigation(reporting, user, allShown).map((tab) => tab.labelKey);

    expect(tabs(userWith('report.view'))).toEqual(['All Reports']);
    expect(tabs(userWith('report.scheduling'))).toEqual(['Report Schedules']);
    expect(tabs(userWith('report.saving'))).toEqual(['Saved Reports']);
  });

  // Report pages and the saved-report viewer are reached from the cards, not tabs.
  test('report pages never appear as tabs', () => {
    const labels = generateTabNavigation(
      reporting,
      buildCurrentUser(PERSONAS.superAdmin),
      allShown,
    ).map((tab) => tab.labelKey);

    expect(labels).toEqual(['All Reports', 'Report Schedules', 'Saved Reports']);
  });
});
