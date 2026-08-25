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

const BANDWIDTH_LIMIT_MAX_KB = 2147483647;
const BANDWIDTH_LIMIT_MAX_TIB = Math.round(BANDWIDTH_LIMIT_MAX_KB / (1024 * 1024 * 1024));

export const getBandwidthLimitSchema = (t: TFunction) =>
  z
    .number()
    .int()
    .min(0, t('Bandwidth limit must be 0 or greater'))
    .max(
      BANDWIDTH_LIMIT_MAX_KB,
      t('Bandwidth limit must be {{max}} KiB (~{{maxTib}} TiB) or less', {
        max: BANDWIDTH_LIMIT_MAX_KB.toLocaleString(),
        maxTib: BANDWIDTH_LIMIT_MAX_TIB,
      }),
    );

export const getEditDisplaySchema = (t: TFunction) =>
  z.object({
    display: z
      .string()
      .min(1, t('Name is required'))
      .max(50, t('Name cannot exceed 50 characters')),
    description: z.string().max(254, t('Description must be 254 characters or less')).optional(),
    latitude: z
      .number()
      .min(-90, t('Latitude must be between -90 and 90'))
      .max(90, t('Latitude must be between -90 and 90'))
      .optional(),
    longitude: z
      .number()
      .min(-180, t('Longitude must be between -180 and 180'))
      .max(180, t('Longitude must be between -180 and 180'))
      .optional(),
    bandwidthLimit: getBandwidthLimitSchema(t).optional(),
    screenSize: z.number().min(0, t('Value must be greater than or equal to 0.')).optional(),
    costPerPlay: z.number().min(0, t('Value must be greater than or equal to 0.')).optional(),
    impressionsPerPlay: z
      .number()
      .min(0, t('Value must be greater than or equal to 0.'))
      .optional(),
    wakeOnLanTime: z
      .string()
      .regex(/^([01]\d|2[0-3]):[0-5]\d$/, t('Please enter a valid time format (e.g., HH:MM).'))
      .optional(),
  });

export const getDisplaySchema = getEditDisplaySchema;
