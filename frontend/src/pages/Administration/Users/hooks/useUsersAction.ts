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

import { isAxiosError } from 'axios';
import type { TFunction } from 'i18next';
import { useState } from 'react';

import { deleteUser } from '@/services/userApi';

interface UseUsersActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
}

export function useUsersActions({ t, handleRefresh, closeModal }: UseUsersActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const confirmDelete = async (
    userId: number,
    options: { deleteAllItems: boolean; reassignUserId: number | null },
  ) => {
    if (isDeleting) return;

    try {
      setIsDeleting(true);
      await deleteUser(userId, {
        deleteAllItems: options.deleteAllItems ? 1 : 0,
        reassignUserId: options.reassignUserId ?? undefined,
      });

      handleRefresh();
      closeModal();
    } catch (err: unknown) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('Failed to delete user.');
      setDeleteError(message);
    } finally {
      setIsDeleting(false);
    }
  };

  return {
    isDeleting,
    deleteError,
    setDeleteError,
    confirmDelete,
  };
}
