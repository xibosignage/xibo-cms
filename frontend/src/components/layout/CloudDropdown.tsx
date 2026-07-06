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

import {
  autoUpdate,
  flip,
  FloatingPortal,
  offset,
  safePolygon,
  shift,
  useDismiss,
  useFloating,
  useHover,
  useInteractions,
} from '@floating-ui/react';
import { Cloud, CloudDownload } from 'lucide-react';
import { DateTime } from 'luxon';
import { useState, type ReactNode } from 'react';
import { Trans, useTranslation } from 'react-i18next';

import { useUserContext } from '@/context/UserContext';

// Matches the legacy warning colour used by the 4.4 "Xibo in the Cloud" topbar icon.
const WARNING_COLOR = '#ae2323';

export default function CloudDropdown() {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const [isOpen, setIsOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'bottom-end',
    whileElementsMounted: autoUpdate,
    middleware: [offset(8), flip(), shift({ padding: 8 })],
  });

  // Restore the 4.4 behaviour: open on hover. safePolygon keeps it open while the
  // cursor travels the offset gap between the icon and the popover.
  const hover = useHover(context, {
    delay: { open: 100, close: 100 },
    handleClose: safePolygon(),
  });
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([hover, dismiss]);

  const cloud = user?.cloudHosting;

  // The backend only populates cloudHosting for super admins on Xibo-themed Cloud instances.
  if (!cloud) return null;

  const renewal = DateTime.fromISO(cloud.renewalDate);
  const isValid = renewal.isValid;

  const daysUntilRenewal = isValid ? renewal.diff(DateTime.now(), 'days').days : Infinity;
  // Mirror the legacy threshold logic: trials warn at 5 days, monthly at 1, otherwise 30.
  const threshold = cloud.isDemo ? 5 : cloud.isMonthly ? 1 : 30;
  // Only warn when renewal is actually at risk — an instance set to auto-renew is healthy,
  // so it shouldn't show the red expiry icon. Trials always warn as they are not renewing.
  const isWarning = (cloud.isDemo || !cloud.willRenew) && daysUntilRenewal < threshold;

  const relative = isValid ? (renewal.toRelative() ?? '') : cloud.renewalDate;
  const formattedDate = isValid ? renewal.toLocaleString(DateTime.DATE_FULL) : cloud.renewalDate;

  const Icon = isWarning ? CloudDownload : Cloud;

  let body: ReactNode;
  if (cloud.isDemo) {
    body = t('This is a trial which expires {{relative}}, on {{date}}.', {
      relative,
      date: formattedDate,
    });
  } else if (cloud.willRenew) {
    body = t('Your renewal date is {{relative}}, on {{date}} and you are set to renew.', {
      relative,
      date: formattedDate,
    });
  } else {
    body = (
      <Trans
        i18nKey="Your renewal date is {{relative}}, on {{date}} and you are <strong>not</strong> set to renew. This Xibo CMS will be deleted after the renewal date."
        values={{ relative, date: formattedDate }}
        components={{ strong: <strong /> }}
      />
    );
  }

  return (
    <>
      <button
        ref={refs.setReference}
        type="button"
        className="cursor-pointer flex items-center justify-center relative h-9.5 w-9.5"
        {...getReferenceProps()}
        aria-label={t('Xibo in the Cloud')}
        title={t('Xibo in the Cloud')}
      >
        <Icon
          size={16}
          className={isWarning ? undefined : 'text-xibo-blue-600'}
          style={isWarning ? { color: WARNING_COLOR } : undefined}
        />
      </button>

      {isOpen && (
        <FloatingPortal>
          <div
            ref={refs.setFloating}
            style={{ ...floatingStyles, zIndex: 9999 }}
            {...getFloatingProps()}
            className="rounded-xl bg-white shadow-lg ring-1 ring-black/5 focus:outline-none overflow-hidden w-80 flex flex-col"
          >
            {/* Header — matches the notification/user popovers */}
            <div className="flex items-center gap-2 bg-gray-100 py-2 px-4">
              <Icon
                size={16}
                className={isWarning ? undefined : 'text-xibo-blue-600'}
                style={isWarning ? { color: WARNING_COLOR } : undefined}
              />
              <h3 className="text-sm font-semibold text-gray-900">{t('Xibo in the Cloud')}</h3>
            </div>

            <div className="p-4">
              <p className="text-sm leading-relaxed text-gray-700">{body}</p>
            </div>
          </div>
        </FloatingPortal>
      )}
    </>
  );
}
