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

import { useTranslation } from 'react-i18next';

import AddAndEditDaypartModal from './AddAndEditDaypartModal';
import DeleteDaypartModal from './DeleteDaypartModal';

import ShareModal from '@/components/ui/modals/ShareModal';
import type { Daypart } from '@/types/daypart';

interface DaypartModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
  };
  selection: {
    selectedDaypart: Daypart | null;
    selectedDaypartId: number | null;
    itemsToDelete: Daypart[];
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
          onSave={() => {
            actions.handleRefresh();
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
