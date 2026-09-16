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

import { DateTime } from 'luxon';

// Parses a CMS "system format" datetime string (Y-m-d H:i:s, see
// DateFormatHelper::getSystemFormat()) as wall-clock time in the CMS's configured
// timezone - not the browser's local timezone, which may differ and shift the
// resulting instant onto the wrong calendar day.
export function parseDateTimeString(value?: string | null, timeZone?: string): Date | undefined {
  if (!value) return undefined;

  const dt = DateTime.fromFormat(value, 'yyyy-MM-dd HH:mm:ss', { zone: timeZone });

  return dt.isValid ? dt.toJSDate() : undefined;
}
