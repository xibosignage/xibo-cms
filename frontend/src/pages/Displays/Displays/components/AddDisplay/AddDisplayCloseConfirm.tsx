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

import { AlertTriangle, ArrowLeft, Monitor } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Modal, { type ModalAction } from '@/components/ui/modals/Modal';

/**
 * 'waiting': the Player hasn't connected yet and the code is still live.
 * 'cancel': as 'waiting', but the operator chose Cancel, which stops watching for the Player.
 * 'back': as 'cancel', but going back to the choice of route.
 * 'unsaved': the Player has connected but the operator's settings haven't been saved.
 */
export type CloseConfirmKind = 'waiting' | 'cancel' | 'back' | 'unsaved';

interface AddDisplayCloseConfirmProps {
  isOpen?: boolean;
  kind: CloseConfirmKind;
  /** Return to the Add Display form. */
  onBack: () => void;
  /** Go ahead with what was asked: close, cancel or go back. */
  onClose: () => void;
  /** Save the settings, then finish as the form's own Save would. Only offered when 'unsaved'. */
  onSave: () => void;
  canSave: boolean;
}

/** Asks before closing a manual configuration that isn't finished. */
export default function AddDisplayCloseConfirm({
  isOpen,
  kind,
  onBack,
  onClose,
  onSave,
  canSave,
}: AddDisplayCloseConfirmProps) {
  const { t } = useTranslation();

  // All but 'unsaved' are asked before the Player connects, so there is nothing to save yet.
  const isWaiting = kind !== 'unsaved';

  const titles: Record<CloseConfirmKind, string> = {
    waiting: t('Close this form?'),
    cancel: t('Cancel adding this display?'),
    back: t('Stop waiting for this Player?'),
    unsaved: t("Save this display's settings?"),
  };
  const title = titles[kind];

  // Giving up on the code doesn't stop a Player that already has it.
  const abandonMessage = t(
    "If you've already entered these details on the Player and pressed Connect, it will still be added, but with the CMS defaults instead of your settings. Keep waiting to finish setting it up here.",
  );

  const messages: Record<CloseConfirmKind, string> = {
    waiting: t(
      "The Player can still connect with this key until its code expires. We'll let you know when it does.",
    ),
    cancel: abandonMessage,
    back: abandonMessage,
    unsaved: t(
      "The display is connected, but its name, folder, group and authorisation haven't been saved yet.",
    ),
  };

  const actionsByKind: Record<CloseConfirmKind, ModalAction[]> = {
    waiting: [
      { label: t('Keep waiting'), onClick: onBack, variant: 'secondary' },
      { label: t('Close'), onClick: onClose },
    ],
    // Keep waiting leads here, because it is the choice that doesn't leave a half-configured display.
    cancel: [
      { label: t('Cancel anyway'), onClick: onClose, variant: 'secondary' },
      { label: t('Keep waiting'), onClick: onBack },
    ],
    back: [
      { label: t('Go back anyway'), onClick: onClose, variant: 'secondary' },
      { label: t('Keep waiting'), onClick: onBack },
    ],
    unsaved: [
      { label: t('Back'), onClick: onBack, variant: 'secondary', leftIcon: ArrowLeft },
      // Keeps Back and this together on the left, away from Save.
      {
        label: t('Close without saving'),
        onClick: onClose,
        variant: 'secondary',
        className: 'mr-auto',
      },
      { label: t('Save'), onClick: onSave, disabled: !canSave },
    ],
  };

  return (
    <Modal
      variant="confirmation"
      isOpen={isOpen}
      onClose={onBack}
      ariaLabel={title}
      size="sm"
      actions={actionsByKind[kind]}
    >
      <div className="flex flex-col p-5 gap-3 text-center">
        <div className="flex justify-center mb-2">
          {isWaiting ? (
            <div className="bg-xibo-blue-50 w-15.5 h-15.5 text-xibo-blue-500 border-xibo-blue-50 border-[7px] rounded-full p-3">
              <Monitor size={26} />
            </div>
          ) : (
            <div className="bg-yellow-100 w-15.5 h-15.5 text-yellow-600 border-yellow-50 border-[7px] rounded-full p-3">
              <AlertTriangle size={26} />
            </div>
          )}
        </div>

        <h2 className="text-lg font-semibold text-gray-800">{title}</h2>

        <p className="text-gray-500">{messages[kind]}</p>
      </div>
    </Modal>
  );
}
