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
import { isAxiosError } from 'axios';
import type { TFunction } from 'i18next';
import type { Dispatch, SetStateAction } from 'react';
import { useState } from 'react';

import { notify } from '@/components/ui/Notification';
import {
  deleteReportSchedule,
  toggleActiveReportSchedule,
  resetReportSchedule,
} from '@/services/reportScheduleApi';
import type { ReportSchedule } from '@/types/reportSchedule';

interface UseReportScheduleActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
}

export function useReportScheduleActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
}: UseReportScheduleActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const [isTogglingActive, setIsTogglingActive] = useState(false);
  const [toggleActiveError, setToggleActiveError] = useState<string | null>(null);

  const [isResetting, setIsResetting] = useState(false);
  const [resetError, setResetError] = useState<string | null>(null);

  const confirmDelete = async (itemsToDelete: ReportSchedule[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteReportSchedule(item.reportScheduleId)),
      );

      const failed = results.filter((r) => r.status === 'rejected');
      if (failed.length > 0) {
        const firstRejected = failed[0] as PromiseRejectedResult;
        const reason = firstRejected.reason;
        const message =
          isAxiosError(reason) && reason.response?.data?.message
            ? reason.response.data.message
            : t('{{count}} item(s) could not be deleted.', { count: failed.length });
        setDeleteError(message);
        setRowSelection({});
        handleRefresh();
        return;
      }

      setRowSelection({});
      handleRefresh();
      closeModal();
    } finally {
      setIsDeleting(false);
    }
  };

  const confirmToggleActive = async (schedule: ReportSchedule) => {
    try {
      setIsTogglingActive(true);
      setToggleActiveError(null);
      await toggleActiveReportSchedule(schedule.reportScheduleId);
      const label = schedule.isActive === 1 ? t('paused') : t('resumed');
      notify.success(t('Schedule {{label}} successfully', { label }));
      handleRefresh();
      closeModal();
    } catch (err: unknown) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('An unexpected error occurred.');
      setToggleActiveError(message);
    } finally {
      setIsTogglingActive(false);
    }
  };

  const confirmReset = async (schedule: ReportSchedule) => {
    try {
      setIsResetting(true);
      setResetError(null);
      await resetReportSchedule(schedule.reportScheduleId);
      notify.success(t('Schedule reset successfully'));
      handleRefresh();
      closeModal();
    } catch (err: unknown) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('An unexpected error occurred.');
      setResetError(message);
    } finally {
      setIsResetting(false);
    }
  };

  return {
    isDeleting,
    deleteError,
    setDeleteError,
    confirmDelete,
    isTogglingActive,
    toggleActiveError,
    setToggleActiveError,
    confirmToggleActive,
    isResetting,
    resetError,
    setResetError,
    confirmReset,
  };
}
