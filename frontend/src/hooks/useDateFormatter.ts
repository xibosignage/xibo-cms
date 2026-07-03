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

  const timeZone = settings?.defaultTimezone || undefined;
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
