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

import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from '../../../../components/ui/modals/Modal';
import { PLAYLIST_FORM_OPTIONS } from '../PlaylistsConfig';

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import { updatePlaylist, createPlaylist } from '@/services/playlistApi';
import type { Playlist } from '@/types/playlist';
import type { Tag } from '@/types/tag';

interface AddAndEditPlaylistModalProps {
  type: 'add' | 'edit';
  openModal: boolean;
  data?: Playlist | null;
  onClose: () => void;
  onSave: (updated: Playlist) => void;
}

interface PlaylistDraft {
  name: string;
  folderId: number | null;
  tags: Tag[];
  enableStat: string;
  isDynamic: boolean;
}

const DEFAULT_DRAFT: PlaylistDraft = {
  name: '',
  folderId: null,
  tags: [],
  enableStat: 'off',
  isDynamic: false,
};

export default function AddAndEditPlaylistModal({
  type,
  openModal,
  onClose,
  data,
  onSave,
}: AddAndEditPlaylistModalProps) {
  const { t } = useTranslation();
  const [openSelect, setOpenSelect] = useState<string | null>(null);

  const [isPending, startTransition] = useTransition();

  const [draft, setDraft] = useState<PlaylistDraft>(() => {
    if (type === 'edit' && data) {
      return {
        name: data.name,
        folderId: data.folderId ?? null,
        tags: data.tags.map((t) => ({ ...t })),
        enableStat: data.enableStat,
        isDynamic: data.isDynamic,
      };
    }
    return { ...DEFAULT_DRAFT };
  });

  useEffect(() => {
    if (type === 'edit' && data) {
      setDraft({
        name: data.name,
        folderId: data.folderId ?? null,
        tags: data.tags.map((t) => ({ ...t })),
        enableStat: data.enableStat,
        isDynamic: data.isDynamic,
      });
    } else {
      setDraft({ ...DEFAULT_DRAFT });
    }
  }, [data, type]);

  const handleSave = () => {
    startTransition(async () => {
      try {
        const serializedTags = draft.tags.map((t) =>
          t.value != null ? `${t.tag}|${t.value}` : t.tag,
        );

        const payload = {
          name: draft.name,
          isDynamic: draft.isDynamic,
          tags: serializedTags.join(','),
          enableStat: draft.enableStat,
          folderId: draft.folderId,
        };

        if (type === 'edit') {
          if (!data) {
            console.error('Cannot edit: Playlist data is missing.');
            return;
          }

          const updatedPlaylist = await updatePlaylist(data.playlistId, payload);

          onSave({
            ...data,
            ...updatedPlaylist,
          });
        } else {
          const newPlaylist = await createPlaylist(payload);
          onSave(newPlaylist);
        }

        onClose();
      } catch (error) {
        console.error('Failed to save playlist:', error);
      }
    });
  };

  const modalTitle = type === 'add' ? t('Add Playlist') : t('Edit Playlist');

  return (
    <Modal
      title={modalTitle}
      onClose={onClose}
      isOpen={openModal}
      isPending={isPending}
      scrollable={false}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isPending,
        },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible gap-3 p-4 pt-0">
        <div className="flex flex-col gap-3 flex-1 min-h-0 p-4 overflow-y-auto">
          {/* Select Folder */}
          <div className="relative z-20">
            <SelectFolder
              selectedId={draft.folderId}
              onSelect={(folder) => {
                setDraft((prev) => ({
                  ...prev,
                  folderId: Number(folder.id),
                }));
              }}
            />
          </div>

          {/* Name */}
          <div className="flex flex-col">
            <label htmlFor="name" className="text-xs font-semibold text-gray-500 leading-5">
              {t('Name')}
            </label>
            <input
              id="name"
              className="border-gray-200 text-sm rounded-lg"
              name="name"
              value={draft.name}
              onChange={(e) => setDraft((prev) => ({ ...prev, name: e.target.value }))}
            />
          </div>

          {/* Tags */}
          <TagInput
            value={draft.tags}
            helpText={t('Tags (Comma-separated: Tag or Tag|Value)')}
            onChange={(tags) => setDraft((prev) => ({ ...prev, tags }))}
          />

          {/* Enable Stats */}
          <SelectDropdown
            label="Enable Playlist Stats Collection?"
            value={draft.enableStat}
            placeholder="Inherit"
            options={PLAYLIST_FORM_OPTIONS.inherit}
            isOpen={openSelect === 'enableStat'}
            onToggle={() => setOpenSelect((prev) => (prev === 'enableStat' ? null : 'enableStat'))}
            onSelect={(value) => {
              setDraft((prev) => ({ ...prev, enableStat: value }));
              setOpenSelect(null);
            }}
            helper={t(
              `Enable the collection of Proof of Play statistics for this Playlist Item. Ensure that 'Enable Stats Collection' is set to 'On' in the Display Settings.`,
            )}
          />

          {/* Dynamic */}
          <div className="p-3 bg-slate-50">
            <Checkbox
              id="isDynamic"
              className="px-3 py-2.5 gap-1 items-start"
              title={t('Dynamic Playlist')}
              label={t(`Use filters to automatically manage media assignments for this playlist.`)}
              checked={draft.isDynamic}
              classNameLabel="text-xs"
              onChange={() => setDraft((prev) => ({ ...prev, isDynamic: !prev.isDynamic }))}
            />
          </div>
        </div>
      </div>
    </Modal>
  );
}
