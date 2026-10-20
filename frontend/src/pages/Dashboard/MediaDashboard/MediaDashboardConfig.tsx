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
