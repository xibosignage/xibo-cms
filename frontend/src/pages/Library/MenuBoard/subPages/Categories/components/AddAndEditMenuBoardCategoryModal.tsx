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

import MediaInput from '@/components/ui/forms/MediaInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getMenuBoardCategorySchema } from '@/schema/menuBoard';
import { createMenuBoardCategory, updateMenuBoardCategory } from '@/services/menuBoardApi';
import type { MenuBoardCategory } from '@/types/menuBoardCategory';

interface AddAndEditMenuBoardCategoryModalProps {
  type: 'add' | 'edit';
  isOpen?: boolean;
  menuId: string | number;
  data?: MenuBoardCategory | null;
  onClose: () => void;
  onSave: () => void;
}

interface CategoryDraft {
  name: string;
  description: string;
  code: string;
  mediaId: number | null;
}

type CategoryFormErrors = {
  name?: string;
  description?: string;
  code?: string;
};

const DEFAULT_DRAFT: CategoryDraft = {
  name: '',
  description: '',
  code: '',
  mediaId: null,
};

const createDraftFromData = (data?: MenuBoardCategory | null): CategoryDraft => {
  if (!data) {
    return { ...DEFAULT_DRAFT };
  }
  return {
    name: data.name ?? '',
    description: data.description ?? '',
    code: data.code ?? '',
    mediaId: data.mediaId ?? null,
  };
};

export default function AddAndEditMenuBoardCategoryModal({
  type,
  isOpen = true,
  menuId,
  onClose,
  data,
  onSave,
}: AddAndEditMenuBoardCategoryModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [formErrors, setFormErrors] = useState<CategoryFormErrors>({});

  const [draft, setDraft] = useState<CategoryDraft>(() => createDraftFromData(data));

  useEffect(() => {
    if (isOpen) {
      setDraft(createDraftFromData(data));
      setApiError(undefined);
      setFormErrors({});
    }
  }, [data, isOpen]);

  const updateDraft = <K extends keyof CategoryDraft>(field: K, value: CategoryDraft[K]) => {
    setDraft((prev) => ({ ...prev, [field]: value }));
  };

  const clearError = (field: keyof CategoryFormErrors) => {
    setFormErrors((prev) => ({ ...prev, [field]: undefined }));
  };

  const handleSave = () => {
    const schema = getMenuBoardCategorySchema(t);
    const result = schema.safeParse(draft);

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
    setApiError(undefined);

    startTransition(async () => {
      try {
        if (type === 'edit') {
          if (!data) {
            return;
          }
          await updateMenuBoardCategory(data.menuCategoryId, draft);
        } else {
          await createMenuBoardCategory(menuId, draft);
        }
        onSave();
        onClose();
      } catch (err: unknown) {
        const axiosError = err as { response?: { data?: { message?: string } } };
        setApiError(axiosError.response?.data?.message ?? t('An unexpected error occurred.'));
      }
    });
  };

  return (
    <Modal
      title={type === 'add' ? t('Add Category') : t('Edit Category')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        { label: isPending ? t('Saving…') : t('Save'), onClick: handleSave, disabled: isPending },
      ]}
    >
      <div className="px-8 pb-8 space-y-4 pt-4">
        <TextInput
          name="name"
          label={t('Name')}
          placeholder={t('Enter Name')}
          helpText={t('The Name for this Menu Board Category.')}
          value={draft.name}
          error={formErrors.name}
          onChange={(val) => {
            updateDraft('name', val);
            clearError('name');
          }}
        />

        <TextInput
          name="code"
          label={t('Code')}
          placeholder={t('Enter Code')}
          helpText={t('The Code identifier for this Menu Board Category.')}
          value={draft.code}
          error={formErrors.code}
          optional
          onChange={(val) => {
            updateDraft('code', val);
            clearError('code');
          }}
        />

        <TextInput
          name="description"
          label={t('Description')}
          placeholder={t('Enter a short description')}
          helpText={t('The description for this Menu Board Category.')}
          value={draft.description}
          error={formErrors.description}
          optional
          multiline
          onChange={(val) => {
            updateDraft('description', val);
            clearError('description');
          }}
        />

        <MediaInput
          label={t('Media')}
          helpText={t('Optionally select an Image or Video to associate with this Category.')}
          value={draft.mediaId ?? undefined}
          optional
          mediaType=""
          onChange={(val) => {
            updateDraft('mediaId', val ? Number(val) : null);
          }}
        />
      </div>
    </Modal>
  );
}
