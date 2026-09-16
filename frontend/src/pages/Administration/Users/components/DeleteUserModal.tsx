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

import { Trash2Icon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchUsers } from '@/services/userApi';

const REASSIGN_PAGE_SIZE = 10;

interface DeleteUserModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onDelete: (options: { deleteAllItems: boolean; reassignUserId: number | null }) => void;
  userName?: string;
  userId?: number;
  isSuperAdmin?: boolean;
  error?: string | null;
  isLoading?: boolean;
}

export default function DeleteUserModal({
  isOpen = true,
  onClose,
  onDelete,
  userName,
  userId,
  isSuperAdmin,
  isLoading,
  error,
}: DeleteUserModalProps) {
  const { t } = useTranslation();
  const [deleteAllItems, setDeleteAllItems] = useState(false);
  const [reassignUserId, setReassignUserId] = useState<string | null>(null);
  const [userOptions, setUserOptions] = useState<SelectOption[]>([]);
  const [userPage, setUserPage] = useState(0);
  const [hasMoreUsers, setHasMoreUsers] = useState(false);
  const [isLoadingMoreUsers, setIsLoadingMoreUsers] = useState(false);
  const [userSearch, setUserSearch] = useState('');
  const debouncedUserSearch = useDebounce(userSearch, 300);

  useEffect(() => {
    if (!isOpen) {
      setDeleteAllItems(false);
      setReassignUserId(null);
      setUserSearch('');
      return;
    }

    setUserOptions([]);
    setUserPage(0);
    fetchUsers({ start: 0, length: REASSIGN_PAGE_SIZE, userName: debouncedUserSearch || undefined })
      .then((res) => {
        setUserOptions(
          res.rows
            .filter((u) => u.userId !== userId)
            .map((u) => ({ label: u.userName, value: String(u.userId) })),
        );
        setHasMoreUsers(res.rows.length === REASSIGN_PAGE_SIZE);
      })
      .catch(() => {
        setUserOptions([]);
        setHasMoreUsers(false);
      });
  }, [isOpen, userId, debouncedUserSearch]);

  const handleLoadMoreUsers = () => {
    if (isLoadingMoreUsers || !hasMoreUsers) {
      return;
    }
    const nextPage = userPage + 1;
    setIsLoadingMoreUsers(true);
    fetchUsers({
      start: nextPage * REASSIGN_PAGE_SIZE,
      length: REASSIGN_PAGE_SIZE,
      userName: debouncedUserSearch || undefined,
    })
      .then((res) => {
        setUserOptions((prev) => [
          ...prev,
          ...res.rows
            .filter((u) => u.userId !== userId)
            .map((u) => ({ label: u.userName, value: String(u.userId) })),
        ]);
        setUserPage(nextPage);
        setHasMoreUsers(res.rows.length === REASSIGN_PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreUsers(false));
  };

  return (
    <Modal
      variant="confirmation"
      isOpen={isOpen}
      isPending={isLoading}
      onClose={onClose}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isLoading ? t('Deleting...') : t('Yes, Delete'),
          onClick: () =>
            onDelete({
              deleteAllItems,
              reassignUserId: reassignUserId ? Number(reassignUserId) : null,
            }),
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div>
          <div className="flex justify-center mb-4">
            <div className="bg-red-100 w-15.5 h-15.5 text-red-800 border-red-50 border-[7px] rounded-full p-3">
              <Trash2Icon size={26} />
            </div>
          </div>
          <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
            {t('Delete User?')}
          </h2>
        </div>

        <p className="text-center text-gray-500">
          <Trans
            i18nKey='Are you sure you want to delete "<strong>{{name}}</strong>"?'
            values={{ name: userName }}
            components={{ strong: <strong /> }}
          />
        </p>

        <div className="mt-2 space-y-3">
          {!isSuperAdmin && (
            <Checkbox
              id="deleteAllItems"
              title={t('Delete all content')}
              label={t('Delete all content owned by this user')}
              className="items-center px-3 py-2.5"
              checked={deleteAllItems}
              onChange={() => {
                setDeleteAllItems((prev) => !prev);
                if (!deleteAllItems) {
                  setReassignUserId(null);
                }
              }}
            />
          )}

          {!deleteAllItems && (
            <SelectDropdown
              label={t('Reassign content to')}
              value={reassignUserId ?? ''}
              options={userOptions}
              searchable
              searchPlaceholder={t('Search users...')}
              onSearch={setUserSearch}
              onLoadMore={handleLoadMoreUsers}
              hasMore={hasMoreUsers}
              isLoadingMore={isLoadingMoreUsers}
              onSelect={(val) => setReassignUserId(val)}
              optional
            />
          )}
        </div>

        {error && (
          <div className="mt-2 text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}
      </div>
    </Modal>
  );
}
