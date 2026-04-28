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

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getTypeOptions } from '@/pages/Displays/DisplayProfile/DisplayProfileConfig';
import { getAddDisplayProfileSchema } from '@/schema/displayProfile';
import { createDisplayProfile } from '@/services/displayProfileApi';
import type { DisplayProfile, DisplayProfileType } from '@/types/displayProfile';

interface AddDisplayProfileModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onSave: (created: DisplayProfile) => void;
}

interface AddDraft {
  name: string;
  type: DisplayProfileType | '';
  isDefault: number;
}

const DEFAULT_DRAFT: AddDraft = {
  name: '',
  type: '',
  isDefault: 0,
};

type AddFormErrors = Partial<Record<'name' | 'type', string>>;

export default function AddDisplayProfileModal({
  isOpen = true,
  onClose,
  onSave,
}: AddDisplayProfileModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [formErrors, setFormErrors] = useState<AddFormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();
  const [draft, setDraft] = useState<AddDraft>({ ...DEFAULT_DRAFT });

  useEffect(() => {
    if (isOpen) {
      setDraft({ ...DEFAULT_DRAFT });
      setFormErrors({});
      setApiError(undefined);
    }
  }, [isOpen]);

  const handleSave = () => {
    startTransition(async () => {
      const schema = getAddDisplayProfileSchema(t);
      const result = schema.safeParse(draft);

      if (!result.success) {
        setApiError(undefined);
        const fieldErrors = result.error.flatten().fieldErrors;
        const mappedErrors: AddFormErrors = {};
        Object.entries(fieldErrors).forEach(([key, value]) => {
          if (value?.[0]) mappedErrors[key as keyof AddFormErrors] = value[0];
        });
        setFormErrors(mappedErrors);
        return;
      }

      setFormErrors({});
      try {
        const created = await createDisplayProfile({
          name: draft.name,
          type: draft.type as DisplayProfileType,
          isDefault: draft.isDefault,
        });
        onSave(created);
        onClose();
      } catch (err: unknown) {
        const apiErr = err as { response?: { data?: { message?: string } } };
        if (apiErr.response?.data?.message) {
          setApiError(apiErr.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred.'));
        }
      }
    });
  };

  return (
    <Modal
      title={t('Add Display Profile')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable={false}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        { label: isPending ? t('Saving…') : t('Save'), onClick: handleSave, disabled: isPending },
      ]}
    >
      <div className="flex flex-col gap-4 px-8 py-4">
        <TextInput
          name="name"
          label={t('Name')}
          helpText={t('The Name of the Profile - (1 - 50 characters)')}
          placeholder={t('Enter name')}
          value={draft.name}
          onChange={(name) => setDraft((prev) => ({ ...prev, name }))}
          error={formErrors.name}
        />

        <SelectDropdown
          label={t('Display Type')}
          value={draft.type}
          options={getTypeOptions(t)}
          onSelect={(val) => setDraft((prev) => ({ ...prev, type: val as DisplayProfileType }))}
          error={formErrors.type}
        />

        <Checkbox
          id="isDefault"
          title={t('Default Profile?')}
          label={t(
            'Is this the default profile for all Displays of this type? Only 1 profile can be the default.',
          )}
          checked={draft.isDefault === 1}
          onChange={(e) => setDraft((prev) => ({ ...prev, isDefault: e.target.checked ? 1 : 0 }))}
        />
      </div>
    </Modal>
  );
}
