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
import {
  ArrowLeft,
  Check,
  Eye,
  EyeOff,
  Info,
  Loader2,
  Minimize2,
  Monitor,
  Server,
  TriangleAlert,
  X,
} from 'lucide-react';
import { useEffect, useRef, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { useApplyDisplaySettings } from '../hooks/useApplyDisplaySettings';
import { useConnectWatcher } from '../hooks/useConnectWatcher';
import { readPendingCode, writePendingCode } from '../hooks/usePendingConnect';

import AddDisplayModeChooser, { type ConnectMode } from './AddDisplayModeChooser';
import AddDisplaySuccessModal, { type SubmittedDisplay } from './AddDisplaySuccessModal';

import { notify } from '@/components/ui/Notification';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import Switch from '@/components/ui/forms/Switch';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import DisplayGroupSelect from '@/pages/Administration/Connectors/components/DisplayGroupSelect';
import {
  addDisplayViaCode,
  fetchConnectCode,
  fetchConnectDetails,
  fetchConnectStatus,
  fetchDisplays,
  fetchLicenceUsage,
  type ConnectDetails,
  type LicenceUsage,
} from '@/services/displaysApi';
import type { Display } from '@/types/display';
import { hasFeature } from '@/utils/permissions';

export interface AddDisplayPrefill {
  code: string;
  displayName?: string;
}

/** 'choose' is the mode-selection screen; the others are the two connection forms. */
type Step = 'choose' | ConnectMode;

interface AddDisplayModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onAdded?: () => void;
  /** Populated when the customer arrived from a deep link, e.g. a scanned QR code. */
  prefill?: AddDisplayPrefill | null;
  /** Called with the new display once the Player has connected and its settings are applied. */
  onManage?: (display: Display) => void;
  /** Called when the user minimizes the modal during the waiting state. */
  onMinimize?: (displayName: string) => void;
}

