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

import AddCampaignModal from './AddCampaignModal';
import CopyCampaignModal from './CopyCampaignModal';
import DeleteCampaignModal from './DeleteCampaignModal';
import EditCampaignModal from './EditCampaignModal';

import FolderActionModals from '@/components/ui/FolderActionModals';
import EditTagsMultipleModal from '@/components/ui/modals/EditTagsMultipleModal';
import MoveModal from '@/components/ui/modals/MoveModal';
import ScheduleEventModal from '@/components/ui/modals/ScheduleEventModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { Campaign } from '@/types/campaign';
import { EventTypeId } from '@/types/event';
import { mergeEntityTags } from '@/utils/tags';

interface CampaignModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => Promise<unknown>;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedCampaign: Campaign | null;
    defaultFolderId?: number;
    itemsToDelete: Campaign[];
    itemsToMove: Campaign[];
    bulkItems: Campaign[];
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: Campaign[]) => void;
    handleConfirmMove: (newFolderId: number) => void;
    handleConfirmClone: (campaign: Campaign | null, newName: string) => void;
    handleAddSuccess: (campaign: Campaign) => void;
  };
  folderActions: ReturnType<typeof useFolderActions>;
}

export function CampaignModals({
  actions,
  selection,
  handlers,
  folderActions,
}: CampaignModalsProps) {
  const { t } = useTranslation();

  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {/* Edit */}
      {isModalOpen('edit') && (
        <EditCampaignModal
          isOpen
          campaign={selection.selectedCampaign}
          onClose={actions.closeModal}
          onSuccess={actions.handleRefresh}
        />
      )}

      {/* Add */}
      {isModalOpen('add') && (
        <AddCampaignModal
          defaultFolderId={selection.defaultFolderId}
          onClose={actions.closeModal}
          onSuccess={handlers.handleAddSuccess}
        />
      )}
      {/* Folder Actions */}
      <FolderActionModals folderActions={folderActions} />

      {/* Share */}
      {isModalOpen('share') && (
        <ShareModal
          title={t('Share Campaign')}
          onClose={() => {
            actions.closeModal();
            selection.setShareEntityIds(null);
            actions.handleRefresh();
          }}
          entityType="campaign"
          entityId={selection.shareEntityIds ?? (selection.selectedCampaign?.campaignId || null)}
        />
      )}

      {/* Copy */}
      {isModalOpen('copy') && (
        <CopyCampaignModal
          onClose={actions.closeModal}
          onConfirm={(name) => handlers.handleConfirmClone(selection.selectedCampaign, name)}
          campaign={selection.selectedCampaign}
          isLoading={actions.isCloning}
          existingNames={selection.existingNames}
        />
      )}

      {/* Delete */}
      {isModalOpen('delete') && (
        <DeleteCampaignModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          campaignName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.campaign : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}

      {/* Move */}
      {isModalOpen('move') && (
        <MoveModal
          onClose={actions.closeModal}
          onConfirm={handlers.handleConfirmMove}
          items={selection.itemsToMove}
          entityLabel={t('Campaigns')}
        />
      )}

      {/* Edit Tags (bulk) */}
      {isModalOpen('editTagsMultiple') && (
        <EditTagsMultipleModal
          targetType="campaign"
          ids={selection.bulkItems.map((item) => item.campaignId)}
          existingTags={mergeEntityTags(selection.bulkItems)}
          onClose={actions.closeModal}
          onSuccess={async () => {
            await actions.handleRefresh();
            actions.closeModal();
          }}
        />
      )}

      {/* Schedule */}
      {isModalOpen('schedule') && selection.selectedCampaign && (
        <ScheduleEventModal
          isOpen
          onClose={() => {
            actions.closeModal();
            actions.handleRefresh();
          }}
          mode="schedule"
          eventTypeId={EventTypeId.Campaign}
          contentId={selection.selectedCampaign.campaignId}
          contentName={selection.selectedCampaign.campaign}
        />
      )}
    </>
  );
}
