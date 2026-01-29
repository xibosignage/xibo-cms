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

import type { LucideIcon } from 'lucide-react';
import { Image, Film, Music, FileText, Archive, File, HelpCircle, Loader2 } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from '../../../../components/ui/modals/Modal';
import { MEDIA_FORM_OPTIONS } from '../MediaConfig';

import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/forms/Checkbox';
import DurationInput from '@/components/ui/forms/DurationInput';
import type { ExpiryValue } from '@/components/ui/forms/ExpiryDateSelect';
import ExpiryDateSelect from '@/components/ui/forms/ExpiryDateSelect';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import { updateMedia } from '@/services/mediaApi';
import type { MediaRow, MediaType, Tag } from '@/types/media';

interface EditMediaModalProps {
  openModal: boolean;
  data: MediaRow;
  onClose: () => void;
  onSave: (updated: MediaRow) => void;
}

type MediaDraft = {
  name: string;
  folder: string;
  fileName: string;
  tags: Tag[];
  orientation: 'portrait' | 'landscape';
  duration: number;
  mediaNoExpiryDate?: ExpiryValue;
  enableStat: string;
  retired: boolean;
  updateInLayouts: boolean;
};

type OpenSelect = 'folder' | 'orientation' | 'expiry' | 'enableStat' | null;

const getIcon = (type: MediaType): LucideIcon => {
  switch (type) {
    case 'image':
      return Image;
    case 'video':
      return Film;
    case 'audio':
      return Music;
    case 'pdf':
      return FileText;
    case 'archive':
      return Archive;
    default:
      return File;
  }
};

function expiryToDateTime(expiry?: ExpiryValue): string | undefined {
  if (!expiry) return undefined;

  let date: Date;

  if (expiry.type === 'preset') {
    const now = new Date();

    switch (expiry.value) {
      case 'Never Expire':
        return undefined;

      case 'End of Today': {
        date = new Date();
        date.setHours(23, 59, 59, 0);
        break;
      }

      case 'In 7 Days':
        date = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
        break;

      case 'In 14 Days':
        date = new Date(now.getTime() + 14 * 24 * 60 * 60 * 1000);
        break;

      case 'In 30 Days':
        date = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000);
        break;

      default:
        return undefined;
    }
  } else {
    date = expiry.to;
  }
  return date.toISOString().slice(0, 19).replace('T', ' ');
}

function expiresToExpiryValue(expires?: string | number): ExpiryValue | undefined {
  if (!expires || expires === '0') return undefined;

  const timestamp = typeof expires === 'string' ? Number(expires) : expires;

  if (Number.isNaN(timestamp)) return undefined;

  const date = new Date(timestamp * 1000);

  return {
    type: 'range',
    from: date,
    to: date,
  };
}

