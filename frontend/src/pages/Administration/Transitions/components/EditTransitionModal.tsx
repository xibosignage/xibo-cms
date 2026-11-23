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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import Modal from '@/components/ui/modals/Modal';
import type { Transition } from '@/types/transition';

interface EditTransitionModalProps {
  isOpen?: boolean;
  transition: Transition | null;
  onClose: () => void;
  onSave: (transitionId: number, availableAsIn: boolean, availableAsOut: boolean) => void;
  error?: string | null;
  isLoading?: boolean;
}

export default function EditTransitionModal({
  isOpen = true,
  transition,
  onClose,
  onSave,
  error,
  isLoading,
}: EditTransitionModalProps) {
  const { t } = useTranslation();

  const [availableAsIn, setAvailableAsIn] = useState(false);
  const [availableAsOut, setAvailableAsOut] = useState(false);

  useEffect(() => {
    if (isOpen && transition) {
      setAvailableAsIn(transition.availableAsIn === 1);
      setAvailableAsOut(transition.availableAsOut === 1);
    }
  }, [isOpen, transition]);

  if (!isOpen || !transition) return null;

  return (
    <Modal
      isOpen={isOpen}
      title={t('Edit Transition')}
      onClose={onClose}
      isPending={isLoading}
      error={error ?? undefined}
      size="md"
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: isLoading ? t('Saving…') : t('Save'),
          onClick: () => onSave(transition.transitionId, availableAsIn, availableAsOut),
          disabled: isLoading,
        },
      ]}
    >
      <div className="flex flex-col gap-6 p-8">
        <div className="flex flex-col gap-1">
          <p className="text-sm text-gray-500">{t('Transition')}</p>
          <p className="text-base font-semibold">{transition.transition}</p>
        </div>

        <div className="flex flex-col gap-1">
          <p className="text-sm text-gray-500">{t('Code')}</p>
          <p className="text-base font-mono">{transition.code}</p>
        </div>

        <div className="border-t border-gray-100 pt-4 flex flex-col gap-4">
          <Checkbox
            id="availableAsIn"
            title={t('Available for In Transitions?')}
            label={t('Can this transition be used for media start?')}
            checked={availableAsIn}
            onChange={(e) => setAvailableAsIn(e.target.checked)}
          />

          <Checkbox
            id="availableAsOut"
            title={t('Available for Out Transitions?')}
            label={t('Can this transition be used for media end?')}
            checked={availableAsOut}
            onChange={(e) => setAvailableAsOut(e.target.checked)}
          />
        </div>
      </div>
    </Modal>
  );
}
