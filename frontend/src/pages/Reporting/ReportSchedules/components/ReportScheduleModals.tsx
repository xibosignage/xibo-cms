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

import type { ModalType } from '../ReportSchedulesConfig';

import DeleteAllSavedReportsModal from './DeleteAllSavedReportsModal';
import DeleteReportScheduleModal from './DeleteReportScheduleModal';
import EditReportScheduleModal from './EditReportScheduleModal';
import ResetReportScheduleModal from './ResetReportScheduleModal';
import ToggleActiveModal from './ToggleActiveModal';

import type { ReportSchedule } from '@/types/reportSchedule';

export interface ReportScheduleModalsProps {
  activeModal: ModalType | null;
  closeModal: () => void;
  handleRefresh: () => void;
  selectedSchedule: ReportSchedule | null;
  itemsToDelete: ReportSchedule[];
  deleteError: string | null;
  isDeleting: boolean;
  confirmDelete: (items: ReportSchedule[]) => void;
  isTogglingActive: boolean;
  toggleActiveError: string | null;
  confirmToggleActive: (schedule: ReportSchedule) => void;
  isResetting: boolean;
  resetError: string | null;
  confirmReset: (schedule: ReportSchedule) => void;
  scheduleForDeleteAll: ReportSchedule | null;
  isDeletingAllSaved: boolean;
  deleteAllSavedError: string | null;
  confirmDeleteAllSaved: () => void;
}

export function ReportScheduleModals({
  activeModal,
  closeModal,
  handleRefresh,
  selectedSchedule,
  itemsToDelete,
  deleteError,
  isDeleting,
  confirmDelete,
  isTogglingActive,
  toggleActiveError,
  confirmToggleActive,
  isResetting,
  resetError,
  confirmReset,
  scheduleForDeleteAll,
  isDeletingAllSaved,
  deleteAllSavedError,
  confirmDeleteAllSaved,
}: ReportScheduleModalsProps) {
  const isModalOpen = (name: ModalType) => activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <EditReportScheduleModal
          isOpen
          schedule={selectedSchedule}
          onClose={closeModal}
          onSuccess={handleRefresh}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteReportScheduleModal
          isOpen
          onClose={closeModal}
          onDelete={() => confirmDelete(itemsToDelete)}
          itemCount={itemsToDelete.length}
          scheduleName={itemsToDelete.length === 1 ? itemsToDelete[0]?.name : undefined}
          error={deleteError}
          isLoading={isDeleting}
        />
      )}

      {isModalOpen('reset') && (
        <ResetReportScheduleModal
          isOpen
          schedule={selectedSchedule}
          onClose={closeModal}
          onConfirm={() => selectedSchedule && confirmReset(selectedSchedule)}
          error={resetError}
          isLoading={isResetting}
        />
      )}

      {isModalOpen('toggleActive') && (
        <ToggleActiveModal
          isOpen
          schedule={selectedSchedule}
          onClose={closeModal}
          onConfirm={() => selectedSchedule && confirmToggleActive(selectedSchedule)}
          error={toggleActiveError}
          isLoading={isTogglingActive}
        />
      )}

      {isModalOpen('deleteAllSaved') && (
        <DeleteAllSavedReportsModal
          isOpen
          scheduleName={scheduleForDeleteAll?.name}
          onClose={closeModal}
          onConfirm={confirmDeleteAllSaved}
          error={deleteAllSavedError}
          isLoading={isDeletingAllSaved}
        />
      )}
    </>
  );
}
