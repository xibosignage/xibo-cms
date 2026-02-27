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

import { useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { notify } from '@/components/ui/Notification';
import { createFolder, deleteFolder, editFolder, moveFolder } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

export type ActionType = 'create' | 'rename' | 'move' | 'delete' | 'share' | null;

interface UseFolderActionsProps {
  onSuccess?: (targetFolder?: { id: number; text: string }) => void;
}

export function useFolderActions({ onSuccess }: UseFolderActionsProps = {}) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();

  const [activeFolder, setActiveFolder] = useState<Folder | null>(null);
  const [actionType, setActionType] = useState<ActionType>(null);

  const [inputText, setInputText] = useState('');
  const [moveTargetId, setMoveTargetId] = useState<number | null>(null);
  const [isMerge, setIsMerge] = useState(false);

  const openAction = (type: ActionType, folder: Folder) => {
    setActiveFolder(folder);
    setActionType(type);

    if (type === 'create') {
      setInputText(t('New Folder'));
    } else if (type === 'rename') {
      setInputText(folder.text);
    } else if (type === 'move') {
      setMoveTargetId(null);
      setIsMerge(false);
    }
  };

  const closeAction = () => {
    setActionType(null);
    setActiveFolder(null);
    setInputText('');
  };

  const handleCreate = () => {
    if (!activeFolder || !inputText.trim()) {
      return;
    }

    startTransition(async () => {
      const result = await createFolder({
        folderName: inputText,
        parentId: activeFolder.id,
      });

      if (result.success) {
        notify.info(t('Folder "{{name}}" created', { name: inputText }));
        closeAction();
        if (result.data.id) {
          // Selected newly created folder
          onSuccess?.({ id: result.data.id, text: inputText });
        } else {
          onSuccess?.();
        }
      } else {
        notify.error(result.error);
      }
    });
  };

  const handleRename = () => {
    if (!activeFolder || !inputText.trim()) {
      return;
    }

    startTransition(async () => {
      const result = await editFolder({
        id: activeFolder.id,
        text: inputText,
      });

      if (result.success) {
        notify.info(t('Renamed to "{{name}}"', { name: inputText }));
        closeAction();

        // Select renamed folder
        onSuccess?.({ id: activeFolder.id, text: inputText });
      } else {
        notify.error(result.error || t('Failed to rename folder'));
      }
    });
  };

  const handleMove = () => {
    if (!activeFolder || !moveTargetId) return;

    startTransition(async () => {
      const result = await moveFolder({
        id: activeFolder.id,
        targetId: moveTargetId,
        merge: isMerge,
      });

      if (result.success) {
        notify.info(t('Folder "{{name}}" moved', { name: activeFolder.text }));
        closeAction();
        onSuccess?.();
      } else {
        notify.error(result.error);
      }
    });
  };

  const handleDelete = () => {
    if (!activeFolder) return;

    startTransition(async () => {
      const result = await deleteFolder(activeFolder.id);

      if (result.success) {
        notify.info(t('Folder "{{name}}" deleted', { name: activeFolder.text }));
        closeAction();

        // Select home folder
        onSuccess?.({ id: -1, text: t('Root Folder') });

        onSuccess?.();
      } else {
        notify.error(result.error || t('Failed to delete folder'));
      }
    });
  };

  return {
    activeFolder,
    actionType,
    isPending,
    formState: {
      inputText,
      setInputText,
      moveTargetId,
      setMoveTargetId,
      isMerge,
      setIsMerge,
    },
    openAction,
    closeAction,
    submitHandlers: {
      create: handleCreate,
      rename: handleRename,
      move: handleMove,
      delete: handleDelete,
    },
  };
}
