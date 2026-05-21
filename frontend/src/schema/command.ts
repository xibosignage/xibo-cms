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

export const getCommandSchema = (t: TFunction, mode: 'add' | 'edit') =>
  z.object({
    command: z
      .string()
      .min(1, t('Name is required'))
      .max(254, t('Name must be at most 254 characters')),
    code:
      mode === 'add'
        ? z
            .string()
            .min(1, t('Code is required'))
            .max(50, t('Code must be at most 50 characters'))
            .regex(/^[a-zA-Z0-9_]+$/, t('Code must contain only letters, numbers, and underscores'))
        : z.string(),
    description: z.string().max(1000, t('Description must be at most 1000 characters')).optional(),
    commandString: z.string().optional(),
    validationString: z.string().optional(),
    createAlertOn: z.enum(['never', 'success', 'failure', 'always']),
  });
