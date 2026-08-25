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

import { CheckCircle2, HelpCircle, Loader2, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import type { Display } from '@/types/display';

export interface SubmittedDisplay {
  code: string;
  displayName: string;
  folderText: string;
  authorise: boolean;
}

/**
 * `submitted` - code sent, nothing further being applied (no edit permission).
 * `waiting`   - watching for the Player to register.
 * `connected` - found it and applied the settings.
 * `ambiguous` - more than one display appeared; the operator must choose.
 * `timedOut`  - the Player never turned up.
 */
export type PanelState = 'submitted' | 'waiting' | 'connected' | 'ambiguous' | 'timedOut';

interface AddDisplaySuccessPanelProps {
  submitted: SubmittedDisplay;
  state: PanelState;
  candidates?: Display[];
  error?: string;
  onPick?: (display: Display) => void;
  onAddAnother: () => void;
  onManage: () => void;
}

function ReadOnlyRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-gray-500">{label}</span>
      <span className="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
        {value}
      </span>
    </div>
  );
}

/**
 * Shown once the activation code has been submitted.
 *
 * The display does not exist yet at this point - the Player creates it when it registers, a few
 * seconds later - so this panel reports what was submitted and then waits. Manage Display only
 * becomes available once a real display has been found and updated.
 */
export default function AddDisplaySuccessPanel({
  submitted,
  state,
  candidates = [],
  error,
  onPick,
  onAddAnother,
  onManage,
}: AddDisplaySuccessPanelProps) {
  const { t } = useTranslation();

  return (
    <div className="px-6 py-4 flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <ReadOnlyRow label={t('Code')} value={submitted.code} />
        {submitted.displayName !== '' && (
          <ReadOnlyRow label={t('Display Name')} value={submitted.displayName} />
        )}
        {submitted.displayName !== '' && (
          <>
            <ReadOnlyRow label={t('Folder')} value={submitted.folderText || t('Default folder')} />
            <ReadOnlyRow
              label={t('Authorize Display')}
              value={submitted.authorise ? t('Yes') : t('No')}
            />
          </>
        )}
      </div>

      {state === 'submitted' && (
        <div
          className="flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-800"
          role="status"
        >
          <CheckCircle2 className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span>{t('Code accepted. Your player will connect shortly.')}</span>
        </div>
      )}

      {state === 'waiting' && (
        <div
          className="flex items-center gap-2 rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700"
          role="status"
        >
          <Loader2 className="h-4 w-4 shrink-0 animate-spin" aria-hidden="true" />
          <span>{t('Waiting for your player to connect. Please keep this window open.')}</span>
        </div>
      )}

      {state === 'connected' && (
        <div
          className="flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-800"
          role="status"
        >
          <CheckCircle2 className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span>{t('Connected. Your display is ready.')}</span>
        </div>
      )}

      {state === 'ambiguous' && (
        <div className="flex flex-col gap-2">
          <div
            className="flex items-start gap-2 rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700"
            role="status"
          >
            <HelpCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <span>{t('More than one display connected just now. Which one is yours?')}</span>
          </div>

          <ul className="flex flex-col gap-2">
            {candidates.map((candidate) => (
              <li key={candidate.displayId}>
                <button
                  type="button"
                  className="w-full rounded-md border border-gray-200 px-3 py-2 text-left text-sm hover:border-xibo-blue-300 hover:bg-xibo-blue-50"
                  onClick={() => onPick?.(candidate)}
                >
                  <span className="font-medium text-gray-800">{candidate.display}</span>
                  <span className="block text-xs text-gray-500">
                    {[candidate.clientType, candidate.clientAddress, candidate.license]
                      .filter(Boolean)
                      .join(' · ')}
                  </span>
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}

      {state === 'timedOut' && (
        <div
          className="flex items-start gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800"
          role="alert"
        >
          <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          <span>
            {t(
              'We have not heard from your player yet. Check that it is powered on and connected to the internet, then try again.',
            )}
          </span>
        </div>
      )}

      {error && (
        <div
          className="flex items-start gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800"
          role="alert"
        >
          <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          <span>
            {t(
              'Your display connected, but we could not apply the name and folder. You can set them from Manage Display.',
            )}
          </span>
        </div>
      )}

      <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
        <Button variant="secondary" onClick={onAddAnother}>
          {state === 'timedOut' ? t('Try Again') : t('Add Another Display')}
        </Button>
        <Button variant="primary" onClick={onManage} disabled={state !== 'connected'}>
          {t('Manage Display')}
        </Button>
      </div>
    </div>
  );
}
