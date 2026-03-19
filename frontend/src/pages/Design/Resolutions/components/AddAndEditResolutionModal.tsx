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
import NumberInput from '@/components/ui/forms/NumberInput';
import TextInput from '@/components/ui/forms/TextInput';
import { getResolutionSchema } from '@/schema/resolution';
import { updateResolution, createResolution } from '@/services/resolutionApi';
import type { Resolution } from '@/types/resolution';

interface AddAndEditResolutionModalProps {
  type: 'add' | 'edit';
  openModal: boolean;
  data?: Resolution | null;
  onClose: () => void;
  onSave: (updated: Resolution) => void;
}

interface ResolutionDraft {
  resolution: string;
  width: number;
  height: number;
  enabled: boolean;
}

const DEFAULT_DRAFT: ResolutionDraft = {
  resolution: '',
  width: 0,
  height: 0,
  enabled: true,
};

type ResolutionFormErrors = Partial<Record<keyof ResolutionDraft, string>>;

export default function AddAndEditResolutionModal({
  type,
  openModal,
  onClose,
  data,
  onSave,
}: AddAndEditResolutionModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [formErrors, setFormErrors] = useState<ResolutionFormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();

  const [draft, setDraft] = useState<ResolutionDraft>(() => {
    if (type === 'edit' && data) {
      return {
        ...DEFAULT_DRAFT,
        resolution: data.resolution,
        width: data.width,
        height: data.height,
        enabled: data.enabled,
      };
    }
    return { ...DEFAULT_DRAFT };
  });

  useEffect(() => {
    if (type === 'edit' && data) {
      setDraft({
        ...DEFAULT_DRAFT,
        resolution: data.resolution,
        width: data.width,
        height: data.height,
        enabled: data.enabled,
      });
    } else {
      setDraft({ ...DEFAULT_DRAFT });
    }

    setFormErrors({});
    setApiError(undefined);
  }, [data, type]);

  const handleSave = () => {
    startTransition(async () => {
      const schema = getResolutionSchema(t);
      const result = schema.safeParse(draft);
      if (!result.success) {
        setApiError(undefined);
        const fieldErrors = result.error.flatten().fieldErrors;
        const mappedErrors: Partial<Record<keyof ResolutionDraft, string>> = {};

        Object.entries(fieldErrors).forEach(([key, value]) => {
          if (value?.[0]) {
            mappedErrors[key as keyof ResolutionDraft] = value[0];
          }
        });

        setFormErrors(mappedErrors);
        return;
      }

      setFormErrors({});
      try {
        const payload = {
          resolution: draft.resolution,
          width: draft.width,
          height: draft.height,
          enabled: draft.enabled,
        };

        if (type === 'edit') {
          if (!data) {
            console.error('Resolution data is missing.');
            return;
          }

          const updatedResolution = await updateResolution(data.resolutionId, payload);

          onSave({
            ...data,
            ...updatedResolution,
          });
        } else {
          const newResolution = await createResolution(payload);
          onSave(newResolution);
        }

        onClose();
      } catch (err: unknown) {
        console.error('Failed to save resolution:', err);

        const apiError = err as { response?: { data?: { message?: string } } };

        if (apiError.response?.data?.message) {
          setApiError(apiError.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred while saving the resolution.'));
        }
      }
    });
  };

  const addModal = type === 'add';
  const modalTitle = addModal ? t('Add Resolution') : t('Edit Resolution');

  return (
    <Modal
      title={modalTitle}
      onClose={onClose}
      isOpen={openModal}
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
          {/* Name */}
          <TextInput
            name="name"
            label={t('Name')}
            placeholder={t('Enter Name')}
            value={draft.resolution}
            onChange={(resolution) => setDraft((prev) => ({ ...prev, resolution: resolution }))}
            error={formErrors.resolution}
          />

          <NumberInput
            name="width"
            label={t('Width')}
            helpText={t('The Width for this Resolution.')}
            value={draft.width}
            onChange={(num) => setDraft((prev) => ({ ...prev, width: num }))}
            error={formErrors.width}
          />

          <NumberInput
            name="height"
            label={t('Height')}
            helpText={t('The Height for this Resolution.')}
            value={draft.height}
            onChange={(num) => setDraft((prev) => ({ ...prev, height: num }))}
            error={formErrors.height}
          />

          {!addModal && (
            <Checkbox
              id="enabled"
              label={t('Is the Resolution enabled for use?')}
              title={t('Enable?')}
              checked={draft.enabled}
              onChange={(e) => setDraft((prev) => ({ ...prev, enabled: e.target.checked }))}
            />
          )}
        </div>
      </div>
    </Modal>
  );
}
