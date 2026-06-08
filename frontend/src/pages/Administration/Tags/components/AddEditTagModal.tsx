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

import { isAxiosError } from 'axios';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getTagSchema } from '@/schema/tag';
import { createTag, updateTag } from '@/services/tagApi';
import type { Tag } from '@/types/tag';

interface AddEditTagModalProps {
  isOpen?: boolean;
  mode: 'add' | 'edit';
  tag: Tag | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface TagDraft {
  name: string;
  options: string;
  isRequired: boolean;
}

const DEFAULT_DRAFT: TagDraft = {
  name: '',
  options: '',
  isRequired: false,
};

function parseOptions(raw: string | null): string {
  if (!raw) return '';
  try {
    const parsed = JSON.parse(raw) as string[];
    return Array.isArray(parsed) ? parsed.join(',') : raw;
  } catch {
    return raw;
  }
}

export default function AddEditTagModal({
  isOpen = true,
  mode,
  tag,
  onClose,
  onSuccess,
}: AddEditTagModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [fieldErrors, setFieldErrors] = useState<Record<string, string | undefined>>({});
  const [draft, setDraft] = useState<TagDraft>({ ...DEFAULT_DRAFT });

  const isEdit = mode === 'edit';

  useEffect(() => {
    if (!isOpen) return;

    setApiError(undefined);
    setFieldErrors({});

    if (isEdit && tag) {
      setDraft({
        name: tag.tag,
        options: parseOptions(tag.options ?? null),
        isRequired: !!tag.isRequired,
      });
    } else {
      setDraft({ ...DEFAULT_DRAFT });
    }
  }, [isOpen, isEdit, tag?.tagId]);

  const handleSave = () => {
    setFieldErrors({});
    setApiError(undefined);

    startTransition(async () => {
      const schema = getTagSchema(t);
      const result = schema.safeParse({
        name: draft.name,
        options: draft.options,
        isRequired: draft.isRequired,
      });

      if (!result.success) {
        const errors = result.error.flatten().fieldErrors;
        setFieldErrors({
          name: errors.name?.[0],
          options: errors.options?.[0],
        });
        return;
      }

      const payload = {
        name: draft.name,
        options: draft.options,
        isRequired: draft.isRequired ? 1 : 0,
      };

      try {
        if (isEdit && tag) {
          await updateTag(tag.tagId, payload);
        } else {
          await createTag(payload);
        }
        onSuccess();
        onClose();
      } catch (err: unknown) {
        if (isAxiosError(err) && err.response?.data?.message) {
          setApiError(err.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred.'));
        }
      }
    });
  };

  if (!isOpen) return null;

  return (
    <Modal
      title={isEdit ? t('Edit Tag') : t('Add Tag')}
      isOpen={isOpen}
      onClose={onClose}
      isPending={isPending}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        { label: isPending ? t('Saving…') : t('Save'), onClick: handleSave, disabled: isPending },
      ]}
    >
      <div className="flex flex-col gap-3 p-6">
        <TextInput
          name="name"
          label={t('Name')}
          helpText={t('The Name for this Tag')}
          placeholder={t('Enter tag name')}
          value={draft.name}
          onChange={(name) => setDraft((prev) => ({ ...prev, name }))}
          error={fieldErrors.name}
        />

        <TextInput
          name="options"
          label={t('Values')}
          helpText={t(
            'Comma separated string of additional values that should be associated with this Tag. Values entered here will be available for selection when assigning this Tag.',
          )}
          placeholder={t('e.g. value1,value2,value3')}
          value={draft.options}
          onChange={(options) => setDraft((prev) => ({ ...prev, options }))}
          error={fieldErrors.options}
        />

        <Checkbox
          id="isRequired"
          label={t('Required Value?')}
          title={t('Tick to ensure that a value is selected when assigning this Tag')}
          checked={draft.isRequired}
          onChange={(e) => setDraft((prev) => ({ ...prev, isRequired: e.target.checked }))}
        />
      </div>
    </Modal>
  );
}
