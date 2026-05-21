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

import type { ModalType } from '../CommandsConfig';

import AddEditCommandModal from './AddEditCommandModal';
import DeleteCommandModal from './DeleteCommandModal';

import ShareModal from '@/components/ui/modals/ShareModal';
import type { Command } from '@/types/command';

interface CommandModalsProps {
  actions: {
    activeModal: ModalType;
    closeModal: () => void;
    handleRefresh: () => void;
    setCommandList: React.Dispatch<React.SetStateAction<Command[]>>;
    deleteError: string | null;
    isDeleting: boolean;
  };
  selection: {
    selectedCommand: Command | null;
    itemsToDelete: Command[];
    shareEntityIds: number | number[] | null;
  };
  handlers: {
    confirmDelete: (items: Command[]) => void;
  };
}

export function CommandModals({ actions, selection, handlers }: CommandModalsProps) {
  const { t } = useTranslation();
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('add') && (
        <AddEditCommandModal
          mode="add"
          command={null}
          onClose={actions.closeModal}
          onSave={(created) => {
            actions.setCommandList((prev) => [created, ...prev]);
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('edit') && (
        <AddEditCommandModal
          mode="edit"
          command={selection.selectedCommand}
          onClose={actions.closeModal}
          onSave={(updated) => {
            actions.setCommandList((prev) =>
              prev.map((m) => (m.commandId === updated.commandId ? { ...m, ...updated } : m)),
            );
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('share') && (
        <ShareModal
          isOpen
          title={t('Share Command')}
          entityType="Command"
          entityId={selection.shareEntityIds}
          onClose={actions.closeModal}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteCommandModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          commandName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.command : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
