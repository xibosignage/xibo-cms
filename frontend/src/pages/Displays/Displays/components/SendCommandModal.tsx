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

import CommandDropdown from '@/components/ui/forms/CommandDropdown';
import Modal from '@/components/ui/modals/Modal';
import type { DisplayCommandTarget } from '@/types/display';

interface SendCommandModalProps {
  items: DisplayCommandTarget[];
  onClose: () => void;
  onConfirm: (items: DisplayCommandTarget[], commandId: number) => void;
  isActionPending: boolean;
  actionError: string | null;
}

export default function SendCommandModal({
  items,
  onClose,
  onConfirm,
  isActionPending,
  actionError,
}: SendCommandModalProps) {
  const { t } = useTranslation();
  const [selectedCommandId, setSelectedCommandId] = useState<number | null>(null);

  const isBulk = items.length > 1;
  const clientType = !isBulk ? (items[0]?.clientType ?? undefined) : undefined;

  return (
    <Modal
      title={t('Send Command')}
      isOpen
      isPending={isActionPending}
      onClose={onClose}
      error={actionError ?? undefined}
      size="md"
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isActionPending },
        {
          label: isActionPending ? t('Sending…') : t('Send'),
          onClick: () => {
            if (selectedCommandId !== null) {
              onConfirm(items, selectedCommandId);
            }
          },
          disabled: isActionPending || selectedCommandId === null,
        },
      ]}
    >
      <div className="flex flex-col p-5 gap-4">
        {isBulk ? (
          <p className="text-sm text-gray-500">
            {t('Send a command to {{count}} display(s).', { count: items.length })}
          </p>
        ) : (
          <p className="text-sm text-gray-500">
            {t('Send a command to {{display}}.', { display: items[0]?.display })}
          </p>
        )}
        <CommandDropdown
          value={selectedCommandId}
          onSelect={setSelectedCommandId}
          type={clientType}
          helpText={t(
            'Pick a command to send to the Player. If the CMS has XMR enabled this will be sent immediately, otherwise it will show an error.',
          )}
        />
      </div>
    </Modal>
  );
}
