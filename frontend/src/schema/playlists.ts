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
import { z } from 'zod';

export const getPlaylistSchema = (t: TFunction) =>
  z
    .object({
      name: z.string().min(1, t('Name is required')),
      folderId: z.number().nullable().optional(),
      tags: z.array(
        z.object({
          tag: z.string(),
          value: z.any().optional(),
        }),
      ),
      enableStat: z.enum(['On', 'Off', 'Inherit']).optional(),
      isDynamic: z.coerce.boolean(),
      filterMediaName: z.string().optional(),
      logicalOperatorName: z.enum(['AND', 'OR']).optional(),
      filterMediaTag: z
        .array(
          z.object({
            tag: z.string(),
            value: z.any().optional(),
          }),
        )
        .optional(),
      exactTags: z.boolean().optional(),
      logicalOperator: z.enum(['AND', 'OR']).optional(),
      filterFolderId: z.number().nullable().optional(),
      maxNumberOfItems: z.number().optional(),
    })
    .superRefine((data, ctx) => {
      if (!data.isDynamic) return;
      const hasFilter =
        data.filterFolderId !== null ||
        (data.filterMediaName?.trim() ?? '') !== '' ||
        (data.filterMediaTag?.length ?? 0) > 0;

      if (!hasFilter) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: t('No filters have been set for this dynamic Playlist!'),
          path: ['filterMediaName'],
        });
      }
    });
