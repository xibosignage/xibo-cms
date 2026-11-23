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

import Checkbox from '@/components/ui/forms/Checkbox';
import Modal from '@/components/ui/modals/Modal';
import { retireLayout } from '@/services/layoutsApi';
import type { Layout } from '@/types/layout';

interface RetireLayoutModalProps {
  layout: Layout | null;
  isOpen?: boolean;
  onClose: () => void;
  onSuccess?: (layout: Layout) => void;
}

export function RetireLayoutModal({
  layout,
  isOpen = true,
  onClose,
  onSuccess,
}: RetireLayoutModalProps) {
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string>();
  const [confirmed, setConfirmed] = useState(false);

  const handleConfirm = async () => {
    if (!layout || isLoading || !confirmed) return;

    try {
      setIsLoading(true);

      const updated = await retireLayout(layout.layoutId);

      onSuccess?.(updated);
      onClose();
    } catch (err) {
      console.error(err);
      setError(t('Failed to retire layout'));
    } finally {
      setIsLoading(false);
    }
  };

  if (!layout) return null;

  return (
    <Modal
      variant="confirmation"
      title={t('Retire Layout')}
      isOpen={isOpen}
      onClose={onClose}
      isPending={isLoading}
      error={error}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: t('Retire'),
          onClick: handleConfirm,
          disabled: !confirmed || isLoading,
        },
      ]}
    >
      <div className="p-4 flex flex-col gap-3">
        <Checkbox
          id="retired"
          className="items-center px-3 py-2.5"
          title={t('Retire Layout')}
          label={t(`It will no longer be visible in the lists.`)}
          checked={confirmed}
          onChange={(e) => setConfirmed(e.target.checked)}
        />
      </div>
    </Modal>
  );
}
