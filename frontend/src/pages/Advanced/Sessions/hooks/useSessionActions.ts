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

import { logoutSession } from '@/services/sessionApi';
import type { Session } from '@/types/session';

interface UseSessionActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
}

export function useSessionActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
}: UseSessionActionsProps) {
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [logoutError, setLogoutError] = useState<string | null>(null);

  const confirmLogout = async (itemsToLogout: Session[]) => {
    if (itemsToLogout.length === 0 || isLoggingOut) {
      return;
    }

    try {
      setIsLoggingOut(true);
      const results = await Promise.allSettled(
        itemsToLogout.map((item) => logoutSession(item.userId)),
      );

      const failed = results.filter((r) => r.status === 'rejected');
      if (failed.length > 0) {
        setLogoutError(`${failed.length} item(s) could not be logged out.`);
      }

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      setLogoutError(t('Session could not be logged out.'));
    } finally {
      setIsLoggingOut(false);
    }
  };

  return {
    isLoggingOut,
    logoutError,
    setLogoutError,
    confirmLogout,
  };
}
