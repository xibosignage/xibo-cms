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
import type { TFunction } from 'i18next';
import type { TransitionStartFunction } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import DisplayGroupSelect from './DisplayGroupSelect';

import InfoBanner from '@/components/ui/InfoBanner';
import Checkbox from '@/components/ui/forms/Checkbox';
import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import { fetchConnectorProxy, updateConnector } from '@/services/connectorApi';
import type { Connector, ConnectorField, SspPartner } from '@/types/connector';

interface SspConnectorFormProps {
  connector: Connector;
  connectorId: string;
  fields: ConnectorField[];
  settings: Record<string, unknown>;
  enabledLabel: string;
  enabledDescription: string;
  enabledMessage?: string;
  onSave: () => void;
  startTransition: TransitionStartFunction;
}

function getMediaTypesOptions(t: TFunction) {
  return [
    { value: 'imagesAndVideo', label: t('Images and Video') },
    { value: 'imageOnly', label: t('Images only') },
    { value: 'videoOnly', label: t('Videos only') },
  ];
}

function getSspIdFieldOptions(t: TFunction) {
  return [
    { value: 'displayId', label: t('Display ID') },
    { value: 'customId', label: t('Custom ID') },
    { value: 'ref1', label: t('Reference 1') },
    { value: 'ref2', label: t('Reference 2') },
    { value: 'ref3', label: t('Reference 3') },
    { value: 'ref4', label: t('Reference 4') },
    { value: 'ref5', label: t('Reference 5') },
  ];
}

