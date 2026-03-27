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

import { type ColumnDef, type PaginationState } from '@tanstack/react-table';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useUserApplications } from './useUserApplications';

import Button from '@/components/ui/Button';
import Modal from '@/components/ui/modals/Modal';
import { DataTable } from '@/components/ui/table/DataTable';
import { TextCell } from '@/components/ui/table/cells/TextCell';
import { useUserContext } from '@/context/UserContext';
import type { UserApplication } from '@/services/userApi';

interface ApplicationsModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function ApplicationsModal({ isOpen, onClose }: ApplicationsModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const { query, revokeMutation } = useUserApplications(user?.userId, isOpen);

  const applications = query.data ?? [];

  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });

  const maxPage = Math.max(0, Math.ceil(applications.length / pagination.pageSize) - 1);
  const safePageIndex = Math.min(pagination.pageIndex, maxPage);

  if (pagination.pageIndex !== safePageIndex && isOpen) {
    setPagination((prev) => ({ ...prev, pageIndex: safePageIndex }));
  }

  useEffect(() => {
    if (!isOpen) {
      setPagination({ pageIndex: 0, pageSize: 5 });
    }
  }, [isOpen]);

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
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="lg"
      title={t('Authorized applications for user')}
      actions={[{ label: t('Close'), variant: 'secondary', onClick: onClose }]}
    >
      <div className="flex flex-col px-8 gap-y-3 py-8">
        <div className="overflow-hidden flex flex-col h-95">
          <DataTable
            data={applications.slice(
              pagination.pageIndex * pagination.pageSize,
              (pagination.pageIndex + 1) * pagination.pageSize,
            )}
            columns={columns}
            pageCount={Math.ceil(applications.length / pagination.pageSize)}
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
    </Modal>
  );
}
