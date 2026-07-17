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

import { useEffect, useState } from 'react';

import { fetchContextButtons } from '@/services/folderApi';

/**
 * Whether the current user is allowed to create a sub-folder under the given folder,
 * per the backend's /folders/contextButtons/{folderId} decision (feature + sharing permission).
 */
export function useFolderCreatePermission(folderId: number | null): boolean {
  const [canCreate, setCanCreate] = useState(false);

  useEffect(() => {
    if (folderId == null) {
      setCanCreate(false);
      return;
    }

    const controller = new AbortController();
    setCanCreate(false);

    fetchContextButtons(folderId, controller.signal)
      .then((permissions) => setCanCreate(!!permissions.create))
      .catch(() => setCanCreate(false));

    return () => controller.abort();
  }, [folderId]);

  return canCreate;
}
