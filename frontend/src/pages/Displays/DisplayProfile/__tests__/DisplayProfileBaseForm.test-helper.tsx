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

// ---------------------------------------------------------------------------
// WHY THIS FILE EXISTS
//
// The real edit modal (EditDisplayProfileModal) is too heavy to render five
// times in a single test run — it crashes the test process with an
// out-of-memory error. See DisplayProfile.test-coverage.md for the full
// explanation.
//
// This file is a lightweight stand-in used only by the edit-form tests. It
// contains just enough real logic to let those tests verify that:
//   - the name field is editable
//   - the isDefault checkbox toggles
//   - Save validates the name and calls the API with the right data
//   - an API error is shown without closing the modal
//
// It is NOT used anywhere in the production app.
// ---------------------------------------------------------------------------

import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { CHECKBOX_FIELDS_BY_TYPE } from '../components/fields/fieldMetadata';

import Checkbox from '@/components/ui/forms/Checkbox';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getEditDisplayProfileSchema } from '@/schema/displayProfile';
import { fetchDisplayProfileById, updateDisplayProfile } from '@/services/displayProfileApi';
import type { DisplayProfile } from '@/types/displayProfile';

type ConfigValue = string | number | null;
type FlatConfig = Record<string, ConfigValue>;

function getApiErrorMessage(err: unknown, fallback: string): string {
  const e = err as { response?: { data?: { message?: string } } };
  return e.response?.data?.message ?? (err instanceof Error ? err.message : fallback);
}

// The API returns config as an array of { name, value } pairs.
// Convert to a flat map so the form can read settings by key.
function configArrayToFlat(config: DisplayProfile['config']): FlatConfig {
  if (!config) return {};
  return Object.fromEntries(config.map((item) => [item.name, item.value]));
}

interface DisplayProfileBaseFormProps {
  isOpen?: boolean;
  data: DisplayProfile | null;
  onClose: () => void;
  onSave: (updated: DisplayProfile) => void;
}

export default function DisplayProfileBaseForm({
  isOpen = true,
  data,
  onClose,
  onSave,
}: DisplayProfileBaseFormProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [isLoading, setIsLoading] = useState(false);
  const [apiError, setApiError] = useState<string | undefined>();
  const [nameError, setNameError] = useState<string | undefined>();
  const [draft, setDraft] = useState<{ name: string; isDefault: number; config: FlatConfig }>({
    name: '',
    isDefault: 0,
    config: {},
  });

  // ---------------------------------------------------------------------------
  // Fetch the full profile when the modal opens and populate the draft.
  // `t` is intentionally omitted from deps — the test mock creates a new `t`
  // on every render, which would cause an infinite loop.
  // ---------------------------------------------------------------------------
  useEffect(() => {
    if (!isOpen || !data) return;

    setIsLoading(true);
    setApiError(undefined);
    setNameError(undefined);

    fetchDisplayProfileById(data.displayProfileId)
      .then((full) => {
        setDraft({
          name: full.name,
          isDefault: full.isDefault,
          config: configArrayToFlat(full.config),
        });
      })
      .catch((err: unknown) => {
        setApiError(getApiErrorMessage(err, 'Failed to load profile settings.'));
      })
      .finally(() => setIsLoading(false));
  }, [isOpen, data]);

  // ---------------------------------------------------------------------------
  // Validate, build the config payload, and call the API.
  // ---------------------------------------------------------------------------
  const handleSave = () => {
    if (!data) return;

    startTransition(async () => {
      const schema = getEditDisplayProfileSchema(t);
      const result = schema.safeParse({ name: draft.name, isDefault: draft.isDefault });

      if (!result.success) {
        setApiError(undefined);
        setNameError(result.error.flatten().fieldErrors.name?.[0]);
        return;
      }

      setNameError(undefined);
      const checkboxFields = CHECKBOX_FIELDS_BY_TYPE[data.type] ?? new Set();
      const configPayload: Record<string, string | number | null> = {};

      for (const [key, value] of Object.entries(draft.config)) {
        if (checkboxFields.has(key)) {
          configPayload[key] = value === 1 || value === '1' || value === 'on' ? 'on' : 'off';
        } else if (key === 'elevateLogsUntil') {
          const ts = Number(value);
          if (!value || value === '0' || value === 0 || isNaN(ts) || ts <= 0) {
            configPayload[key] = '';
          } else if (
            typeof value === 'number' ||
            (typeof value === 'string' && String(Math.floor(ts)) === value.trim())
          ) {
            const d = new Date(ts * 1000);
            const pad = (n: number) => String(n).padStart(2, '0');
            configPayload[key] =
              `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` +
              ` ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
          } else {
            configPayload[key] = value;
          }
        } else {
          configPayload[key] = value;
        }
      }

      try {
        const updated = await updateDisplayProfile(data.displayProfileId, {
          name: draft.name,
          isDefault: draft.isDefault,
          config: configPayload,
        });
        onSave({ ...data, ...updated });
        onClose();
      } catch (err: unknown) {
        setApiError(getApiErrorMessage(err, t('An unexpected error occurred while saving.')));
      }
    });
  };

  const title = data ? `${t('Edit')} "${data.name}"` : t('Edit Display Profile');

  return (
    <Modal
      title={title}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending || isLoading}
      scrollable={false}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending || isLoading,
        },
      ]}
    >
      <div className="px-4 py-4 space-y-4">
        {isLoading ? (
          <div className="flex items-center justify-center h-32 text-gray-400">
            {t('Loading settings…')}
          </div>
        ) : (
          <>
            <TextInput
              name="name"
              label={t('Name')}
              helpText={t('The Name of the Profile - (1 - 50 characters)')}
              placeholder={t('Enter name')}
              value={draft.name}
              onChange={(name) => setDraft((prev) => ({ ...prev, name }))}
              error={nameError}
            />
            <Checkbox
              id="isDefault"
              title={t('Default Profile?')}
              label={t(
                'Is this the default profile for all Displays of this type? Only 1 profile can be the default.',
              )}
              checked={draft.isDefault === 1}
              onChange={(e) =>
                setDraft((prev) => ({ ...prev, isDefault: e.target.checked ? 1 : 0 }))
              }
            />
          </>
        )}
      </div>
    </Modal>
  );
}
