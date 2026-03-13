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

import { z } from 'zod';

export const layoutSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'Name is required')
    .max(250, 'Name must be at most 250 characters'),

  description: z
    .string()
    .max(250, 'Description must be at most 250 characters')
    .nullable()
    .optional(),

  code: z.string().max(50, 'Code must be at most 50 characters').optional(),
});
