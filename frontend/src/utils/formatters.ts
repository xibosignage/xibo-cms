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

export const formatDuration = (seconds: number): string => {
  if (typeof seconds !== 'number') {
    return '-';
  }
  return new Date(seconds * 1000).toISOString().slice(11, 19);
};

export function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${parseFloat((bytes / Math.pow(1024, i)).toFixed(2))} ${units[i]}`;
}

export function formatFileSizeIEC(bytes: number): string {
  if (bytes === 0) return '0 B';
  const units = ['B', 'KiB', 'MiB', 'GiB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${parseFloat((bytes / Math.pow(1024, i)).toFixed(2))} ${units[i]}`;
}

export function formatRelativeDate(dateStr: string, t: TFunction): string {
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  if (diffDays === 0) return t('Today');
  if (diffDays === 1) return t('1 day ago');
  if (diffDays < 30) return t('{{count}} days ago', { count: diffDays });
  const diffMonths = Math.floor(diffDays / 30);
  if (diffMonths === 1) return t('1 month ago');
  return t('{{count}} months ago', { count: diffMonths });
}
