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

import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { Check, X } from 'lucide-react';

import type { Display } from '@/types/display';

export function getDisplayColumns(t: TFunction): ColumnDef<Display>[] {
  return [
    {
      accessorKey: 'display',
      header: t('Display'),
    },
    {
      accessorKey: 'loggedIn',
      header: t('Logged In'),
      cell: ({ getValue }) =>
        getValue<number>() ? (
          <Check className="h-4 w-4 text-green-500" />
        ) : (
          <X className="h-4 w-4 text-red-400" />
        ),
    },
    {
      accessorKey: 'licensed',
      header: t('Authorised'),
      cell: ({ getValue }) =>
        getValue<number>() ? (
          <Check className="h-4 w-4 text-green-500" />
        ) : (
          <X className="h-4 w-4 text-red-400" />
        ),
    },
  ];
}
