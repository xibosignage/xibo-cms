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

import { notify } from '@/components/ui/Notification';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { addDisplayViaCode } from '@/services/displaysApi';

interface AddDisplayModalProps {
  isOpen?: boolean;
  onClose: () => void;
}

export default function AddDisplayModal({ isOpen = true, onClose }: AddDisplayModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [userCode, setUserCode] = useState('');
  const [apiError, setApiError] = useState<string | undefined>();

  useEffect(() => {
    if (isOpen) {
      setUserCode('');
      setApiError(undefined);
    }
  }, [isOpen]);

  const handleSubmit = () => {
    startTransition(async () => {
      setApiError(undefined);
      try {
        await addDisplayViaCode(userCode);
        notify.success(t('CMS Credentials Added'));
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
      title={t('Add Display via Code')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable={false}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        {
          label: isPending ? t('Saving...') : t('Save'),
          onClick: handleSubmit,
          disabled: isPending || userCode.trim() === '',
        },
      ]}
    >
      <div className="px-6 py-4 flex flex-col gap-4">
        <div className="rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700 flex flex-col gap-2">
          <p>
            {t(
              'After submitting this form with valid code, your CMS Address and Key will be sent and stored in the temporary storage in our Authentication Service.',
            )}
          </p>
          <p>
            {t(
              'The Player linked to the submitted code, will make regular calls to our Authentication Service to retrieve the CMS details and configure itself with them. Your details are removed from the temporary storage once the Player is configured.',
            )}
          </p>
          <p>
            {t(
              'Please note that your CMS needs to make a successful call to our Authentication Service for this feature to work.',
            )}
          </p>
        </div>

        <TextInput
          name="user_code"
          label={t('Code')}
          placeholder=" "
          onChange={(value) => setUserCode(value)}
          helpText={t('Please provide the code displayed on the Player screen')}
          value={userCode}
        />
      </div>
    </Modal>
  );
}
