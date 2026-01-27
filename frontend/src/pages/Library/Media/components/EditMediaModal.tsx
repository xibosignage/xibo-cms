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
.*/

import { HelpCircle } from 'lucide-react';
import React, { useState } from 'react';
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
import type { MediaRow } from '@/types/media';

interface EditMediaModalProps {
  openModal: boolean;
  onClose: () => void;
  data: MediaRow;
}

type OpenSelect = 'folder' | 'orientation' | 'expiry' | 'enableStat' | null;

function EditMediaModal({ openModal, onClose, data }: EditMediaModalProps) {
  const { t } = useTranslation();
  const [isImageLoading, setIsImageLoading] = useState(true);
  const [openSelect, setOpenSelect] = useState<null | OpenSelect>(null);
  const [expiry, setExpiry] = useState<ExpiryValue | undefined>();

  const toggleFolder = () => setOpenSelect((prev) => (prev === 'folder' ? null : 'folder'));

  const [mediaData, setMediaState] = useState({
    folder: '',
    fileName: data.fileName,
    tags: data.tags,
    orientation: data.orientation,
    duration: data.duration,
    mediaNoExpiryDate: expiry, // TODO: Update correct expiry data
    enableStat: data.enableStat,
    retired: data.retired,
    update: false, // TODO: Update correct update info data
  });
  return (
    <Modal
      title="Edit Media"
      onClose={onClose}
      isOpen={openModal}
      actions={[
        {
          label: 'Cancel',
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: 'Save',
          // TODO: Add update media function for Save Button
        },
      ]}
    >
      {/* Media Preview */}
      <div className="p-4 flex gap-3 bg-slate-50 mt-3">
        <div className="h-[150px] aspect-7/6 relative bg-gray-400 overflow-hidden rounded">
          {/* Loader */}
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
          value={mediaData.folder}
          homeFolders={MEDIA_FORM_OPTIONS.folders.home}
          myFileFolders={MEDIA_FORM_OPTIONS.folders.myFiles}
          isOpen={openSelect === 'folder'}
          onToggle={toggleFolder}
          onSelect={(folder) => {
            setMediaState((prev) => ({ ...prev, folder }));
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
            name="fileName"
            value={mediaData.fileName}
            onChange={(e) => setMediaState((prev) => ({ ...prev, fileName: e.target.value }))}
          />
        </div>

        {/* Tags */}
        <TagInput
          value={mediaData.tags}
          onChange={(tags) => setMediaState((prev) => ({ ...prev, tags }))}
        />

        {/* Orientation */}
        <SelectDropdown
          label="Orientation"
          value={mediaData.orientation}
          placeholder="Select orientation"
          options={MEDIA_FORM_OPTIONS.orientation}
          isOpen={openSelect === 'orientation'}
          onToggle={() => setOpenSelect((prev) => (prev === 'orientation' ? null : 'orientation'))}
          onSelect={(value) => {
            setMediaState((prev) => ({ ...prev, orientation: value }));
            setOpenSelect(null);
          }}
        />

        {/* Duration */}
        <DurationInput
          value={mediaData.duration}
          onChange={(seconds) =>
            setMediaState((prev) => ({
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
          value={mediaData.enableStat}
          placeholder="Inherit"
          options={MEDIA_FORM_OPTIONS.inherit}
          isOpen={openSelect === 'enableStat'}
          onToggle={() => setOpenSelect((prev) => (prev === 'enableStat' ? null : 'enableStat'))}
          onSelect={(value) => {
            setMediaState((prev) => ({ ...prev, enableStat: value }));
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
          checked={mediaData.retired}
          classNameLabel="text-xs"
          onChange={() => setMediaState((prev) => ({ ...prev, retired: !prev.retired }))}
        />
        <Checkbox
          id="retired"
          className="items-center"
          title={t('Update this media in all layouts it is assigned to')}
          label={t(`Note: It will only be updated in layouts you have permission to edit.`)}
          checked={mediaData.update}
          classNameLabel="text-xs"
          onChange={() => setMediaState((prev) => ({ ...prev, update: !prev.update }))}
        />
      </div>
    </Modal>
  );
}

export default EditMediaModal;
