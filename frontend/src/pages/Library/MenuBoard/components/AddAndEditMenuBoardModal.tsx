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

import SelectFolder from '@/components/ui/forms/SelectFolder';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { usePermissions } from '@/hooks/usePermissions';
import { getMenuBoardSchema } from '@/schema/menuBoard';
import { createMenuBoard, updateMenuBoard } from '@/services/menuBoardApi';
import type { MenuBoard } from '@/types/menuBoard';

interface AddAndEditMenuBoardModalProps {
  type: 'add' | 'edit';
  isOpen?: boolean;
  data?: MenuBoard | null;
  onClose: () => void;
  onSave: () => void;
}

interface MenuBoardDraft {
  name: string;
  description: string;
  code: string;
  folderId: number | null;
}

type MenuBoardFormErrors = {
  name?: string;
  description?: string;
  code?: string;
};

const DEFAULT_DRAFT: MenuBoardDraft = {
  name: '',
  description: '',
  code: '',
  folderId: null,
};

const createDraftFromData = (data?: MenuBoard | null): MenuBoardDraft => {
  if (!data) {
    return { ...DEFAULT_DRAFT };
  }
  return {
    name: data.name ?? '',
    description: data.description ?? '',
    code: data.code ?? '',
    folderId: data.folderId ?? null,
  };
};

export default function AddAndEditMenuBoardModal({
  type,
  isOpen = true,
  onClose,
  data,
  onSave,
}: AddAndEditMenuBoardModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [formErrors, setFormErrors] = useState<MenuBoardFormErrors>({});
  const canViewFolders = usePermissions()?.canViewFolders;

  const [draft, setDraft] = useState<MenuBoardDraft>(() => createDraftFromData(data));

  useEffect(() => {
    if (isOpen) {
      setDraft(createDraftFromData(data));
      setApiError(undefined);
      setFormErrors({});
    }
  }, [data, isOpen]);

  const updateDraft = <K extends keyof MenuBoardDraft>(field: K, value: MenuBoardDraft[K]) => {
    setDraft((prev) => ({ ...prev, [field]: value }));
  };

  const clearError = (field: keyof MenuBoardFormErrors) => {
    setFormErrors((prev) => ({ ...prev, [field]: undefined }));
  };

  const handleSave = () => {
    const schema = getMenuBoardSchema(t);
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
          await updateMenuBoard(data.menuId, draft);
        } else {
          await createMenuBoard(draft);
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
      title={type === 'add' ? t('Add Menu Board') : t('Edit Menu Board')}
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
        {canViewFolders && (
          <div className="relative z-20">
            <SelectFolder
              selectedId={draft.folderId}
              onSelect={(f) => updateDraft('folderId', f?.id ?? null)}
            />
          </div>
        )}

        <TextInput
          name="name"
          label={t('Name')}
          placeholder={t('Enter Name')}
          helpText={t('The Name for this Menu Board.')}
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
          helpText={t('The Code identifier for this Menu Board.')}
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
          helpText={t('Up to 250 characters.')}
          value={draft.description}
          error={formErrors.description}
          optional
          multiline
          onChange={(val) => {
            updateDraft('description', val);
            clearError('description');
          }}
        />
      </div>
    </Modal>
  );
}
