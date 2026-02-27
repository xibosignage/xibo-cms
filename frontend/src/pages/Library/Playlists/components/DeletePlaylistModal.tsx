import { Info, Trash2Icon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';

interface DeletePlaylistModalProps {
  isOpen: boolean;
  onClose: () => void;
  onDelete: () => void;
  itemCount: number;
  playlistName?: string;
  error?: string | null;
  isLoading?: boolean;
}

export default function DeletePlaylistModal({
  isOpen,
  onClose,
  onDelete,
  playlistName,
  isLoading,
  itemCount,
  error,
}: DeletePlaylistModalProps) {
  const { t } = useTranslation();
  return (
    <Modal
      isOpen={isOpen}
      isPending={isLoading}
      onClose={onClose}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isLoading ? t('Deleting…') : t('Yes, Delete'),
          onClick: () => onDelete(),
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div>
          <div className="flex justify-center mb-4">
            <div className="bg-red-100 w-15.5 h-15.5 text-red-800 border-red-50 border-[7px] rounded-full p-3">
              <Trash2Icon size={26} />
            </div>
          </div>
          <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
            {itemCount === 1 ? t('Delete Playlist?') : t('Delete Playlists?')}
          </h2>
        </div>
        <p className="text-center text-gray-500">
          {itemCount === 1 ? (
            <>
              {t('Are you sure you want to delete ')}"<strong>{playlistName}</strong>?"
            </>
          ) : (
            <>
              {t('Are you sure you want to delete ')}
              <strong>{itemCount}</strong> {t('playlists')}?
            </>
          )}
        </p>

        <span className="flex gap-px rounded-md bg-gray-50 p-1.5">
          <Info size={12} />
          <span className="text-[12px] px-1 font-medium">
            {t(
              'All media will be unassigned, and playlist-specific contend (Text/RSS) will be permanently lost. This removes the playlist from all layouts.',
            )}
          </span>
        </span>

        {error && (
          <div className="mt-2 text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}
      </div>
    </Modal>
  );
}
