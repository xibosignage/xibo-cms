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

export const getMenuBoardSchema = (t: TFunction) =>
  z.object({
    name: z
      .string()
      .trim()
      .min(1, t('Name is required'))
      .max(255, t('Name must be at most 255 characters')),
    description: z
      .string()
      .max(250, t('Description must be at most 250 characters'))
      .nullable()
      .optional(),
    code: z.string().max(50, t('Code must be at most 50 characters')).optional().nullable(),
    folderId: z.number().nullable().optional(),
  });

export type MenuBoardFormValues = z.infer<ReturnType<typeof getMenuBoardSchema>>;

export const getMenuBoardCategorySchema = (t: TFunction) =>
  z.object({
    name: z
      .string()
      .trim()
      .min(1, t('Name is required'))
      .max(255, t('Name must be at most 255 characters')),
    description: z
      .string()
      .max(250, t('Description must be at most 250 characters'))
      .nullable()
      .optional(),
    code: z.string().max(50, t('Code must be at most 50 characters')).optional().nullable(),
    mediaId: z.number().nullable().optional(),
  });

export type MenuBoardCategoryFormValues = z.infer<ReturnType<typeof getMenuBoardCategorySchema>>;

export const getMenuBoardProductSchema = (t: TFunction) =>
  z.object({
    name: z
      .string()
      .trim()
      .min(1, t('Name is required'))
      .max(255, t('Name must be at most 255 characters')),
    price: z.number().nullable().optional(),
    description: z
      .string()
      .max(255, t('Description must be at most 255 characters'))
      .nullable()
      .optional(),
    code: z.string().max(50, t('Code must be at most 50 characters')).optional().nullable(),
    displayOrder: z.number().int().nullable().optional(),
    availability: z.number().int().min(0).max(1).nullable().optional(),
    allergyInfo: z.string().nullable().optional(),
    calories: z.number().int().nullable().optional(),
    mediaId: z.number().nullable().optional(),
  });

export type MenuBoardProductFormValues = z.infer<ReturnType<typeof getMenuBoardProductSchema>>;
