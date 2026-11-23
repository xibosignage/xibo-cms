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

import { type ColumnDef, type PaginationState } from '@tanstack/react-table';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useUserApplications } from './hooks/useUserApplications';

import Button from '@/components/ui/Button';
import { DataTable } from '@/components/ui/table/DataTable';
import { TextCell } from '@/components/ui/table/cells/TextCell';
import { useUserContext } from '@/context/UserContext';
import PreferencesLayout from '@/pages/Preferences/PreferencesLayout';
import type { UserApplication } from '@/services/userApi';

export default function Applications() {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const { query, revokeMutation } = useUserApplications(user?.userId);

  const applications = query.data ?? [];

  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });

  const maxPage = Math.max(0, Math.ceil(applications.length / pagination.pageSize) - 1);
  const safePageIndex = Math.min(pagination.pageIndex, maxPage);

  if (pagination.pageIndex !== safePageIndex) {
    setPagination((prev) => ({ ...prev, pageIndex: safePageIndex }));
  }

  const columns: ColumnDef<UserApplication>[] = [
    {
      accessorKey: 'name',
      header: t('Name'),
      enableSorting: false,
      cell: ({ row }) => (
        <TextCell truncate={true} className="h-9.75 font-medium text-gray-900">
          {row.original.name}
        </TextCell>
      ),
    },
    {
      accessorKey: 'approvedDate',
      header: t('Approved Date'),
      enableSorting: false,
      cell: ({ row }) => (
        <TextCell truncate={true} className="h-9.75 text-gray-500">
          {row.original.approvedDate}
        </TextCell>
      ),
    },
    {
      accessorKey: 'approvedIp',
      header: t('Approved IP Address'),
      enableSorting: false,
      cell: ({ row }) => (
        <TextCell truncate={true} className="h-9.75 text-gray-500">
          {row.original.approvedIp}
        </TextCell>
      ),
    },
    {
      id: 'actions',
      header: '',
      size: 100,
      enableSorting: false,
      cell: ({ row }) => (
        <div className="flex justify-end pr-2">
          <Button
            variant="primary"
            onClick={() => revokeMutation.mutate(row.original)}
            disabled={revokeMutation.isPending}
            className="px-3 py-1.5 text-xs h-auto"
            title={t('Revoke Access for this Application')}
          >
            {t('Revoke')}
          </Button>
        </div>
      ),
    },
  ];

  return (
    <PreferencesLayout>
      <div className="flex flex-1 flex-col gap-5 p-5">
        <div className="flex flex-col gap-1">
          <h2 className="text-xl font-semibold text-gray-800">{t('My Applications')}</h2>
          <p className="text-sm text-gray-500">{t('Authorized applications for user')}</p>
        </div>

        <div className="overflow-hidden flex flex-col flex-1 min-h-0 p-5 bg-white rounded-lg border border-gray-200">
          <DataTable
            data={applications.slice(
              pagination.pageIndex * pagination.pageSize,
              (pagination.pageIndex + 1) * pagination.pageSize,
            )}
            columns={columns}
            pageCount={Math.ceil(applications.length / pagination.pageSize)}
            rowCount={applications.length}
            pagination={pagination}
            onPaginationChange={setPagination}
            enableSelection={false}
            rowSelection={{}}
            onRowSelectionChange={() => {}}
            loading={query.isFetching}
            hideToolbar={true}
            sorting={[]}
            onSortingChange={() => {}}
            globalFilter=""
            onGlobalFilterChange={() => {}}
            noResultsCustom={
              <h3 className="text-lg font-semibold text-gray-600 py-12">
                {t('No applications found.')}
              </h3>
            }
          />
        </div>
      </div>
    </PreferencesLayout>
  );
}
