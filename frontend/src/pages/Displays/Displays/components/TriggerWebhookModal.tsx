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

import Modal from '@/components/ui/modals/Modal';
import type { DisplayCommandTarget } from '@/types/display';

interface TriggerWebhookModalProps {
  items: DisplayCommandTarget[];
  onClose: () => void;
  onConfirm: (items: DisplayCommandTarget[], triggerCode: string) => void;
  isActionPending: boolean;
  actionError: string | null;
}

export default function TriggerWebhookModal({
  items,
  onClose,
  onConfirm,
  isActionPending,
  actionError,
}: TriggerWebhookModalProps) {
  const { t } = useTranslation();
  const [triggerCode, setTriggerCode] = useState('');

  const isBulk = items.length > 1;

  const handleClose = () => {
    setTriggerCode('');
    onClose();
  };

  return (
    <Modal
      isOpen
      isPending={isActionPending}
      onClose={handleClose}
      error={actionError ?? undefined}
      size="md"
      actions={[
        {
          label: t('Cancel'),
          onClick: handleClose,
          variant: 'secondary',
          disabled: isActionPending,
        },
        {
          label: isActionPending ? t('Triggering…') : t('Trigger'),
          onClick: () => onConfirm(items, triggerCode),
          disabled: isActionPending || !triggerCode.trim(),
        },
      ]}
    >
      <div className="flex flex-col p-5 gap-3">
        <h2 className="text-lg font-semibold mb-1">{t('Trigger a web hook')}</h2>
        {isBulk && (
          <p className="text-sm text-gray-500">
            {t('Trigger a web hook for {{count}} display(s).', { count: items.length })}
          </p>
        )}
        <div>
          <label htmlFor="triggerCode" className="block text-sm font-medium text-gray-700 mb-1">
            {t('Trigger Code')}
          </label>
          <input
            id="triggerCode"
            type="text"
            value={triggerCode}
            onChange={(e) => setTriggerCode(e.target.value)}
            placeholder={t('Enter trigger code')}
            className="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p className="text-xs text-gray-500 mt-1">
            {t(
              'Enter the code associated with the web hook you wish to trigger. The webhook trigger code must be added to Interactive Actions in scheduled content for this Player.',
            )}
          </p>
        </div>
      </div>
    </Modal>
  );
}
