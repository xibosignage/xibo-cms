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
  BadgeCheck,
  Camera,
  Eraser,
  RefreshCw,
  ShieldCheck,
  ShieldOff,
  Wifi,
  XCircle,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

import AddDisplayModal from './AddDisplayModal';
import AssignLayoutModal from './AssignLayoutModal';
import AssignMediaModal from './AssignMediaModal';
import CollectNowModal from './CollectNowModal';
import DeleteDisplayModal from './DeleteDisplayModal';
import EditDisplayModal from './EditDisplayModal';
import ManageGroupMembershipModal from './ManageGroupMembershipModal';
import SendCommandModal from './SendCommandModal';
import SetBandwidthModal from './SetBandwidthModal';
import SetDefaultLayoutModal from './SetDefaultLayoutModal';
import TransferCmsModal from './TransferCmsModal';
import TriggerWebhookModal from './TriggerWebhookModal';

import Modal from '@/components/ui/modals/Modal';
import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { MoveCmsData } from '@/services/displaysApi';
import type { Display, DisplayCommandTarget } from '@/types/display';

interface DisplayModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isActionPending: boolean;
    actionError: string | null;
  };
  selection: {
    selectedDisplay: Display | null;
    itemsToDelete: Display[];
    itemsToMove: Display[];
    actionDisplay: Display | null;
    bulkActionItems: Display[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: Display[]) => void;
    handleConfirmMove: (folderId: number) => void;
    confirmAuthorise: (display: Display) => void;
    confirmCheckLicence: (display: Display) => void;
    confirmRequestScreenShot: (display: Display) => void;
    confirmCollectNow: (display: Display) => void;
    confirmWakeOnLan: (display: Display) => void;
    confirmPurgeAll: (display: Display) => void;
    confirmTriggerWebhook: (display: Display, triggerCode: string) => void;
    confirmSetDefaultLayout: (display: Display, layoutId: number) => void;
    confirmMoveCms: (display: Display, data: MoveCmsData) => void;
    confirmMoveCmsCancel: (display: Display) => void;
    confirmSetBandwidth: (items: Display[], bandwidthLimitKb: number) => void;
    confirmBulkAuthorise: (items: Display[]) => void;
    confirmBulkCheckLicence: (items: Display[]) => void;
    confirmBulkRequestScreenShot: (items: Display[]) => void;
    confirmBulkCollectNow: (items: Display[]) => void;
    confirmBulkTriggerWebhook: (items: DisplayCommandTarget[], triggerCode: string) => void;
    confirmBulkSetDefaultLayout: (items: Display[], layoutId: number) => void;
    confirmSendCommand: (items: DisplayCommandTarget[], commandId: number) => void;
    confirmBulkMoveCms: (items: Display[], data: MoveCmsData) => void;
  };
}

function ActionError({ error }: { error: string | null }) {
  if (!error) {
    return null;
  }
  return (
    <div className="mt-2 text-center">
      <p className="text-sm font-medium text-red-600">{error}</p>
    </div>
  );
}

