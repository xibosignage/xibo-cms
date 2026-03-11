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

import type { RowSelectionState } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import type { Dispatch, SetStateAction } from 'react';
import { useState } from 'react';

import { deleteResolution } from '@/services/resolutionApi';
import type { Resolution } from '@/types/resolution';

interface UseResolutionActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
}

export function useResolutionActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
}: UseResolutionActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const confirmDelete = async (itemsToDelete: Resolution[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteResolution(item.resolutionId)),
      );

      const failed = results.filter((r) => r.status === 'rejected');
      if (failed.length > 0) {
        setDeleteError(`${failed.length} item(s) could not be deleted.`);
      }

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      setDeleteError(t('Some selected items cannot be deleted.'));
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
