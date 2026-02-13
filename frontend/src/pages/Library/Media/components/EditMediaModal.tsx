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

import { HelpCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from '../../../../components/ui/modals/Modal';
import { getMediaIcon, MEDIA_FORM_OPTIONS } from '../MediaConfig';

import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/forms/Checkbox';
import DurationInput from '@/components/ui/forms/DurationInput';
import type { ExpiryValue } from '@/components/ui/forms/ExpiryDateSelect';
import ExpiryDateSelect from '@/components/ui/forms/ExpiryDateSelect';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import { updateMedia } from '@/services/mediaApi';
import type { Media } from '@/types/media';
import type { Tag } from '@/types/tag';
import { expiresToExpiryValue, expiryToDateTime } from '@/utils/date';

interface EditMediaModalProps {
  openModal: boolean;
  data: Media;
  onClose: () => void;
  onSave: (updated: Media) => void;
}

type MediaDraft = {
  name: string;
  folderId: number | null;
  fileName: string;
  tags: Tag[];
  orientation: 'portrait' | 'landscape';
  duration: number;
  mediaNoExpiryDate?: ExpiryValue;
  enableStat: string;
  retired: boolean;
  updateInLayouts: boolean;
};

type OpenSelect = 'orientation' | 'expiry' | 'enableStat' | null;

export default function EditMediaModal({ openModal, onClose, data, onSave }: EditMediaModalProps) {
  const { t } = useTranslation();
  const [isImageLoading, setIsImageLoading] = useState(true);
  const [openSelect, setOpenSelect] = useState<null | OpenSelect>(null);
  const [expiry, setExpiry] = useState<ExpiryValue | undefined>(() =>
    expiresToExpiryValue(data.expires),
  );

  const [isSaving, setIsSaving] = useState(false);
  const [draft, setDraft] = useState<MediaDraft>(() => ({
    name: data.name,
    folderId: data.folderId ?? null,
    fileName: data.name,
    tags: data.tags.map((t) => ({ ...t })),
    orientation: data.orientation,
    duration: data.duration,
    mediaNoExpiryDate: expiresToExpiryValue(data.expires),
    enableStat: data.enableStat,
    retired: data.retired,
    updateInLayouts: data.updateInLayouts,
  }));

  const Icon = getMediaIcon(data.mediaType);

  useEffect(() => {
    const initialExpiry = expiresToExpiryValue(data.expires);

    setExpiry(initialExpiry);

    setDraft({
      folderId: data.folderId,
      name: data.name,
      fileName: data.name,
      tags: data.tags.map((t) => ({ ...t })),
      orientation: data.orientation,
      duration: data.duration,
      mediaNoExpiryDate: initialExpiry,
      enableStat: data.enableStat,
      retired: data.retired,
      updateInLayouts: data.updateInLayouts,
    });
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
      folderId: draft.folderId,
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
      isPending={isSaving}
      scrollable={false}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isSaving,
        },
        {
          label: isSaving ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isSaving,
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible gap-3 p-4 pt-0">
        <div className="shrink-0 p-4 m-4 mt-0 flex gap-3 bg-slate-50 rounded-lg">
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

          <div>
            <Button variant="secondary" className="border-0 bg-transparent">
              {t('Replace File')}
            </Button>
          </div>
        </div>

        <div className="flex flex-col gap-3 flex-1 min-h-0 p-4 overflow-y-auto pb-32">
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

          {/* Orientation */}
          <SelectDropdown
            label="Orientation"
            value={draft.orientation}
            placeholder="Select orientation"
            options={MEDIA_FORM_OPTIONS.orientation}
            isOpen={openSelect === 'orientation'}
            onToggle={() =>
              setOpenSelect((prev) => (prev === 'orientation' ? null : 'orientation'))
            }
            onSelect={(value) => {
              setDraft((prev) => ({ ...prev, orientation: value as 'portrait' | 'landscape' }));
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
            onChange={() =>
              setDraft((prev) => ({ ...prev, updateInLayouts: !prev.updateInLayouts }))
            }
          />
        </div>
      </div>
    </Modal>
  );
}
