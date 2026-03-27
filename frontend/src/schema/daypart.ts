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
import z from 'zod';

export const getDaypartSchema = (t: TFunction) =>
  z.object({
    name: z.string().min(1, t('Name is required')),
    description: z.string().optional(),
    isRetired: z.boolean().optional(),
    startTime: z.string().min(1, t('Start time is required')),
    endTime: z.string().min(1, t('End time is required')),
    exceptions: z
      .array(
        z.object({
          day: z.string(),
          start: z.string(),
          end: z.string(),
        }),
      )
      .optional(),
  });