export default function SspConnectorForm({
  connector,
  connectorId,
  fields,
  settings,
  enabledLabel,
  enabledDescription,
  enabledMessage,
  onSave,
  startTransition,
}: SspConnectorFormProps) {
  const { t } = useTranslation();

  const mediaTypesOptions = getMediaTypesOptions(t);
  const sspIdFieldOptions = getSspIdFieldOptions(t);

  const apiKeyField = fields.find((f) => f.name === 'apiKey');
  const cmsUrlField = fields.find((f) => f.name === 'cmsUrl');
  const apiKeyProviderOnly = apiKeyField?.providerOnly ?? false;
  const cmsUrlProviderOnly = cmsUrlField?.providerOnly ?? false;
  const savedApiKey = Boolean(settings.apiKey);

  const [activeTab, setActiveTab] = useState('general');
  const [formValues, setFormValues] = useState<Record<string, string>>(() => ({
    isEnabled: String(connector.isEnabled),
    apiKey: String(settings.apiKey ?? ''),
    cmsUrl: String(settings.cmsUrl ?? window.location.origin),
  }));
  const [displayGroups, setDisplayGroups] = useState<
    Record<string, { id: number | null; label: string }>
  >({});
  const [saveError, setSaveError] = useState<string | null>(null);

  const {
    data: partners,
    isLoading: partnersLoading,
    isError: partnersIsError,
  } = useQuery({
    queryKey: ['connectors', connectorId, 'proxy', 'getAvailablePartnersFilter'],
    queryFn: () =>
      fetchConnectorProxy<Record<string, SspPartner>>(
        String(connector.connectorId!),
        'getAvailablePartnersFilter',
      ),
    enabled: connector.connectorId !== null && savedApiKey,
  });

  const partnerEntries = partners ? Object.entries(partners) : [];

  function handleChange(name: string, value: string) {
    setFormValues((prev) => ({ ...prev, [name]: value }));
  }

  function handleSovChange(partnerId: string, seconds: number | undefined) {
    const sov = seconds ?? 0;
    setFormValues((prev) => ({
      ...prev,
      [`${partnerId}_sov`]: String(sov),
      [`${partnerId}_sovPercent`]: ((100 * sov) / 3600).toFixed(2),
    }));
  }

  function handleSovPercentChange(partnerId: string, percent: number | undefined) {
    const pct = percent ?? 0;
    setFormValues((prev) => ({
      ...prev,
      [`${partnerId}_sovPercent`]: String(pct),
      [`${partnerId}_sov`]: String(Math.round((3600 * pct) / 100)),
    }));
  }

  function getPartnerStr(partnerId: string, field: string, fallback = '') {
    const key = `${partnerId}_${field}`;
    return formValues[key] !== undefined ? formValues[key] : String(settings[key] ?? fallback);
  }

  function getPartnerBool(partnerId: string, field: string) {
    const key = `${partnerId}_${field}`;
    const raw = formValues[key] !== undefined ? formValues[key] : String(settings[key] ?? '0');
    return Boolean(Number(raw));
  }

  function handleSubmit(e: React.SyntheticEvent<HTMLFormElement>) {
    e.preventDefault();
    setSaveError(null);
    startTransition(async () => {
      const payload: Record<string, unknown> = { ...formValues };

      for (const [partnerId, dg] of Object.entries(displayGroups)) {
        if (dg.id !== null) {
          payload[`${partnerId}_displayGroupId`] = dg.id;
        }
      }

      for (const [partnerId] of partnerEntries) {
        const dgKey = `${partnerId}_displayGroupId`;
        if (!(partnerId in displayGroups) && settings[dgKey] !== undefined) {
          payload[dgKey] = settings[dgKey];
        }
        const sspKey = `${partnerId}_sspIdField`;
        if (formValues[sspKey] === undefined && settings[sspKey] !== undefined) {
          payload[sspKey] = settings[sspKey];
        }
      }

      try {
        await updateConnector(connectorId, payload);
        onSave();
      } catch {
        setSaveError(t('Failed to save connector settings. Please try again.'));
      }
    });
  }

  const tabs = [
    { id: 'general', label: t('General') },
    ...partnerEntries.map(([id, p]) => ({ id, label: p.name })),
  ];

  function tabClass(id: string): string {
    const isActive = activeTab === id;
    return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
      isActive
        ? 'border-xibo-blue-600 text-xibo-blue-500'
        : 'border-gray-200 text-gray-500 hover:text-xibo-blue-600'
    }`;
  }

  return (
    <form
      id="ssp-connector-form"
      onSubmit={handleSubmit}
      className="flex flex-col h-full overflow-y-hidden"
    >
      <nav role="tablist" className="flex px-4 overflow-x-auto shrink-0">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            role="tab"
            type="button"
            aria-selected={activeTab === tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={tabClass(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </nav>

      <div className="flex-1 overflow-y-auto flex flex-col gap-4 px-8 py-4">
        {activeTab === 'general' && (
          <>
            <div>
              <h3 className="text-base font-semibold text-gray-800">{t('Xibo SSP Connector')}</h3>
              <p className="text-sm text-gray-500">
                {t('work with world leading supply side platforms')}
              </p>
            </div>

            <p className="text-sm text-gray-600">
              {t(
                'Onboard with one of our supported SSPs, enter your API key and configure which' +
                  ' displays you want to activate. In most cases you will need to list your' +
                  ' displays with the SSP and copy your SSP ID into the CMS.',
              )}
            </p>
            <p className="text-sm text-gray-600">
              {t(
                'Please note that your players will require HTTP access to' +
                  ' https://exchange.xibo-adspace.com to receive ads from any SSP.',
              )}
            </p>

            {savedApiKey && partnersLoading && (
              <div className="flex items-center gap-2">
                <div className="h-4 w-4 animate-spin rounded-full border-2 border-xibo-blue-600 border-t-transparent" />
                <span className="text-sm text-gray-500">{t('Loading partners…')}</span>
              </div>
            )}
            {savedApiKey && partnersIsError && (
              <InfoBanner type="danger">
                {t('Cannot contact SSP service, please try again shortly.')}
              </InfoBanner>
            )}
            {savedApiKey && !partnersLoading && !partnersIsError && partners !== undefined && (
              <InfoBanner type="info">{t('Your API key is connected.')}</InfoBanner>
            )}
            {!savedApiKey && (
              <InfoBanner type="info">
                {t(
                  'To see a list of available partners please enter your API key,' +
                    ' save this form and then come back here.',
                )}
              </InfoBanner>
            )}

            {!apiKeyProviderOnly && (
              <TextInput
                name="apiKey"
                label={t('API Key')}
                helpText={apiKeyField?.helpText}
                value={formValues.apiKey ?? ''}
                onChange={(val) => handleChange('apiKey', val)}
              />
            )}

            {!cmsUrlProviderOnly && (
              <TextInput
                name="cmsUrl"
                label={t('CMS URL')}
                helpText={cmsUrlField?.helpText}
                value={formValues.cmsUrl ?? ''}
                onChange={(val) => handleChange('cmsUrl', val)}
              />
            )}

            <div className="border-t border-gray-100 pt-4 flex flex-col gap-2">
              <h4 className="text-sm font-semibold text-gray-700">{enabledLabel}</h4>
              {enabledMessage && <p className="text-sm text-gray-600">{enabledMessage}</p>}
              <Checkbox
                id="ssp-isEnabled"
                label={enabledDescription}
                checked={Boolean(Number(formValues.isEnabled))}
                onChange={(e) => handleChange('isEnabled', e.target.checked ? '1' : '0')}
              />
            </div>
          </>
        )}

        {partnerEntries.map(([partnerId, partner]) => {
          if (activeTab !== partnerId) {
            return null;
          }

          const dgState = displayGroups[partnerId] ?? {
            id:
              settings[`${partnerId}_displayGroupId`] !== undefined
                ? Number(settings[`${partnerId}_displayGroupId`])
                : null,
            label: '',
          };

          const sovVal = Number(getPartnerStr(partnerId, 'sov', '0'));
          const sovPct =
            formValues[`${partnerId}_sovPercent`] !== undefined
              ? Number(formValues[`${partnerId}_sovPercent`])
              : Number(((100 * sovVal) / 3600).toFixed(2));

          return (
            <div key={partnerId} className="flex flex-col gap-4">
              {partner.logo ? (
                <div className="flex justify-center py-2">
                  {partner.url ? (
                    <a href={partner.url} target="_blank" rel="noreferrer">
                      <img
                        src={partner.logo}
                        alt={partner.name}
                        className="max-h-16 max-w-60 object-contain"
                      />
                    </a>
                  ) : (
                    <img
                      src={partner.logo}
                      alt={partner.name}
                      className="max-h-16 max-w-60 object-contain"
                    />
                  )}
                </div>
              ) : (
                <h4 className="text-base font-semibold text-gray-800">
                  {partner.url ? (
                    <a
                      href={partner.url}
                      target="_blank"
                      rel="noreferrer"
                      className="text-blue-600 hover:underline"
                    >
                      {partner.name}
                    </a>
                  ) : (
                    partner.name
                  )}
                </h4>
              )}

              {partner.description && (
                <p className="text-sm text-gray-600">{partner.description}</p>
              )}

              <Checkbox
                id={`${partnerId}-enabled`}
                label={t('Enabled?')}
                checked={getPartnerBool(partnerId, 'enabled')}
                onChange={(e) => handleChange(`${partnerId}_enabled`, e.target.checked ? '1' : '0')}
              />

              <TextInput
                name={`${partnerId}_key`}
                label={t('API Key')}
                helpText={t('Enter your API Key from this SSP.')}
                value={getPartnerStr(partnerId, 'key')}
                onChange={(val) => handleChange(`${partnerId}_key`, val)}
              />

              <Checkbox
                id={`${partnerId}-isTest`}
                label={t('Test mode?')}
                checked={getPartnerBool(partnerId, 'isTest')}
                onChange={(e) => handleChange(`${partnerId}_isTest`, e.target.checked ? '1' : '0')}
              />

              {partner.isWidgetSupported && (
                <div className="flex flex-col gap-0.5">
                  <Checkbox
                    id={`${partnerId}-isUseWidget`}
                    label={t('Use the SSP widget to schedule ad requests manually?')}
                    checked={getPartnerBool(partnerId, 'isUseWidget')}
                    onChange={(e) =>
                      handleChange(`${partnerId}_isUseWidget`, e.target.checked ? '1' : '0')
                    }
                  />
                  <span className="text-xs text-gray-400 ml-6">
                    {t(
                      'When using the SSP widget you do not need to configure a share of voice, duration or min/max duration.',
                    )}
                  </span>
                </div>
              )}

              <div className="flex gap-4">
                <div className="flex-1">
                  <NumberInput
                    name={`${partnerId}_sov`}
                    label={t('Share of Voice (seconds/hour)')}
                    helpText={t(
                      'How many seconds per hour would you like to dedicate to this SSP?',
                    )}
                    value={sovVal}
                    min={0}
                    max={3600}
                    onChange={(val) => handleSovChange(partnerId, val)}
                  />
                </div>
                <div className="flex-1">
                  <NumberInput
                    name={`${partnerId}_sovPercent`}
                    label={t('Share of Voice (%)')}
                    helpText={t('As a percentage')}
                    value={sovPct}
                    min={0}
                    max={100}
                    onChange={(val) => handleSovPercentChange(partnerId, val)}
                  />
                </div>
              </div>

              <NumberInput
                name={`${partnerId}_duration`}
                label={t('Duration (s)')}
                helpText={t('The expected duration of each ad served by the SSP.')}
                value={Number(getPartnerStr(partnerId, 'duration', '')) || undefined}
                onChange={(val) => handleChange(`${partnerId}_duration`, String(val ?? ''))}
              />

              <NumberInput
                name={`${partnerId}_minDuration`}
                label={t('Min Duration (s)')}
                helpText={t('The minimum duration of an ad served by the SSP.')}
                value={Number(getPartnerStr(partnerId, 'minDuration', '')) || undefined}
                onChange={(val) => handleChange(`${partnerId}_minDuration`, String(val ?? ''))}
              />

              <NumberInput
                name={`${partnerId}_maxDuration`}
                label={t('Max Duration (s)')}
                helpText={t('The maximum duration of an ad served by the SSP.')}
                value={Number(getPartnerStr(partnerId, 'maxDuration', '')) || undefined}
                onChange={(val) => handleChange(`${partnerId}_maxDuration`, String(val ?? ''))}
              />

              <SelectDropdown
                label={t('Allowed content types')}
                helpText={t(
                  'Which content types should be allowed on these displays.' +
                    ' Most SSPs will be able to further refine this by display.',
                )}
                value={getPartnerStr(partnerId, 'mediaTypesAllowed', 'imagesAndVideo')}
                options={mediaTypesOptions}
                onSelect={(val) => handleChange(`${partnerId}_mediaTypesAllowed`, val)}
              />

              <DisplayGroupSelect
                label={t('Display Group')}
                helpText={t(
                  'Which displays would you like to enroll with this SSP.' +
                    ' Leave blank to enroll them all.',
                )}
                value={dgState.id}
                valueLabel={dgState.label}
                onChange={(id, label) =>
                  setDisplayGroups((prev) => ({ ...prev, [partnerId]: { id, label } }))
                }
              />

              <SelectDropdown
                label={t('ID field')}
                helpText={t('Which field would you like to use as the ID for this SSP?')}
                value={getPartnerStr(partnerId, 'sspIdField', 'displayId')}
                options={sspIdFieldOptions}
                onSelect={(val) => handleChange(`${partnerId}_sspIdField`, val)}
              />
            </div>
          );
        })}

        {saveError && <InfoBanner type="danger">{saveError}</InfoBanner>}
      </div>
    </form>
  );
}
