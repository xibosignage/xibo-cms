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

// Layout built, but displays not yet notified (Regular Maintenance notifies them)
const LAYOUT_STATUS_PENDING_NOTIFY = 5;
const LAYOUT_STATUS_VALID = 1;

export function isPendingNotifyLayoutStatus(status?: number): boolean {
  return status === LAYOUT_STATUS_PENDING_NOTIFY;
}

// A Layout pending display notification is valid
export function toNotifiedLayoutStatus(status?: number): number | undefined {
  return isPendingNotifyLayoutStatus(status) ? LAYOUT_STATUS_VALID : status;
}
