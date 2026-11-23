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

import type { RowSelectionState } from '@tanstack/react-table';
import { isAxiosError } from 'axios';
import type { TFunction } from 'i18next';
import type { Dispatch, SetStateAction } from 'react';
import { useState } from 'react';

import { notify } from '@/components/ui/Notification';
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
        const firstRejected = failed[0] as PromiseRejectedResult;
        const reason = firstRejected.reason;
        const message =
          isAxiosError(reason) && reason.response?.data?.message
            ? reason.response.data.message
            : t('{{count}} item(s) could not be logged out.', { count: failed.length });
        setLogoutError(message);
        setRowSelection({});
        handleRefresh();
        return;
      }

      notify.success(
        t('{{count}} session(s) logged out successfully.', { count: itemsToLogout.length }),
      );
      setRowSelection({});
      handleRefresh();
      closeModal();
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
