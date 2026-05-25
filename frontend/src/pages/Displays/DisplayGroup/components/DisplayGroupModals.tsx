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

import AddAndEditDisplayGroupModal from './AddAndEditDisplayGroupModal';
import CopyDisplayGroupModal from './CopyDisplayGroupModal';
import DeleteDisplayGroupModal from './DeleteDisplayGroupModal';
import ManageMembersModal from './ManageMembersModal';

import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { CopyDisplayGroupFormData } from '@/pages/Displays/DisplayGroup/hooks/useDisplayGroupActions';
import AssignLayoutModal from '@/pages/Displays/Displays/components/AssignLayoutModal';
import AssignMediaModal from '@/pages/Displays/Displays/components/AssignMediaModal';
import CollectNowModal from '@/pages/Displays/Displays/components/CollectNowModal';
import SendCommandModal from '@/pages/Displays/Displays/components/SendCommandModal';
import TriggerWebhookModal from '@/pages/Displays/Displays/components/TriggerWebhookModal';
import type { DisplayCommandTarget } from '@/types/display';
import type { DisplayGroup } from '@/types/displayGroup';

interface DisplayGroupModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCopying: boolean;
    isMoving: boolean;
    isActionPending: boolean;
    actionError: string | null;
  };
  selection: {
    selectedDisplayGroup: DisplayGroup | null;
    itemsToDelete: DisplayGroup[];
    existingNames: string[];
    itemsToMove: DisplayGroup[];
    folderName: string;
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: DisplayGroup[]) => void;
    confirmCopy: (displayGroupId: number, data: CopyDisplayGroupFormData) => void;
    confirmMove: (targetFolderId: number) => void;
    confirmCollectNow: () => void;
    confirmSendCommand: (displayGroupId: number, commandId: number) => void;
    confirmTriggerWebhook: (displayGroupId: number, triggerCode: string) => void;
    confirmBulkSendCommand: (items: DisplayGroup[], commandId: number) => void;
    confirmBulkTriggerWebhook: (items: DisplayGroup[], triggerCode: string) => void;
    getAllSelectedItems: () => DisplayGroup[];
  };
}

export function DisplayGroupModals({ actions, selection, handlers }: DisplayGroupModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;
  const { t } = useTranslation();

  const displayAdapter: DisplayCommandTarget | null = selection.selectedDisplayGroup
    ? {
        displayGroupId: selection.selectedDisplayGroup.displayGroupId,
        display: selection.selectedDisplayGroup.displayGroup,
      }
    : null;

  return (
    <>
      {(isModalOpen('add') || isModalOpen('edit')) && (
        <AddAndEditDisplayGroupModal
          type={actions.activeModal === 'edit' ? 'edit' : 'add'}
          isOpen
          data={actions.activeModal === 'edit' ? selection.selectedDisplayGroup : null}
          onClose={actions.closeModal}
          onSave={() => {
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('copy') && (
        <CopyDisplayGroupModal
          displayGroup={selection.selectedDisplayGroup}
          onClose={actions.closeModal}
          onConfirm={(data) => {
            if (selection.selectedDisplayGroup) {
              handlers.confirmCopy(selection.selectedDisplayGroup.displayGroupId, data);
            }
          }}
          existingNames={selection.existingNames}
          isLoading={actions.isCopying}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteDisplayGroupModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          displayGroupName={
            selection.itemsToDelete.length === 1
              ? selection.itemsToDelete[0]?.displayGroup
              : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}

      {isModalOpen('move') && (
        <MoveModal
          onClose={actions.closeModal}
          onConfirm={handlers.confirmMove}
          items={selection.itemsToMove.map((item) => ({
            ...item,
            folderName: selection.folderName,
          }))}
          entityLabel={t('Display Groups')}
          isLoading={actions.isMoving}
        />
      )}

      {isModalOpen('assignFiles') && displayAdapter && (
        <AssignMediaModal
          display={displayAdapter}
          onClose={actions.closeModal}
          onSave={actions.handleRefresh}
        />
      )}

      {isModalOpen('assignLayouts') && displayAdapter && (
        <AssignLayoutModal
          display={displayAdapter}
          onClose={actions.closeModal}
          onSave={actions.handleRefresh}
        />
      )}

      {isModalOpen('collectNow') && selection.selectedDisplayGroup && (
        <CollectNowModal
          onClose={actions.closeModal}
          onConfirm={handlers.confirmCollectNow}
          isActionPending={actions.isActionPending}
          actionError={null}
        />
      )}

      {isModalOpen('sendCommand') && displayAdapter && (
        <SendCommandModal
          items={[displayAdapter]}
          onClose={actions.closeModal}
          onConfirm={(_items, commandId) => {
            if (selection.selectedDisplayGroup) {
              handlers.confirmSendCommand(selection.selectedDisplayGroup.displayGroupId, commandId);
            }
          }}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('triggerWebhook') && displayAdapter && (
        <TriggerWebhookModal
          items={[displayAdapter]}
          onClose={actions.closeModal}
          onConfirm={(_items, triggerCode) => {
            if (selection.selectedDisplayGroup) {
              handlers.confirmTriggerWebhook(
                selection.selectedDisplayGroup.displayGroupId,
                triggerCode,
              );
            }
          }}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('bulkSendCommand') && (
        <SendCommandModal
          items={handlers.getAllSelectedItems().map((dg) => ({
            displayGroupId: dg.displayGroupId,
            display: dg.displayGroup,
          }))}
          onClose={actions.closeModal}
          onConfirm={(_items, commandId) => {
            handlers.confirmBulkSendCommand(handlers.getAllSelectedItems(), commandId);
          }}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('bulkTriggerWebhook') && (
        <TriggerWebhookModal
          items={handlers.getAllSelectedItems().map((dg) => ({
            displayGroupId: dg.displayGroupId,
            display: dg.displayGroup,
          }))}
          onClose={actions.closeModal}
          onConfirm={(_items, triggerCode) => {
            handlers.confirmBulkTriggerWebhook(handlers.getAllSelectedItems(), triggerCode);
          }}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('members') && (
        <ManageMembersModal
          isOpen
          displayGroup={selection.selectedDisplayGroup}
          onClose={actions.closeModal}
          onSuccess={actions.handleRefresh}
        />
      )}

      {isModalOpen('share') && (
        <ShareModal
          isOpen
          title={t('Share Display Group')}
          entityType="displayGroup"
          entityId={
            selection.shareEntityIds ?? (selection.selectedDisplayGroup?.displayGroupId || null)
          }
          onClose={() => {
            actions.closeModal();
            selection.setShareEntityIds(null);
            actions.handleRefresh();
          }}
        />
      )}
    </>
  );
}
