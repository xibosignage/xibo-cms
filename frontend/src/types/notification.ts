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

export interface Notification {
  notificationId: number | null;
  subject: string;
  type: string;
  releaseDt: number | string;
  createDt?: number | string;
  isInterrupt: number;
  isSystem: number;
  body: string;
  read: number;
  readDt?: number | string | null;
  originalFileName?: string;
  filename?: string;
  userId: number;
  nonusers?: string;
  userGroups?: { groupId: number; group: string; isUserSpecific?: number }[];
  displayGroups?: { displayGroupId: number; displayGroup: string; isDisplaySpecific?: number }[];
  canEdit?: boolean;
  canDelete?: boolean;
}
