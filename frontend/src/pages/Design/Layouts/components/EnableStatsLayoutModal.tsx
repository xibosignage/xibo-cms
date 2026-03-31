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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import Modal from '@/components/ui/modals/Modal';
import { updateLayout } from '@/services/layoutsApi';
import type { Layout } from '@/types/layout';

interface EnableStatsLayoutModalProps {
  layout: Layout | null;
  isOpen?: boolean;
  onClose: () => void;
  onSuccess?: (layout: Layout) => void;
}

export function EnableStatsLayoutModal({
  layout,
  isOpen = true,
  onClose,
  onSuccess,
}: EnableStatsLayoutModalProps) {
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string>();
  const [enabled, setEnabled] = useState(false);

  useEffect(() => {
    if (layout) {
      setEnabled(!!layout.enableStat);
    }
  }, [layout]);

  const handleConfirm = async () => {
    if (!layout || isLoading) return;

    try {
      setIsLoading(true);

      const updated = await updateLayout(layout.layoutId, {
        enableStat: enabled ? 1 : 0,
      });

      onSuccess?.(updated);
      onClose();
    } catch (err) {
      console.error(err);
      setError(t('Failed to update stats setting'));
    } finally {
      setIsLoading(false);
    }
  };

  if (!layout) return null;

  return (
    <Modal
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
          disabled: isLoading,
        },
      ]}
    >
      <div className="p-4 flex flex-col gap-3">
        <Checkbox
          id="update"
          className="items-center px-3 py-2.5"
          title={t('Enable Stats Collections')}
          label={t(
            `Collect Proof of Play statistics. Requires 'Enable Stats Collection' to be set to 'On' in Display Settings.`,
          )}
          checked={enabled}
          onChange={(e) => setEnabled(e.target.checked)}
        />
      </div>
    </Modal>
  );
}
