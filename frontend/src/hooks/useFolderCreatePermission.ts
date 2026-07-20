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

import { useQuery } from '@tanstack/react-query';

import { fetchContextButtons } from '@/services/folderApi';

/**
 * Whether the current user is allowed to create a sub-folder under the given folder,
 * per the backend's /folders/contextButtons/{folderId} decision (feature + sharing permission).
 *
 * Keyed on folderId so concurrent consumers of the same folder (e.g. the sidebar and the
 * info panel both showing the currently selected folder) share a single cached request.
 */
export function useFolderCreatePermission(folderId: number | null): boolean {
  const { data } = useQuery({
    queryKey: ['folderPermissions', folderId],
    queryFn: ({ signal }) => fetchContextButtons(folderId as number, signal),
    enabled: folderId != null,
  });

  return data?.create ?? false;
}
