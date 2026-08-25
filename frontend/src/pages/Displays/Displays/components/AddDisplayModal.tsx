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

import { isAxiosError } from 'axios';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { useApplyDisplaySettings } from '../hooks/useApplyDisplaySettings';
import { useNewDisplayDetector } from '../hooks/useNewDisplayDetector';

import AddDisplaySuccessPanel, { type SubmittedDisplay } from './AddDisplaySuccessPanel';

import SelectFolder from '@/components/ui/forms/SelectFolder';
import Switch from '@/components/ui/forms/Switch';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import {
  addDisplayViaCode,
  fetchHighestDisplayId,
  fetchLicenceUsage,
  type LicenceUsage,
} from '@/services/displaysApi';
import type { Display } from '@/types/display';
import { hasFeature } from '@/utils/permissions';

export interface AddDisplayPrefill {
  code: string;
  displayName?: string;
}

interface AddDisplayModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onAdded?: () => void;
  /** Populated when the customer arrived from a deep link, e.g. a scanned QR code. */
  prefill?: AddDisplayPrefill | null;
  /** Called with the new display once the Player has connected and its settings are applied. */
  onManage?: (display: Display) => void;
}

export default function AddDisplayModal({
  isOpen = true,
  onClose,
  onAdded,
  prefill = null,
  onManage,
}: AddDisplayModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const [isPending, startTransition] = useTransition();

  const [userCode, setUserCode] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [folder, setFolder] = useState<{ id: number; text: string } | null>(null);
  const [authorise, setAuthorise] = useState(true);

  const [licence, setLicence] = useState<LicenceUsage | null>(null);
  const [watermark, setWatermark] = useState<number | null>(null);
  const [submitted, setSubmitted] = useState<SubmittedDisplay | null>(null);
  const [adopted, setAdopted] = useState<Display | null>(null);
  const [apiError, setApiError] = useState<string | undefined>();
  const [showDetail, setShowDetail] = useState(false);

  const { state: detectState, candidates } = useNewDisplayDetector(watermark);
  const { apply, error: applyError } = useApplyDisplaySettings();

  // Authorising takes a licence slot, so only offer it to users who are allowed to authorise.
  const canAuthorise = hasFeature(user, 'displays.authorise');

  // Naming and filing a display is an edit, which is a different permission from adding one.
  // Without it we cannot apply anything, so fall back to the plain activation-code form.
  const canApplySettings = hasFeature(user, 'displays.modify');

  const resetForm = () => {
    setUserCode(prefill?.code ?? '');
    setDisplayName(prefill?.displayName ?? '');
    setFolder(null);
    setAuthorise(true);
    setWatermark(null);
    setSubmitted(null);
    setAdopted(null);
    setApiError(undefined);
    setShowDetail(false);
  };

  useEffect(() => {
    if (isOpen) {
      resetForm();
    }
  }, [isOpen, prefill?.code, prefill?.displayName]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    let cancelled = false;

    fetchLicenceUsage()
      .then((usage) => {
        if (!cancelled) {
          setLicence(usage);
        }
      })
      .catch(() => {
        // Licence usage is advisory. If we cannot read it, the form still works.
        if (!cancelled) {
          setLicence(null);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [isOpen]);

  const licenceHelpText = (): string => {
    if (!licence) {
      return '';
    }

    if (licence.maxLicensed === 0) {
      return t('Unlimited licences available.');
    }

    return t('{{used}} of {{max}} licences in use, {{available}} available.', {
      used: licence.currentlyLicensed,
      max: licence.maxLicensed,
      available: licence.available ?? 0,
    });
  };

  const handleSubmit = () => {
    startTransition(async () => {
      setApiError(undefined);

      try {
        // Note where the display list stands BEFORE the Player can register, so whatever turns up
        // afterwards can be recognised as new. Must happen before the code is submitted.
        const highestSeenId = canApplySettings ? await fetchHighestDisplayId() : null;

        await addDisplayViaCode(userCode.trim());

        setSubmitted({
          code: userCode.trim(),
          displayName: displayName.trim(),
          folderText: folder?.text ?? '',
          authorise: canAuthorise && authorise,
        });

        if (highestSeenId !== null) {
          setWatermark(highestSeenId);
        }

        // Let the grid pick the display up as soon as it registers.
        onAdded?.();
      } catch (err: unknown) {
        if (isAxiosError(err) && err.response?.data?.message) {
          setApiError(err.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred.'));
        }
      }
    });
  };

  /** Adopt a detected display: apply the chosen name, folder and authorisation to it. */
  const adopt = async (display: Display) => {
    const updated = await apply(display, {
      displayName: displayName.trim() || display.display,
      folderId: folder?.id ?? null,
      authorise: canAuthorise && authorise,
    });

    // Even if the edit failed the display exists, so keep it for the Manage CTA - it just kept
    // its default name and folder. useApplyDisplaySettings surfaces the error.
    setAdopted(updated ?? display);
    onAdded?.();
  };

  // Exactly one new display means it is ours. More than one is handled by the picker.
  useEffect(() => {
    const only = candidates[0];

    if (detectState === 'found' && candidates.length === 1 && only && !adopted) {
      void adopt(only);
    }
  }, [detectState, candidates, adopted]);

  const handleManage = () => {
    if (adopted && onManage) {
      onManage(adopted);
      return;
    }

    onClose();
  };

  // Without the edit permission there is nothing to fill in beyond the code.
  const canSubmit = canApplySettings
    ? userCode.trim() !== '' && displayName.trim() !== ''
    : userCode.trim() !== '';

  /** What the success panel should show. */
  const panelState = (() => {
    if (adopted) {
      return 'connected' as const;
    }
    if (!canApplySettings) {
      // Nothing is being applied, so there is nothing to wait for.
      return 'submitted' as const;
    }
    if (detectState === 'ambiguous') {
      return 'ambiguous' as const;
    }
    if (detectState === 'timedOut') {
      return 'timedOut' as const;
    }
    return 'waiting' as const;
  })();

  return (
    <Modal
      title={submitted ? t('Display Added') : t('Add Display')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable={false}
      error={apiError}
      actions={
        submitted
          ? []
          : [
              { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
              {
                label: isPending ? t('Saving...') : t('Save'),
                onClick: handleSubmit,
                disabled: isPending || !canSubmit,
              },
            ]
      }
    >
      {submitted ? (
        <AddDisplaySuccessPanel
          submitted={submitted}
          state={panelState}
          candidates={candidates}
          error={applyError}
          onPick={adopt}
          onAddAnother={resetForm}
          onManage={handleManage}
        />
      ) : (
        <div className="px-6 py-4 flex flex-col gap-4">
          <TextInput
            name="user_code"
            label={t('Code')}
            placeholder=" "
            onChange={(value) => setUserCode(value)}
            helpText={t('Please provide the code displayed on the Player screen')}
            value={userCode}
          />

          {canApplySettings && (
            <>
              <TextInput
                name="displayName"
                label={t('Display Name')}
                placeholder=" "
                onChange={(value) => setDisplayName(value)}
                helpText={t('A name for this display, so you can find it again later')}
                value={displayName}
              />

              <div className="flex flex-col gap-1">
                <label className="text-sm font-medium text-gray-700" htmlFor="add-display-folder">
                  {t('Folder')}
                </label>
                <SelectFolder
                  selectedId={folder?.id}
                  selectedText={folder?.text}
                  onSelect={setFolder}
                  optional
                  placeholder={t('Where should this display be filed?')}
                />
              </div>
            </>
          )}

          {canAuthorise && canApplySettings && (
            <Switch
              label={t('Authorize Display')}
              checked={authorise}
              onChange={setAuthorise}
              helpText={licenceHelpText()}
            />
          )}

          {!canApplySettings && (
            <p className="text-sm text-gray-500">
              {t(
                'Your display will be added with a default name. An administrator can rename it and choose a folder for you.',
              )}
            </p>
          )}

          <div className="rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 text-sm text-xibo-blue-700">
            <button
              type="button"
              className="flex w-full items-center gap-1 p-3 text-left font-medium"
              onClick={() => setShowDetail((prev) => !prev)}
              aria-expanded={showDetail}
            >
              {showDetail ? (
                <ChevronDown className="h-4 w-4 shrink-0" aria-hidden="true" />
              ) : (
                <ChevronRight className="h-4 w-4 shrink-0" aria-hidden="true" />
              )}
              {t('How connecting with a code works')}
            </button>

            {showDetail && (
              <div className="flex flex-col gap-2 px-3 pb-3">
                <p>
                  {t(
                    'After submitting this form with valid code, your CMS Address and Key will be sent and stored in the temporary storage in our Authentication Service.',
                  )}
                </p>
                <p>
                  {t(
                    'The Player linked to the submitted code, will make regular calls to our Authentication Service to retrieve the CMS details and configure itself with them. Your details are removed from the temporary storage once the Player is configured.',
                  )}
                </p>
                <p>
                  {t(
                    'Please note that your CMS needs to make a successful call to our Authentication Service for this feature to work.',
                  )}
                </p>
              </div>
            )}
          </div>
        </div>
      )}
    </Modal>
  );
}
