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

import { useUserContext } from '@/context/UserContext';
import {
  DEFAULT_CMS_DATE_FORMAT,
  DEFAULT_CMS_TIME_FORMAT,
  formatCmsDate,
  formatCmsDateTime,
  formatCmsTime,
  type DateLike,
} from '@/utils/date';

export interface DateFormatter {
  dateFormat: string;
  timeFormat: string;
  timeZone?: string;
  formatDateTime: (value: DateLike) => string;
  formatDate: (value: DateLike) => string;
  formatTime: (value: DateLike) => string;
}

export function useDateFormatter(): DateFormatter {
  const { user } = useUserContext();
  const settings = user?.settings;

  // The CMS-wide `defaultTimezone` setting, mirrored per-request via /user/me
  // (see User::myDetails() in lib/Controller/User.php) - not a personal
  // per-user preference and not the browser's local timezone.
  const timeZone = settings?.defaultTimezone || 'UTC';
  const dateFormat = settings?.DATE_FORMAT_JS || DEFAULT_CMS_DATE_FORMAT;
  const timeFormat = settings?.TIME_FORMAT_JS || DEFAULT_CMS_TIME_FORMAT;
  const locale = settings?.translate?.jsLocale || undefined;

  return {
    dateFormat,
    timeFormat,
    timeZone,
    formatDateTime: (value) => formatCmsDateTime(value, { format: dateFormat, timeZone, locale }),
    formatDate: (value) => formatCmsDate(value, { format: dateFormat, timeZone, locale }),
    formatTime: (value) => formatCmsTime(value, { format: timeFormat, timeZone, locale }),
  };
}
