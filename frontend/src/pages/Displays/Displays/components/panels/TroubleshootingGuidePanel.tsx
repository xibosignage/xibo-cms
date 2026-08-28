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

import { AlertTriangle, CircleCheck, HelpCircle, LifeBuoy } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { getDisplayStatusBucket } from '../../DisplayStatusConfig';

import { PanelCard } from './PanelCard';

import Accordion, { ACCORDION_NESTED_HEADER_CLASS } from '@/components/ui/Accordion';
import type { Display } from '@/types/display';

interface TroubleshootingGuidePanelProps {
  display: Display;
  /** Show the full guide even for a healthy display — set when the caller
   * (e.g. the Proof of Play placeholder's "zero plays" link) wants the
   * reader to check troubleshooting steps despite the display checking in
   * normally. */
  forceShow: boolean;
}

interface Step {
  title: string;
  description: string;
}

// Numbered step list matching display-management.html's .ts-steps — a
// circled step number (using the app's real xibo-blue token, not the mock's
// hex) followed by a bold title and a plain-language description, instead of
// a flat bullet list.
function StepList({ steps }: { steps: Step[] }) {
  return (
    <ol className="flex flex-col px-4 pb-4 pt-1">
      {steps.map((step, index) => (
        <li key={index} className="flex gap-3 py-2.5 border-t border-gray-100 first:border-t-0">
          <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-xibo-blue-600 text-[11px] font-bold text-white">
            {index + 1}
          </span>
          <div className="text-sm">
            <p className="font-semibold text-gray-700">{step.title}</p>
            <p className="text-gray-500">{step.description}</p>
          </div>
        </li>
      ))}
    </ol>
  );
}

// Static first-response copy — a first draft for product/content review, not
// final signed-off wording. Intentionally has no data dependency (backlog
// row 15). The healthy/"all clear" box below is the one part that IS
// data-driven, following display-management.html's troubleshootingSteps('online')
// case, which swaps the whole guide for a single confirmation line.
export default function TroubleshootingGuidePanel({
  display,
  forceShow,
}: TroubleshootingGuidePanelProps) {
  const { t } = useTranslation();
  const bucket = getDisplayStatusBucket(display);
  const isHealthy = bucket === 'online';
  const isOffline = bucket === 'offline';

  const needsAttentionSteps: Step[] = [
    {
      title: t('Check network connectivity'),
      description: t('Confirm the display is still checking in — is it still online?'),
    },
    {
      title: t('Check the Authorised status'),
      description: t('Shown above — an unauthorised display will not receive content.'),
    },
    {
      title: t('Check the commercial licence status'),
      description: t('A display that is not licensed or in trial may stop syncing.'),
    },
    {
      title: t('Check media sync status'),
      description: t(
        'In Advanced Diagnostics below — look for incomplete Dependencies, Layouts or Widgets.',
      ),
    },
    {
      title: t('Still not resolved?'),
      description: t('Try a manual "Collect Now" and re-check after a few minutes.'),
    },
  ];

  // Distinct from needsAttentionSteps — a display that's fully offline needs
  // on-site power/network checks first, not the CMS-side authorisation/sync
  // checks that apply to a display that's still checking in. Matches
  // xibo-displays.html's troubleshootingSteps()'s separate "offline" branch.
  const offlineSteps: Step[] = [
    {
      title: t('Check power & video output'),
      description: t(
        'Confirm the screen and player device are powered on and the video cable is properly connected.',
      ),
    },
    {
      title: t('Check network connectivity'),
      description: t(
        'Confirm Wi-Fi/Ethernet at the site is active, and try reaching the CMS URL from another device on the same network.',
      ),
    },
    {
      title: t('Review the reported fault'),
      description: t('Check Advanced Diagnostics for the specific error the player last logged.'),
    },
    {
      title: t("Check storage isn't full"),
      description: t(
        'Low free storage can block new content downloads — free up space if it is critically low.',
      ),
    },
    {
      title: t('Restart the player device'),
      description: t('A full power cycle resolves the majority of stuck-connection issues.'),
    },
    {
      title: t('Still stuck?'),
      description: t('Contact support with the Display ID and the fault message above.'),
    },
  ];

  const primarySteps = isOffline ? offlineSteps : needsAttentionSteps;
  const primaryTitle = isOffline ? t('Offline / fault reported') : t('Needs attention');

  const zeroPlaysSteps: Step[] = [
    {
      title: t('Confirm a schedule is assigned'),
      description: t(
        'Check for a Layout or Campaign scheduled to this display, or to a display group it belongs to.',
      ),
    },
    {
      title: t("Confirm the layout isn't empty"),
      description: t('It should not contain only invalid or unsupported widgets.'),
    },
    {
      title: t('Check display & Interleave settings'),
      description: t(
        'Confirm the display is not disabled, and its Interleave Default Layout setting is not hiding scheduled content.',
      ),
    },
    {
      title: t('Check the schedule window'),
      description: t(
        'Date range and day-parting — the display may simply be outside its active window.',
      ),
    },
    {
      title: t('Confirm downloads finished'),
      description: t(
        'If the layout was recently published, check Advanced Diagnostics to confirm the new content has finished downloading.',
      ),
    },
  ];

  return (
    <PanelCard
      title={t('Troubleshooting Guide')}
      icon={LifeBuoy}
      className="border-blue-100"
      headerClassName="bg-blue-50"
      bodyClassName="bg-blue-50/40"
    >
      {isHealthy && !forceShow ? (
        <div className="p-4">
          <div className="flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-100 px-4 py-3 text-sm font-medium text-teal-800">
            <CircleCheck className="size-4 shrink-0" />
            {t('All clear — this display is checking in normally. No action needed.')}
          </div>
        </div>
      ) : (
        <div className="p-4 flex flex-col gap-3">
          <Accordion
            title={primaryTitle}
            icon={AlertTriangle}
            defaultOpen
            className="border-gray-100 bg-white"
            headerClassName={ACCORDION_NESTED_HEADER_CLASS}
            contentClassName="bg-white"
          >
            <StepList steps={primarySteps} />
          </Accordion>

          <Accordion
            title={t('Online but zero plays')}
            icon={HelpCircle}
            className="border-gray-100 bg-white"
            headerClassName={ACCORDION_NESTED_HEADER_CLASS}
            contentClassName="bg-white"
          >
            <StepList steps={zeroPlaysSteps} />
          </Accordion>
        </div>
      )}
    </PanelCard>
  );
}
