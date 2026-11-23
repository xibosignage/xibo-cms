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
