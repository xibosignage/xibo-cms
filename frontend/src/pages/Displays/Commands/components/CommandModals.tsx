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
