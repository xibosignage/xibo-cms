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
  FloatingPortal,
  flip,
  offset,
  shift,
  useDismiss,
  useFloating,
  useHover,
  useInteractions,
} from '@floating-ui/react';
import { isAxiosError } from 'axios';
import { ChevronDown, Info, Loader, Loader2, Minimize2, X } from 'lucide-react';
import { useEffect, useRef, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { useNewDisplayDetector } from '../hooks/useNewDisplayDetector';

import AddDisplaySuccessModal, { type SubmittedDisplay } from './AddDisplaySuccessModal';

import { notify } from '@/components/ui/Notification';
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
  /** Called when the user minimizes the modal during the waiting state. */
  onMinimize?: () => void;
}

export default function AddDisplayModal({
  isOpen = true,
  onClose,
  onAdded,
  prefill = null,
  onManage,
  onMinimize,
}: AddDisplayModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const [isPending, startTransition] = useTransition();

  // Ref tracks current isOpen so async callbacks always read the latest value
  const isOpenRef = useRef(isOpen);
  isOpenRef.current = isOpen;

  const [userCode, setUserCode] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [folderId, setFolderId] = useState<number | null>(null);
  const [folderText, setFolderText] = useState<string | null>(null);
  const [authorise, setAuthorise] = useState(true);
  const [apiError, setApiError] = useState<string | undefined>();

  const [licence, setLicence] = useState<LicenceUsage | null>(null);
  const [watermark, setWatermark] = useState<number | null>(null);
  const [submitted, setSubmitted] = useState<SubmittedDisplay | null>(null);
  const [adopted, setAdopted] = useState<Display | null>(null);

  const { state: detectState, candidates } = useNewDisplayDetector(watermark);

  // Authorising is available to the same audience as this form: displays.add also gates the
  // toggleAuthorise endpoint, and the CMS accepts it on the same basis at registration.
  const canAuthorise = hasFeature(user, 'displays.add');

  // Naming and filing a display is an edit, which is a different permission from adding one.
  // The CMS only honours these fields from users who hold it, so hide them from everyone else.
  const canApplySettings = hasFeature(user, 'displays.modify');

  // Derived UI phases — show loading as soon as the user clicks Add (isPending)
  // and keep it while polling for the new display (submitted but not yet adopted).
  const isWaiting = isPending || (submitted !== null && !adopted);

  const resetForm = () => {
    setUserCode(prefill?.code ?? '');
    setDisplayName(prefill?.displayName ?? '');
    setFolderId(null);
    setFolderText(null);
    setAuthorise(true);
    setApiError(undefined);
    setWatermark(null);
    setSubmitted(null);
    setAdopted(null);
  };

  // Info tooltip
  const [isTooltipOpen, setIsTooltipOpen] = useState(false);
  const {
    refs: tooltipRefs,
    floatingStyles: tooltipStyles,
    context: tooltipContext,
  } = useFloating({
    open: isTooltipOpen,
    onOpenChange: setIsTooltipOpen,
    placement: 'bottom-start',
    middleware: [offset(8), flip(), shift()],
  });
  const tooltipHover = useHover(tooltipContext, { delay: { open: 200, close: 100 } });
  const tooltipDismiss = useDismiss(tooltipContext);
  const { getReferenceProps: getTooltipRefProps, getFloatingProps: getTooltipFloatingProps } =
    useInteractions([tooltipHover, tooltipDismiss]);

  useEffect(() => {
    if (isOpen && !submitted) {
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

        // The CMS caches these settings against the code and applies them itself when the
        // Player registers - nothing further needs to be sent once the display appears.
        await addDisplayViaCode({
          userCode: userCode.trim(),
          displayName: canApplySettings ? displayName.trim() : undefined,
          folderId: canApplySettings ? folderId : undefined,
          authorised: canAuthorise && authorise,
        });

        setSubmitted({
          code: userCode.trim(),
          displayName: displayName.trim(),
          folderText: folderText ?? '',
          authorise: canAuthorise && authorise,
          licenceText: licenceHelpText(),
        });

        if (highestSeenId !== null) {
          setWatermark(highestSeenId);
        }

        // Let the grid pick the display up as soon as it registers.
        onAdded?.();
      } catch (err: unknown) {
        let message = t('An unexpected error occurred.');
        if (isAxiosError(err) && err.response?.data?.message) {
          message = err.response.data.message;
        } else if (err instanceof Error) {
          message = err.message;
        }
        setApiError(message);
        if (!isOpenRef.current) {
          notify.error(message);
        }
      }
    });
  };

  /**
   * Adopt a detected display. The CMS already applied the chosen name, folder and authorisation
   * when the Player registered, so adoption is only bookkeeping for the Manage CTA.
   */
  const adopt = (display: Display) => {
    setAdopted(display);
    onAdded?.();
    if (!isOpenRef.current) {
      notify.success(t('Display has been added successfully.'), {
        action: {
          label: t('Manage'),
          onClick: () => {
            onManage?.(display);
          },
        },
      });
    }
  };

  // Exactly one new display means it is ours. More than one is handled by the picker.
  useEffect(() => {
    const only = candidates[0];

    if (detectState === 'found' && candidates.length === 1 && only && !adopted) {
      adopt(only);
    }
  }, [detectState, candidates, adopted]);

  // Toast notifications for detection outcomes — only when minimized
  useEffect(() => {
    if (!isOpenRef.current && detectState === 'timedOut') {
      notify.error(t('Connection timed out.'), {
        action: {
          label: t('Try Again'),
          onClick: () => resetForm(),
        },
      });
    }
  }, [detectState, isOpen]);

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

  return (
    <>
      {/* Success modal — shown when the display has been detected */}
      {adopted && submitted && (
        <AddDisplaySuccessModal
          submitted={submitted}
          onClose={onClose}
          onAddAnother={resetForm}
          onManage={handleManage}
        />
      )}

      <Modal
        onClose={onClose}
        isOpen={isOpen}
        error={apiError}
        size="sm"
        actions={
          adopted
            ? []
            : [
                {
                  label: t('Cancel'),
                  onClick: onClose,
                  variant: 'secondary' as const,
                },
                {
                  label: t('Add'),
                  onClick: handleSubmit,
                  disabled: isPending || isWaiting || !canSubmit,
                  leftIcon: isPending || isWaiting ? Loader2 : undefined,
                  className: isPending || isWaiting ? '[&_svg]:animate-spin' : undefined,
                },
              ]
        }
      >
        {/* Custom header with title, info tooltip, and close button */}
        <div className="shrink-0 flex items-center justify-between px-8 pt-8 pb-3">
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-semibold">{t('Add Display')}</h2>
            <button
              ref={tooltipRefs.setReference}
              {...getTooltipRefProps()}
              type="button"
              className="text-xibo-blue-500 hover:text-xibo-blue-600 transition-colors"
              aria-label={t('More information')}
            >
              <Info size={20} />
            </button>
            <FloatingPortal>
              {isTooltipOpen && (
                <div
                  ref={tooltipRefs.setFloating}
                  style={tooltipStyles}
                  {...getTooltipFloatingProps()}
                  className="z-9999 bg-white shadow-xl rounded-lg border border-gray-100 p-4 max-w-sm text-sm text-gray-600 flex flex-col gap-2"
                >
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
            </FloatingPortal>
          </div>
          <button
            type="button"
            aria-label={isWaiting ? t('Minimize') : t('Close')}
            onClick={isWaiting && onMinimize ? onMinimize : onClose}
            className="size-6 shrink-0 text-gray-500 cursor-pointer hover:text-gray-600 transition-colors"
          >
            {isWaiting ? (
              <Minimize2 className="w-4 h-4" aria-hidden="true" />
            ) : (
              <X className="w-4 h-4" aria-hidden="true" />
            )}
          </button>
        </div>

        {/* Form fields */}
        <div className="px-6 py-4 flex flex-col gap-5">
          <TextInput
            name="user_code"
            label={t('Activation Code') + '*'}
            placeholder={t('Enter code')}
            onChange={(value) => setUserCode(value)}
            helpText={t('Code shown on the player screen')}
            value={userCode}
            disabled={isWaiting}
          />

          <TextInput
            name="display_name"
            label={t('Display Name') + '*'}
            placeholder={t('Enter Display Name')}
            onChange={(value) => setDisplayName(value)}
            value={displayName}
            disabled={isWaiting}
          />

          <SelectFolder
            selectedId={folderId}
            selectedText={folderText}
            optional
            onSelect={(folder) => {
              setFolderId(folder ? folder.id : null);
              setFolderText(folder ? folder.text : null);
            }}
          />

          {/* Display Group — placeholder dropdown (to be connected to API later) */}
          <div className="flex flex-col gap-1 w-full">
            <label className="flex items-center justify-between text-sm font-semibold text-gray-500 leading-4.5">
              <span>{t('Display Group')}</span>
              <span className="text-xs font-normal text-gray-500">{t('Optional')}</span>
            </label>
            <div className="h-11.25 border border-gray-200 rounded-lg flex items-center bg-white cursor-default">
              <span className="py-2 px-3 flex-1 text-sm text-gray-400 truncate">
                {t('Select Display Group')}
              </span>
              <div className="pr-3 text-gray-500 shrink-0">
                <ChevronDown size={14} />
              </div>
            </div>
          </div>

          {/* Authorise automatically */}
          <div className="flex flex-col gap-1.5 rounded-lg border border-gray-100 bg-gray-50 p-4">
            <div className="flex items-center justify-between">
              <span className="text-sm font-semibold text-gray-700">
                {t('Authorise automatically')}
              </span>
              <div className="shrink-0">
                <Switch
                  checked={authorise}
                  onChange={setAuthorise}
                  hideOnOff
                  size="sm"
                  disabled={isWaiting}
                />
              </div>
            </div>
            <p className="text-xs text-gray-400">{licenceHelpText()}</p>
          </div>

          {/* Waiting panel */}
          {isWaiting && (
            <div className="flex flex-col items-center gap-3 rounded-lg bg-slate-50 p-6 text-center">
              <div className="bg-xibo-blue-100 w-9.5 h-9.5 flex justify-center items-center rounded-full">
                <Loader className="h-6 w-6 text-xibo-blue-400 animate-spin" />
              </div>
              <p className="text-md font-semibold text-gray-800">
                {t('Waiting for display to connect...')}
              </p>
              <p className="text-sm text-gray-500">
                {t('Verifying your activation code. Please do not close this window.')}
              </p>
            </div>
          )}
        </div>
      </Modal>
    </>
  );
}
