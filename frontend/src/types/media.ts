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

export interface MediaPermissions {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}

export interface Media {
  folderId: number;
  storedAs: string;
  mediaId: number;
  name: string;
  thumbnail: string;
  mediaType: MediaType;
  createdDt: string;
  modifiedDt: string;
  ownerId: string;
  groupsWithPermissionsList?: string[] | null;
  width?: number;
  height?: number;
  valid: boolean;
  released: number;
  fileName: string;
  fileSizeFormatted: string;
  orientation: 'portrait' | 'landscape' | 'square' | null;
  tags: Tag[];
  fileSize: number;
  duration: number;
  enableStat: string;
  retired: boolean;
  expires: string;
  expiresFormatted: string;
  updateInLayouts: boolean;
  userPermissions: MediaPermissions;
  deleteOldRevisions: boolean;
}

export type MediaType =
  | 'image'
  | 'video'
  | 'audio'
  | 'pdf'
  | 'genericfile'
  | 'htmlpackage'
  | 'powerpoint';
