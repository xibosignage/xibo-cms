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

export const getCampaignSchema = (t: TFunction) =>
  z
    .object({
      name: z
        .string()
        .min(1, t('Name is required'))
        .max(100, t('Name must be less than 100 characters')),

      folderId: z.number().nullable(),

      tags: z
        .array(
          z.object({
            tag: z.string(),
            value: z.string().optional(),
          }),
        )
        .optional(),

      type: z.enum(['list', 'ad']),

      cyclePlaybackEnabled: z.boolean(),

      playCount: z.union([z.number(), z.literal('')]).optional(),

      listPlayOrder: z.enum(['round', 'block']).optional(),

      targetType: z.enum(['plays', 'budget', 'impressions']).optional(),

      target: z.union([z.number(), z.literal('')]).optional(),
    })
    .superRefine((data, ctx) => {
      if (data.type === 'ad') {
        if (data.target === '' || data.target == null) {
          ctx.addIssue({
            path: ['target'],
            code: z.ZodIssueCode.custom,
            message: t('Target is required for Ad Campaigns'),
          });
        } else if (Number(data.target) <= 0) {
          ctx.addIssue({
            path: ['target'],
            code: z.ZodIssueCode.custom,
            message: t('Target must be greater than 0'),
          });
        }
      } else if (data.cyclePlaybackEnabled) {
        if (!data.playCount || data.playCount === null) {
          ctx.addIssue({
            path: ['playCount'],
            code: z.ZodIssueCode.custom,
            message: t('Play count is required when cycle playback is enabled'),
          });
        } else if (Number(data.playCount) <= 0) {
          ctx.addIssue({
            path: ['playCount'],
            code: z.ZodIssueCode.custom,
            message: t('Play count must be greater than 0'),
          });
        }
      } else {
        if (!data.listPlayOrder) {
          ctx.addIssue({
            path: ['listPlayOrder'],
            code: z.ZodIssueCode.custom,
            message: t('List play order is required'),
          });
        }
      }
    });
