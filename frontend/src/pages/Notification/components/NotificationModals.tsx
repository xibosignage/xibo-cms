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

import AddNotificationModal from './AddNotificationModal';
import DeleteNotificationModal from './DeleteNotificationModal';
import ShowNotificationModal from './ShowNotificationModal';

import type { Notification } from '@/types/notification';

interface NotificationModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
  };
  selection: {
    selectedNotification: Notification | null;
    itemsToDelete: Notification[];
    selectedEditId: number | null;
  };
  handlers: {
    confirmDelete: (items: Notification[]) => void;
  };
}

export function NotificationModals({ actions, selection, handlers }: NotificationModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('add') && (
        <AddNotificationModal onClose={actions.closeModal} onSave={actions.handleRefresh} />
      )}

      {isModalOpen('edit') && selection.selectedEditId != null && (
        <AddNotificationModal
          editNotificationId={selection.selectedEditId}
          onClose={actions.closeModal}
          onSave={actions.handleRefresh}
        />
      )}

      {isModalOpen('show') && selection.selectedNotification && (
        <ShowNotificationModal
          notification={selection.selectedNotification}
          onClose={actions.closeModal}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteNotificationModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          notificationSubject={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.subject : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
