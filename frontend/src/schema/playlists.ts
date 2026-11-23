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

export const getPlaylistSchema = (t: TFunction, maxNumberOfItemsLimit = 0) =>
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

      if (maxNumberOfItemsLimit > 0 && (data.maxNumberOfItems ?? 0) > maxNumberOfItemsLimit) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: t('Maximum number of items cannot exceed the limit set in CMS Settings'),
          path: ['maxNumberOfItems'],
        });
      }

      if ((data.maxNumberOfItems ?? 0) < 1) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: t('Max number of items must be at least 1'),
          path: ['maxNumberOfItems'],
        });
      }
    });
