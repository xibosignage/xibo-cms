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

import { vi } from 'vitest';

export * from '../folderApi';

export const fetchContextButtons = vi.fn().mockResolvedValue({ create: true });
export const selectFolder = vi.fn();
export const fetchFolderById = vi.fn().mockResolvedValue({
  id: 1,
  text: 'Root',
  type: 'root',
  parentId: 0,
  isRoot: 1,
  children: null,
  ownerId: 1,
  ownerName: 'MockUser',
});
export const fetchFolderTree = vi.fn().mockResolvedValue([]);
export const searchFolders = vi.fn().mockResolvedValue([]);
