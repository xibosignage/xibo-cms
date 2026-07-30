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
import { useLocation } from 'react-router-dom';

import FeedbackForm from './FeedbackForm';
import HelpHeader from './HelpHeader';
import HelpMainPanel from './HelpMainPanel';

import { notify } from '@/components/ui/Notification';
import { useBranding } from '@/context/BrandingContext';
import { useUserContext } from '@/context/UserContext';
import { useHelpKey, useHelpPageLinks } from '@/hooks/useHelpPane';

type HelpStep = 'main' | 'feedback';

export default function HelpPane() {
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

  useEffect(() => {
    if (!isOpen) {
      return;
    }
    const onKeyDown = (ev: KeyboardEvent) => {
      if (ev.key === 'Escape' && step !== 'feedback') {
        setIsOpen(false);
      }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [isOpen, step]);

  const openPane = () => {
    setStep('main');
    setIsOpen(true);
  };

  const close = () => setIsOpen(false);

  const title = step === 'main' ? t('Help Centre') : t('Feedback');

  return (
    <>
      {isOpen && (
        <div
          className="fixed inset-0 z-50"
          onClick={() => {
            if (step !== 'feedback') {
              close();
            }
          }}
        />
      )}

      <div className="fixed bottom-5 right-5 z-50 hidden md:flex flex-col items-end gap-5">
        {isOpen && (
          <div
            className="relative flex max-h-[calc(100vh-6.25rem)] w-105 flex-col overflow-hidden rounded-xl outline outline-gray-200 border-gray-200 bg-white shadow-lg"
            role="dialog"
            aria-label={t('Help Centre')}
          >
            <HelpHeader title={title} onClose={close} />

            <div className="overflow-y-auto">
              {step === 'main' && (
                <HelpMainPanel
                  appName={branding.appName}
                  isXiboThemed={isXiboThemed}
                  links={links}
                  landingPage={landingPage}
                  onOpenFeedback={() => setStep('feedback')}
                />
              )}

              {step === 'feedback' && (
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
              )}
            </div>
          </div>
        )}

        <button
          type="button"
          aria-label={t('Help Centre')}
          aria-expanded={isOpen}
          onClick={() => (isOpen ? close() : openPane())}
          className="flex size-11.25 items-center justify-center rounded-full bg-xibo-blue-600 text-white shadow-lg transition-colors hover:bg-xibo-blue-700 cursor-pointer focus:outline-4 focus:outline-blue-500/25"
        >
          <span className="text-2xl">?</span>
        </button>
      </div>
    </>
  );
}
