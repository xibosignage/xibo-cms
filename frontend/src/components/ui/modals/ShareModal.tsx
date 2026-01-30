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

import type {
  ColumnDef,
  ColumnFiltersState,
  VisibilityState,
  SortingState,
} from '@tanstack/react-table';
import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  getPaginationRowModel,
  getFilteredRowModel,
  getSortedRowModel,
} from '@tanstack/react-table';
import { ChevronDown, ChevronsUpDown, ChevronUp, Loader2, Search, UserRound } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { FILTER_DROPWN_OPTIONS } from '../../../pages/Library/Media/MediaConfig';

import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import { DataTablePagination } from '@/components/ui/table/DataTablePagination';
import { fetchUsers } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';

interface UserRow {
  id: number;
  name: string;
  type: 'user' | 'group';
  view: boolean;
  edit: boolean;
  delete: boolean;
}

interface ShareMediaModalProps {
  openModal: boolean;
  onClose: () => void;
}

type OpenSelect = 'owner' | 'filter' | null;

type OwnerOption = {
  label: string;
  value: string;
};

export default function ShareModal({ openModal, onClose }: ShareMediaModalProps) {
  const { t } = useTranslation();
  const [openSelect, setOpenSelect] = useState<null | OpenSelect>(null);
  const [user, setUser] = useState<string | null>(null);
  const [filter, setFilter] = useState('all');
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
  const [sorting, setSorting] = useState<SortingState>([]);
  const [ownerOptions, setOwnerOptions] = useState<OwnerOption[]>([]);
  const [ownerLoading, setOwnerLoading] = useState(false);
  const [pagination, setPagination] = useState({
    pageIndex: 0,
    pageSize: 25,
  });

  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({
    type: false,
  });

  const [tableData, setTableData] = useState<UserRow[]>([]);
  const [loading, setLoading] = useState(false);
  const pageSizeOptions = [5, 10, 20, 50, 100];

  const togglePermission = (rowIndex: number, key: keyof Omit<UserRow, 'id' | 'name' | 'type'>) => {
    setTableData((prev) =>
      prev.map((row, index) => (index === rowIndex ? { ...row, [key]: !row[key] } : row)),
    );
  };

  const columns: ColumnDef<UserRow>[] = [
    {
      accessorKey: 'name',
      header: 'Name',
      enableColumnFilter: true,
      enableSorting: true,
    },
    {
      accessorKey: 'type',
      enableHiding: true,
    },
    {
      id: 'view',
      header: 'View',
      cell: ({ row }) => (
        <div className="flex justify-center">
          <Checkbox
            id={`view-${row.index}`}
            checked={row.original.view}
            onChange={() => togglePermission(row.index, 'view')}
          />
        </div>
      ),
    },
    {
      id: 'edit',
      header: 'Edit',
      cell: ({ row }) => (
        <div className="flex justify-center">
          <Checkbox
            id={`edit-${row.index}`}
            checked={row.original.edit}
            onChange={() => togglePermission(row.index, 'edit')}
          />
        </div>
      ),
    },
    {
      id: 'delete',
      header: 'Delete',
      cell: ({ row }) => (
        <div className="flex justify-center">
          <Checkbox
            id={`delete-${row.index}`}
            checked={row.original.delete}
            onChange={() => togglePermission(row.index, 'delete')}
          />
        </div>
      ),
    },
  ];

  const table = useReactTable({
    data: tableData,
    columns,
    state: {
      pagination,
      columnFilters,
      columnVisibility,
      sorting,
    },
    onPaginationChange: setPagination,
    onColumnFiltersChange: setColumnFilters,
    onColumnVisibilityChange: setColumnVisibility,
    onSortingChange: setSorting,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
  });

  useEffect(() => {
    if (!openModal) return;

    const loadData = async () => {
      setLoading(true);

      const start = pagination.pageIndex * pagination.pageSize;
      const length = pagination.pageSize;

      const search = (table.getColumn('name')?.getFilterValue() as string) || '';

      const [usersRes, groupsRes] = await Promise.all([
        filter !== 'group'
          ? fetchUsers({
              start,
              length,
              userName: search || undefined,
            })
          : Promise.resolve({ rows: [], totalCount: 0 }),

        filter !== 'user'
          ? fetchUserGroups({
              start,
              length,
              userGroup: search || undefined,
            })
          : Promise.resolve({ rows: [], totalCount: 0 }),
      ]);

      const users: UserRow[] = usersRes.rows.map((u) => ({
        id: u.userId,
        name: u.userName,
        type: 'user',
        view: false,
        edit: false,
        delete: false,
      }));

      const groups: UserRow[] = groupsRes.rows.map((g) => ({
        id: g.groupId,
        name: g.group,
        type: 'group',
        view: false,
        edit: false,
        delete: false,
      }));

      setTableData([...users, ...groups]);
      setLoading(false);
    };

    loadData();
  }, [openModal, pagination.pageIndex, pagination.pageSize, filter, table]);

  useEffect(() => {
    if (!openModal) return;

    const loadOwners = async () => {
      setOwnerLoading(true);

      try {
        const res = await fetchUsers({ start: 0, length: 100 });

        const { rows = [] } = res ?? {};

        const options: OwnerOption[] = rows.map((u) => ({
          label: u.userName,
          value: String(u.userId),
        }));

        setOwnerOptions(options);
      } finally {
        setOwnerLoading(false);
      }
    };

    loadOwners();
  }, [openModal]);

  return (
    <div>
      <Modal
        title="Share Media"
        isOpen={openModal}
        onClose={onClose}
        actions={[
          {
            label: 'Cancel',
            onClick: onClose,
            variant: 'secondary',
          },
          {
            label: 'Save',
            // TODO: Implement actual sharing functionality
          },
        ]}
      >
        <div className="flex flex-col min-h-[60vh] px-8 gap-y-3 py-8">
          <SelectDropdown
            label="Select Owner"
            value={user as string}
            placeholder={ownerLoading ? t('Loading...') : t('Select Owner')}
            options={ownerOptions}
            isOpen={openSelect === 'owner'}
            onToggle={() => setOpenSelect((prev) => (prev === 'owner' ? null : 'owner'))}
            onSelect={(value) => {
              setUser(value);
              setOpenSelect(null);
            }}
            addLeftLabel
            leftLabelContent={
              <span className="flex gap-x-2.5 items-center">
                <UserRound size={14} /> {t('Owner')}
              </span>
            }
            optionLabel="Select Owner"
            addOptionAvatar
          />
          <div className="flex relative gap-2 items-end px-3 py-5 bg-slate-50">
            <SelectDropdown
              isOpen={openSelect === 'filter'}
              label="Filter by"
              placeholder="All"
              value={filter}
              options={FILTER_DROPWN_OPTIONS}
              onSelect={(value) => {
                setFilter(value);

                if (value === 'all') {
                  table.getColumn('type')?.setFilterValue(undefined);
                } else {
                  table.getColumn('type')?.setFilterValue(value);
                }

                setOpenSelect(null);
              }}
              onToggle={() => setOpenSelect((prev) => (prev === 'filter' ? null : 'filter'))}
              className="w-[150px]"
            />
            <div className="relative flex-1 flex w-full mb-1">
              <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none max-h-[45px]">
                <Search className="w-4 h-4 text-gray-400" />
              </div>
              <input
                name="search"
                value={(table.getColumn('name')?.getFilterValue() as string) ?? ''}
                onChange={(e) => {
                  table.getColumn('name')?.setFilterValue(e.target.value);
                  table.resetPageIndex();
                }}
                placeholder={t('Search')}
                className="py-3 px-3 pl-10 block rounded-lg w-full text-sm border-gray-200 disabled:opacity-50 disabled:pointer-events-none"
              />
            </div>
            <Button
              variant="link"
              className="absolute top-0 right-0 focus:outline-0"
              onClick={() => {
                setFilter('all');
                table.resetColumnFilters();
                table.resetPageIndex();
              }}
            >
              Reset
            </Button>
          </div>
          {/* TODO: Refactor Table to separate component */}
          <div className="min-h-[400px] relative overflow-y-auto">
            <table className="w-full border-collapse">
              <thead className="bg-gray-50 sticky top-0 border-b border-gray-200">
                {table.getHeaderGroups().map((headerGroup) => (
                  <tr key={headerGroup.id}>
                    {headerGroup.headers.map((header) => {
                      const isSorted = header.column.getIsSorted();
                      const canSort = header.column.getCanSort();
                      return (
                        <th
                          key={header.id}
                          className={twMerge(
                            'py-2 text-sm font-medium text-gray-500 uppercase',
                            header.column.id !== 'name'
                              ? 'text-center px-4 w-[100px]'
                              : 'text-left pl-4 flex justify-between',
                          )}
                        >
                          {flexRender(header.column.columnDef.header, header.getContext())}
                          {canSort && (
                            <div
                              className={twMerge(
                                'flex justify-center items-center p-1 size-6',
                                header.column.getCanSort() ? 'cursor-pointer select-none' : '',
                              )}
                              onClick={header.column.getToggleSortingHandler()}
                            >
                              {isSorted === 'asc' ? (
                                <ChevronUp className="size-4" />
                              ) : isSorted === 'desc' ? (
                                <ChevronDown className="size-4" />
                              ) : (
                                <ChevronsUpDown className="size-4" />
                              )}
                            </div>
                          )}
                        </th>
                      );
                    })}
                  </tr>
                ))}
              </thead>

              {loading ? (
                <div className="absolute bg-white w-full h-[200px] center z-50 backdrop-blur-sm animate-in fade-in duration-300">
                  <div className="flex flex-col items-center">
                    <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
                    <span className="mt-2 text-gray-500">{t('Loading...')}</span>
                  </div>
                </div>
              ) : (
                <tbody>
                  {table.getRowModel().rows.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="py-10 text-center text-gray-500">
                        {t('No results found')}
                      </td>
                    </tr>
                  ) : (
                    table.getRowModel().rows.map((row) => (
                      <tr key={row.id} className="hover:bg-gray-50">
                        {row.getVisibleCells().map((cell) => (
                          <td key={cell.id} className="p-3 text-sm font-semibold">
                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                          </td>
                        ))}
                      </tr>
                    ))
                  )}
                </tbody>
              )}
            </table>
          </div>
          <DataTablePagination table={table} loading={loading} pageSizeOptions={pageSizeOptions} />
        </div>
      </Modal>
    </div>
  );
}
