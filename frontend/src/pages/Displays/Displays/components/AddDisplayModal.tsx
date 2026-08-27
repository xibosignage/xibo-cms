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
import { Check, Copy, Info, Loader, Loader2, Minimize2, TriangleAlert, X } from 'lucide-react';
import { useEffect, useRef, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { useApplyDisplaySettings } from '../hooks/useApplyDisplaySettings';
import { useManualConnectWatcher } from '../hooks/useManualConnectWatcher';
import { useNewDisplayDetector } from '../hooks/useNewDisplayDetector';

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
  fetchDisplays,
  fetchHighestDisplayId,
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
  onMinimize?: () => void;
}

/** Read-only CMS credential with a copy button, for the manual configuration screen. */
function CopyableValue({ label, value }: { label: string; value: string }) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Clipboard access is unavailable over plain http, and can be blocked outright. The value is
      // selectable either way, so let the operator copy it by hand rather than claiming we did.
      setCopied(false);
    }
  };

  return (
    <TextInput
      name={label}
      label={label}
      value={value}
      readOnly
      suffix={
        <button
          type="button"
          onClick={copy}
          aria-label={copied ? t('Copied') : t('Copy')}
          className="p-3 text-gray-400 hover:text-gray-600"
        >
          {copied ? (
            <Check size={16} className="text-teal-500" aria-hidden="true" />
          ) : (
            <Copy size={16} aria-hidden="true" />
          )}
        </button>
      }
    />
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

  const [step, setStep] = useState<Step>('choose');
  const [userCode, setUserCode] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [folderId, setFolderId] = useState<number | null>(null);
  const [folderText, setFolderText] = useState<string | null>(null);
  const [displayGroupId, setDisplayGroupId] = useState<number | null>(null);
  const [displayGroupText, setDisplayGroupText] = useState('');
  const [authorise, setAuthorise] = useState(true);
  const [apiError, setApiError] = useState<string | undefined>();

  const [licence, setLicence] = useState<LicenceUsage | null>(null);
  const [connect, setConnect] = useState<ConnectDetails | null>(null);
  /** One-time code the operator types into the Player instead of a display name. */
  const [connectCode, setConnectCode] = useState<string | null>(null);
  const [watermark, setWatermark] = useState<number | null>(null);
  const [submitted, setSubmitted] = useState<SubmittedDisplay | null>(null);
  /** The Player has registered. In manual mode this only unlocks the form. */
  const [detected, setDetected] = useState<Display | null>(null);
  /** The flow is finished and the summary can be shown. */
  const [adopted, setAdopted] = useState<Display | null>(null);

  const { state: detectState, candidates } = useNewDisplayDetector(watermark);

  // Manual configuration identifies its Player by a one-time code rather than by watching for
  // whatever registers next, so it asks about that code alone.
  const manual = useManualConnectWatcher(step === 'manual' ? connectCode : null);
  const { apply, error: applyError } = useApplyDisplaySettings();

  // Authorising is available to the same audience as this form: displays.add also gates the
  // toggleAuthorise endpoint, and the CMS accepts it on the same basis at registration.
  const canAuthorise = hasFeature(user, 'displays.add');

  // Naming and filing a display is an edit, which is a different permission from adding one.
  // The CMS only honours these fields from users who hold it.
  const canApplySettings = hasFeature(user, 'displays.modify');

  // Derived UI phases — show loading as soon as the user clicks Add (isPending)
  // and keep it while polling for the new display (submitted but not yet adopted).
  const isWaiting = step === 'code' && (isPending || (submitted !== null && !adopted));

  /** Manual mode is still waiting for the coded Player to reach the CMS. */
  const isConnecting = step === 'manual' && manual.state === 'waiting';

  /**
   * Manual configuration cannot be saved until the Player has registered, because until then
   * there is no display to save the chosen settings against.
   */
  const isConnected = step === 'manual' && manual.state === 'connected';

  const resetForm = () => {
    // A deep-linked code means the customer already chose their route.
    setStep(prefill?.code ? 'code' : 'choose');
    setUserCode(prefill?.code ?? '');
    setDisplayName(prefill?.displayName ?? '');
    setFolderId(null);
    setFolderText(null);
    setDisplayGroupId(null);
    setDisplayGroupText('');
    setAuthorise(true);
    setApiError(undefined);
    setConnect(null);
    setConnectCode(null);
    setWatermark(null);
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
        // The code is issued before the form is usable: it is what the operator types into the
        // Player, and what lets the CMS recognise that Player as this form's.
        const [details, issued] = await Promise.all([fetchConnectDetails(), fetchConnectCode()]);

        setConnect(details);
        setConnectCode(issued.code);
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
    if (manual.state === 'connected' && manual.displayName && displayName.trim() === '') {
      setDisplayName(manual.displayName);
    }
  }, [manual.state, manual.displayName]);

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
    setApiError(undefined);
    setConnect(null);
    setConnectCode(null);
    setWatermark(null);
    setDetected(null);
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
          displayGroupId: canApplySettings ? displayGroupId : undefined,
          authorised: canAuthorise && authorise,
        });

        setSubmitted(summarise());

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
   * Save the chosen settings against the display the Player just created.
   *
   * Manual configuration carries no activation code, so the CMS had nothing to correlate the
   * registration with and applied none of these settings itself. They are applied from here.
   */
  const handleManualSubmit = () => {
    if (!manual.displayId) {
      return;
    }

    startTransition(async () => {
      setApiError(undefined);

      try {
        // The code check gives us an id, but applying settings needs the whole display: the edit
        // endpoint is a full replace, and a partial payload blanks the Player's hardware key.
        const { rows } = await fetchDisplays({
          start: 0,
          length: 1,
          displayId: manual.displayId ?? undefined,
        });
        const target = rows[0];

        if (!target) {
          setApiError(t('That display could no longer be found.'));
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

  // Exactly one new display means it is ours.
  useEffect(() => {
    const only = candidates[0];

    if (detectState === 'found' && candidates.length === 1 && only && !detected) {
      setDetected(only);

      // Only activation-code mode watches the display list; manual mode matches its one-time code.
      if (step === 'code') {
        adopt(only);
      }
    }
  }, [detectState, candidates, detected, step]);

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
      return [
        // Deliberately never disabled: the operator must always be able to change their mind,
        // even while Cancel is locked waiting for the Player.
        {
          label: t('Back'),
          onClick: handleBack,
          variant: 'secondary' as const,
        },
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary' as const,
          disabled: !isConnected,
        },
        {
          label: t('Save'),
          onClick: handleManualSubmit,
          disabled: isPending || !isConnected || !canSubmitManual,
          leftIcon: isPending ? Loader2 : undefined,
          className: isPending ? '[&_svg]:animate-spin' : undefined,
        },
      ];
    }

    return [
      {
        label: t('Back'),
        onClick: handleBack,
        variant: 'secondary' as const,
      },
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
    ];
  };

  const canMinimize = isWaiting || isConnecting;

  return (
    <>
      {/* Success modal — shown when the flow has completed */}
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
        ariaLabel={t('Add Display')}
        error={apiError ?? applyError}
        size="sm"
        actions={buildActions()}
      >
        {/* Custom header with title, info tooltip, and close button */}
        <div className="shrink-0 flex items-center justify-between px-8 pt-8 pb-3">
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-semibold">{t('Add Display')}</h2>
            {step === 'code' && (
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
          </div>
          <button
            type="button"
            aria-label={canMinimize ? t('Minimize') : t('Close')}
            onClick={canMinimize && onMinimize ? onMinimize : onClose}
            className="size-6 shrink-0 text-gray-500 cursor-pointer hover:text-gray-600 transition-colors"
          >
            {canMinimize ? (
              <Minimize2 className="w-4 h-4" aria-hidden="true" />
            ) : (
              <X className="w-4 h-4" aria-hidden="true" />
            )}
          </button>
        </div>

        {step === 'choose' ? (
          <AddDisplayModeChooser onSelect={handleSelectMode} />
        ) : (
          <div className="px-6 py-4 flex flex-col gap-5">
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
              <div className="flex flex-col gap-3 rounded-lg border border-gray-100 bg-gray-50 p-4">
                <p className="text-xs text-gray-500">
                  {t('Enter these details into the Player, then press Connect on the Player.')}
                </p>

                <CopyableValue label={t('CMS URL')} value={connect?.cmsAddress ?? ''} />
                {/* The one-time code rides on the end of the key, so the operator copies a single
                    value and the Player's own Display Name field stays free for a real name. */}
                <CopyableValue
                  label={t('CMS Secret Key')}
                  value={connect && connectCode ? `${connect.cmsKey}||${connectCode}` : ''}
                />

                <p className="text-xs text-gray-500">
                  {t('Name the Player as you like - that name appears here once it connects.')}
                </p>
              </div>
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

            {/* Connection status — manual configuration mode */}
            {isConnecting && (
              <div
                className="flex flex-col items-center gap-3 rounded-lg bg-slate-50 p-6 text-center"
                role="status"
              >
                <div className="bg-xibo-blue-100 w-9.5 h-9.5 flex justify-center items-center rounded-full">
                  <Loader className="h-6 w-6 text-xibo-blue-400 animate-spin" />
                </div>
                <p className="text-md font-semibold text-gray-800">
                  {t('Waiting for display to connect...')}
                </p>
                <p className="text-sm text-gray-500">
                  {t('Press Connect on the Player once you have entered the details above.')}
                </p>
              </div>
            )}

            {isConnected && (
              <div
                className="flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-800"
                role="status"
              >
                <Check className="h-4 w-4 shrink-0" aria-hidden="true" />
                <span>
                  {t('Connected to {{display}}. Save to apply your settings.', {
                    display: manual.displayName ?? '',
                  })}
                </span>
              </div>
            )}

            {step === 'manual' && manual.state === 'expired' && (
              <div
                className="flex items-start gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800"
                role="alert"
              >
                <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span>{t('This code has expired. Go Back and start again to get a new one.')}</span>
              </div>
            )}
          </div>
        )}
      </Modal>
    </>
  );
}
