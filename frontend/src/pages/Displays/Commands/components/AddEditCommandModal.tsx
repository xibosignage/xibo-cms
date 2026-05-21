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

import CommandBuilder from './CommandBuilder/CommandBuilder';

import MultiSelectDropdown from '@/components/ui/forms/MultiSelectDropdown';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getCommandSchema } from '@/schema/command';
import { createCommand, updateCommand } from '@/services/commandApi';
import type { Command } from '@/types/command';

const getAvailableOnOptions = (t: ReturnType<typeof useTranslation>['t']) => [
  { value: 'android', label: t('Android') },
  { value: 'chromeOS', label: t('ChromeOS') },
  { value: 'linux', label: t('Linux') },
  { value: 'sssp', label: t('Tizen') },
  { value: 'lg', label: t('webOS') },
  { value: 'windows', label: t('Windows') },
];

const getCreateAlertOptions = (t: ReturnType<typeof useTranslation>['t']) => [
  { value: 'never', label: t('Never') },
  { value: 'success', label: t('Success') },
  { value: 'failure', label: t('Failure') },
  { value: 'always', label: t('Always') },
];

interface AddEditCommandModalProps {
  isOpen?: boolean;
  mode: 'add' | 'edit';
  command: Command | null;
  onClose: () => void;
  onSave: (command: Command) => void;
}

interface CommandDraft {
  command: string;
  code: string;
  description: string;
  commandString: string;
  validationString: string;
  availableOn: string[];
  createAlertOn: string;
}

const DEFAULT_DRAFT: CommandDraft = {
  command: '',
  code: '',
  description: '',
  commandString: '',
  validationString: '',
  availableOn: [],
  createAlertOn: 'never',
};

type FormErrors = Partial<Record<keyof CommandDraft, string>>;

export default function AddEditCommandModal({
  isOpen = true,
  mode,
  command,
  onClose,
  onSave,
}: AddEditCommandModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [formErrors, setFormErrors] = useState<FormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();
  const [draft, setDraft] = useState<CommandDraft>({ ...DEFAULT_DRAFT });

  const isEdit = mode === 'edit';

  useEffect(() => {
    if (isOpen) {
      if (isEdit && command) {
        setDraft({
          command: command.command,
          code: command.code,
          description: command.description ?? '',
          commandString: command.commandString ?? '',
          validationString: command.validationString ?? '',
          availableOn: command.availableOn?.split(',').filter(Boolean) ?? [],
          createAlertOn: command.createAlertOn || 'never',
        });
      } else {
        setDraft({ ...DEFAULT_DRAFT });
      }
      setFormErrors({});
      setApiError(undefined);
    }
  }, [isOpen]);

  const handleSave = () => {
    startTransition(async () => {
      const schema = getCommandSchema(t, mode);
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
        let saved: Command;
        if (isEdit && command) {
          saved = await updateCommand(command.commandId, {
            command: draft.command,
            description: draft.description,
            commandString: draft.commandString || undefined,
            validationString: draft.validationString || undefined,
            availableOn: draft.availableOn.length > 0 ? draft.availableOn : undefined,
            createAlertOn: draft.createAlertOn,
          });
        } else {
          saved = await createCommand({
            command: draft.command,
            code: draft.code,
            description: draft.description,
            commandString: draft.commandString || undefined,
            validationString: draft.validationString || undefined,
            availableOn: draft.availableOn.length > 0 ? draft.availableOn : undefined,
            createAlertOn: draft.createAlertOn,
          });
        }
        onSave(saved);
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

  const modalActions = [
    {
      label: t('Cancel'),
      onClick: onClose,
      variant: 'secondary' as const,
      disabled: isPending,
    },
    {
      label: isPending ? t('Saving…') : t('Save'),
      onClick: handleSave,
      disabled: isPending,
    },
  ];

  return (
    <Modal
      title={isEdit ? t('Edit Command') : t('Add Command')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable
      size="md"
      error={apiError}
      actions={modalActions}
    >
      <div className="flex flex-col gap-4 px-8 py-4">
        <TextInput
          name="command"
          label={t('Name')}
          helpText={t('The Name for this Command')}
          placeholder={t('Enter name')}
          value={draft.command}
          onChange={(val) => setDraft((prev) => ({ ...prev, command: val }))}
          error={formErrors.command}
        />

        <TextInput
          name="code"
          label={t('Code')}
          helpText={t(
            'A reference code for this command which is used to identify the command internally.',
          )}
          placeholder={t('Enter code')}
          value={draft.code}
          onChange={(val) => setDraft((prev) => ({ ...prev, code: val }))}
          error={formErrors.code}
          disabled={isEdit}
        />

        <TextInput
          name="description"
          label={t('Description')}
          helpText={t(
            'This should be a textual description of what the command is trying to achieve.',
          )}
          placeholder={t('Enter description')}
          value={draft.description}
          onChange={(val) => setDraft((prev) => ({ ...prev, description: val }))}
          error={formErrors.description}
          multiline
          rows={5}
          optional
        />

        <CommandBuilder
          value={draft.commandString}
          onChange={(val) => setDraft((prev) => ({ ...prev, commandString: val }))}
          error={formErrors.commandString}
        />

        <TextInput
          name="validationString"
          label={t('Validation')}
          helpText={t(
            'The Validation String for this Command. An override for this can be provided in Display Settings.',
          )}
          placeholder={t('Enter validation string')}
          value={draft.validationString}
          onChange={(val) => setDraft((prev) => ({ ...prev, validationString: val }))}
          error={formErrors.validationString}
        />

        <MultiSelectDropdown
          label={t('Available On')}
          helpText={t('Leave empty if this command should be available on all types of Display.')}
          value={draft.availableOn}
          options={getAvailableOnOptions(t)}
          onChange={(values) => setDraft((prev) => ({ ...prev, availableOn: values }))}
          placeholder={t('All display types')}
          optional
        />

        <SelectDropdown
          label={t('Create Alert On')}
          helpText={t('On command execution, when should a Display alert be created?')}
          value={draft.createAlertOn}
          options={getCreateAlertOptions(t)}
          onSelect={(val) => setDraft((prev) => ({ ...prev, createAlertOn: val || 'never' }))}
        />
      </div>
    </Modal>
  );
}
