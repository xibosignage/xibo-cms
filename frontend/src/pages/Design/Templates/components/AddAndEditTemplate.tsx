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

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import { getTemplateSchema } from '@/schema/templates';
import { fetchResolution } from '@/services/resolutionApi';
import { createTemplate, updateTemplate } from '@/services/templatesApi';
import type { Resolution } from '@/types/resolution';
import type { Tag } from '@/types/tag';
import type { Template } from '@/types/templates';

interface AddAndEditTemplateModalProps {
  type: 'add' | 'edit';
  isOpen?: boolean;
  data?: Template | null;
  onClose: () => void;
  onSave: (updated: Template) => void;
}

type TemplateDraft = {
  name: string;
  folderId: number | null;
  tags: Tag[];
  description: string;
  retired: boolean;
  resolutionId: number | null;
};

type TemplateFormErrors = Partial<Record<keyof TemplateDraft, string>>;

const DEFAULT_DRAFT: TemplateDraft = {
  name: '',
  folderId: null,
  tags: [],
  description: '',
  retired: false,
  resolutionId: null,
};

export default function AddAndEditTemplateModal({
  type,
  isOpen = true,
  onClose,
  data,
  onSave,
}: AddAndEditTemplateModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [formErrors, setFormErrors] = useState<TemplateFormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();
  const [resolutions, setResolutions] = useState<Resolution[]>([]);
  const [loadingResolutions, setLoadingResolutions] = useState(false);

  const [draft, setDraft] = useState<TemplateDraft>(() => {
    if (type === 'edit' && data) {
      return {
        name: data.layout || '',
        folderId: data.folderId ?? null,
        tags: data.tags?.map((t) => ({ ...t })) ?? [],
        description: data.description ?? '',
        retired: !!data.retired,
        resolutionId: null,
      };
    }
    return DEFAULT_DRAFT;
  });

  useEffect(() => {
    if (!isOpen) return;

    const loadResolutions = async () => {
      setLoadingResolutions(true);
      try {
        const res = await fetchResolution({
          start: 0,
          length: 100,
        });

        setResolutions(res.rows);
      } catch (err) {
        console.error(err);
      } finally {
        setLoadingResolutions(false);
      }
    };

    loadResolutions();
  }, [isOpen]);

  useEffect(() => {
    if (type === 'edit' && data) {
      setDraft({
        name: data.layout || '',
        folderId: data.folderId ?? null,
        tags: data.tags?.map((t) => ({ ...t })) ?? [],
        description: data.description ?? '',
        retired: !!data.retired,
        resolutionId: data.resolutionId,
      });
    } else {
      setDraft(DEFAULT_DRAFT);
    }
  }, [data, type]);

  const resolutionOptions = resolutions.map((r) => ({
    label: r.resolution,
    value: String(r.resolutionId),
  }));

  const handleSave = () => {
    startTransition(async () => {
      const schema = getTemplateSchema(t);
      const result = schema.safeParse(draft);

      if (!result.success) {
        setApiError(undefined);
        const fieldErrors = result.error.flatten().fieldErrors;
        const mappedErrors: TemplateFormErrors = {};

        Object.entries(fieldErrors).forEach(([key, value]) => {
          if (value?.[0]) {
            mappedErrors[key as keyof TemplateFormErrors] = value[0];
          }
        });

        setFormErrors(mappedErrors);
        return;
      }

      setFormErrors({});
      try {
        const commonPayload = {
          name: draft.name,
          description: draft.description,
          tags: draft.tags.map((t) => (t.value ? `${t.tag}|${t.value}` : t.tag)).join(','),
          retired: draft.retired ? 1 : 0,
          folderId: draft.folderId,
        };

        if (type === 'edit') {
          if (!data) {
            console.error('Template data is missing.');
            return;
          }

          const updatedTemplate = await updateTemplate(data.layoutId, {
            ...commonPayload,
            retired: draft.retired ? 1 : 0,
          });

          onSave({ ...data, ...updatedTemplate });
        } else {
          const payload = {
            ...commonPayload,
            resolutionId: draft.resolutionId,
          };

          const newTemplate = await createTemplate(payload);
          onSave(newTemplate);
        }
        onClose();
      } catch (err: unknown) {
        console.error('Failed to save playlist:', err);

        const apiError = err as { response?: { data?: { message?: string } } };

        if (apiError.response?.data?.message) {
          setApiError(apiError.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred while saving the playlist.'));
        }
      }
    });
  };

  const modalTitle = type === 'add' ? t('Add Template') : t('Edit Template');

  return (
    <Modal
      title={modalTitle}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable={false}
      error={apiError}
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
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible gap-3 px-4">
        <div className="flex flex-col gap-3 flex-1 min-h-0 p-4 overflow-y-auto">
          {/* Select Folder */}
          <div className="relative z-20">
            <SelectFolder
              selectedId={draft.folderId}
              onSelect={(folder) => {
                setDraft((prev) => ({
                  ...prev,
                  folderId: folder ? Number(folder.id) : null,
                }));
              }}
            />
          </div>

          <TextInput
            name="name"
            label={t('Name')}
            value={draft.name}
            onChange={(val) => setDraft((prev) => ({ ...prev, name: val }))}
            error={formErrors.name}
          />

          <TextInput
            name="description"
            label={t('Description')}
            value={draft.description}
            placeholder={t('Add description')}
            multiline
            rows={3}
            onChange={(val) => setDraft((prev) => ({ ...prev, description: val }))}
            error={formErrors.description}
          />

          <TagInput
            value={draft.tags}
            helpText={t('Tags separated by commas')}
            onChange={(tags) => setDraft((prev) => ({ ...prev, tags }))}
          />

          {type === 'add' ? (
            <SelectDropdown
              label="Resolution"
              value={draft.resolutionId ? String(draft.resolutionId) : undefined}
              placeholder={loadingResolutions ? 'Loading...' : 'Select resolution'}
              options={resolutionOptions}
              onSelect={(value) => {
                setDraft((prev) => ({
                  ...prev,
                  resolutionId: Number(value),
                }));

                if (formErrors.resolutionId) {
                  setFormErrors((prev) => ({
                    ...prev,
                    resolutionId: undefined,
                  }));
                }
              }}
              error={formErrors.resolutionId}
            />
          ) : (
            <Checkbox
              id="retired"
              title={t('Retire this template?')}
              className="items-center px-3 py-2.5"
              label={t('Retired templates cannot be used for new assignments.')}
              checked={draft.retired}
              onChange={() => setDraft((prev) => ({ ...prev, retired: !prev.retired }))}
            />
          )}
        </div>
      </div>
    </Modal>
  );
}
