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

import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { LatestNewsItem, StatusDashboardData } from '@/types/dashboard';
import type { User } from '@/types/user';

// The Display Activity table rows are Display entities. Re-exported from the Displays
// fixtures so there is one source of truth for the Display shape.
export {
  buildDisplay,
  EMPTY_DISPLAY_TABLE,
  mockDisplay,
  SINGLE_DISPLAY,
} from '@/pages/Displays/Displays/__tests__/fixtures/display';

// -----------------------------------------------------------------------------
// Factory that produces a GET /statusdashboard response with safe minimal defaults.
// Only fields used in assertions or required for rendering carry meaningful
// values; everything else is the zero value for its type.
//
// librarySize: '1.5 GiB' — the Library Size card (sent pre-formatted)
// countUsers: 4 — the Active Users card
// displayStatus: '[3,2]' — JSON [online, offline]; the Active Displays card shows
//   the sum (5) and the Status donut shows the split. Deliberately asymmetric so a
//   swap is visible.
// displayMediaStatus: '[4,1]' — JSON [upToDate, notUpToDate] for the Content Status donut
// librarySuffix / bandwidthSuffix: 'GiB' — the unit the server's labels carry; needed
//   as soon as a test adds library or bandwidth data
// bandwidthWidget: '' — zero value, and it selects the "No bandwidth data" branch
// libraryWidgetLabels: '' — zero value, and it selects the "No library data" branch
// latestNews: [] — zero value, and it selects the "No news available." branch
// -----------------------------------------------------------------------------
export const buildStatusDashboardData = (
  overrides: Partial<StatusDashboardData> = {},
): StatusDashboardData => ({
  librarySize: '1.5 GiB',
  countUsers: 4,
  nowShowing: 0,
  displayStatus: JSON.stringify([3, 2]),
  displayMediaStatus: JSON.stringify([4, 1]),
  bandwidthWidget: '',
  libraryWidgetLabels: '',
  libraryWidgetData: '',
  libraryLimitSet: false,
  libraryLimit: '',
  librarySuffix: 'GiB',
  xmdsLimitSet: false,
  bandwidthSuffix: 'GiB',
  latestNews: [],
  ...overrides,
});

export const mockStatusDashboardData = buildStatusDashboardData();

// -----------------------------------------------------------------------------
// Builders for the JSON strings the server packs into the response.
// Shapes mirror lib/Controller/StatusDashboard.php; always use these rather than
// hand-written JSON.
// -----------------------------------------------------------------------------

// bandwidthWidget: { labels, datasets: [used, available?] }. The available dataset
// is only sent when a bandwidth limit is set.
export const buildBandwidthWidget = (
  labels: string[],
  used: number[],
  available?: number[],
): string =>
  JSON.stringify({
    labels,
    datasets: [
      { label: 'Used', backgroundColor: '', data: used },
      ...(available ? [{ label: 'Available', backgroundColor: '', data: available }] : []),
    ],
  });

// libraryWidgetLabels / libraryWidgetData. Labels must be server-shaped:
// ucfirst(type) + ' ' + suffix, e.g. 'Image GiB', 'Genericfile GiB', 'Free GiB'.
// Do NOT write 'Generic File GiB', which would hide the multi-word type bug.
export const buildLibraryWidget = (
  rows: [label: string, value: number][],
): Pick<StatusDashboardData, 'libraryWidgetLabels' | 'libraryWidgetData'> => ({
  libraryWidgetLabels: JSON.stringify(rows.map(([label]) => label)),
  libraryWidgetData: JSON.stringify(rows.map(([, value]) => value)),
});

// -----------------------------------------------------------------------------
// Factory that produces one latestNews item.
//
// title: 'Test News' — the item's heading
// link: '...' — the "Full Article" href; '' hides the link
// date: FIXED_NOW's date — formatRelativeDate gives "Today" when system time is
//   pinned to FIXED_NOW
// description / image — zero values (image is never rendered)
// -----------------------------------------------------------------------------
export const FIXED_NOW = new Date('2026-10-14T12:00:00Z');

export const buildNewsItem = (overrides: Partial<LatestNewsItem> = {}): LatestNewsItem => ({
  title: 'Test News',
  description: '',
  link: 'https://xibosignage.com/news/test-news',
  date: '2026-10-14T09:00:00Z',
  image: '',
  ...overrides,
});

export const mockNewsItem = buildNewsItem();

// The default logged-in user. Super Admin, so the router's dashboard.status gate passes.
// The page itself has no permission-gated controls.
export const mockUser: User = buildCurrentUser(PERSONAS.superAdmin, {
  userId: 1,
  userName: 'TestUser',
  groupId: 1,
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
});

// Query keys used by useDashboardStats.
export const queryKeys = {
  statusDashboardStats: ['dashboard', 'stats'] as const,
};
