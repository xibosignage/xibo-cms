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

import { type ColumnDef, type PaginationState, type SortingState } from '@tanstack/react-table';
import { Search, UserRound } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { TextCell } from '../table/cells/TextCell';

import Button from '@/components/ui/Button';
import { notify } from '@/components/ui/Notification';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import { DataTable } from '@/components/ui/table/DataTable';
import type { UserType } from '@/services/permissionsApi';
import {
  fetchPermissions,
  fetchMultiPermissions,
  savePermissions,
  saveMultiPermissions,
  type PermissionEntry,
} from '@/services/permissionsApi';
import { fetchUsers } from '@/services/userApi';
import type { User } from '@/types/user';

interface UserRow {
  id: number;
  name: string;
  type: 'user' | 'group';
  view: number;
  edit: number;
  delete: number;
}

interface PermissionChange {
  view: number;
  edit: number;
  delete: number;
}

interface ShareModalProps {
  title?: string;
  openModal: boolean;
  onClose: () => void;
  entityType: string;
  entityId: number | number[] | null;
  showOwner?: boolean;
  currentOwnerId?: number | null;
}

type OpenSelect = 'owner' | 'filter' | null;

type OwnerOption = {
  label: string;
  value: string;
};

export default function ShareModal({
  title,
  openModal,
  onClose,
  entityType,
  entityId,
  showOwner = true,
  currentOwnerId,
}: ShareModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();

  const [openSelect, setOpenSelect] = useState<null | OpenSelect>(null);
  const [user, setUser] = useState<string | null>(null);
  const [filter, setFilter] = useState('all');

  const [sorting, setSorting] = useState<SortingState>([]);
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });
  const [rowCount, setRowCount] = useState(0);
  const [nameFilter, setNameFilter] = useState('');

  const [ownerOptions, setOwnerOptions] = useState<OwnerOption[]>([]);
  const [ownerLoading, setOwnerLoading] = useState(false);

  const [serverData, setServerData] = useState<UserRow[]>([]);
  const [modifiedPermissions, setModifiedPermissions] = useState<Record<number, PermissionChange>>(
    {},
  );
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!openModal) {
      setModifiedPermissions({});
      setPagination({ pageIndex: 0, pageSize: 10 });
      setNameFilter('');
      setFilter('all');
    }
  }, [openModal]);

  const togglePermission = (row: UserRow, key: keyof PermissionChange) => {
    setModifiedPermissions((prev) => {
      const id = row.id;
      const current = prev[id] || { view: row.view, edit: row.edit, delete: row.delete };

      // If current is 1, set next to 0
      // If current is 0, set next to 1
      // If current is -1 (mixed), set to 1
      const nextValue = current[key] === 1 ? 0 : 1;

      const updated = { ...current, [key]: nextValue };

      return { ...prev, [id]: updated };
    });
  };

  // Merge Data
  const tableData = serverData.map((row) => {
    const modifications = modifiedPermissions[row.id];
    if (modifications) {
      return { ...row, ...modifications };
    }
    return row;
  });

  const columns: ColumnDef<UserRow>[] = [
    {
      accessorKey: 'name',
      header: t('Name'),
      enableColumnFilter: true,
      enableSorting: true,
      cell: ({ row }) => (
        <TextCell truncate={true} className="h-9.75">
          {row.original.name}
        </TextCell>
      ),
    },
    {
      accessorKey: 'type',
      header: t('Type'),
      enableHiding: true,
      cell: ({ row }) => {
        const value = row.original.type;
        if (!value) {
          return '';
        }

        const capitalized = value.charAt(0).toUpperCase() + value.slice(1);
        return (
          <TextCell truncate={true} className="h-9.75">
            {t(capitalized)}
          </TextCell>
        );
      },
      size: 80,
    },
    {
      id: 'view',
      header: () => <div className="text-center">{t('View')}</div>,
      cell: ({ row }) => (
        <div className="flex justify-center">
          <Checkbox
            id={`view-${row.index}`}
            checked={row.original.view === 1}
            indeterminate={row.original.view === -1}
            onChange={() => togglePermission(row.original, 'view')}
          />
        </div>
      ),
      enableSorting: false,
      size: 80,
    },
    {
      id: 'edit',
      header: () => <div className="text-center">{t('Edit')}</div>,
      cell: ({ row }) => (
        <div className="flex justify-center">
          <Checkbox
            id={`edit-${row.index}`}
            checked={row.original.edit === 1}
            indeterminate={row.original.edit === -1}
            onChange={() => togglePermission(row.original, 'edit')}
          />
        </div>
      ),
      enableSorting: false,
      size: 80,
    },
    {
      id: 'delete',
      header: () => <div className="text-center">{t('Delete')}</div>,
      cell: ({ row }) => (
        <div className="flex justify-center">
          <Checkbox
            id={`delete-${row.index}`}
            checked={row.original.delete === 1}
            indeterminate={row.original.delete === -1}
            onChange={() => togglePermission(row.original, 'delete')}
          />
        </div>
      ),
      enableSorting: false,
      size: 80,
    },
  ];

  // Data loading
  useEffect(() => {
    if (!openModal || !entityId) return;

    const loadData = async () => {
      setLoading(true);

      const start = pagination.pageIndex * pagination.pageSize;
      const length = pagination.pageSize;

      try {
        let response;

        if (Array.isArray(entityId)) {
          response = await fetchMultiPermissions({
            entity: entityType,
            ids: entityId,
            start,
            length,
            type: filter as UserType,
            name: nameFilter || undefined,
          });
        } else {
          response = await fetchPermissions({
            entity: entityType,
            id: entityId,
            start,
            length,
            type: filter as UserType,
            name: nameFilter || undefined,
          });
        }

        setRowCount(response.totalCount);

        const rows: UserRow[] = response.rows.map((p: PermissionEntry) => ({
          id: p.groupId,
          name: p.group,
          type: p.isUserSpecific === 1 ? 'user' : 'group',
          view: p.view,
          edit: p.edit,
          delete: p.delete,
        }));

        setServerData(rows);
      } catch (error) {
        console.error(error);
        notify.error(t('Failed to load data'));
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, [
    openModal,
    pagination.pageIndex,
    pagination.pageSize,
    filter,
    nameFilter,
    entityId,
    entityType,
    t,
  ]);

  useEffect(() => {
    if (currentOwnerId) setUser(String(currentOwnerId));
  }, [currentOwnerId]);

  const handleSave = () => {
    if (!entityId) return;

    startTransition(async () => {
      try {
        const groupIdsPayload: Record<number, { view: number; edit: number; delete: number }> = {};

        Object.entries(modifiedPermissions).forEach(([key, perms]) => {
          const id = Number(key);
          groupIdsPayload[id] = {
            view: perms.view,
            edit: perms.edit,
            delete: perms.delete,
          };
        });

        if (Array.isArray(entityId)) {
          await saveMultiPermissions({
            entity: entityType,
            ids: entityId,
            groupIds: groupIdsPayload,
            ownerId: user ? parseInt(user) : undefined,
          });
        } else {
          await savePermissions({
            entity: entityType,
            id: entityId,
            groupIds: groupIdsPayload,
            ownerId: user ? parseInt(user) : undefined,
          });
        }

        notify.success(t('Permissions saved successfully'));
        onClose();
      } catch (error) {
        console.error(error);
        notify.error(t('Failed to save permissions'));
      }
    });
  };

  useEffect(() => {
    if (!openModal) return;

    const loadOwners = async () => {
      setOwnerLoading(true);
      try {
        const res = await fetchUsers({ start: 0, length: 100 });
        const rows = (Array.isArray(res) ? res : res.rows || []) as User[];
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
        title={title}
        isOpen={openModal}
        onClose={onClose}
        size="lg"
        actions={[
          { label: 'Cancel', onClick: onClose, variant: 'secondary' },
          {
            label: isPending ? t('Saving...') : t('Save'),
            onClick: handleSave,
            disabled: loading || isPending,
            variant: 'primary',
          },
        ]}
      >
        <div className="flex flex-col min-h-[60vh] px-8 gap-y-3 py-8">
          {!Array.isArray(entityId) && showOwner && (
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
          )}

          <div className="flex relative gap-2 items-end px-3 py-5 bg-slate-50">
            <SelectDropdown
              isOpen={openSelect === 'filter'}
              label="Filter by"
              placeholder="All"
              value={filter}
              options={[
                { label: t('All'), value: 'all' },
                { label: t('User'), value: 'user' },
                { label: t('Group'), value: 'group' },
              ]}
              onSelect={(value) => {
                setFilter(value);
                setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                setOpenSelect(null);
              }}
              onToggle={() => setOpenSelect((prev) => (prev === 'filter' ? null : 'filter'))}
              className="w-37.5"
            />
            <div className="relative flex-1 flex w-full mb-1">
              <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none max-h-11.25">
                <Search className="w-4 h-4 text-gray-400" />
              </div>
              <input
                name="search"
                value={nameFilter}
                onChange={(e) => {
                  setNameFilter(e.target.value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
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
                setNameFilter('');
                setPagination((prev) => ({ ...prev, pageIndex: 0 }));
              }}
            >
              Reset
            </Button>
          </div>

          <div className="overflow-hidden min-h-100 flex flex-col">
            <DataTable
              data={tableData}
              columns={columns}
              pageCount={Math.ceil(rowCount / pagination.pageSize)}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={nameFilter}
              onGlobalFilterChange={setNameFilter}
              enableSelection={false}
              rowSelection={{}}
              onRowSelectionChange={() => {}}
              loading={loading}
              hideToolbar={true}
            />
          </div>
        </div>
      </Modal>
    </div>
  );
}
