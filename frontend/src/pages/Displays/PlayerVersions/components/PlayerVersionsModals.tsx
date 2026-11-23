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

import type { ModalType } from '../PlayerVersionsConfig';

import AddPlayerVersionModal from './AddPlayerVersionModal';
import DeletePlayerVersionModal from './DeletePlayerVersionModal';
import EditPlayerVersionModal from './EditPlayerVersionModal';

import type { PlayerVersion } from '@/types/playerVersion';

interface PlayerVersionModalsProps {
  actions: {
    activeModal: ModalType;
    closeModal: () => void;
    handleRefresh: () => void;
    setPlayerVersionList: React.Dispatch<React.SetStateAction<PlayerVersion[]>>;
    deleteError: string | null;
    isDeleting: boolean;
  };
  selection: {
    selectedPlayerVersion: PlayerVersion | null;
    itemsToDelete: PlayerVersion[];
  };
  handlers: {
    confirmDelete: (items: PlayerVersion[]) => void;
  };
}

export function PlayerVersionModals({ actions, selection, handlers }: PlayerVersionModalsProps) {
  const isModalOpen = (name: ModalType) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('add') && (
        <AddPlayerVersionModal
          onClose={actions.closeModal}
          onSave={() => {
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('edit') && (
        <EditPlayerVersionModal
          data={selection.selectedPlayerVersion}
          onClose={actions.closeModal}
          onSave={(updated) => {
            actions.setPlayerVersionList((prev) =>
              prev.map((m) => (m.versionId === updated.versionId ? { ...m, ...updated } : m)),
            );
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('delete') && (
        <DeletePlayerVersionModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          playerVersionName={
            selection.itemsToDelete.length === 1
              ? selection.itemsToDelete[0]?.playerShowVersion
              : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
