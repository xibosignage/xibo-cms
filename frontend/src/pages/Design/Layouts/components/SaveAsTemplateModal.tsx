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

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getTemplateSchema } from '@/schema/templates';
import { saveLayoutAsTemplate } from '@/services/layoutsApi';
import type { Layout } from '@/types/layout';
import type { Tag } from '@/types/tag';

interface SaveAsTemplateModalProps {
  isOpen?: boolean;
  layout: Layout | null;
  onClose: () => void;
}

export default function SaveAsTemplateModal({
  isOpen = true,
  layout,
  onClose,
}: SaveAsTemplateModalProps) {
  const { t } = useTranslation();

  const [name, setName] = useState('');
  const [description, setDescription] = useState(layout?.description ?? '');
  const [tags, setTags] = useState<Tag[]>(layout?.tags?.map((t) => ({ ...t })) ?? []);
  const [includeWidgets, setIncludeWidgets] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [formErrors, setFormErrors] = useState<{ name?: string }>({});
  const [apiError, setApiError] = useState<string | undefined>();
  const [folderId, setFolderId] = useState<number | undefined>(layout?.folderId);

  useEffect(() => {
    if (layout) {
      const baseName = layout.name || layout.layout;

      setName((prev) => {
        if (prev && prev !== '') return prev;

        return `${baseName} Template`;
      });
      setDescription(layout.description ?? '');
      setTags(layout.tags?.map((t) => ({ ...t })) ?? []);
      setFolderId(layout.folderId);
    }
  }, [layout]);

  const handleSave = async () => {
    if (!layout || isSaving) return;

    const schema = getTemplateSchema(t);

    const result = schema.safeParse({
      name,
      description,
      tags,
      includeWidgets,
    });

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors;

      setFormErrors({
        name: fieldErrors.name?.[0],
      });

      return;
    }

    setFormErrors({});
    setApiError(undefined);

    try {
      setIsSaving(true);

      const serializedTags = tags.map((t) => (t.value ? `${t.tag}|${t.value}` : t.tag)).join(',');

      await saveLayoutAsTemplate(layout.layoutId, {
        name,
        includeWidgets,
        description,
        tags: serializedTags,
        folderId,
      });

      onClose();
    } catch (err) {
      console.error(err);

      const apiErr = err as { response?: { data?: { message?: string } } };

      if (apiErr.response?.data?.message) {
        setApiError(apiErr.response.data.message);
      } else if (err instanceof Error) {
        setApiError(err.message);
      } else {
        setApiError(t('Failed to create template'));
      }
    } finally {
      setIsSaving(false);
    }
  };

  if (!layout) return null;

  return (
    <Modal
      title={t('Save as Template')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isSaving}
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
          disabled: isSaving || !name.trim(),
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible gap-3 p-4 pt-0">
        <div className="flex flex-col gap-3 flex-1 min-h-0 p-4 overflow-y-auto pb-20">
          <div className="relative z-20">
            <SelectFolder
              selectedId={folderId}
              onSelect={(folder) => {
                setFolderId(folder?.id ? Number(folder.id) : undefined);
              }}
            />
          </div>

          <TextInput
            name="name"
            label={t('Template Name')}
            value={name}
            onChange={(value) => {
              setName(value);
              setFormErrors((prev) => ({ ...prev, name: undefined }));
            }}
            error={formErrors.name}
          />

          <TextInput
            name="description"
            label={t('Description')}
            value={description}
            onChange={setDescription}
            multiline
            rows={3}
          />

          <TagInput
            value={tags}
            helpText={t('Tags separated by commas. Use Tag|Value for tagged attributes.')}
            onChange={(tags) => setTags(tags)}
          />

          <Checkbox
            id="includeWidgets"
            className="items-center px-3 py-2.5"
            title={t('Include Widgets')}
            label={t('Include widgets from this layout in the template')}
            checked={includeWidgets}
            onChange={(e) => setIncludeWidgets(e.target.checked)}
          />
        </div>
      </div>
    </Modal>
  );
}
