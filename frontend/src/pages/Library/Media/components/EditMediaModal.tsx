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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from '../../../../components/ui/modals/Modal';
import { MEDIA_FORM_OPTIONS } from '../MediaConfig';

import Checkbox from '@/components/ui/forms/Checkbox';
import DurationInput from '@/components/ui/forms/DurationInput';
import ExpiryDateSelect from '@/components/ui/forms/ExpiryDateSelect';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import { getCommonFormOptions } from '@/config/commonForms';
import { getMediaSchema } from '@/schema/media';
import { updateMedia } from '@/services/mediaApi';
import type { Media } from '@/types/media';
import type { Tag } from '@/types/tag';
import type { ExpiryValue } from '@/utils/date';
import { expiresToExpiryValue, expiryToDateTime } from '@/utils/date';

interface EditMediaModalProps {
  isOpen?: boolean;
  data: Media;
  onClose: () => void;
  onSave: (updated: Media) => void;
}

type MediaFormErrors = Partial<Record<keyof MediaDraft, string>>;

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

export default function EditMediaModal({
  isOpen = true,
  onClose,
  data,
  onSave,
}: EditMediaModalProps) {
  const { t } = useTranslation();
  const [expiry, setExpiry] = useState<ExpiryValue>(expiresToExpiryValue(data.expires));
  const [apiError, setApiError] = useState<string | undefined>();
  const [formErrors, setFormErrors] = useState<MediaFormErrors>({});
  const [isSaving, setIsSaving] = useState(false);

  const clearError = (field: keyof MediaDraft) => {
    setFormErrors((prev) => ({
      ...prev,
      [field]: undefined,
    }));
  };

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

    const mediaSchema = getMediaSchema(t);
    const result = mediaSchema.safeParse(draft);

    if (!result.success) {
      const fieldErrors: Partial<MediaFormErrors> = {};

      result.error.errors.forEach((err) => {
        const field = err.path[0] as keyof MediaFormErrors;

        if (field) {
          fieldErrors[field] = err.message;
        }
      });

      setFormErrors(fieldErrors);
      return;
    }

    setFormErrors({});
    setIsSaving(true);

    const serializedTags = draft.tags.map((t) => (t.value != null ? `${t.tag}|${t.value}` : t.tag));

    const expires = expiryToDateTime(expiry);

    try {
      const updatedMedia = await updateMedia(data.mediaId, {
        name: draft.name,
        duration: draft.duration,
        retired: draft.retired ? 1 : 0,
        updateInLayouts: draft.updateInLayouts ? 1 : 0,
        tags: serializedTags.join(','),
        orientation: draft.orientation,
        enableStat: draft.enableStat,
        expires,
        mediaNoExpiryDate: expiry?.type === 'never' ? 1 : 0,
        folderId: draft.folderId,
      });

      onSave({
        ...data,
        ...updatedMedia,
      });

      onClose();
    } catch (err) {
      console.error('Failed to update media', err);
      const apiError = err as { response?: { data?: { message?: string } } };

      if (apiError.response?.data?.message) {
        setApiError(apiError.response.data.message);
      } else if (err instanceof Error) {
        setApiError(err.message);
      } else {
        setApiError(t('An unexpected error occurred while saving the playlist.'));
      }
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <Modal
      title={t('Edit Media')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isSaving}
      scrollable={false}
      error={apiError}
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
        <div className="flex flex-col gap-3 flex-1 min-h-0 p-4 overflow-y-auto pb-20">
          {/* Select Folder */}
          <div className="relative z-20">
            <SelectFolder
              selectedId={draft.folderId}
              onSelect={(folder) => {
                setDraft((prev) => ({
                  ...prev,
                  folderId: Number(folder?.id),
                }));
              }}
            />
          </div>

          {/* Name */}
          <TextInput
            name="name"
            label={t('Name')}
            value={draft.name ?? ''}
            onChange={(value) => {
              setDraft((prev) => ({
                ...prev,
                name: value,
              }));
              clearError('name');
            }}
            error={formErrors.name}
          />

          {/* Tags */}
          <TagInput
            value={draft.tags}
            helpText={t('Tags separated by commas. Use Tag|Value for tagged attributes.')}
            onChange={(tags) => setDraft((prev) => ({ ...prev, tags }))}
          />

          <div className="grid grid-cols-2 gap-2">
            {/* Orientation */}
            <SelectDropdown
              label="Orientation"
              value={draft.orientation}
              placeholder="Select orientation"
              options={getCommonFormOptions(t).orientation}
              onSelect={(value) => {
                setDraft((prev) => ({ ...prev, orientation: value as 'portrait' | 'landscape' }));
              }}
              error={formErrors.orientation}
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
          </div>

          {/* Expiry Date */}
          <ExpiryDateSelect
            value={expiry}
            options={MEDIA_FORM_OPTIONS.expiryDates}
            onSelect={(value) => {
              setExpiry(value);
            }}
          />

          {/* Enable Stats */}
          <SelectDropdown
            label="Enable Media Stats Collection?"
            value={draft.enableStat}
            placeholder="Inherit"
            options={getCommonFormOptions(t).inherit}
            onSelect={(value) => {
              setDraft((prev) => ({ ...prev, enableStat: value }));
            }}
            helper={t(
              `Enable the collection of Proof of Play statistics for this Media Item. Ensure that 'Enable Stats Collection' is set to 'On' in the Display Settings.`,
            )}
            error={formErrors.enableStat}
          />

          {/* Retired */}
          <Checkbox
            id="retired"
            className="items-center px-3 py-2.5"
            title={t('Retire this media?')}
            label={t(
              `Retired media remains on existing Layouts but is not available to assign to new Layouts.`,
            )}
            checked={draft.retired}
            onChange={() => setDraft((prev) => ({ ...prev, retired: !prev.retired }))}
          />
          <Checkbox
            id="update"
            className="items-center px-3 py-2.5"
            title={t('Update this media in all layouts it is assigned to')}
            label={t(`Note: It will only be updated in layouts you have permission to edit.`)}
            checked={draft.updateInLayouts}
            onChange={() =>
              setDraft((prev) => ({ ...prev, updateInLayouts: !prev.updateInLayouts }))
            }
          />
        </div>
      </div>
    </Modal>
  );
}
