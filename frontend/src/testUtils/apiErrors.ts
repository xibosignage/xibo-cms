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

import { AxiosError, AxiosHeaders } from 'axios';

/**
 * An API error response carrying the message the server sent.
 *
 * Built the same way as `apiError` in `pages/Reporting/__tests__/fixtures/apiError.ts`, which
 * it could replace; the two are kept separate until that fixture's owners agree to move it here.
 */
export const apiError = (message: string, status = 422) =>
  new AxiosError('Request failed', String(status), undefined, undefined, {
    status,
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
    data: { message },
  });

/** What the API returns for an item someone else has already deleted. */
export const alreadyDeleted = () => apiError('Not Found', 404);

/** A real server failure, carrying the message the server sent. */
export const serverError = (message: string) => apiError(message, 500);
