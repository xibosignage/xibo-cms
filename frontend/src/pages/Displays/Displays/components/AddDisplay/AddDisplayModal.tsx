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
import { Link } from 'react-router-dom';

import AddDisplayCloseConfirm, { type CloseConfirmKind } from './AddDisplayCloseConfirm';
import AddDisplayModeChooser, { type ConnectMode } from './AddDisplayModeChooser';
import AddDisplaySuccessModal, { type SubmittedDisplay } from './AddDisplaySuccessModal';
import { useConnectWatcher } from './hooks/useConnectWatcher';
import { readPendingCode, writePendingCode, type PendingCode } from './hooks/usePendingConnect';

import { notify } from '@/components/ui/Notification';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import Switch from '@/components/ui/forms/Switch';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import DisplayGroupSelect from '@/pages/Administration/Connectors/components/DisplayGroupSelect';
import {
  addDisplayViaCode,
  applyConnectSettings,
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

/** Which control asked to leave a manual configuration part-way. */
type ConfirmFrom = 'close' | 'cancel' | 'back';

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
  /** Called from a toast action to reopen the Add modal after it was minimized or closed. */
  onReopen?: () => void;
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

    timerRef.current = setTimeout(() => setHidden(true), 60000);
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
  onReopen,
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
  const [licenceLoading, setLicenceLoading] = useState(true);
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
  /**
   * Manual mode saved the settings but has no record to adopt: the display is in a folder this user
   * can't see, or reading it back failed.
   */
  const [savedWithoutRecord, setSavedWithoutRecord] = useState(false);
  /** What the success summary should point out, e.g. the display group couldn't be assigned. */
  const [doneWarnings, setDoneWarnings] = useState<string[]>([]);
  const isDone = adopted !== null || savedWithoutRecord;

  // Both routes identify their Player by a code the CMS matched at registration, rather than by
  // watching for whatever registers next. Manual mode uses a code the CMS issued; activation mode
  // uses the code the operator typed, which the Player carries back on its server key.
  const watched = useConnectWatcher(step === 'manual' ? connectCode : submittedCode);

  // Authorising is available to the same audience as this form, as it is for an activation code:
  // the CMS applies it on the user's behalf, so the edit permission isn't needed.
  const canAuthorise = hasFeature(user, 'displays.add');

  // Naming and filing a display is an edit, which is a different permission from adding one.
  // The CMS only honours these fields from users who hold it.
  const canApplySettings = hasFeature(user, 'displays.modify');
  const canOpenBilling = hasFeature(user, 'billing.manage');

  // Derived UI phases — show loading as soon as the user clicks Add (isPending)
  // and keep it while polling for the new display (submitted but not yet adopted).
  // Stops as soon as the watcher reports the code expired, or the display turned up but the
  // account can't see it - both are terminal outcomes, not "still waiting".
  const isWaiting =
    step === 'code' &&
    (isPending || (submitted !== null && !isDone && watched.state !== 'expired' && !apiError));

  /** Manual mode is still waiting for the coded Player to reach the CMS. */
  const isConnecting = step === 'manual' && watched.state === 'waiting';

  /**
   * Manual configuration cannot be saved until the Player has registered, because until then
   * there is no display to save the chosen settings against. A Player that was already registered
   * isn't offered them at all: it isn't a new display, so the flow ends with it as it is.
   */
  const isConnected = step === 'manual' && watched.state === 'connected' && !watched.existing;

  /**
   * The name, folder, group and authorise fields. Manual mode shows them only once the Player has
   * connected, because until then there is no display to apply them to.
   */
  const showSettings = step === 'code' ? !isWaiting : isConnected;

  /**
   * Closing part-way asks first. Which question depends on where the Player is right now, so it is
   * worked out live: a Player connecting while "Close this form?" is showing turns it into the
   * offer to save. If the code expires there is nothing left to ask about.
   */
  const [confirmOpen, setConfirmOpen] = useState(false);
  /** What asked: closing keeps watching for the Player, Cancel and Back stop. */
  const [confirmFrom, setConfirmFrom] = useState<ConfirmFrom>('close');
  let confirmKind: CloseConfirmKind | null = null;
  if (confirmOpen && isConnected) {
    confirmKind = 'unsaved';
  } else if (confirmOpen && isConnecting) {
    confirmKind = confirmFrom === 'close' ? 'waiting' : confirmFrom;
  }

  useEffect(() => {
    if (!isConnecting && !isConnected) {
      setConfirmOpen(false);
    }
  }, [isConnecting, isConnected]);

  /** Every licence is in use, so a new display can't be authorised. */
  const licenceFull =
    licence !== null && licence.maxLicensed !== null && (licence.available ?? 0) === 0;

  useEffect(() => {
    if (licenceFull) {
      setAuthorise(false);
    }
  }, [licenceFull]);

  /**
   * Identifies the current manual attempt. Back, Cancel and closing the form all move it on, so a
   * code that arrives after the operator has left is not remembered for a form that no longer wants it.
   */
  const attemptRef = useRef(0);

  useEffect(
    () => () => {
      attemptRef.current++;
    },
    [],
  );

  const resetForm = () => {
    // A deep-linked code means the customer already chose their route.
    setStep(prefill?.code ? 'code' : 'choose');
    setUserCode(prefill?.code ?? '');
    setDisplayName(prefill?.displayName ?? '');
    setFolderId(defaultDisplayFolder);
    setFolderText(null);
    setDisplayGroupId(null);
    setDisplayGroupText('');
    setAuthorise(!licenceFull);
    setApiError(undefined);
    setSavedWithoutRecord(false);
    setDoneWarnings([]);
    setConnect(null);
    setConnectCode(null);
    setSubmittedCode(null);
    setSubmitted(null);
    setDetected(null);
    setAdopted(null);
    setConfirmOpen(false);
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

  // Read again once the display has been added, since it may have taken a licence, and again when
  // Add Another starts over.
  useEffect(() => {
    if (!isOpen) {
      return;
    }

    let cancelled = false;
    setLicenceLoading(true);

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
      })
      .finally(() => {
        if (!cancelled) {
          setLicenceLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [isOpen, isDone]);

  /**
   * Opening the form picks up a Player it is still waiting for, so the form is what watches it
   * (the background watcher stands down while the form is open) and starting another one can't
   * silently replace it.
   *
   * - A deep link carrying an activation code this account already submitted (the "Return to Add
   *   Display" link on its notification) resumes waiting rather than offering to submit it again.
   *   A code nobody has submitted yet, e.g. from a scanned QR code, reports as expired here and the
   *   form is left for the operator to fill in.
   * - Otherwise a pending code from earlier resumes on its own route.
   */
  useEffect(() => {
    if (!isOpen || submittedCode || connectCode || step === 'manual') {
      return;
    }

    const pending = readPendingCode();
    const prefillCode = prefill?.code?.trim();
    const resume: PendingCode | null = prefillCode ? { code: prefillCode, mode: 'code' } : pending;

    if (!resume) {
      return;
    }

    let cancelled = false;

    void (async () => {
      try {
        const status = await fetchConnectStatus(resume.code);

        if (cancelled || !status) {
          return;
        }

        if (status.expired) {
          if (!prefillCode) {
            writePendingCode(null);
          }
          return;
        }

        if (resume.mode === 'manual') {
          // startManual reads the pending code back and carries on with it.
          startManual();
          return;
        }

        setStep('code');
        setUserCode(resume.code);
        setSubmitted({
          code: resume.code,
          displayName: prefill?.displayName ?? '',
          folderText: '',
          authorise: false,
        });
        setSubmittedCode(resume.code);
        writePendingCode(resume);
      } catch {
        // Not being able to resume just leaves the form as it was.
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [isOpen, prefill?.code]);

  const licenceHelpText = (): string => {
    if (licenceLoading) {
      return t('Loading licence information...');
    }

    if (!licence || licence.maxLicensed === null) {
      return '';
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
  });

  /**
   * Enter manual configuration: fetch the address and key for the operator to copy into the
   * Player, along with the one-time code that lets this form recognise that Player when it connects.
   */
  const startManual = () => {
    setStep('manual');
    setApiError(undefined);

    const attempt = ++attemptRef.current;
    // The operator went back, cancelled or closed the form while this was loading.
    const abandoned = () => attempt !== attemptRef.current;

    startTransition(async () => {
      try {
        const details = await fetchConnectDetails();
        if (abandoned()) {
          return;
        }
        setConnect(details);

        // A code left over from a previous page load is still live on the CMS, and the Player may
        // already be carrying it. Pick it back up rather than stranding it behind a fresh one.
        // Only a manual code can be resumed here. An activation code is a different thing entirely -
        // resuming one would offer it as the CMS key, which is meaningless for a manual setup.
        const pending = readPendingCode();

        if (pending?.mode === 'manual') {
          const status = await fetchConnectStatus(pending.code);
          if (abandoned()) {
            return;
          }

          if (!status.expired) {
            setConnectCode(pending.code);
            return;
          }

          writePendingCode(null);
        }

        // The code is what the operator types into the Player, and what lets the CMS recognise that
        // Player as this form's. It carries no settings: the Player registers with the CMS defaults,
        // unauthorised, and the operator's choices are applied by Save once it has connected.
        const issued = await fetchConnectCode();
        if (abandoned()) {
          return;
        }

        setConnectCode(issued.code);
        writePendingCode({ code: issued.code, mode: 'manual' });
      } catch (err: unknown) {
        if (abandoned()) {
          return;
        }
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
    attemptRef.current++;
    setStep('choose');
    // Deliberately abandoning this attempt, so the code should not be resumed later.
    writePendingCode(null);
    setApiError(undefined);
    setConnect(null);
    setConnectCode(null);
    setSubmittedCode(null);
    // A submitted code has been used up (it expired, or the Player never came), so it can't be
    // sent again. A code that was never sent, e.g. one the CMS rejected, stays for the operator to fix.
    if (submitted !== null) {
      setUserCode('');
    }
    // Without this, choosing Activation Code again would still count as submitted and wait forever.
    setSubmitted(null);
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
                onReopen?.();
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
   * The manual-connect code carries no settings, so the Player registered with the CMS defaults,
   * often in a folder this user can't see or edit. The CMS applies the settings on the user's
   * behalf, because the code that brought the display in is theirs, by the same rules as an
   * activation code. That is why the form only appears once the Player has connected.
   */
  const handleManualSubmit = () => {
    if (!watched.displayId || !connectCode) {
      return;
    }

    startTransition(async () => {
      setApiError(undefined);

      try {
        const result = await applyConnectSettings(connectCode, {
          displayName: canApplySettings ? displayName.trim() : undefined,
          folderId: canApplySettings ? folderId : undefined,
          displayGroupId: canApplySettings ? displayGroupId : undefined,
          authorised: canAuthorise && authorise,
        });

        // The settings are saved from here on, and the CMS won't take them twice, so nothing below
        // may send the operator back to the form.
        const warnings: string[] = [];

        // Read back for the summary and Manage Display, which need the full record. A display
        // filed where this user can't see it has none to read, so the summary uses the result.
        let target: Display | null = null;
        if (result.viewable) {
          try {
            const { rows } = await fetchDisplays({
              start: 0,
              length: 1,
              displayId: result.displayId,
            });
            target = rows[0] ?? null;
          } catch {
            // Reported below, with a missing row.
          }

          if (!target) {
            warnings.push(
              t(
                'Your settings were saved, but the display could not be loaded. Refresh the list to see it.',
              ),
            );
          }
        } else {
          warnings.push(
            t("This display is in a folder you can't access, so it won't appear in your list."),
          );
        }

        const summary = summarise();

        if (!result.displayGroupAssigned) {
          warnings.push(
            t(
              "The display couldn't be added to the {{group}} display group. Add it from Display Groups.",
              {
                group: summary.displayGroup,
              },
            ),
          );
        }

        // The code has done its job; a later Add Display should start clean.
        writePendingCode(null);

        setSubmitted({
          ...summary,
          displayName: result.display,
          authorise: result.licensed === 1,
          // Only claim the group the display actually joined.
          displayGroup: result.displayGroupAssigned ? summary.displayGroup : undefined,
        });
        setDoneWarnings(warnings);
        setAdopted(target);
        setSavedWithoutRecord(target === null);
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
      const message = watched.existing
        ? t('{{name}} was already registered, so it was not added again.', {
            name: display.display,
          })
        : t('Display has been added successfully.');
      notify.success(message, {
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
  // settings were applied server side at registration, so nothing else is left to do. Either mode
  // also finishes here when the Player was already registered, since nothing is applied to it.
  useEffect(() => {
    const finishes = step === 'code' || (step === 'manual' && watched.existing);
    if (!finishes || watched.state !== 'connected' || !watched.displayId || detected) {
      return;
    }

    void (async () => {
      try {
        const { rows } = await fetchDisplays({
          start: 0,
          length: 1,
          displayId: watched.displayId ?? undefined,
        });
        const target = rows[0];

        if (target) {
          setDetected(target);
          // Manual mode has no submission of its own when it ends here.
          setSubmitted((current) => current ?? summarise());
          adopt(target);
          return;
        }

        // The Player has registered at this point - it just isn't visible in this query. A Player
        // that was already registered belongs to a display this account has no rights over. A new
        // one almost always landed in a folder the user group has no view permission on.
        const message = watched.existing
          ? t("This Player is already registered to a display you don't have access to.")
          : t(
              'The display connected, but your account does not have permission to view it. Ask an administrator to grant your user group access to its folder.',
            );

        setApiError(message);

        if (!isOpenRef.current) {
          notify.error(message);
          onClose();
        }
      } catch (err: unknown) {
        setApiError(
          isAxiosError(err) && err.response?.data?.message
            ? err.response.data.message
            : t('Failed to fetch display details.'),
        );
      }
    })();
  }, [step, watched.state, watched.displayId, watched.existing, detected]);

  // Toast notifications for detection outcomes — only when minimized
  useEffect(() => {
    if (!isOpenRef.current && watched.state === 'expired') {
      notify.error(t('Connection timed out.'), {
        action: {
          label: t('Try Again'),
          onClick: () => {
            onReopen?.();
          },
        },
      });
      onClose();
    }
  }, [watched.state, isOpen]);

  const handleCancel = () => {
    attemptRef.current++;
    resetForm();
    writePendingCode(null);
    onClose();
  };

  /** Start again for another display. Clearing the adopted display reads the licence count again. */
  const handleAddAnother = () => {
    resetForm();
  };

  /** Saving the connected display's settings. Nothing may close the form until it finishes. */
  const isSaving = isPending && isConnected;

  /**
   * Closing manual configuration part-way leaves something unfinished, so ask first: before the
   * Player connects, the code is still live; after, the display exists but its settings are unsaved.
   *
   * Cancel and Back ask too. Forgetting the code doesn't stop a Player that already has it from
   * connecting, it only means nothing is left to finish setting that display up.
   */
  const closeOr =
    (close: () => void, from: ConfirmFrom = 'close') =>
    () => {
      if (isSaving) {
        return;
      }

      if (step === 'manual' && (isConnected || isConnecting)) {
        setConfirmFrom(from);
        setConfirmOpen(true);
        return;
      }

      close();
    };

  /** Close while waiting: the code stays live and the background watcher reports the Player. */
  const handleCloseWhileWaiting = () => {
    setConfirmOpen(false);
    onClose();
  };

  /** Close without saving: the display keeps what it registered with, unauthorised. */
  const handleCloseUnsaved = () => {
    setConfirmOpen(false);
    writePendingCode(null);
    // The display exists now, so the list should show it.
    onAdded?.();
    onClose();
  };

  const handleSaveFromConfirm = () => {
    setConfirmOpen(false);
    handleManualSubmit();
  };

  /** Going ahead from the confirmation, for each question it can ask. */
  const confirmedByKind: Record<CloseConfirmKind, () => void> = {
    waiting: handleCloseWhileWaiting,
    cancel: handleCancel,
    back: () => {
      setConfirmOpen(false);
      handleBack();
    },
    unsaved: handleCloseUnsaved,
  };

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
    if (isDone) {
      return [];
    }

    if (step === 'choose') {
      return [
        {
          label: t('Cancel'),
          onClick: handleCancel,
          variant: 'secondary' as const,
        },
      ];
    }

    if (step === 'manual') {
      // Until the Player connects there is nothing to save, only details to copy. Back and Cancel
      // abandon the attempt once confirmed - closing (X, Esc) is what keeps waiting in the background.
      if (!isConnected) {
        return [
          {
            label: t('Back'),
            onClick: closeOr(handleBack, 'back'),
            variant: 'secondary' as const,
            leftIcon: ArrowLeft,
            className: 'mr-auto',
          },
          {
            label: t('Cancel'),
            onClick: closeOr(handleCancel, 'cancel'),
            variant: 'secondary' as const,
          },
        ];
      }

      return [
        {
          label: t('Cancel'),
          onClick: closeOr(handleCancel, 'cancel'),
          variant: 'secondary' as const,
          disabled: isSaving,
        },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleManualSubmit,
          disabled: isPending || !canSubmitManual || licenceLoading,
        },
      ];
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
        onClick: handleCancel,
        variant: 'secondary' as const,
      },
      {
        label: t('Add'),
        onClick: handleSubmit,
        // Once submitted, an expired code can't be retried as-is - the operator needs a fresh one.
        disabled:
          isPending || isWaiting || !canSubmit || watched.state === 'expired' || licenceLoading,
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
      {isDone && submitted && (
        <AddDisplaySuccessModal
          submitted={submitted}
          display={adopted}
          alreadyRegistered={watched.existing}
          warnings={doneWarnings}
          canManage={adopted !== null}
          licenceText={licenceLoading ? undefined : licenceHelpText()}
          licenceLoading={licenceLoading}
          onClose={onClose}
          onAddAnother={handleAddAnother}
          onManage={handleManage}
        />
      )}

      {confirmKind && (
        <AddDisplayCloseConfirm
          isOpen={isOpen}
          kind={confirmKind}
          onBack={() => setConfirmOpen(false)}
          onClose={confirmedByKind[confirmKind]}
          onSave={handleSaveFromConfirm}
          canSave={!isPending && canSubmitManual && !licenceLoading}
        />
      )}

      <Modal
        onClose={closeOr(onClose)}
        isOpen={isOpen && !(isDone && submitted) && !confirmKind}
        ariaLabel={t('Add Display')}
        error={apiError}
        size="sm"
        actions={buildActions()}
      >
        {/* Custom header with title, info tooltip, and minimize/close button */}
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
                        {step === 'code'
                          ? t(
                              'After submitting this form with a valid code, your CMS Address and Key will be sent to our Authentication Service. The Player linked to the code will retrieve these details and configure itself automatically. Your details are removed from the service once the Player is configured. Please note that your CMS needs to make a successful call to our Authentication Service for this feature to work.',
                            )
                          : t(
                              'Copy the CMS URL and Secret Key shown below into the Player, then press Connect. The Secret Key includes a one-time code that links this form to that Player. Once the Player connects, you can name it and choose a folder and display group. These settings are applied when you click Save.',
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
              onClick={canMinimize && onMinimize ? () => onMinimize(displayName) : closeOr(onClose)}
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

            {step === 'code' && !isWaiting && (
              <TextInput
                name="user_code"
                label={t('Activation Code') + '*'}
                placeholder={t('Enter code')}
                onChange={(value) => setUserCode(value)}
                helpText={t('Code shown on the player screen')}
                value={userCode}
              />
            )}

            {step === 'manual' && (
              <>
                {/* Connection animation — grey while waiting, colored once connected */}
                {(isConnecting || isConnected) && (
                  <div
                    className="flex flex-col items-center gap-4 rounded-lg bg-slate-50 p-6"
                    role="status"
                  >
                    {isConnected ? (
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
                        {isConnected
                          ? t('Display connected!')
                          : t('Waiting for display to connect...')}
                      </p>
                      <p className="text-xs text-gray-500 mt-1">
                        {isConnected
                          ? watched.displayName
                            ? t('Connected to {{display}}. Save to apply your settings.', {
                                display: watched.displayName,
                              })
                            : t('Connected. Save to apply your settings.')
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

            {showSettings && (
              <>
                {/* Naming and filing are edits, so they are only offered to users who can edit. */}
                {canApplySettings && (
                  <>
                    <TextInput
                      name="display_name"
                      label={t('Display Name') + '*'}
                      placeholder={t('Enter Display Name')}
                      onChange={(value) => setDisplayName(value)}
                      value={displayName}
                      maxLength={50}
                      // The field stops taking characters at the limit, so say why.
                      helpText={
                        displayName.length >= 50
                          ? t('Display names can be up to 50 characters.')
                          : undefined
                      }
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
                    />
                  </>
                )}

                {/* Authorise automatically */}
                {canAuthorise && (
                  <div className="flex flex-col gap-1.5 rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <div className="flex items-center justify-between">
                      <span className="text-sm font-semibold text-gray-700">
                        {t('Authorise automatically')}
                      </span>
                      <div className="shrink-0">
                        <Switch
                          checked={authorise}
                          onChange={setAuthorise}
                          ariaLabel={t('Authorise automatically')}
                          disabled={licenceFull}
                          hideOnOff
                          size="sm"
                        />
                      </div>
                    </div>
                    {licenceHelpText() && (
                      <p className="text-xs text-gray-400">{licenceHelpText()}</p>
                    )}
                    {licenceFull && (
                      <p className="text-xs text-gray-600">
                        {t('Every display licence is in use.')}
                        {canOpenBilling && (
                          <>
                            {' '}
                            <Link to="/administration/billing" className="underline">
                              {t('Add more licences in Billing')}
                            </Link>
                          </>
                        )}
                      </p>
                    )}
                  </div>
                )}
              </>
            )}

            {(step === 'manual' || step === 'code') && watched.state === 'expired' && (
              <div
                className="flex items-center justify-between gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800"
                role="alert"
              >
                <div className="flex items-start gap-2">
                  <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                  <span>
                    {step === 'code'
                      ? t(
                          'Your activation code has expired. Please go back and try again with a new code.',
                        )
                      : t('Your connection code has expired for security reasons.')}
                  </span>
                </div>
                {step === 'manual' && (
                  <button
                    type="button"
                    onClick={startManual}
                    className="shrink-0 text-xs font-semibold text-yellow-800 hover:text-yellow-900 underline"
                  >
                    {t('Refresh')}
                  </button>
                )}
                {step === 'code' && (
                  <button
                    type="button"
                    onClick={handleBack}
                    className="shrink-0 text-xs font-semibold text-yellow-800 hover:text-yellow-900 underline"
                  >
                    {t('Try Again')}
                  </button>
                )}
              </div>
            )}
          </div>
        )}
      </Modal>
    </>
  );
}
