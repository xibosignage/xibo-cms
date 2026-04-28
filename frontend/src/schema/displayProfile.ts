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

export const getAddDisplayProfileSchema = (t: TFunction) =>
  z.object({
    name: z.string().min(1, t('Name is required')).max(50, t('Name must be 50 characters or less')),
    type: z.enum(['android', 'windows', 'linux', 'lg', 'sssp', 'chromeOS'], {
      errorMap: () => ({ message: t('Display type is required') }),
    }),
    isDefault: z.number(),
  });

export const getEditDisplayProfileSchema = (t: TFunction) =>
  z.object({
    name: z.string().min(1, t('Name is required')).max(50, t('Name must be 50 characters or less')),
    isDefault: z.number(),
  });

export const getDisplayProfileSchema = getEditDisplayProfileSchema;
