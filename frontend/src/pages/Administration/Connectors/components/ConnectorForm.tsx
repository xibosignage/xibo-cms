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

import type { TransitionStartFunction } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import InfoBanner from '@/components/ui/InfoBanner';
import Checkbox from '@/components/ui/forms/Checkbox';
import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import { updateConnector } from '@/services/connectorApi';
import type { Connector, ConnectorField, ConnectorFormAlert } from '@/types/connector';

interface ConnectorFormProps {
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

function ConnectorFieldInput({
  field,
  value,
  onChange,
}: {
  field: ConnectorField;
  value: unknown;
  onChange: (name: string, val: unknown) => void;
}) {
  if (field.type === 'checkbox') {
    return (
      <Checkbox
        id={field.name}
        label={field.label}
        checked={Boolean(value ?? field.default ?? false)}
        onChange={(e) => onChange(field.name, e.target.checked ? 1 : 0)}
      />
    );
  }

  if (field.type === 'number') {
    const num =
      value !== undefined && value !== null
        ? Number(value)
        : typeof field.default === 'number'
          ? field.default
          : undefined;
    return (
      <NumberInput
        name={field.name}
        label={field.label}
        helpText={field.helpText}
        value={num}
        onChange={(n) => onChange(field.name, n)}
      />
    );
  }

  if (field.type === 'select' && field.options) {
    return (
      <SelectDropdown
        label={field.label}
        helpText={field.helpText}
        value={String(value ?? field.default ?? '')}
        options={field.options}
        onSelect={(val) => onChange(field.name, val)}
      />
    );
  }

  return (
    <TextInput
      name={field.name}
      label={field.label}
      helpText={field.helpText}
      value={String(value ?? field.default ?? '')}
      onChange={(val) => onChange(field.name, val)}
    />
  );
}

export default function ConnectorForm({
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
}: ConnectorFormProps) {
  const { t } = useTranslation();
  const [formValues, setFormValues] = useState<Record<string, unknown>>({
    ...settings,
    isEnabled: connector.isEnabled,
  });
  const [error, setError] = useState<string | null>(null);

  function handleChange(name: string, value: unknown) {
    setFormValues((prev) => ({ ...prev, [name]: value }));
  }

  function handleSubmit(e: React.SyntheticEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    startTransition(async () => {
      try {
        await updateConnector(connectorId, formValues);
        onSave();
      } catch {
        setError(t('Failed to save connector settings. Please try again.'));
      }
    });
  }

  const visibleFields = fields.filter((f) => !f.providerOnly);
  const allFieldsProviderOnly = fields.length > 0 && visibleFields.length === 0;
  const hasMixedProviderFields = visibleFields.length > 0 && fields.some((f) => f.providerOnly);

  return (
    <form id="connector-form" onSubmit={handleSubmit} className="flex flex-col gap-3 p-8 pt-2">
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

      {allFieldsProviderOnly && (
        <p className="text-sm text-gray-600">
          {t('Your platform provider has configured this connector for you.')}
        </p>
      )}

      {hasMixedProviderFields && (
        <InfoBanner type="info">
          {t('Some settings are managed by your service provider and cannot be changed here.')}
        </InfoBanner>
      )}

      {!allFieldsProviderOnly &&
        formAlerts?.map((alert, i) => (
          <InfoBanner key={i} type={alert.type}>
            {alert.text}
          </InfoBanner>
        ))}

      {visibleFields.map((field) => (
        <ConnectorFieldInput
          key={field.name}
          field={field}
          value={formValues[field.name]}
          onChange={handleChange}
        />
      ))}

      <div className="border-t border-gray-100 pt-4 flex flex-col gap-2">
        <h4 className="text-sm font-semibold text-gray-700">{enabledLabel}</h4>
        {enabledMessage && <p className="text-sm text-gray-600">{enabledMessage}</p>}
        <Checkbox
          id="connector-isEnabled"
          label={enabledDescription}
          checked={Boolean(formValues.isEnabled)}
          onChange={(e) => handleChange('isEnabled', e.target.checked ? 1 : 0)}
        />
      </div>

      {error && <InfoBanner type="danger">{error}</InfoBanner>}
    </form>
  );
}
