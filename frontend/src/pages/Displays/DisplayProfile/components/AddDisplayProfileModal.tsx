/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { isAxiosError } from 'axios';
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
        setApiError(t('Please fix the highlighted errors before saving.'));
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

  return (
    <Modal
      title={t('Add Display Profile')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
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
          helpText={t('The Name for this Display Profile')}
          placeholder={t('Enter name')}
          value={draft.name}
          onChange={(name) => setDraft((prev) => ({ ...prev, name }))}
          error={formErrors.name}
        />

        <SelectDropdown
          label={t('Display Type')}
          helpText={t('What type of display is this profile intended for?')}
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