export default function EditMediaModal({ openModal, onClose, data, onSave }: EditMediaModalProps) {
  const { t } = useTranslation();
  const [isImageLoading, setIsImageLoading] = useState(true);
  const [openSelect, setOpenSelect] = useState<null | OpenSelect>(null);
  const [expiry, setExpiry] = useState<ExpiryValue | undefined>();
  const [isSaving, setIsSaving] = useState(false);
  const [draft, setDraft] = useState<MediaDraft>(() => ({
    name: data.name,
    folder: '',
    fileName: data.name,
    tags: data.tags.map((t) => ({ ...t })),
    orientation: data.orientation,
    duration: data.duration,
    mediaNoExpiryDate: expiry,
    enableStat: data.enableStat,
    retired: data.retired,
    updateInLayouts: data.updateInLayouts,
  }));

  const Icon = getIcon(data.mediaType);

  const toggleFolder = () => setOpenSelect((prev) => (prev === 'folder' ? null : 'folder'));

  useEffect(() => {
    setDraft({
      folder: '',
      name: data.name,
      fileName: data.name,
      tags: data.tags.map((t) => ({ ...t })),
      orientation: data.orientation,
      duration: data.duration,
      mediaNoExpiryDate: expiry,
      enableStat: data.enableStat,
      retired: data.retired,
      updateInLayouts: data.updateInLayouts,
    });

    setExpiry(expiresToExpiryValue(data.expires));
  }, [data]);

  const handleSave = async () => {
    if (isSaving) return;
    setIsSaving(true);

    const serializedTags = draft.tags.map((t) => (t.value != null ? `${t.tag}|${t.value}` : t.tag));

    const expires = expiryToDateTime(expiry);

    const updatedMedia = await updateMedia(data.mediaId, {
      name: draft.name,
      duration: draft.duration,
      retired: draft.retired ? 1 : 0,
      updateInLayouts: draft.updateInLayouts ? 1 : 0,
      tags: serializedTags.join(','),
      orientation: draft.orientation,
      enableStat: draft.enableStat,
      expires,
      mediaNoExpiryDate: expires === undefined ? 1 : 0,
    });

    onSave({
      ...data,
      ...updatedMedia,
    });
    onClose();
  };

  return (
    <Modal
      title={t('Edit Media')}
      onClose={onClose}
      isOpen={openModal}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: t('Save'),
          onClick: handleSave,
          disabled: isSaving,
        },
      ]}
    >
      {isSaving && (
        <div className="absolute w-full h-full center bg-black/20 top-0 left-0 z-10">
          <span className="flex items-center gap-1 flex-col text-white">
            <Loader2 size={32} className="animate-spin text-white" />
            {isSaving ? t('Saving…') : t('Save')}
          </span>
        </div>
      )}
      {/* Media Preview */}
      <div className="p-4 flex gap-3 bg-slate-50 mt-3">
        <div className="h-[150px] aspect-7/6 relative bg-gray-400 overflow-hidden rounded">
          <div className="h-[150px] aspect-7/6 bg-gray-100 flex items-center justify-center rounded">
            {data.thumbnail ? (
              <>
                {isImageLoading && <div className="absolute inset-0 animate-pulse bg-gray-200" />}
                <img
                  src={data.thumbnail}
                  alt={data.fileName}
                  onLoad={() => setIsImageLoading(false)}
                  onError={() => setIsImageLoading(false)}
                  className={`h-full w-full object-contain transition-opacity duration-300 ${
                    isImageLoading ? 'opacity-0' : 'opacity-100'
                  }`}
                />
              </>
            ) : (
              <Icon className="w-10 h-10 text-gray-400" />
            )}
          </div>
        </div>
        <div className="flex flex-col justify-between flex-1">
          <div>
            <span className="text-sm text-gray-500 font-semibold flex items-center gap-1">
              {t('FILE NAME')} <HelpCircle size={12} />
            </span>
            <span className="text-sm">{t(data.fileName)}</span>
          </div>
          <div>
            <span className="text-sm text-gray-500 font-semibold flex items-center gap-1">
              {t('FILE SIZE')} <HelpCircle size={12} />
            </span>
            <span className="text-sm">{t(data.fileSizeFormatted)}</span>
          </div>
          <div>
            <span className="text-sm text-gray-500 font-semibold flex items-center gap-1">
              {t('RESOLUTION')} <HelpCircle size={12} />
            </span>
            <span className="text-sm">
              {data.width} x {data.height}
            </span>
          </div>
        </div>
        <div className="">
          <Button variant="secondary" className="border-0 bg-transparent">
            {t('Replace File')}
          </Button>
        </div>
      </div>
      {/* Forms */}
      <div className="flex flex-col gap-3 mt-3 max-h-[450px] overflow-y-auto px-2">
        {/* Select Folder */}
        <SelectFolder
          value={draft.folder}
          homeFolders={MEDIA_FORM_OPTIONS.folders.home}
          myFileFolders={MEDIA_FORM_OPTIONS.folders.myFiles}
          isOpen={openSelect === 'folder'}
          onToggle={toggleFolder}
          onSelect={(folder) => {
            setDraft((prev) => ({ ...prev, folder }));
            setOpenSelect(null);
          }}
        />

        {/* Name */}
        <div className="flex flex-col">
          <label htmlFor="folderLocation" className="text-xs font-semibold text-gray-500 leading-5">
            {t('Name')}
          </label>
          <input
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

        {/* Orientation */}
        <SelectDropdown
          label="Orientation"
          value={draft.orientation}
          placeholder="Select orientation"
          options={MEDIA_FORM_OPTIONS.orientation}
          isOpen={openSelect === 'orientation'}
          onToggle={() => setOpenSelect((prev) => (prev === 'orientation' ? null : 'orientation'))}
          onSelect={(value) => {
            setDraft((prev) => ({
              ...prev,
              orientation: value as 'portrait' | 'landscape',
            }));
            setOpenSelect(null);
          }}
        />

        {/* Duration */}
        <DurationInput
          value={draft.duration}
          onChange={(seconds) =>
            setDraft((prev) => ({
              ...prev,
              duration: seconds,
            }))
          }
        />

        {/* Expiry Date */}
        <ExpiryDateSelect
          value={expiry}
          options={MEDIA_FORM_OPTIONS.expiryDates}
          isOpen={openSelect === 'expiry'}
          onToggle={() => setOpenSelect((prev) => (prev === 'expiry' ? null : 'expiry'))}
          onSelect={(value) => {
            setExpiry(value);
            setOpenSelect(null);
          }}
        />

        {/* Inherit */}
        <SelectDropdown
          label="Enable Media Stats Collection?"
          value={draft.enableStat}
          placeholder="Inherit"
          options={MEDIA_FORM_OPTIONS.inherit}
          isOpen={openSelect === 'enableStat'}
          onToggle={() => setOpenSelect((prev) => (prev === 'enableStat' ? null : 'enableStat'))}
          onSelect={(value) => {
            setDraft((prev) => ({ ...prev, enableStat: value }));
            setOpenSelect(null);
          }}
          helper={t(
            `Enable the collection of Proof of Play statistics for this Media Item. Ensure that 'Enable Stats Collection' is set to 'On' in the Display Settings.`,
          )}
        />

        {/* Retired */}
        <Checkbox
          id="retired"
          className="items-center"
          title={t('Retire this media?')}
          label={t(
            `Retired media remains on existing Layouts but is not available to assign to new Layouts.`,
          )}
          checked={draft.retired}
          classNameLabel="text-xs"
          onChange={() => setDraft((prev) => ({ ...prev, retired: !prev.retired }))}
        />
        <Checkbox
          id="update"
          className="items-center"
          title={t('Update this media in all layouts it is assigned to')}
          label={t(`Note: It will only be updated in layouts you have permission to edit.`)}
          checked={draft.updateInLayouts}
          classNameLabel="text-xs"
          onChange={() => setDraft((prev) => ({ ...prev, updateInLayouts: !prev.updateInLayouts }))}
        />
      </div>
    </Modal>
  );
}
