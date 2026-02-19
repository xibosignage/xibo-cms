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

import ShareModal from './modals/ShareModal';

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { useFolderActions } from '@/hooks/useFolderActions';

interface FolderActionModalsProps {
  folderActions: ReturnType<typeof useFolderActions>;
}

export default function FolderActionModals({ folderActions }: FolderActionModalsProps) {
  const { t } = useTranslation();
  const { actionType, activeFolder, closeAction, isPending, formState, submitHandlers } =
    folderActions;

  return (
    <>
      {/* Create Modal */}
      <Modal
        isOpen={actionType === 'create'}
        isPending={isPending}
        onClose={closeAction}
        title={t('Create New Folder')}
        size="sm"
        actions={[
          { label: t('Cancel'), onClick: closeAction, variant: 'secondary' },
          {
            label: isPending ? t('Creating...') : t('Create'),
            onClick: submitHandlers.create,
            disabled: isPending || !formState.inputText.trim(),
          },
        ]}
      >
        <div className="p-8 pt-0">
          <TextInput
            name="createFolderText"
            label={t('Folder Name')}
            className="w-full border-gray-200 rounded-lg text-sm"
            value={formState.inputText}
            placeholder={t('Add folder name')}
            onChange={(e) => formState.setInputText(e.target.value)}
          />
        </div>
      </Modal>

      {/* Rename Modal */}
      <Modal
        isOpen={actionType === 'rename'}
        isPending={isPending}
        onClose={closeAction}
        title={t('Rename Folder')}
        size="sm"
        actions={[
          { label: t('Cancel'), onClick: closeAction, variant: 'secondary' },
          {
            label: isPending ? t('Renaming...') : t('Rename'),
            onClick: submitHandlers.rename,
            disabled: isPending || !formState.inputText.trim(),
          },
        ]}
      >
        <div className="p-8 pt-0">
          <TextInput
            name="renameFolderText"
            label={t('Folder Name')}
            className="w-full border-gray-200 rounded-lg text-sm"
            value={formState.inputText}
            placeholder={t('Add folder name')}
            onChange={(e) => formState.setInputText(e.target.value)}
          />
        </div>
      </Modal>

      {/* Move Modal */}
      <Modal
        isOpen={actionType === 'move'}
        isPending={isPending}
        onClose={closeAction}
        title={t('Move Folder')}
        size="md"
        actions={[
          { label: t('Cancel'), onClick: closeAction, variant: 'secondary' },
          {
            label: isPending ? t('Moving...') : t('Move'),
            onClick: submitHandlers.move,
            disabled: isPending || !formState.moveTargetId,
          },
        ]}
      >
        <div className="p-8 pt-0 flex flex-col gap-4 overflow-visible">
          <div className="text-sm text-gray-600">
            {t('Move')} <strong>{activeFolder?.text}</strong> {t('to')}:
          </div>
          <SelectFolder
            selectedId={formState.moveTargetId}
            onSelect={(folder) => formState.setMoveTargetId(folder.id)}
          />
          <Checkbox
            id="merge-folder"
            label={t('Merge contents if folder exists?')}
            checked={formState.isMerge}
            onChange={() => formState.setIsMerge(!formState.isMerge)}
          />
        </div>
      </Modal>

      {/* Share Modal */}
      <ShareModal
        openModal={actionType === 'share'}
        showOwner={false}
        onClose={closeAction}
        title={t('Share Folder')}
        entityType="folder"
        entityId={activeFolder?.id ?? null}
      />

      {/* Delete Modal */}
      <Modal
        isOpen={actionType === 'delete'}
        isPending={isPending}
        onClose={closeAction}
        title={t('Delete Folder')}
        size="md"
        actions={[
          { label: t('Cancel'), onClick: closeAction, variant: 'secondary' },
          {
            label: isPending ? t('Deleting...') : t('Delete'),
            onClick: submitHandlers.delete,
            disabled: isPending,
          },
        ]}
      >
        <div className="p-8 pt-0 flex flex-col gap-4">
          <div className="text-sm text-gray-600 bg-red-100 p-4 py-8 rounded-lg overflow-hidden">
            {t('Delete')} <strong>"{activeFolder?.text}"</strong>?
          </div>
        </div>
      </Modal>
    </>
  );
}
