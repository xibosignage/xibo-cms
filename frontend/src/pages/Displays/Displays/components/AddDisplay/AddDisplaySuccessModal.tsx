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

import { Check, Info, Loader2, Plus, TriangleAlert } from 'lucide-react';
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
}

interface AddDisplaySuccessModalProps {
  submitted: SubmittedDisplay;
  display?: Display | null;
  /** The Player was already registered, so nothing on the form was applied to it. */
  alreadyRegistered?: boolean;
  /** Parts of the setup that didn't go as asked, shown above the summary. */
  warnings?: string[];
  /** False when this user can't see the display, so there is nothing to manage. */
  canManage?: boolean;
  /** Licence usage read after the display was added, so it counts this display. */
  licenceText?: string;
  /** The licence usage is being read again, so the count isn't known yet. */
  licenceLoading?: boolean;
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
  alreadyRegistered = false,
  warnings = [],
  canManage = true,
  licenceText: currentLicenceText,
  licenceLoading = false,
  onClose,
  onAddAnother,
  onManage,
}: AddDisplaySuccessModalProps) {
  const { t } = useTranslation();

  const isLicensed = display ? display.licensed === 1 : submitted.authorise;

  // What the form asked for only describes the display when the form was applied to it.
  const formGroup = alreadyRegistered ? undefined : submitted.displayGroup;
  const licenceText = alreadyRegistered ? undefined : currentLicenceText;

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
      title={alreadyRegistered ? t('Display Already Registered') : t('Display Added Successfully')}
    >
      <div className="px-6 py-6 flex flex-col items-center gap-5">
        {alreadyRegistered ? (
          <div className="flex items-center justify-center h-14 w-14 rounded-full bg-xibo-blue-50 border-2 border-xibo-blue-200">
            <Info className="h-7 w-7 text-xibo-blue-500" strokeWidth={2.5} />
          </div>
        ) : (
          <div className="flex items-center justify-center h-14 w-14 rounded-full bg-teal-50 border-2 border-teal-200">
            <Check className="h-7 w-7 text-teal-500" strokeWidth={2.5} />
          </div>
        )}

        {/* Subtitle */}
        <p className="text-sm text-gray-500 text-center">
          {alreadyRegistered
            ? t(
                'This Player was already registered with the CMS, so it was not added again and the settings on this form were not applied. Use Manage Display to change them.',
              )
            : t('Your Display has been added and verified.')}
        </p>

        {warnings.length > 0 && (
          <div
            className="bg-yellow-50 border border-yellow-200 text-sm text-yellow-800 rounded-lg w-full p-4"
            role="alert"
          >
            <div className="flex">
              <div className="shrink-0">
                <TriangleAlert size={18} />
              </div>
              <div className="ms-4 flex flex-col gap-2">
                {warnings.map((warning) => (
                  <p key={warning} className="text-sm font-semibold">
                    {warning}
                  </p>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* Summary card */}
        <div className="w-full rounded-lg border border-gray-200 px-4">
          <SummaryRow label={t('Display Name')}>
            {display?.display || submitted.displayName || t('—')}
          </SummaryRow>
          <SummaryRow label={t('Display Group')}>
            {formGroup ||
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
          <SummaryRow label={t('Licence')}>
            <span className="inline-flex items-center gap-1.5">
              {isLicensed && <Check className="h-4 w-4 text-teal-500" strokeWidth={2.5} />}
              <span>{isLicensed ? t('Authorised') : t('Not authorised')}</span>
              {!alreadyRegistered && licenceLoading && (
                <Loader2
                  className="h-4 w-4 animate-spin text-gray-400"
                  role="status"
                  aria-label={t('Loading licence information...')}
                />
              )}
              {licenceText && <span className="text-gray-400">| {licenceText}</span>}
            </span>
          </SummaryRow>
        </div>

        {/* Actions */}
        <div className="flex w-full gap-3 pt-1">
          <Button variant="secondary" onClick={onAddAnother} leftIcon={Plus} className="flex-1">
            {t('Add Another')}
          </Button>
          {canManage && (
            <Button variant="primary" onClick={onManage} className="flex-1">
              {t('Manage Display')}
            </Button>
          )}
        </div>
      </div>
    </Modal>
  );
}
