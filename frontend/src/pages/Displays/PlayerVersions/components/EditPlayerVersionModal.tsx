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

import NumberInput from '@/components/ui/forms/NumberInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getEditPlayerVersionSchema } from '@/schema/playerVersion';
import { updatePlayerVersion } from '@/services/playerVersionApi';
import type { PlayerVersion } from '@/types/playerVersion';

interface EditPlayerVersionModalProps {
  isOpen?: boolean;
  data: PlayerVersion | null;
  onClose: () => void;
  onSave: (updated: PlayerVersion) => void;
}

interface EditDraft {
  playerShowVersion: string;
  version: string;
  code: number;
}

export default function EditPlayerVersionModal({
  isOpen = true,
  data,
  onClose,
  onSave,
}: EditPlayerVersionModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [formErrors, setFormErrors] = useState<Partial<Record<keyof EditDraft, string>>>({});
  const [draft, setDraft] = useState<EditDraft>({
    playerShowVersion: '',
    version: '',
    code: 0,
  });

  useEffect(() => {
    if (isOpen && data) {
      setDraft({
        playerShowVersion: data.playerShowVersion,
        version: data.version,
        code: data.code,
      });
      setApiError(undefined);
      setFormErrors({});
    }
  }, [isOpen, data]);

  const handleSave = () => {
    if (!data) {
      return;
    }

    startTransition(async () => {
      const schema = getEditPlayerVersionSchema(t);
      const result = schema.safeParse(draft);

      if (!result.success) {
        setApiError(t('Please fix the highlighted errors before saving.'));
        const fieldErrors = result.error.flatten().fieldErrors;
        const mapped: Partial<Record<keyof EditDraft, string>> = {};
        for (const [key, value] of Object.entries(fieldErrors)) {
          if (value?.[0]) {
            mapped[key as keyof EditDraft] = value[0];
          }
        }
        setFormErrors(mapped);
        return;
      }

      setFormErrors({});

      try {
        const updated = await updatePlayerVersion(data.versionId, {
          version: draft.version,
          code: draft.code,
          playerShowVersion: draft.playerShowVersion,
        });
        onSave({ ...data, ...updated });
        onClose();
      } catch (err: unknown) {
        const message =
          (isAxiosError(err) && err.response?.data?.message) ||
          (err instanceof Error ? err.message : t('An unexpected error occurred while saving.'));
        setApiError(message);
      }
    });
  };

  const title = data ? `${t('Edit')} "${data.playerShowVersion}"` : t('Edit Player Version');

  return (
    <Modal
      title={title}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable={false}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      <div className="flex flex-col gap-4 px-8 py-4">
        <TextInput
          name="playerShowVersion"
          label={t('Player Version Name')}
          placeholder={t('Enter player version name')}
          helpText={t(
            'The Name of the player version application, this will be displayed in Version drop-downs in Display Profile and Display',
          )}
          value={draft.playerShowVersion}
          onChange={(val) => setDraft((prev) => ({ ...prev, playerShowVersion: val }))}
          error={formErrors.playerShowVersion}
        />
        <TextInput
          name="version"
          label={t('Version')}
          placeholder={t('Enter version')}
          helpText={t(
            'The Version number of this installer file, this should be correctly populated on upload, otherwise adjust it here',
          )}
          value={draft.version}
          onChange={(val) => setDraft((prev) => ({ ...prev, version: val }))}
          error={formErrors.version}
        />
        <NumberInput
          name="code"
          label={t('Code')}
          placeholder={t('Enter code')}
          helpText={t(
            'The Code number of this installer file, this should be correctly populated on upload, otherwise adjust it here',
          )}
          value={draft.code}
          onChange={(val) => setDraft((prev) => ({ ...prev, code: val ?? 0 }))}
          error={formErrors.code}
        />
      </div>
    </Modal>
  );
}
