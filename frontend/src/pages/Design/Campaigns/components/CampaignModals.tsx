import { useTranslation } from 'react-i18next';

import AddCampaignModal from './AddCampaignModal';
import CopyCampaignModal from './CopyCampaignModal';
import DeleteCampaignModal from './DeleteCampaignModal';
import EditCampaignModal from './EditCampaignModal';

import FolderActionModals from '@/components/ui/FolderActionModals';
import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { Campaign } from '@/types/campaign';

interface CampaignModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedCampaign: Campaign | null;
    itemsToDelete: Campaign[];
    itemsToMove: Campaign[];
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: Campaign[]) => void;
    handleConfirmMove: (newFolderId: number) => void;
    handleConfirmClone: (campaign: Campaign | null, newName: string) => void;
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
          onClose={actions.closeModal}
          onSuccess={() => {
            actions.handleRefresh();
          }}
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
    </>
  );
}
