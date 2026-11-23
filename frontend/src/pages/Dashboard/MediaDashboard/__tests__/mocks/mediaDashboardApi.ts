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

import type { FetchDashboardMediaResponse } from '@/services/dashboardApi';
import {
  fetchMediaDashboard,
  fetchUnreleasedMedia,
  fetchUnusedMedia,
} from '@/services/dashboardApi';
import { tidyLibrary } from '@/services/mediaApi';
import type { MediaDashboardData } from '@/types/dashboard';

// Thin wrappers over the services the Media Manager Dashboard calls. They only set return
// values; no paging, filtering or branching lives here.
//
// Requires at the test file level:
//   vi.mock('@/services/dashboardApi', () => ({ fetchMediaDashboard, fetchUnusedMedia, fetchUnreleasedMedia }))
//   vi.mock('@/services/mediaApi', () => ({ tidyLibrary }))

// Controlled promise (§4.4): returns the promise and its resolve/reject handles.
const controlled = <T>() => {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
};

// GET /mediamanager
export const mockMediaDashboard = (data: MediaDashboardData) => {
  vi.mocked(fetchMediaDashboard).mockResolvedValue(data);
};
export const mockMediaDashboardError = (message: string) => {
  vi.mocked(fetchMediaDashboard).mockRejectedValue(new Error(message));
};
export const mockMediaDashboardPending = () => {
  const { promise, resolve } = controlled<MediaDashboardData>();
  vi.mocked(fetchMediaDashboard).mockReturnValue(promise);
  return resolve;
};

// GET /library?unusedOnly=1 and ?unreleasedOnly=1
export const mockUnusedMedia = (response: FetchDashboardMediaResponse) => {
  vi.mocked(fetchUnusedMedia).mockResolvedValue(response);
};
export const mockUnreleasedMedia = (response: FetchDashboardMediaResponse) => {
  vi.mocked(fetchUnreleasedMedia).mockResolvedValue(response);
};

// DELETE /library/tidy
export const mockTidy = (countDeleted: number) => {
  vi.mocked(tidyLibrary).mockResolvedValue({ countDeleted });
};
export const mockTidyError = () => {
  vi.mocked(tidyLibrary).mockRejectedValue(new Error('Request failed with status code 500'));
};
export const mockTidyPending = () => {
  const { promise, resolve } = controlled<{ countDeleted: number }>();
  vi.mocked(tidyLibrary).mockReturnValue(promise);
  return resolve;
};
