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

import {
  autoUpdate,
  flip,
  FloatingFocusManager,
  FloatingPortal,
  offset,
  shift,
  useClick,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation } from 'react-router-dom';

import FeedbackForm from './FeedbackForm';
import HelpHeader from './HelpHeader';
import HelpMainPanel from './HelpMainPanel';

import type { MenuTriggerProps } from '@/components/layout/menuTrigger';
import { notify } from '@/components/ui/Notification';
import { useBranding } from '@/context/BrandingContext';
import { useUserContext } from '@/context/UserContext';
import { useHelpKey, useHelpPageLinks } from '@/hooks/useHelpPane';

interface HelpSteps {
  main: true;
  feedback: true;
}

type HelpStep = keyof HelpSteps;

export default function HelpMenu({ placement = 'top-end', renderTrigger }: MenuTriggerProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const branding = useBranding();

  const { pathname } = useLocation();
  const [isOpen, setIsOpen] = useState(false);
  const [step, setStep] = useState<HelpStep>('main');

  useEffect(() => {
    setIsOpen(false);
  }, [pathname]);

  const helpKey = useHelpKey();
  const { data } = useHelpPageLinks(helpKey, isOpen);
  const links = data?.links ?? [];
  const landingPage = data?.landingPage;

  const isXiboThemed = branding.appName === 'Xibo';
  const isMainStep = step === 'main';

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: (open) => {
      if (open) {
        setStep('main');
      }
      setIsOpen(open);
    },
    placement,
    whileElementsMounted: autoUpdate,
    middleware: [offset(20), flip(), shift({ padding: 8 })],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context, { escapeKey: isMainStep, outsidePress: false });
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  const close = () => setIsOpen(false);

  return (
    <>
      {renderTrigger ? (
        renderTrigger({ ref: refs.setReference, referenceProps: getReferenceProps(), isOpen })
      ) : (
        <button
          ref={refs.setReference}
          {...getReferenceProps()}
          type="button"
          aria-label={t('Help Centre')}
          aria-expanded={isOpen}
          className="flex size-11.25 items-center justify-center rounded-full bg-xibo-blue-600 text-white shadow-lg transition-colors hover:bg-xibo-blue-700 cursor-pointer focus:outline-4 focus:outline-xibo-blue-500/25"
        >
          <span className="text-2xl">?</span>
        </button>
      )}

      {isOpen && (
        <FloatingPortal>
          <div
            className="fixed inset-0 z-50"
            onClick={() => {
              if (isMainStep) {
                close();
              }
            }}
          />
          <FloatingFocusManager context={context} modal={false} closeOnFocusOut={false}>
            <div
              ref={refs.setFloating}
              style={floatingStyles}
              {...getFloatingProps()}
              className="z-50 flex max-h-[calc(100vh-6.25rem)] w-105 max-w-[calc(100vw-1rem)] flex-col overflow-hidden rounded-xl outline outline-gray-200 border-gray-200 bg-white shadow-lg"
              role="dialog"
              aria-label={t('Help Centre')}
            >
              {step === 'main' && (
                <>
                  <HelpHeader title={t('Help Centre')} onClose={close} />
                  <div className="overflow-y-auto">
                    <HelpMainPanel
                      appName={branding.appName}
                      isXiboThemed={isXiboThemed}
                      links={links}
                      landingPage={landingPage}
                      onOpenFeedback={() => setStep('feedback')}
                    />
                  </div>
                </>
              )}

              {step === 'feedback' && (
                <>
                  <HelpHeader title={t('Feedback')} onClose={close} />
                  <div className="overflow-y-auto">
                    <FeedbackForm
                      userName={user?.userName ?? ''}
                      email={user?.email ?? ''}
                      accountId={String(user?.settings?.accountId ?? '')}
                      onBack={() => setStep('main')}
                      onSuccess={() => {
                        notify.success(t('Thank you for leaving your feedback!'));
                        close();
                      }}
                    />
                  </div>
                </>
              )}
            </div>
          </FloatingFocusManager>
        </FloatingPortal>
      )}
    </>
  );
}