export function DisplayModals({ actions, selection, handlers }: DisplayModalsProps) {
  const { t } = useTranslation();

  const isModalOpen = (name: string) => actions.activeModal === name;
  const display = selection.actionDisplay;
  const bulkItems = selection.bulkActionItems;

  return (
    <>
      {isModalOpen('add') && <AddDisplayModal onClose={actions.closeModal} />}

      {isModalOpen('edit') && (
        <EditDisplayModal
          data={selection.selectedDisplay}
          onClose={actions.closeModal}
          onSave={() => {
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteDisplayModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          displayName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.display : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}

      {isModalOpen('move') && (
        <MoveModal
          onClose={actions.closeModal}
          onConfirm={handlers.handleConfirmMove}
          items={selection.itemsToMove.map((d) => ({ ...d, folderId: d.folderId ?? 0 }))}
          entityLabel={t('Displays')}
        />
      )}

      {isModalOpen('share') && (
        <ShareModal
          title={t('Share Display')}
          onClose={() => {
            actions.closeModal();
            selection.setShareEntityIds(null);
            actions.handleRefresh();
          }}
          entityType="displayGroup"
          entityId={selection.shareEntityIds}
        />
      )}

      {isModalOpen('authorise') && display && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending
                ? t('Saving…')
                : display.licensed === 1
                  ? t('Yes, Unauthorise')
                  : t('Yes, Authorise'),
              onClick: () => handlers.confirmAuthorise(display),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
                {display.licensed === 1 ? <ShieldOff size={26} /> : <ShieldCheck size={26} />}
              </div>
            </div>
            <h2 className="text-center text-lg font-semibold mb-1">
              {display.licensed === 1 ? t('Unauthorise Display') : t('Authorise Display')}
            </h2>
            <p className="text-center text-gray-500">
              {display.licensed === 1
                ? t('Are you sure you want to de-authorise this Display?')
                : t('Are you sure you want to authorise this Display?')}
            </p>
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {isModalOpen('checkLicence') && display && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending ? t('Checking…') : t('Check Licence'),
              onClick: () => handlers.confirmCheckLicence(display),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
                <BadgeCheck size={26} />
              </div>
            </div>
            <h2 className="text-center text-lg font-semibold mb-1">
              {t('Check Commercial Licence')}
            </h2>
            <p className="text-center text-gray-500">
              {t('Are you sure you want to ask this Player to check its Licence?')}
            </p>
            <p className="text-center text-gray-500 text-sm">
              {t(
                'The result of this check will be immediately actioned and the status reported in Commercial Licence column.',
              )}
            </p>
            {!display.xmrChannel && (
              <div className="rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700">
                {t(
                  'XMR is not working on this Player yet and therefore the licence check may not occur.',
                )}
              </div>
            )}
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {isModalOpen('requestScreenShot') && display && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending ? t('Requesting…') : t('Request Screenshot'),
              onClick: () => handlers.confirmRequestScreenShot(display),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
                <Camera size={26} />
              </div>
            </div>
            <h2 className="text-center text-lg font-semibold mb-1">{t('Request Screen Shot')}</h2>
            <p className="text-center text-gray-500">
              {t('Are you sure you want to request a screenshot?')}
            </p>
            <p className="text-center text-gray-500 text-sm">
              {t(
                'If the Player is configured for push messaging, screenshots are requested immediately and should be seen when the form is closed.',
              )}
            </p>
            <p className="text-center text-gray-500 text-sm">
              {t(
                'Screenshots can be seen in the Display Grid by selecting Column Visibility and enabling the Screenshot column.',
              )}
            </p>
            {!display.xmrChannel && (
              <div className="rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700">
                {t(
                  'XMR is not working on this Player yet, the screenshot will be requested the next time the Player connects on its collection interval.',
                )}
              </div>
            )}
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {isModalOpen('collectNow') && display && (
        <CollectNowModal
          onClose={actions.closeModal}
          onConfirm={() => handlers.confirmCollectNow(display)}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('wakeOnLan') && display && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending ? t('Sending…') : t('Yes, Wake on LAN'),
              onClick: () => handlers.confirmWakeOnLan(display),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
                <Wifi size={26} />
              </div>
            </div>
            <h2 className="text-center text-lg font-semibold mb-1">{t('Wake On LAN')}</h2>
            <p className="text-center text-gray-500">
              {t('Are you sure you want to send a Wake On LAN message to this display?')}
            </p>
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {isModalOpen('purgeAll') && display && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending ? t('Purging…') : t('Yes, Purge All'),
              onClick: () => handlers.confirmPurgeAll(display),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-red-100 w-15.5 h-15.5 text-red-800 border-red-50 border-[7px] rounded-full p-3">
                <Eraser size={26} />
              </div>
            </div>
            <h2 className="text-center text-lg font-semibold mb-1 text-red-800">
              {t('Purge all Media files')}
            </h2>
            <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
              {t(
                'Caution! Triggering this action will ask the Player to remove every downloaded Media file from its storage.',
              )}
            </div>
            <div className="rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700">
              {t(
                'This action will be immediately actioned. The Player will remove all existing Media files from its local storage and request fresh copies of required files from the CMS.',
              )}
            </div>
            {!display.xmrChannel && (
              <div className="rounded-lg border border-xibo-blue-200 bg-xibo-blue-50 p-3 text-sm text-xibo-blue-700">
                {t(
                  'XMR is not working on this Player yet and therefore the purge may not occur immediately.',
                )}
              </div>
            )}
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {/* Trigger Webhook — unified for single and bulk */}
      {isModalOpen('triggerWebhook') && display && (
        <TriggerWebhookModal
          items={[display]}
          onClose={actions.closeModal}
          onConfirm={(_items, triggerCode) => handlers.confirmTriggerWebhook(display, triggerCode)}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('bulkTriggerWebhook') && (
        <TriggerWebhookModal
          items={bulkItems}
          onClose={actions.closeModal}
          onConfirm={(items, triggerCode) => handlers.confirmBulkTriggerWebhook(items, triggerCode)}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('defaultLayout') && display && (
        <SetDefaultLayoutModal
          display={display}
          onClose={actions.closeModal}
          onConfirm={(layoutId) => handlers.confirmSetDefaultLayout(display, layoutId)}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('moveCms') && display && (
        <TransferCmsModal
          display={display}
          onClose={actions.closeModal}
          onConfirm={(data) => handlers.confirmMoveCms(display, data)}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('moveCmsCancel') && display && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          title={t('Cancel Transfer?')}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending ? t('Cancelling…') : t('Yes'),
              onClick: () => handlers.confirmMoveCmsCancel(display),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
                <XCircle size={26} />
              </div>
            </div>
            <p className="text-center text-gray-500">
              {t(
                'Are you sure you want to cancel this CMS transfer? This is only possible if the Display has not already transferred.',
              )}
            </p>
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {isModalOpen('setBandwidth') && (
        <SetBandwidthModal
          displayCount={bulkItems.length}
          onClose={actions.closeModal}
          onConfirm={(bandwidthLimitKb) =>
            handlers.confirmSetBandwidth(bulkItems, bandwidthLimitKb)
          }
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('bulkAuthorise') && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending ? t('Saving…') : t('Yes, Toggle Authorise'),
              onClick: () => handlers.confirmBulkAuthorise(bulkItems),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
                <ShieldCheck size={26} />
              </div>
            </div>
            <h2 className="text-center text-lg font-semibold mb-1">{t('Toggle Authorise')}</h2>
            <p className="text-center text-gray-500">
              {t('Are you sure you want to toggle authorisation for {{count}} display(s)?', {
                count: bulkItems.length,
              })}
            </p>
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {isModalOpen('bulkCheckLicence') && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending ? t('Checking…') : t('Check Licence'),
              onClick: () => handlers.confirmBulkCheckLicence(bulkItems),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
                <BadgeCheck size={26} />
              </div>
            </div>
            <h2 className="text-center text-lg font-semibold mb-1">
              {t('Check Commercial Licence')}
            </h2>
            <p className="text-center text-gray-500">
              {t('Are you sure you want to ask {{count}} Player(s) to check their Licence?', {
                count: bulkItems.length,
              })}
            </p>
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {isModalOpen('bulkRequestScreenShot') && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending ? t('Requesting…') : t('Request Screenshot'),
              onClick: () => handlers.confirmBulkRequestScreenShot(bulkItems),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
                <Camera size={26} />
              </div>
            </div>
            <h2 className="text-center text-lg font-semibold mb-1">{t('Request Screen Shot')}</h2>
            <p className="text-center text-gray-500">
              {t('Are you sure you want to request a screenshot for {{count}} display(s)?', {
                count: bulkItems.length,
              })}
            </p>
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {isModalOpen('bulkCollectNow') && (
        <Modal
          isOpen
          isPending={actions.isActionPending}
          onClose={actions.closeModal}
          actions={[
            { label: t('Cancel'), onClick: actions.closeModal, variant: 'secondary' },
            {
              label: actions.isActionPending ? t('Collecting…') : t('Collect Now'),
              onClick: () => handlers.confirmBulkCollectNow(bulkItems),
              disabled: actions.isActionPending,
            },
          ]}
          size="md"
        >
          <div className="flex flex-col p-5 gap-3">
            <div className="flex justify-center mb-2">
              <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
                <RefreshCw size={26} />
              </div>
            </div>
            <h2 className="text-center text-lg font-semibold mb-1">{t('Collect Now')}</h2>
            <p className="text-center text-gray-500">
              {t('Are you sure you want to request a collection for {{count}} display(s)?', {
                count: bulkItems.length,
              })}
            </p>
            <ActionError error={actions.actionError} />
          </div>
        </Modal>
      )}

      {isModalOpen('bulkDefaultLayout') && (
        <SetDefaultLayoutModal
          onClose={actions.closeModal}
          onConfirm={(layoutId) => handlers.confirmBulkSetDefaultLayout(bulkItems, layoutId)}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {/* Send Command — unified for single and bulk */}
      {isModalOpen('sendCommand') && display && (
        <SendCommandModal
          items={[display]}
          onClose={actions.closeModal}
          onConfirm={(items, commandId) => handlers.confirmSendCommand(items, commandId)}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('bulkSendCommand') && (
        <SendCommandModal
          items={bulkItems}
          onClose={actions.closeModal}
          onConfirm={(items, commandId) => handlers.confirmSendCommand(items, commandId)}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {isModalOpen('bulkMoveCms') && (
        <TransferCmsModal
          onClose={actions.closeModal}
          onConfirm={(data) => handlers.confirmBulkMoveCms(bulkItems, data)}
          isActionPending={actions.isActionPending}
          actionError={actions.actionError}
        />
      )}

      {/* Single-display only modals */}
      {isModalOpen('assignMedia') && display && (
        <AssignMediaModal
          display={display}
          onClose={actions.closeModal}
          onSave={actions.handleRefresh}
        />
      )}

      {isModalOpen('assignLayout') && display && (
        <AssignLayoutModal
          display={display}
          onClose={actions.closeModal}
          onSave={actions.handleRefresh}
        />
      )}

      {isModalOpen('manageGroups') && display && (
        <ManageGroupMembershipModal
          display={display}
          onClose={actions.closeModal}
          onSave={actions.handleRefresh}
        />
      )}
    </>
  );
}