/** Read-only credential field with optional hide/reveal toggle. */
function ReadOnlyField({
  label,
  value,
  secret = false,
}: {
  label: string;
  value: string;
  secret?: boolean;
}) {
  const { t } = useTranslation();
  const [hidden, setHidden] = useState(false);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!secret || hidden) return;

    timerRef.current = setTimeout(() => setHidden(true), 15000);
    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [secret, hidden]);

  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-xs font-semibold uppercase text-gray-500">{label}</span>
      <div className="flex items-center justify-between gap-2 rounded-lg bg-gray-100 px-4 py-3">
        <span className="text-md font-medium text-gray-800 select-all break-all">
          {hidden ? '•'.repeat(value.length) : value}
        </span>
        {secret && (
          <button
            type="button"
            onClick={() => setHidden((prev) => !prev)}
            aria-label={hidden ? t('Show') : t('Hide')}
            className="shrink-0 text-gray-400 hover:text-gray-600 transition-colors"
          >
            {hidden ? (
              <EyeOff size={18} aria-hidden="true" />
            ) : (
              <Eye size={18} aria-hidden="true" />
            )}
          </button>
        )}
      </div>
    </div>
  );
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

  const defaultDisplayFolder =
    Number(user?.settings?.DISPLAY_DEFAULT_FOLDER) || (user?.homeFolderId ?? 1);

  const [step, setStep] = useState<Step>('choose');
  const [userCode, setUserCode] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [folderId, setFolderId] = useState<number | null>(defaultDisplayFolder);
  const [folderText, setFolderText] = useState<string | null>(null);
  const [displayGroupId, setDisplayGroupId] = useState<number | null>(null);
  const [displayGroupText, setDisplayGroupText] = useState('');
  const [authorise, setAuthorise] = useState(true);
  const [apiError, setApiError] = useState<string | undefined>();

  const [licence, setLicence] = useState<LicenceUsage | null>(null);
  const [connect, setConnect] = useState<ConnectDetails | null>(null);
  /** One-time code appended to the CMS key the operator copies into the Player. */
  const [connectCode, setConnectCode] = useState<string | null>(null);
  /** The activation code actually submitted, which the CMS matches at registration. */
  const [submittedCode, setSubmittedCode] = useState<string | null>(null);
  const [submitted, setSubmitted] = useState<SubmittedDisplay | null>(null);
  /** The Player has registered. In manual mode this only unlocks the form. */
  const [detected, setDetected] = useState<Display | null>(null);
  /** The flow is finished and the summary can be shown. */
  const [adopted, setAdopted] = useState<Display | null>(null);

  // Both routes identify their Player by a code the CMS matched at registration, rather than by
  // watching for whatever registers next. Manual mode uses a code the CMS issued; activation mode
  // uses the code the operator typed, which the Player carries back on its server key.
  const watched = useConnectWatcher(step === 'manual' ? connectCode : submittedCode);
  const { apply, error: applyError } = useApplyDisplaySettings();

  // Authorising is available to the same audience as this form: displays.add also gates the
  // toggleAuthorise endpoint, and the CMS accepts it on the same basis at registration.
  const canAuthorise = hasFeature(user, 'displays.add');

  // Naming and filing a display is an edit, which is a different permission from adding one.
  // The CMS only honours these fields from users who hold it.
  const canApplySettings = hasFeature(user, 'displays.modify');

  // Derived UI phases — show loading as soon as the user clicks Add (isPending)
  // and keep it while polling for the new display (submitted but not yet adopted).
  // Stops as soon as the watcher reports the code expired, or the display turned up but the
  // account can't see it - both are terminal outcomes, not "still waiting".
  const isWaiting =
    step === 'code' &&
    (isPending || (submitted !== null && !adopted && watched.state !== 'expired' && !apiError));

  /** Manual mode is still waiting for the coded Player to reach the CMS. */
  const isConnecting = step === 'manual' && watched.state === 'waiting';

  /**
   * Manual configuration cannot be saved until the Player has registered, because until then
   * there is no display to save the chosen settings against.
   */
  const isConnected = step === 'manual' && watched.state === 'connected';

  /** Manual flow completed: display saved successfully. */
  const isManualDone = step === 'manual' && adopted !== null && submitted !== null;

  const resetForm = () => {
    // A deep-linked code means the customer already chose their route.
    setStep(prefill?.code ? 'code' : 'choose');
    setUserCode(prefill?.code ?? '');
    setDisplayName(prefill?.displayName ?? '');
    setFolderId(defaultDisplayFolder);
    setFolderText(null);
    setDisplayGroupId(null);
    setDisplayGroupText('');
    setAuthorise(true);
    setApiError(undefined);
    setConnect(null);
    setConnectCode(null);
    setSubmittedCode(null);
    setSubmitted(null);
    setDetected(null);
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
    if (isOpen && !submitted && !isPending && !apiError && step === 'choose') {
      resetForm();
    }
  }, [isOpen, prefill?.code, prefill?.displayName, defaultDisplayFolder]);

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

  /** The summary shown once the flow completes. */
  const summarise = (): SubmittedDisplay => ({
    code: userCode.trim(),
    displayName: displayName.trim(),
    folderText: folderText ?? '',
    displayGroup: displayGroupText,
    authorise: canAuthorise && authorise,
    licenceText: licenceHelpText(),
  });

  /**
   * Enter manual configuration: fetch the address and key for the operator to copy into the
   * Player, and note where the display list stands so the Player can be spotted when it arrives.
   */
  const startManual = () => {
    setStep('manual');
    setApiError(undefined);

    startTransition(async () => {
      try {
        const details = await fetchConnectDetails();
        setConnect(details);

        // A code left over from a previous page load is still live on the CMS, and the Player may
        // already be carrying it. Pick it back up rather than stranding it behind a fresh one.
        // Only a manual code can be resumed here. An activation code is a different thing entirely -
        // resuming one would offer it as the CMS key, which is meaningless for a manual setup.
        const pending = readPendingCode();

        if (pending?.mode === 'manual') {
          const status = await fetchConnectStatus(pending.code);

          if (!status.expired) {
            setConnectCode(pending.code);
            return;
          }

          writePendingCode(null);
        }

        // The code is issued before the form is usable: it is what the operator types into the
        // Player, and what lets the CMS recognise that Player as this form's.
        const issued = await fetchConnectCode();

        setConnectCode(issued.code);
        writePendingCode({ code: issued.code, mode: 'manual' });
      } catch (err: unknown) {
        setApiError(
          isAxiosError(err) && err.response?.data?.message
            ? err.response.data.message
            : t('Could not read the CMS connection details.'),
        );
      }
    });
  };

  /**
   * Adopt the name the operator gave the Player as the starting point for the form.
   *
   * They have just typed it on the device, so it is almost always what they want here too. Only
   * fills a blank field: whatever they typed in the CMS wins over whatever is on the Player.
   */
  useEffect(() => {
    if (
      step === 'manual' &&
      watched.state === 'connected' &&
      watched.displayName &&
      displayName.trim() === ''
    ) {
      setDisplayName(watched.displayName);
    }
  }, [step, watched.state, watched.displayName]);

  const handleSelectMode = (mode: ConnectMode) => {
    if (mode === 'manual') {
      startManual();
      return;
    }

    setStep('code');
  };

  /** Leave a connection form and go back to the choice of route, abandoning any polling. */
  const handleBack = () => {
    setStep('choose');
    // Deliberately abandoning this attempt, so the code should not be resumed later.
    writePendingCode(null);
    setApiError(undefined);
    setConnect(null);
    setConnectCode(null);
    setSubmittedCode(null);
    setDetected(null);
  };

  const handleSubmit = () => {
    startTransition(async () => {
      setApiError(undefined);

      try {
        // The CMS caches these settings against the code and applies them itself when the
        // Player registers - nothing further needs to be sent once the display appears.
        await addDisplayViaCode({
          userCode: userCode.trim(),
          displayName: canApplySettings ? displayName.trim() : undefined,
          folderId: canApplySettings ? folderId : undefined,
          displayGroupId: canApplySettings ? displayGroupId : undefined,
          authorised: canAuthorise && authorise,
        });

        setSubmitted(summarise());

        // The CMS recorded this code against whichever Player presents it, so watching the code is
        // an exact match rather than a guess at which display appeared next.
        setSubmittedCode(userCode.trim());

        // Remembered so that closing this form does not lose track of the Player: a background
        // watcher picks it up and says when it arrives.
        writePendingCode({ code: userCode.trim(), mode: 'code' });

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
          notify.error(message, {
            action: {
              label: t('Try Again'),
              onClick: () => {
                resetForm();
              },
            },
          });
          onClose();
        }
      }
    });
  };

  /**
   * Save the chosen settings against the display the Player just created.
   *
   * Manual configuration carries no activation code, so the CMS had nothing to correlate the
   * registration with and applied none of these settings itself. They are applied from here.
   */
  const handleManualSubmit = () => {
    if (!watched.displayId) {
      return;
    }

    startTransition(async () => {
      setApiError(undefined);

      try {
        const { rows } = await fetchDisplays({
          start: 0,
          length: 1,
          displayId: watched.displayId ?? undefined,
        });
        const target = rows[0];

        if (!target) {
          // The Player has registered with the CMS at this point - it just isn't visible in this
          // query, which for a non-admin account almost always means their user group has no view
          // permission on the folder the display landed in, rather than the display being missing.
          setApiError(
            t(
              'The display connected, but your account does not have permission to view it. Ask an administrator to grant your user group access to its folder.',
            ),
          );
          return;
        }

        const updated = await apply(target, {
          displayName: displayName.trim() || target.display,
          folderId,
          displayGroupId,
          authorise: canAuthorise && authorise,
        });

        // The display exists whether or not the edit landed, so always finish the flow. The
        // summary reports what was asked for, and applyError explains anything that did not stick.
        // The code has done its job; a later Add Display should start clean.
        writePendingCode(null);

        setSubmitted(summarise());
        setAdopted(updated ?? target);
        onAdded?.();
      } catch (err: unknown) {
        setApiError(
          isAxiosError(err) && err.response?.data?.message
            ? err.response.data.message
            : t('An unexpected error occurred.'),
        );
      }
    });
  };

  /**
   * Adopt a detected display. In activation-code mode the CMS already applied the chosen name,
   * folder and authorisation when the Player registered, so this is only bookkeeping.
   */
  const adopt = (display: Display) => {
    // This form saw it through, so there is nothing left for the background watcher to report.
    writePendingCode(null);

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
      onClose();
    }
  };

  // Activation-code mode finishes as soon as the CMS confirms which Player took the code: the
  // settings were applied server side at registration, so nothing else is left to do.
  useEffect(() => {
    if (step !== 'code' || watched.state !== 'connected' || !watched.displayId || detected) {
      return;
    }

    void (async () => {
      const { rows } = await fetchDisplays({
        start: 0,
        length: 1,
        displayId: watched.displayId ?? undefined,
      });
      const target = rows[0];

      if (target) {
        setDetected(target);
        adopt(target);
        return;
      }

      // The Player has registered at this point - it just isn't visible in this query, which for
      // a non-admin account almost always means their user group has no view permission on the
      // folder the display landed in, rather than the display being missing.
      const message = t(
        'The display connected, but your account does not have permission to view it. Ask an administrator to grant your user group access to its folder.',
      );

      setApiError(message);

      if (!isOpenRef.current) {
        notify.error(message);
        onClose();
      }
    })();
  }, [step, watched.state, watched.displayId, detected]);

  // Toast notifications for detection outcomes — only when minimized
  useEffect(() => {
    if (!isOpenRef.current && watched.state === 'expired') {
      notify.error(t('Connection timed out.'), {
        action: {
          label: t('Try Again'),
          onClick: () => resetForm(),
        },
      });
      onClose();
    }
  }, [watched.state, isOpen]);

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

  // Manual configuration has no code to enter, so only the name is required.
  const canSubmitManual = canApplySettings ? displayName.trim() !== '' : true;

  /** The footer buttons differ per step, so build them rather than branching inline. */
  const buildActions = () => {
    if (adopted) {
      return [];
    }

    if (step === 'choose') {
      return [
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary' as const,
        },
      ];
    }

    if (step === 'manual') {
      const manualActions = [];

      if (!isConnected) {
        manualActions.push({
          label: t('Back'),
          onClick: handleBack,
          variant: 'secondary' as const,
          leftIcon: ArrowLeft,
          className: 'mr-auto',
        });
      }

      manualActions.push(
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary' as const,
          disabled: isConnecting,
        },
        {
          label: t('Save'),
          onClick: handleManualSubmit,
          disabled: !isConnected || !canSubmitManual,
        },
      );

      return manualActions;
    }

    const actions = [];

    if (!isWaiting) {
      actions.push({
        label: t('Back'),
        onClick: handleBack,
        variant: 'secondary' as const,
        leftIcon: ArrowLeft,
        className: 'mr-auto',
      });
    }

    actions.push(
      {
        label: t('Cancel'),
        onClick: onClose,
        variant: 'secondary' as const,
        disabled: isWaiting,
      },
      {
        label: t('Add'),
        onClick: handleSubmit,
        // Once submitted, an expired code can't be retried as-is - the operator needs a fresh one.
        disabled: isPending || isWaiting || !canSubmit || watched.state === 'expired',
        leftIcon: isPending || isWaiting ? Loader2 : undefined,
        className: isPending || isWaiting ? '[&_svg]:animate-spin' : undefined,
      },
    );

    return actions;
  };

  const canMinimize = isWaiting;

  return (
    <>
      {/* Success modal — shown when the flow has completed */}
      {adopted && submitted && (
        <AddDisplaySuccessModal
          submitted={submitted}
          display={adopted}
          onClose={onClose}
          onAddAnother={resetForm}
          onManage={handleManage}
        />
      )}

      <Modal
        onClose={onClose}
        isOpen={isOpen && !(adopted && submitted)}
        ariaLabel={t('Add Display')}
        error={apiError ?? applyError}
        size="sm"
        actions={buildActions()}
      >
        {/* Custom header with title, info tooltip, and close button */}
        <div className="shrink-0 flex items-center justify-between px-8 pt-8 pb-3">
          <h2 className="text-lg font-semibold">{t('Add Display')}</h2>
          <div className="flex items-center gap-2">
            {step !== 'choose' && (
              <>
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
              </>
            )}
            <button
              type="button"
              aria-label={canMinimize ? t('Minimize') : t('Close')}
              onClick={() => (canMinimize && onMinimize ? onMinimize(displayName) : onClose())}
              className="size-6 shrink-0 text-gray-500 cursor-pointer hover:text-gray-600 transition-colors"
            >
              {canMinimize ? (
                <Minimize2 className="w-4 h-4" aria-hidden="true" />
              ) : (
                <X className="w-4 h-4" aria-hidden="true" />
              )}
            </button>
          </div>
        </div>

        {step === 'choose' ? (
          <AddDisplayModeChooser onSelect={handleSelectMode} />
        ) : (
          <div className="px-6 py-4 flex flex-col gap-5">
            {/* Waiting panel — activation code mode */}
            {isWaiting && (
              <div
                className="flex flex-col items-center gap-4 rounded-lg bg-slate-50 p-6"
                role="status"
              >
                <div className="flex items-center gap-4">
                  <div className="flex flex-col items-center gap-1">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full border bg-xibo-blue-50 border-xibo-blue-200">
                      <Monitor className="h-6 w-6 text-xibo-blue-500" />
                    </div>
                    <span className="text-xs text-gray-500">{t('Player')}</span>
                  </div>

                  <div className="flex items-center gap-1.5 pb-4">
                    <span className="h-1.5 w-1.5 rounded-full bg-gray-300 animate-[pulse_1.4s_ease-in-out_infinite]" />
                    <span className="h-1.5 w-1.5 rounded-full bg-gray-300 animate-[pulse_1.4s_ease-in-out_0.2s_infinite]" />
                    <span className="h-1.5 w-1.5 rounded-full bg-gray-300 animate-[pulse_1.4s_ease-in-out_0.4s_infinite]" />
                  </div>

                  <div className="flex flex-col items-center gap-1">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full border bg-teal-50 border-teal-200">
                      <Server className="h-6 w-6 text-teal-500" />
                    </div>
                    <span className="text-xs text-gray-500">{t('CMS')}</span>
                  </div>
                </div>

                <div className="text-center">
                  <p className="text-sm font-semibold text-gray-800">
                    {t('Waiting for display to connect...')}
                  </p>
                  <p className="text-xs text-gray-500 mt-1">
                    {t('Verifying your activation code. Please do not close this window.')}
                  </p>
                </div>
              </div>
            )}

            {step === 'code' && (
              <TextInput
                name="user_code"
                label={t('Activation Code') + '*'}
                placeholder={t('Enter code')}
                onChange={(value) => setUserCode(value)}
                helpText={t('Code shown on the player screen')}
                value={userCode}
                disabled={isWaiting}
              />
            )}

            {step === 'manual' && (
              <>
                {/* Connection animation — grey while waiting, colored once connected */}
                {(isConnecting || isConnected || isManualDone) && (
                  <div
                    className="flex flex-col items-center gap-4 rounded-lg bg-slate-50 p-6"
                    role="status"
                  >
                    {isConnected || isManualDone ? (
                      <div className="flex items-center justify-center h-14 w-14 rounded-full bg-teal-50 border-2 border-teal-200">
                        <Check className="h-7 w-7 text-teal-500" strokeWidth={2.5} />
                      </div>
                    ) : (
                      <div className="flex items-center gap-4">
                        <div className="flex flex-col items-center gap-1">
                          <div className="flex h-12 w-12 items-center justify-center rounded-full border bg-xibo-blue-50 border-xibo-blue-200">
                            <Monitor className="h-6 w-6 text-xibo-blue-500" />
                          </div>
                          <span className="text-xs text-gray-500">{t('Player')}</span>
                        </div>

                        <div className="flex items-center gap-1.5 pb-4">
                          <span className="h-1.5 w-1.5 rounded-full bg-gray-300 animate-[pulse_1.4s_ease-in-out_infinite]" />
                          <span className="h-1.5 w-1.5 rounded-full bg-gray-300 animate-[pulse_1.4s_ease-in-out_0.2s_infinite]" />
                          <span className="h-1.5 w-1.5 rounded-full bg-gray-300 animate-[pulse_1.4s_ease-in-out_0.4s_infinite]" />
                        </div>

                        <div className="flex flex-col items-center gap-1">
                          <div className="flex h-12 w-12 items-center justify-center rounded-full border bg-teal-50 border-teal-200">
                            <Server className="h-6 w-6 text-teal-500" />
                          </div>
                          <span className="text-xs text-gray-500">{t('CMS')}</span>
                        </div>
                      </div>
                    )}

                    <div className="text-center">
                      <p className="text-sm font-semibold text-gray-800">
                        {isManualDone
                          ? t('Display added successfully!')
                          : isConnected
                            ? t('Display connected!')
                            : t('Waiting for display to connect...')}
                      </p>
                      <p className="text-xs text-gray-500 mt-1">
                        {isManualDone
                          ? t('Your display settings have been saved.')
                          : isConnected
                            ? t('Connected to {{display}}. Save to apply your settings.', {
                                display: watched.displayName ?? '',
                              })
                            : t(
                                'Press Connect on the Player once you have entered the details below.',
                              )}
                      </p>
                    </div>
                  </div>
                )}

                <div className="flex flex-col gap-3 rounded-lg border border-gray-100 p-4">
                  {connect && connectCode ? (
                    <>
                      <p className="text-sm text-gray-800">
                        {t(
                          'Enter these details into the Player, then press Connect on the Player.',
                        )}
                      </p>
                      <ReadOnlyField label={t('CMS URL')} value={connect.cmsAddress} />
                      <ReadOnlyField
                        label={t('Secret Key')}
                        value={`${connect.cmsKey}||${connectCode}`}
                        secret
                      />
                      <p className="text-xs text-gray-500">
                        {t(
                          'Name the Player as you like - that name appears here once it connects.',
                        )}
                      </p>
                    </>
                  ) : (
                    <div className="flex items-center justify-center gap-2 py-4">
                      <Loader2 className="h-4 w-4 text-gray-400 animate-spin" />
                      <p className="text-xs text-gray-500">
                        {t('Retrieving your CMS connection details...')}
                      </p>
                    </div>
                  )}
                </div>
              </>
            )}

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

            <DisplayGroupSelect
              label={t('Display Group')}
              value={displayGroupId}
              valueLabel={displayGroupText}
              onChange={(id, label) => {
                setDisplayGroupId(id);
                setDisplayGroupText(label);
              }}
              optional
              excludeDynamic
            />

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

            {/* Waiting panel — activation code mode */}
            {isWaiting && (
              <div
                className="flex flex-col items-center gap-4 rounded-lg bg-slate-50 p-6"
                role="status"
              >
                <div className="flex items-center gap-4">
                  <div className="flex flex-col items-center gap-1">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 border border-gray-200">
                      <Monitor className="h-6 w-6 text-gray-400" />
                    </div>
                    <span className="text-xs text-gray-500">{t('Player')}</span>
                  </div>

                  <div className="flex items-center gap-1.5 pb-4">
                    <span className="h-1.5 w-1.5 rounded-full bg-gray-300 animate-[pulse_1.4s_ease-in-out_infinite]" />
                    <span className="h-1.5 w-1.5 rounded-full bg-gray-300 animate-[pulse_1.4s_ease-in-out_0.2s_infinite]" />
                    <span className="h-1.5 w-1.5 rounded-full bg-gray-300 animate-[pulse_1.4s_ease-in-out_0.4s_infinite]" />
                  </div>

                  <div className="flex flex-col items-center gap-1">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 border border-gray-200">
                      <Server className="h-6 w-6 text-gray-400" />
                    </div>
                    <span className="text-xs text-gray-500">{t('CMS')}</span>
                  </div>
                </div>

                <div className="text-center">
                  <p className="text-sm font-semibold text-gray-800">
                    {t('Waiting for display to connect...')}
                  </p>
                  <p className="text-xs text-gray-500 mt-1">
                    {t('Verifying your activation code. Please do not close this window.')}
                  </p>
                </div>
              </div>
            )}

            {(step === 'manual' || step === 'code') && watched.state === 'expired' && (
              <div
                className="flex items-center justify-between gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800"
                role="alert"
              >
                <div className="flex items-start gap-2">
                  <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                  <span>{t('Your secret key has expired for security reasons.')}</span>
                </div>
                <button
                  type="button"
                  onClick={startManual}
                  className="shrink-0 text-xs font-semibold text-yellow-800 hover:text-yellow-900 underline"
                >
                  {t('Refresh')}
                </button>
              </div>
            )}
          </div>
        )}
      </Modal>
    </>
  );
}
