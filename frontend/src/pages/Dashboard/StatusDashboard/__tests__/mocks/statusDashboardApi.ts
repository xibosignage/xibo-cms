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

import { vi } from 'vitest';

import { fetchStatusDashboard } from '@/services/dashboardApi';
import type { FetchDisplaysResponse } from '@/services/displaysApi';
import { fetchDisplays } from '@/services/displaysApi';
import type { StatusDashboardData } from '@/types/dashboard';

// Thin wrappers over the two services the Status Dashboard calls. They only set return
// values; no filtering, paging or branching lives here.
//
// Requires at the test file level:
//   vi.mock('@/services/dashboardApi', () => ({ fetchStatusDashboard: vi.fn() }));
//   vi.mock('@/services/displaysApi', () => ({ fetchDisplays: vi.fn().mockResolvedValue(…) }));

// GET /statusdashboard resolves with the given data.
export const mockStatusDashboard = (data: StatusDashboardData) => {
  vi.mocked(fetchStatusDashboard).mockResolvedValue(data);
};

// GET /statusdashboard rejects with an Error carrying the given message.
export const mockStatusDashboardError = (message: string) => {
  vi.mocked(fetchStatusDashboard).mockRejectedValue(new Error(message));
};

// GET /statusdashboard stays pending until the returned resolve() is called (§4.4).
export const mockStatusDashboardPending = () => {
  let resolve!: (data: StatusDashboardData) => void;
  const pending = new Promise<StatusDashboardData>((res) => {
    resolve = res;
  });
  vi.mocked(fetchStatusDashboard).mockReturnValue(pending);
  return resolve;
};

// The Display Activity table's request (fetchDisplays, via useDisplaysData) resolves
// with the given response.
export const mockDashboardDisplays = (response: FetchDisplaysResponse) => {
  vi.mocked(fetchDisplays).mockResolvedValue(response);
};
