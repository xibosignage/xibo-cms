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

import { useQuery } from '@tanstack/react-query';
import type { TransitionStartFunction } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import Badge from '@/components/ui/Badge';
import InfoBanner from '@/components/ui/InfoBanner';
import Checkbox from '@/components/ui/forms/Checkbox';
import TextInput from '@/components/ui/forms/TextInput';
import { fetchConnectorProxy, updateConnector } from '@/services/connectorApi';
import type {
  Connector,
  ConnectorField,
  ConnectorFormAlert,
  DashboardCredential,
  DashboardService,
} from '@/types/connector';

interface CredentialDraft {
  id?: string;
  userName?: string;
  password?: string;
  twoFactorSecret?: string;
  url?: string;
  remove: boolean;
}

interface DashboardConnectorFormProps {
  connector: Connector;
  fields: ConnectorField[];
  settings: Record<string, unknown>;
  connectorId: string;
  formSubtitle?: string;
  formDescriptionHtml?: string;
  formAlerts?: ConnectorFormAlert[];
  enabledLabel: string;
  enabledDescription: string;
  enabledMessage?: string;
  onSave: () => void;
  startTransition: TransitionStartFunction;
}

export default function DashboardConnectorForm({
  connector,
  fields,
  settings,
  connectorId,
  formSubtitle,
  formDescriptionHtml,
  formAlerts,
  enabledLabel,
  enabledDescription,
  enabledMessage,
  onSave,
  startTransition,
}: DashboardConnectorFormProps) {
  const { t } = useTranslation();

  const existingCredentials = (settings.credentials ?? {}) as Record<string, DashboardCredential>;

  const apiKeyField = fields.find((f) => f.name === 'apiKey');

  const [formValues, setFormValues] = useState<Record<string, string>>(() => {
    const init: Record<string, string> = { isEnabled: String(connector.isEnabled) };
    for (const field of fields) {
      if (!field.providerOnly) {
        init[field.name] = String(settings[field.name] ?? field.default ?? '');
      }
    }
    return init;
  });

  const [credentialDrafts, setCredentialDrafts] = useState<Record<string, CredentialDraft>>(() => {
    const drafts: Record<string, CredentialDraft> = {};
    for (const [type, cred] of Object.entries(existingCredentials)) {
      drafts[type] = { id: cred.id, remove: false };
    }
    return drafts;
  });

  const [error, setError] = useState<string | null>(null);

  // Gate on the SAVED api key, not the form value, the server uses the stored key
  const savedApiKey = Boolean(settings.apiKey);

  const {
    data: servicesResponse,
    isLoading: servicesLoading,
    isError: servicesIsError,
  } = useQuery({
    queryKey: ['connectors', connectorId, 'proxy', 'getAvailableServices'],
    queryFn: () =>
      fetchConnectorProxy<DashboardService[] | string>(
        String(connector.connectorId!),
        'getAvailableServices',
      ),
    enabled: connector.connectorId !== null && savedApiKey,
  });

  const { data: errorStates } = useQuery({
    queryKey: ['connectors', connectorId, 'proxy', 'getCredentialErrorStates'],
    queryFn: () =>
      fetchConnectorProxy<string[]>(String(connector.connectorId!), 'getCredentialErrorStates'),
    enabled: connector.connectorId !== null && savedApiKey,
  });

  const credentialErrorTypes = Array.isArray(errorStates) ? errorStates : [];

  const servicesList = Array.isArray(servicesResponse) ? servicesResponse : [];
  const servicesError = typeof servicesResponse === 'string' ? servicesResponse : null;

  function handleChange(name: string, value: string) {
    setFormValues((prev) => ({ ...prev, [name]: value }));
  }

  function handleCredentialChange(
    type: string,
    field: keyof CredentialDraft,
    value: string | boolean,
  ) {
    setCredentialDrafts((prev) => ({
      ...prev,
      [type]: { remove: false, ...prev[type], [field]: value },
    }));
  }

  function handleSubmit(e: React.SyntheticEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    startTransition(async () => {
      const payload: Record<string, unknown> = { ...formValues };

      for (const service of servicesList) {
        const draft = credentialDrafts[service.type];
        if (!draft) {
          continue;
        }
        if (draft.id) {
          payload[`${service.type}_id`] = draft.id;
        }
        if (draft.userName) {
          payload[`${service.type}_userName`] = draft.userName;
        }
        if (draft.password) {
          payload[`${service.type}_password`] = draft.password;
        }
        if (draft.twoFactorSecret) {
          payload[`${service.type}_twoFactorSecret`] = draft.twoFactorSecret;
        }
        if (service.isUrl && draft.url) {
          payload[`${service.type}_url`] = draft.url;
        }
        if (draft.remove) {
          payload[`${service.type}_remove`] = 1;
        }
      }

      try {
        await updateConnector(connectorId, payload);
        onSave();
      } catch {
        setError(t('Failed to save connector settings. Please try again.'));
      }
    });
  }

  return (
    <form
      id="dashboard-connector-form"
      onSubmit={handleSubmit}
      className="flex flex-col gap-3 px-8 py-4"
    >
      {(formSubtitle || formDescriptionHtml) && (
        <div className="pb-2">
          {formSubtitle && <p className="text-2xl text-gray-800">{formSubtitle}</p>}
          {formDescriptionHtml && (
            <div
              className="text-sm text-gray-600 mt-1 [&_p]:mb-2 [&_a]:text-blue-600 [&_a]:underline [&_a]:hover:text-blue-800"
              dangerouslySetInnerHTML={{ __html: formDescriptionHtml }}
            />
          )}
        </div>
      )}

      {formAlerts?.map((alert, i) => (
        <InfoBanner key={i} type={alert.type}>
          {alert.text}
        </InfoBanner>
      ))}

      {apiKeyField && !apiKeyField.providerOnly && (
        <div className="flex flex-col gap-3">
          <h4 className="font-semibold text-gray-700">{t('Settings')}</h4>
          <p className="text-sm text-gray-600">
            {t(
              'Your API key allows for secure communication between the CMS and the Xibo dashboard' +
                ' service. It is used to register your credentials and retrieve dashboards.' +
                ' It is never possible to retrieve credentials.',
            )}
          </p>
          <TextInput
            name="apiKey"
            label={t('API Key')}
            helpText={apiKeyField.helpText}
            value={formValues.apiKey ?? ''}
            onChange={(val) => handleChange('apiKey', val)}
          />
        </div>
      )}

      <div className="flex flex-col gap-3">
        <h4 className="font-semibold text-gray-700">{t('Credentials')}</h4>

        {!savedApiKey ? (
          <InfoBanner type="info" className="mx-0">
            {t(
              'To see a list of available services please enter your API key,' +
                ' save this form and then come back here.',
            )}
          </InfoBanner>
        ) : servicesLoading ? (
          <div className="flex items-center gap-2 py-2">
            <div className="h-4 w-4 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
            <span className="text-sm text-gray-500">{t('Loading services…')}</span>
          </div>
        ) : servicesIsError || servicesError ? (
          <InfoBanner type="danger" className="mx-0">
            {servicesError ??
              t('Could not contact the dashboard service. Please try again shortly.')}
          </InfoBanner>
        ) : servicesList.length === 0 ? (
          <InfoBanner type="info" className="mx-0">
            {t('No services are available for your API key.')}
          </InfoBanner>
        ) : (
          <>
            <p className="text-sm text-gray-600">
              {t(
                'Select the type of dashboard you want to connect with and enter your credentials below.' +
                  ' Credentials are stored in our secure dashboard service and not in the CMS or Players.' +
                  ' Once you have entered the credentials and this form has been accepted,' +
                  ' you cannot retrieve them from the CMS.',
              )}
            </p>
            <InfoBanner type="neutral" className="mx-0">
              {t('Please note: changing credentials can take a few minutes after pressing save.')}
            </InfoBanner>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="bg-gray-50 border-b border-gray-200 text-sm text-gray-600 uppercase">
                    <th className="px-4 py-2 text-left font-semibold w-1/2">{t('Type & Info')}</th>
                    <th className="px-4 py-2 text-left font-semibold w-1/6">{t('Status')}</th>
                    <th className="px-4 py-2 text-left font-semibold">{t('User')}</th>
                  </tr>
                </thead>
                <tbody>
                  {servicesList.map((service) => {
                    const existing = existingCredentials[service.type];
                    const draft = credentialDrafts[service.type] ?? { remove: false };

                    return (
                      <tr key={service.type} className="border-b border-gray-200 last:border-0">
                        <td className="px-4 py-3 align-top">
                          <p className="font-semibold text-gray-800 mb-3">{service.name}</p>
                          <div className="flex flex-col gap-2">
                            <TextInput
                              name={`${service.type}_userName`}
                              label={t('Username')}
                              placeholder={t('Enter Username')}
                              value={
                                existing
                                  ? (draft.userName ?? existing.userName)
                                  : (draft.userName ?? '')
                              }
                              onChange={(val) =>
                                handleCredentialChange(service.type, 'userName', val)
                              }
                            />
                            <TextInput
                              name={`${service.type}_password`}
                              label={t('Password')}
                              placeholder={t('Enter Password')}
                              type="password"
                              value={draft.password ?? ''}
                              onChange={(val) =>
                                handleCredentialChange(service.type, 'password', val)
                              }
                            />
                            <TextInput
                              name={`${service.type}_twoFactorSecret`}
                              label={t('Second Factor Secret')}
                              placeholder={t('Enter Factor Secret')}
                              value={draft.twoFactorSecret ?? ''}
                              onChange={(val) =>
                                handleCredentialChange(service.type, 'twoFactorSecret', val)
                              }
                            />
                            {service.isUrl && (
                              <TextInput
                                name={`${service.type}_url`}
                                label={t('URL')}
                                placeholder={t('Paste URL')}
                                value={draft.url ?? ''}
                                onChange={(val) => handleCredentialChange(service.type, 'url', val)}
                              />
                            )}
                          </div>
                        </td>
                        <td className="px-4 py-3 align-top">
                          {existing ? (
                            credentialErrorTypes.includes(service.type) ? (
                              <Badge type="danger" variation="outline">
                                {t('Error')}
                              </Badge>
                            ) : (
                              <Badge type="success" variation="outline">
                                {t('Connected')}
                              </Badge>
                            )
                          ) : (
                            <span className="text-gray-400">-</span>
                          )}
                        </td>
                        <td className="px-4 py-3 align-top">
                          <div className="flex items-start justify-between gap-4">
                            <span className="text-gray-600">
                              {existing ? (
                                existing.userName
                              ) : (
                                <span className="text-gray-400">-</span>
                              )}
                            </span>
                            <button
                              type="button"
                              disabled={!existing}
                              onClick={() =>
                                handleCredentialChange(service.type, 'remove', !draft.remove)
                              }
                              className={`whitespace-nowrap text-sm font-medium ${
                                existing
                                  ? draft.remove
                                    ? 'text-gray-400 line-through'
                                    : 'text-blue-600 hover:text-blue-800'
                                  : 'text-gray-300 cursor-default'
                              }`}
                            >
                              {t('Remove')}
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </>
        )}
      </div>

      <div className="border-t border-gray-200 pt-4">
        <h4 className="font-semibold text-gray-700 mb-2">{enabledLabel}</h4>
        {enabledMessage && <p className="text-sm text-gray-600 mb-3">{enabledMessage}</p>}
        <Checkbox
          id="connector-isEnabled"
          label={enabledDescription}
          checked={Boolean(Number(formValues.isEnabled))}
          onChange={(e) => handleChange('isEnabled', e.target.checked ? '1' : '0')}
        />
      </div>

      {error && <InfoBanner type="danger">{error}</InfoBanner>}
    </form>
  );
}
