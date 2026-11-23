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

import type { Tag } from './tag';

export interface Layout {
  layoutId: number;
  layout: string;
  parentId?: number;
  showDrafts?: number;
  userId?: number;
  retired: boolean;
  tags: Tag[];
  exactTags?: number;
  logicalOperator?: string;
  ownerUserGroupId?: number;
  publishedStatusId?: number;
  embed?: string;
  campaignId: number;
  folderId: number;
  status: number;
  statusDescription?: string;
  duration: number;
  thumbnail?: string;
  previewUrl?: string;
  previewDraftUrl?: string;
  campaignType?: string;
  owner: string;
  groupsWithPermissions?: string;
  groupsWithPermissionsList?: string[] | null;
  enableStat: boolean;
  modifiedDt: string;
  code?: string | number;
  description?: string;
  descriptionFormatted?: string;
  ownerId: number;
  userPermissions?: LayoutPermissions;
  permissionsFolderId: number;
  orientation: string;
  width: number;
  height: number;
  publishedStatus: string;
  publishedDate?: string | null;
}

export interface LayoutPermissions {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}
