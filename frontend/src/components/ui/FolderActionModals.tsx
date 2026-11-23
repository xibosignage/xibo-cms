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

import { Trans, useTranslation } from 'react-i18next';

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
      {actionType === 'create' && (
        <Modal
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
              onChange={(val) => formState.setInputText(val)}
            />
          </div>
        </Modal>
      )}

      {/* Rename Modal */}
      {actionType === 'rename' && (
        <Modal
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
              onChange={(val) => formState.setInputText(val)}
            />
          </div>
        </Modal>
      )}

      {/* Move Modal */}
      {actionType === 'move' && (
        <Modal
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
              <Trans
                i18nKey="Move <strong>{{name}}</strong> to:"
                values={{ name: activeFolder?.text }}
                components={{ strong: <strong /> }}
              />
            </div>
            <SelectFolder
              selectedId={formState.moveTargetId}
              onSelect={(folder) => folder && formState.setMoveTargetId(folder.id)}
            />
            <Checkbox
              id="merge-folder"
              label={t('Merge contents if folder exists?')}
              checked={formState.isMerge}
              onChange={() => formState.setIsMerge(!formState.isMerge)}
            />
          </div>
        </Modal>
      )}

      {/* Share Modal */}
      {actionType === 'share' && (
        <ShareModal
          showOwner={false}
          onClose={closeAction}
          title={t('Share Folder')}
          entityType="folder"
          entityId={activeFolder?.id ?? null}
        />
      )}

      {/* Delete Modal */}
      {actionType === 'delete' && (
        <Modal
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
              <Trans
                i18nKey='Delete <strong>"{{name}}"</strong>?'
                values={{ name: activeFolder?.text }}
                components={{ strong: <strong /> }}
              />
            </div>
          </div>
        </Modal>
      )}
    </>
  );
}
