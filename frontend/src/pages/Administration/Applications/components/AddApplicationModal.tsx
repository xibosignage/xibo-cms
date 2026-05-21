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

import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getApplicationSchema } from '@/schema/application';
import { createApplication } from '@/services/applicationApi';
import type { Application } from '@/types/application';

interface AddApplicationModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onSuccess: (newApplication: Application) => void;
}

export default function AddApplicationModal({
  isOpen = true,
  onClose,
  onSuccess,
}: AddApplicationModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [name, setName] = useState('');
  const [nameError, setNameError] = useState<string | undefined>();
  const [apiError, setApiError] = useState<string | undefined>();

  useEffect(() => {
    if (!isOpen) {
      setName('');
      setNameError(undefined);
      setApiError(undefined);
    }
  }, [isOpen]);

  const handleSave = () => {
    setNameError(undefined);
    setApiError(undefined);

    startTransition(async () => {
      const schema = getApplicationSchema(t);
      const result = schema.safeParse({ name });

      if (!result.success) {
        setNameError(result.error.flatten().fieldErrors.name?.[0]);
        return;
      }

      try {
        const newApplication = await createApplication(name);
        onClose();
        onSuccess(newApplication);
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

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={t('Add Application')}
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
          placeholder={t('Enter application name')}
          value={name}
          onChange={setName}
          error={nameError}
        />
      </div>
    </Modal>
  );
}
