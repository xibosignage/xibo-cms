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
  width?: number;
  height?: number;
  valid: boolean;
  fileName: string;
  fileSizeFormatted: string;
  orientation: 'portrait' | 'landscape';
  tags: Tag[];
  fileSize: number;
  duration: number;
  mediaNoExpiryDate: string;
  enableStat: string;
  retired: boolean;
  expires: string;
  updateInLayouts: boolean;
  userPermissions: MediaPermissions;
}

export type MediaType = 'image' | 'video' | 'audio' | 'pdf' | 'archive' | 'other';
