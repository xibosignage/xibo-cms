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

import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getSyncGroupSchema } from '@/schema/syncGroup';
import { createSyncGroup, fetchSyncGroupDisplays, updateSyncGroup } from '@/services/syncGroupApi';
import type { SyncGroup } from '@/types/syncGroup';

interface AddAndEditSyncGroupModalProps {
  isOpen?: boolean;
  mode: 'add' | 'edit';
  syncGroup: SyncGroup | null;
  onClose: () => void;
  onSave: (syncGroup: SyncGroup) => void;
  onAfterSave?: (syncGroup: SyncGroup) => void;
}

interface SyncGroupDraft {
  name: string;
  syncPublisherPort: number;
  syncSwitchDelay: number;
  syncVideoPauseDelay: number;
  leadDisplayId: number | null;
  folderId: number | null;
}

const DEFAULT_DRAFT: SyncGroupDraft = {
  name: '',
  syncPublisherPort: 9590,
  syncSwitchDelay: 750,
  syncVideoPauseDelay: 100,
  leadDisplayId: null,
  folderId: null,
};

type FormErrors = Partial<Record<keyof SyncGroupDraft, string>>;

export default function AddAndEditSyncGroupModal({
  isOpen = true,
  mode,
  syncGroup,
  onClose,
  onSave,
  onAfterSave,
}: AddAndEditSyncGroupModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [formErrors, setFormErrors] = useState<FormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();
  const [draft, setDraft] = useState<SyncGroupDraft>({ ...DEFAULT_DRAFT });
  const [memberDisplayOptions, setMemberDisplayOptions] = useState<SelectOption[]>([]);

  const isEdit = mode === 'edit';

  useEffect(() => {
    if (isOpen) {
      if (isEdit && syncGroup) {
        setDraft({
          name: syncGroup.name,
          syncPublisherPort: syncGroup.syncPublisherPort,
          syncSwitchDelay: syncGroup.syncSwitchDelay,
          syncVideoPauseDelay: syncGroup.syncVideoPauseDelay,
          leadDisplayId: syncGroup.leadDisplayId || null,
          folderId: syncGroup.folderId || null,
        });

        fetchSyncGroupDisplays(syncGroup.syncGroupId).then((displays) => {
          setMemberDisplayOptions(
            displays.map((d) => ({ value: String(d.displayId), label: d.display })),
          );
        });
      } else {
        setDraft({ ...DEFAULT_DRAFT });
        setMemberDisplayOptions([]);
      }
      setFormErrors({});
      setApiError(undefined);
    }
  }, [isOpen]);

  const handleSave = () => {
    startTransition(async () => {
      const schema = getSyncGroupSchema(t);
      const result = schema.safeParse(draft);

      if (!result.success) {
        setApiError(undefined);
        const fieldErrors = result.error.flatten().fieldErrors;
        const mappedErrors: FormErrors = {};
        Object.entries(fieldErrors).forEach(([key, value]) => {
          if (value?.[0]) mappedErrors[key as keyof FormErrors] = value[0];
        });
        setFormErrors(mappedErrors);
        return;
      }

      setFormErrors({});
      try {
        const payload = {
          ...draft,
          leadDisplayId: draft.leadDisplayId ?? undefined,
          folderId: draft.folderId ?? undefined,
        };
        let saved: SyncGroup;
        if (isEdit && syncGroup) {
          saved = await updateSyncGroup(syncGroup.syncGroupId, payload);
        } else {
          saved = await createSyncGroup(payload);
        }
        onSave(saved);
        if (onAfterSave) {
          onAfterSave(saved);
        } else {
          onClose();
        }
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

  const modalActions = [
    {
      label: t('Cancel'),
      onClick: onClose,
      variant: 'secondary' as const,
      disabled: isPending,
    },
    {
      label: isPending ? t('Saving\u2026') : t('Save'),
      onClick: handleSave,
      disabled: isPending,
    },
  ];

  return (
    <Modal
      title={isEdit ? t('Edit Sync Group') : t('Add Sync Group')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable={false}
      size="lg"
      error={apiError}
      actions={modalActions}
    >
      <div className="flex flex-col gap-4 px-8 py-4">
        <div className="relative z-20">
          <SelectFolder
            selectedId={draft.folderId}
            onSelect={(folder) => setDraft((prev) => ({ ...prev, folderId: folder?.id ?? null }))}
          />
        </div>
        <TextInput
          name="name"
          label={t('Name')}
          helpText={t('A name for this Sync Group')}
          placeholder={t('Enter name')}
          value={draft.name}
          onChange={(name) => setDraft((prev) => ({ ...prev, name }))}
          error={formErrors.name}
        />

        <NumberInput
          name="syncPublisherPort"
          label={t('Publisher Port')}
          helpText={t('The port on which players will communicate')}
          value={draft.syncPublisherPort}
          onChange={(val) => setDraft((prev) => ({ ...prev, syncPublisherPort: val }))}
          error={formErrors.syncPublisherPort}
          min={1}
        />

        <NumberInput
          name="syncSwitchDelay"
          label={t('Switch Delay')}
          helpText={t('The delay in ms when displaying the changes in content')}
          value={draft.syncSwitchDelay}
          onChange={(val) => setDraft((prev) => ({ ...prev, syncSwitchDelay: val }))}
          error={formErrors.syncSwitchDelay}
          min={0}
        />

        <NumberInput
          name="syncVideoPauseDelay"
          label={t('Video Pause Delay')}
          helpText={t('The delay in ms before unpausing the video on start')}
          value={draft.syncVideoPauseDelay}
          onChange={(val) => setDraft((prev) => ({ ...prev, syncVideoPauseDelay: val }))}
          error={formErrors.syncVideoPauseDelay}
          min={0}
        />

        {isEdit && (
          <SelectDropdown
            label={t('Lead Display')}
            helpText={t('Select Lead Display for this sync group')}
            value={draft.leadDisplayId ? String(draft.leadDisplayId) : ''}
            options={memberDisplayOptions}
            onSelect={(val) =>
              setDraft((prev) => ({ ...prev, leadDisplayId: val ? Number(val) : null }))
            }
            clearable
            searchable
          />
        )}
      </div>
    </Modal>
  );
}
