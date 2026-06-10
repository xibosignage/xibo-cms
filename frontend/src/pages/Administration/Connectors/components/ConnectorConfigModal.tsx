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

import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import ConnectorForm from './ConnectorForm';
import DashboardConnectorForm from './DashboardConnectorForm';
import SspConnectorForm from './SspConnectorForm';

import Button from '@/components/ui/Button';
import Modal from '@/components/ui/modals/Modal';
import { DASHBOARD_CLASS, SSP_CLASS } from '@/pages/Administration/Connectors/ConnectorsConfig';
import { connectorQueryKeys } from '@/pages/Administration/Connectors/hooks/useConnectorsData';
import { fetchConnectorFields } from '@/services/connectorApi';
import type { Connector } from '@/types/connector';

interface ConnectorConfigModalProps {
  connector: Connector;
  onClose: () => void;
  onSave: () => void;
}

function getConnectorId(connector: Connector): string {
  if (connector.connectorId !== null) {
    return String(connector.connectorId);
  }
  return encodeURIComponent(connector.className);
}

export default function ConnectorConfigModal({
  connector,
  onClose,
  onSave,
}: ConnectorConfigModalProps) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [isSaving, startSaving] = useTransition();
  const connectorId = getConnectorId(connector);
  const isDashboard = connector.className === DASHBOARD_CLASS;
  const isSSP = connector.className === SSP_CLASS;

  const { data, isLoading, isError } = useQuery({
    queryKey: connectorQueryKeys.fields(connectorId),
    queryFn: ({ signal }) => fetchConnectorFields(connectorId, signal),
  });

  const formId = isDashboard
    ? 'dashboard-connector-form'
    : isSSP
      ? 'ssp-connector-form'
      : 'connector-form';

  function handleSave() {
    void queryClient.invalidateQueries({ queryKey: connectorQueryKeys.fields(connectorId) });
    onSave();
    onClose();
  }

  const formActions = [
    { label: t('Cancel'), variant: 'secondary' as const, onClick: onClose },
    {
      label: isSaving ? t('Saving…') : t('Save'),
      isSubmit: true,
      formId,
      disabled: isSaving,
    },
  ];

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={t('Configure {{title}}', { title: connector.title })}
      size={isDashboard || isSSP ? 'xl' : 'lg'}
      variant={isSSP ? 'tabbed' : 'standard'}
      scrollable={!isSSP}
      actions={isLoading || isError || !data ? undefined : formActions}
    >
      {isLoading ? (
        <div className="flex items-center justify-center px-8 py-12">
          <div className="h-6 w-6 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
        </div>
      ) : isError ? (
        <div className="px-8 py-4 flex flex-col gap-4">
          <div className="rounded-md bg-red-50 border border-red-200 p-4">
            <p className="text-sm text-red-700">{t('Failed to load connector configuration.')}</p>
          </div>
          <div className="flex justify-end border-t pt-4">
            <Button type="button" variant="secondary" onClick={onClose}>
              {t('Close')}
            </Button>
          </div>
        </div>
      ) : data ? (
        isDashboard ? (
          <DashboardConnectorForm
            connector={connector}
            fields={data.fields}
            settings={data.settings}
            connectorId={connectorId}
            formSubtitle={data.formSubtitle}
            formDescriptionHtml={data.formDescriptionHtml}
            formAlerts={data.formAlerts}
            enabledLabel={data.enabledLabel}
            enabledDescription={data.enabledDescription}
            enabledMessage={data.enabledMessage}
            onSave={handleSave}
            startTransition={startSaving}
          />
        ) : isSSP ? (
          <SspConnectorForm
            connector={connector}
            connectorId={connectorId}
            fields={data.fields}
            settings={data.settings}
            enabledLabel={data.enabledLabel}
            enabledDescription={data.enabledDescription}
            enabledMessage={data.enabledMessage}
            onSave={handleSave}
            startTransition={startSaving}
          />
        ) : (
          <ConnectorForm
            connector={connector}
            fields={data.fields}
            settings={data.settings}
            connectorId={connectorId}
            formSubtitle={data.formSubtitle}
            formDescriptionHtml={data.formDescriptionHtml}
            formAlerts={data.formAlerts}
            enabledLabel={data.enabledLabel}
            enabledDescription={data.enabledDescription}
            enabledMessage={data.enabledMessage}
            onSave={handleSave}
            startTransition={startSaving}
          />
        )
      ) : null}
    </Modal>
  );
}
