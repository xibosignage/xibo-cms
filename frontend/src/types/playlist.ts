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

export interface PlaylistPermissions {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}

export interface Playlist {
  folderId: number;
  playlistId: number;
  name: string;
  createdDt: string;
  modifiedDt: string;
  ownerId: string;
  groupsWithPermissionsList?: string[] | null;
  width?: number;
  height?: number;
  valid: boolean;
  tags: Tag[];
  duration: number;
  requiresDurationUpdate?: number;
  enableStat: string;
  retired: boolean;
  expires: number;
  updateInLayouts: boolean;
  isDynamic: boolean | number;
  filterMediaName: string;
  filterMediaNameLogicalOperator?: 'OR' | 'AND';
  filterMediaTags?: string;
  filterExactTags?: number;
  filterMediaTagsLogicalOperator?: 'OR' | 'AND';
  filterFolderId: number | null;
  maxNumberOfItems: number;
  userPermissions?: PlaylistPermissions;
}
