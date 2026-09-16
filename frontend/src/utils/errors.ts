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

import { isAxiosError } from 'axios';

/**
 * A 404 from a delete request means the item was already gone by the time this
 * request reached the server - e.g. another tab/user deleted it first in a
 * concurrent bulk-delete. Bulk-delete flows treat this as an implicit success
 * rather than a failure to report to the user.
 */
export function isAlreadyDeletedError(reason: unknown): boolean {
  return isAxiosError(reason) && reason.response?.status === 404;
}
