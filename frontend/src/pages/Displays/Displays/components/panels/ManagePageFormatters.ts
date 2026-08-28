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

import type { TFunction } from 'i18next';

import type { Display } from '@/types/display';
import { formatFileSize } from '@/utils/formatters';

export function formatStorageUsed(display: Display, t: TFunction): string {
  const { storageAvailableSpace, storageTotalSpace } = display;
  if (storageAvailableSpace == null || storageTotalSpace == null || storageTotalSpace <= 0) {
    return '-';
  }

  const used = Math.max(0, storageTotalSpace - storageAvailableSpace);
  const percentUsed = Math.round((used / storageTotalSpace) * 100);

  return t('{{used}} of {{total}} ({{percent}}% used)', {
    used: formatFileSize(used),
    total: formatFileSize(storageTotalSpace),
    percent: percentUsed,
  });
}

/** Percentage of storage used (0-100), or null when it can't be computed. */
export function getStorageUsedPercent(display: Display): number | null {
  const { storageAvailableSpace, storageTotalSpace } = display;
  if (storageAvailableSpace == null || storageTotalSpace == null || storageTotalSpace <= 0) {
    return null;
  }

  const used = Math.max(0, storageTotalSpace - storageAvailableSpace);
  return Math.round((used / storageTotalSpace) * 100);
}
