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

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { MoveCmsData } from '@/services/displaysApi';
import type { Display } from '@/types/display';

interface TransferCmsModalProps {
  display?: Display;
  onClose: () => void;
  onConfirm: (data: MoveCmsData) => void;
  isActionPending: boolean;
  actionError: string | null;
}

export default function TransferCmsModal({
  display,
  onClose,
  onConfirm,
  isActionPending,
  actionError,
}: TransferCmsModalProps) {
  const { t } = useTranslation();

  const [newCmsAddress, setNewCmsAddress] = useState(display?.newCmsAddress ?? '');
  const [newCmsKey, setNewCmsKey] = useState(display?.newCmsKey ?? '');
  const [twoFactorCode, setTwoFactorCode] = useState('');

  const canSubmit = newCmsAddress.trim() !== '' && twoFactorCode.trim() !== '';

  return (
    <Modal
      title={t('Transfer to another CMS')}
      isOpen
      isPending={isActionPending}
      onClose={onClose}
      error={actionError ?? undefined}
      size="md"
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isActionPending },
        {
          label: isActionPending ? t('Saving…') : t('Save'),
          onClick: () => {
            if (canSubmit) {
              onConfirm({ newCmsAddress, newCmsKey, twoFactorCode });
            }
          },
          disabled: isActionPending || !canSubmit,
        },
      ]}
    >
      <div className="flex flex-col p-5 gap-4">
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
          {t(
            'This action requires Google Authenticator Two Factor authentication to be active on your account.',
          )}
        </div>
        <div className="rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700">
          {t(
            'Please note: Once the CMS Address and Key are authenticated in this form the Display will attempt to register with the CMS Instance details entered. Once transferred the Display will stop communicating with this CMS Instance.',
          )}
        </div>
        <TextInput
          name="newCmsAddress"
          label={t('New CMS Address')}
          helpText={t('Full URL to the new CMS, including https://')}
          value={newCmsAddress}
          onChange={setNewCmsAddress}
          placeholder="https://"
        />
        <TextInput
          name="newCmsKey"
          label={t('New CMS Key')}
          helpText={t('CMS Secret Key associated with the provided new CMS Address')}
          value={newCmsKey}
          onChange={setNewCmsKey}
        />
        <TextInput
          name="twoFactorCode"
          label={t('Two Factor Code')}
          helpText={t('Please enter your Two Factor authentication code')}
          value={twoFactorCode}
          onChange={setTwoFactorCode}
        />
      </div>
    </Modal>
  );
}
