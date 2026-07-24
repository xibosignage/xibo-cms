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

import { notify } from '@/components/ui/Notification';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';

export type EnableStatValue = boolean | 'On' | 'Off' | 'Inherit';

interface EnableStatsMultipleModalProps {
  isOpen?: boolean;
  ids: Array<number | string>;
  entityLabel?: string;
  mode?: 'boolean' | 'inherit';
  setEnableStat: (id: number | string, enableStat: EnableStatValue) => Promise<void>;
  onClose: () => void;
  onSuccess?: () => void;
}

export default function EnableStatsMultipleModal({
  isOpen = true,
  ids,
  entityLabel,
  mode = 'boolean',
  setEnableStat,
  onClose,
  onSuccess,
}: EnableStatsMultipleModalProps) {
  const { t } = useTranslation();
  const [enabled, setEnabled] = useState(false);
  const [inheritValue, setInheritValue] = useState<'On' | 'Off' | 'Inherit'>('Inherit');
  const [isLoading, setIsLoading] = useState(false);
  const [progress, setProgress] = useState<{ done: number; total: number } | null>(null);
  const [error, setError] = useState<string>();

  const label = entityLabel ?? t('items');

  const handleConfirm = async () => {
    if (isLoading || ids.length === 0) {
      return;
    }

    const value: EnableStatValue = mode === 'inherit' ? inheritValue : enabled;

    setError(undefined);
    setIsLoading(true);
    setProgress({ done: 0, total: ids.length });

    let failures = 0;
    for (let i = 0; i < ids.length; i += 1) {
      try {
        await setEnableStat(ids[i]!, value);
      } catch (err) {
        console.error(err);
        failures += 1;
      }
      setProgress({ done: i + 1, total: ids.length });
    }

    setIsLoading(false);
    setProgress(null);

    if (failures > 0) {
      const succeeded = ids.length - failures;
      setError(
        t('{{failed}} of {{total}} {{label}} could not be updated.', {
          failed: failures,
          total: ids.length,
          label,
        }),
      );
      if (succeeded > 0) {
        onSuccess?.();
      }
      return;
    }

    notify.success(
      t('Stats collection updated for {{count}} {{label}}.', { count: ids.length, label }),
    );
    onSuccess?.();
    onClose();
  };

  return (
    <Modal
      variant="confirmation"
      title={t('Enable Stats Collection')}
      isOpen={isOpen}
      onClose={onClose}
      isPending={isLoading}
      error={error}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: progress
            ? t('Saving {{done}}/{{total}}…', { done: progress.done, total: progress.total })
            : t('Save'),
          onClick: handleConfirm,
          disabled: isLoading || ids.length === 0,
        },
      ]}
    >
      <div className="p-4 flex flex-col gap-3">
        <p className="text-sm text-gray-500">
          {t('Updating stats collection for {{count}} selected {{label}}.', {
            count: ids.length,
            label,
          })}
        </p>
        {mode === 'inherit' ? (
          <SelectDropdown
            label={t('Enable Stats Collection')}
            value={inheritValue}
            options={[
              { label: t('On'), value: 'On' },
              { label: t('Off'), value: 'Off' },
              { label: t('Inherit'), value: 'Inherit' },
            ]}
            onSelect={(value) => setInheritValue(value as 'On' | 'Off' | 'Inherit')}
            helpText={t(
              `Collect Proof of Play statistics. 'Inherit' uses the Layout or Display setting.`,
            )}
          />
        ) : (
          <Checkbox
            id="enableStatMultiple"
            className="items-center px-3 py-2.5"
            title={t('Enable Stats Collections')}
            label={t(
              `Collect Proof of Play statistics. Requires 'Enable Stats Collection' to be set to 'On' in Display Settings.`,
            )}
            checked={enabled}
            onChange={(e) => setEnabled(e.target.checked)}
          />
        )}
      </div>
    </Modal>
  );
}
