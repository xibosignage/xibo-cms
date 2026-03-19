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

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import { getLayoutSchema } from '@/schema/layout';
import { updateLayout } from '@/services/layoutsApi';
import type { Layout } from '@/types/layout';
import type { Tag } from '@/types/tag';

interface EditLayoutModalProps {
  openModal: boolean;
  data: Layout;
  onClose: () => void;
  onSave: (updated: Layout) => void;
}

type LayoutFormErrors = {
  name?: string;
  description?: string;
  code?: string;
};

type LayoutDraft = {
  name: string;
  folderId: number;
  tags: Tag[];
  enableStat: boolean;
  retired: boolean;
  description: string | null;
  code: string;
};

export default function EditLayout({ openModal, onClose, data, onSave }: EditLayoutModalProps) {
  const { t } = useTranslation();

  const [isSaving, setIsSaving] = useState(false);
  const [formErrors, setFormErrors] = useState<LayoutFormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();

  const [draft, setDraft] = useState<LayoutDraft>(() => ({
    name: data.name || data.layout,
    folderId: data.folderId,
    tags: data.tags?.map((t) => ({ ...t })) ?? [],
    enableStat: data.enableStat,
    retired: data.retired,
    description: data.description ?? null,
    code: data.code ? String(data.code) : '',
  }));

  useEffect(() => {
    setDraft({
      name: data.name || data.layout,
      folderId: data.folderId,
      tags: data.tags?.map((t) => ({ ...t })) ?? [],
      enableStat: data.enableStat,
      retired: data.retired,
      description: data.description ?? null,
      code: data.code ? String(data.code) : '',
    });
  }, [data]);

  const clearError = (field: keyof LayoutFormErrors) => {
    setFormErrors((prev) => ({
      ...prev,
      [field]: undefined,
    }));
  };

  const handleSave = async () => {
    if (isSaving) return;

    const layoutSchema = getLayoutSchema(t);
    const result = layoutSchema.safeParse(draft);

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors;

      setFormErrors({
        name: fieldErrors.name?.[0],
        description: fieldErrors.description?.[0],
        code: fieldErrors.code?.[0],
      });

      return;
    }

    setFormErrors({});

    try {
      setIsSaving(true);

      const serializedTags = draft.tags
        .map((t) => (t.value ? `${t.tag}|${t.value}` : t.tag))
        .join(',');

      const updatedLayout = await updateLayout(data.layoutId, {
        name: draft.name,
        description: draft.description,
        tags: serializedTags,
        retired: draft.retired ? 1 : 0,
        enableStat: draft.enableStat ? 1 : 0,
        folderId: draft.folderId,
      });

      onSave(updatedLayout);
      onClose();
    } catch (err) {
      console.error('Failed to update layout', err);
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
      title={t('Edit Layout')}
      onClose={onClose}
      isOpen={openModal}
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

          <TextInput
            name="description"
            label={t('Description')}
            value={draft.description ?? ''}
            placeholder={t('Add description')}
            helpText={t('Optional description for this layout')}
            onChange={(value) => {
              setDraft((prev) => ({
                ...prev,
                description: value,
              }));
              clearError('description');
            }}
            multiline
            rows={3}
            error={formErrors.description}
          />
          {/* Tags */}
          <TagInput
            value={draft.tags}
            helpText={t('Tags separated by commas. Use Tag|Value for tagged attributes.')}
            onChange={(tags) => setDraft((prev) => ({ ...prev, tags }))}
          />

          <TextInput
            name="code"
            label={t('Code')}
            value={draft.code}
            placeholder={t('Optional identifier')}
            helpText={t('Code identifier for this layout')}
            onChange={(value) =>
              setDraft((prev) => ({
                ...prev,
                code: value,
              }))
            }
            error={formErrors.code}
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
            checked={draft.enableStat}
            onChange={() => setDraft((prev) => ({ ...prev, enableStat: !prev.enableStat }))}
          />
        </div>
      </div>
    </Modal>
  );
}
