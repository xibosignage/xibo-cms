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

import { Check, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import Modal from '@/components/ui/modals/Modal';
import type { Display } from '@/types/display';

export interface SubmittedDisplay {
  code: string;
  displayName: string;
  folderText: string;
  displayGroup?: string;
  authorise: boolean;
  licenceText?: string;
}

interface AddDisplaySuccessModalProps {
  submitted: SubmittedDisplay;
  display?: Display | null;
  onClose: () => void;
  onAddAnother: () => void;
  onManage: () => void;
}

function SummaryRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
      <span className="text-sm text-gray-500">{label}</span>
      <span className="text-sm font-medium text-gray-800">{children}</span>
    </div>
  );
}

export default function AddDisplaySuccessModal({
  submitted,
  display,
  onClose,
  onAddAnother,
  onManage,
}: AddDisplaySuccessModalProps) {
  const { t } = useTranslation();

  const isLicensed = display ? display.licensed === 1 : submitted.authorise;

  const statusBadge = (() => {
    const status = display?.mediaInventoryStatus;
    if (status === 1) {
      return { label: t('Up to date'), bg: 'bg-teal-50 border-teal-200 text-teal-600' };
    }
    if (status === 3) {
      return { label: t('Downloading'), bg: 'bg-orange-50 border-orange-200 text-orange-600' };
    }
    // Freshly added displays may not have an accurate status yet — infer from licence
    if (isLicensed) {
      return { label: t('Downloading'), bg: 'bg-orange-50 border-orange-200 text-orange-600' };
    }
    return { label: t('Out of date'), bg: 'bg-gray-50 border-gray-200 text-gray-600' };
  })();

  return (
    <Modal
      isOpen
      onClose={onClose}
      size="sm"
      showCloseButton
      ariaLabel={t('Display Added Successfully')}
    >
      <div className="px-6 py-6 flex flex-col items-center gap-5">
        {/* Success icon */}
        <div className="flex items-center justify-center h-14 w-14 rounded-full bg-teal-50 border-2 border-teal-200">
          <Check className="h-7 w-7 text-teal-500" strokeWidth={2.5} />
        </div>

        {/* Title and subtitle */}
        <div className="text-center">
          <h3 className="text-lg font-semibold text-gray-900">{t('Display Added Successfully')}</h3>
          <p className="text-sm text-gray-500 mt-1">
            {t('Your Display has been added and verified.')}
          </p>
        </div>

        {/* Summary card */}
        <div className="w-full rounded-lg border border-gray-200 px-4">
          <SummaryRow label={t('Display Name')}>
            {display?.display || submitted.displayName || t('—')}
          </SummaryRow>
          <SummaryRow label={t('Display Group')}>
            {submitted.displayGroup ||
              display?.displayGroups?.find((g) => g.isDynamic === 0 && g.isDisplaySpecific === 0)
                ?.displayGroup ||
              t('—')}
          </SummaryRow>
          <SummaryRow label={t('Status')}>
            <span
              className={`inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium ${statusBadge.bg}`}
            >
              {statusBadge.label}
            </span>
          </SummaryRow>
          <SummaryRow label={t('License')}>
            <span className="inline-flex items-center gap-1.5">
              {isLicensed && <Check className="h-4 w-4 text-teal-500" strokeWidth={2.5} />}
              <span>{isLicensed ? t('Authorized') : t('Not authorized')}</span>
              {submitted.licenceText && (
                <span className="text-gray-400">| {submitted.licenceText}</span>
              )}
            </span>
          </SummaryRow>
        </div>

        {/* Actions */}
        <div className="flex w-full gap-3 pt-1">
          <Button variant="secondary" onClick={onAddAnother} leftIcon={Plus} className="flex-1">
            {t('Add Another')}
          </Button>
          <Button variant="primary" onClick={onManage} className="flex-1">
            {t('Manage Display')}
          </Button>
        </div>
      </div>
    </Modal>
  );
}
