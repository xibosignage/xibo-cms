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

import { useTranslation } from 'react-i18next';

import AddAndEditDaypartModal from './AddAndEditDaypartModal';
import DeleteDaypartModal from './DeleteDaypartModal';

import ShareModal from '@/components/ui/modals/ShareModal';
import type { Daypart } from '@/types/daypart';

interface DaypartModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void | Promise<unknown>;
    deleteError: string | null;
    isDeleting: boolean;
  };
  selection: {
    selectedDaypart: Daypart | null;
    selectedDaypartId: number | null;
    itemsToDelete: Daypart[];
    scheduleCount?: number;
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: Daypart[]) => void;
  };
}

export function DaypartModals({ actions, selection, handlers }: DaypartModalsProps) {
  const { t } = useTranslation();
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditDaypartModal
          type={selection.selectedDaypartId ? 'edit' : 'add'}
          onClose={actions.closeModal}
          data={selection.selectedDaypart}
          onSave={async () => {
            await actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteDaypartModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          daypartName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.name : undefined
          }
          scheduleCount={selection.scheduleCount}
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}

      {isModalOpen('share') && (
        <ShareModal
          title={t('Share Daypart')}
          onClose={() => {
            actions.closeModal();
            selection.setShareEntityIds(null);
            actions.handleRefresh();
          }}
          entityType="DayPart"
          entityId={selection.shareEntityIds}
        />
      )}
    </>
  );
}
