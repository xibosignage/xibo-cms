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

import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';

import { StatusCell, TextCell } from '@/components/ui/table/cells';
import { getStatusTypeFromMediaType } from '@/pages/Library/Media/MediaConfig';
import type { Media } from '@/types/media';

export function getUnusedColumns(t: TFunction): ColumnDef<Media>[] {
  return [
    {
      accessorKey: 'mediaId',
      header: t('ID'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      cell: (info) => (
        <TextCell truncate weight="bold">
          {info.getValue<string>()}
        </TextCell>
      ),
    },
    {
      accessorKey: 'mediaType',
      header: t('Type'),
      cell: (info) => {
        const value = info.getValue() as string;
        return <StatusCell label={value} type={getStatusTypeFromMediaType(value)} />;
      },
    },
    {
      accessorKey: 'fileSizeFormatted',
      header: t('Size'),
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
  ];
}

export function getUnreleasedColumns(t: TFunction): ColumnDef<Media>[] {
  return [
    {
      accessorKey: 'mediaId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      cell: (info) => (
        <TextCell truncate weight="bold">
          {info.getValue<string>()}
        </TextCell>
      ),
    },
  ];
}
