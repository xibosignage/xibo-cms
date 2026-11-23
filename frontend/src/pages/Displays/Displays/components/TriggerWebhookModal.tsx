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
            className="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-xibo-blue-500"
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
