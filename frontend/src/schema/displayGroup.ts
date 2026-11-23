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

import type { TFunction } from 'i18next';
import { z } from 'zod';

export const getDisplayGroupSchema = (t: TFunction) =>
  z.object({
    displayGroup: z
      .string()
      .trim()
      .min(1, t('Name is required'))
      .max(50, t('Name must be at most 50 characters')),

    description: z.string().max(254, t('Description must be at most 254 characters')).optional(),

    tags: z
      .array(
        z.object({
          tag: z.string(),
          value: z.union([z.string(), z.number()]).optional().nullable(),
        }),
      )
      .optional(),

    isDynamic: z.boolean().optional(),

    dynamicCriteria: z.string().optional(),

    dynamicCriteriaTags: z.string().optional(),
  });
