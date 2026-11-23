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

import { deferred } from '@/testUtils/deferred';

// Each helper queues one response on the global fetch stub from setupTests.ts.

const jsonResponse = (body: unknown, status = 200) =>
  ({ ok: status >= 200 && status < 300, status, json: async () => body }) as unknown as Response;

export const stubAboutConfig = (body: unknown) =>
  vi.mocked(fetch).mockResolvedValueOnce(jsonResponse(body));

export const stubAboutConfigError = (status: number, body: unknown) =>
  vi.mocked(fetch).mockResolvedValueOnce(jsonResponse(body, status));

export const stubAboutConfigInvalidJson = () =>
  vi.mocked(fetch).mockResolvedValueOnce({
    ok: true,
    status: 200,
    json: async () => {
      throw new SyntaxError('Unexpected token < in JSON');
    },
  } as unknown as Response);

export const stubAboutConfigNetworkError = () =>
  vi.mocked(fetch).mockRejectedValueOnce(new TypeError('Failed to fetch'));

// Holds the request in flight; call release(body) to let it finish.
export const holdAboutConfig = () => {
  const request = deferred<Response>();
  vi.mocked(fetch).mockReturnValueOnce(request.promise);
  return { release: (body: unknown) => request.resolve(jsonResponse(body)) };
};
