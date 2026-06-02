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

import { isAxiosError } from 'axios';
import { Check, ClipboardCopy, Plus, UserRound, X } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import InfoBanner from '@/components/ui/InfoBanner';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { fetchApplicationDetails, fetchScopes, updateApplication } from '@/services/applicationApi';
import { fetchUsers } from '@/services/userApi';
import type { Application, ApplicationScope } from '@/types/application';

interface EditApplicationModalProps {
  isOpen?: boolean;
  application: Application | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface EditDraft {
  name: string;
  authCode: boolean;
  clientCredentials: boolean;
  isConfidential: boolean;
  resetKeys: boolean;
  redirectUris: string[];
  description: string;
  logo: string;
  coverImage: string;
  companyName: string;
  termsUrl: string;
  privacyUrl: string;
}

type OwnerOption = {
  label: string;
  value: string;
};

type Tab = 'general' | 'advanced' | 'sharing';

function tabClass(activeTab: Tab, tab: Tab): string {
  const isActive = activeTab === tab;
  return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
    isActive ? 'border-blue-600 text-blue-500' : 'border-gray-200 text-gray-500 hover:text-blue-600'
  }`;
}

export default function EditApplicationModal({
  isOpen = true,
  application,
  onClose,
  onSuccess,
}: EditApplicationModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [activeTab, setActiveTab] = useState<Tab>('general');
  const [apiError, setApiError] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const [appDetails, setAppDetails] = useState<Application | null>(null);
  const [allScopes, setAllScopes] = useState<ApplicationScope[]>([]);
  const [selectedScopeIds, setSelectedScopeIds] = useState<Set<string>>(new Set());

  const [draft, setDraft] = useState<EditDraft>({
    name: '',
    authCode: false,
    clientCredentials: false,
    isConfidential: true,
    resetKeys: false,
    redirectUris: [],
    description: '',
    logo: '',
    coverImage: '',
    companyName: '',
    termsUrl: '',
    privacyUrl: '',
  });

  const [ownerOptions, setOwnerOptions] = useState<OwnerOption[]>([]);
  const [ownerLoading, setOwnerLoading] = useState(false);
  const [selectedOwner, setSelectedOwner] = useState<string | null>(null);

  const [copiedKey, setCopiedKey] = useState(false);
  const [copiedSecret, setCopiedSecret] = useState(false);

  useEffect(() => {
    if (!isOpen || !application) {
      return;
    }

    setActiveTab('general');
    setApiError('');
    setSelectedOwner(null);

    setIsLoading(true);

    Promise.all([fetchApplicationDetails(application.key), fetchScopes()])
      .then(([details, scopes]) => {
        setAppDetails(details);
        setAllScopes(scopes);

        const assignedIds = new Set((details.scopes ?? []).map((s) => s.id));
        setSelectedScopeIds(assignedIds);

        setSelectedOwner(String(details.userId));

        setDraft({
          name: details.name,
          authCode: details.authCode === 1,
          clientCredentials: details.clientCredentials === 1,
          isConfidential: details.isConfidential === 1,
          resetKeys: false,
          redirectUris: (details.redirectUris ?? []).map((r) => r.redirectUri),
          description: details.description ?? '',
          logo: details.logo ?? '',
          coverImage: details.coverImage ?? '',
          companyName: details.companyName ?? '',
          termsUrl: details.termsUrl ?? '',
          privacyUrl: details.privacyUrl ?? '',
        });
      })
      .catch(() => {
        setApiError(t('Failed to load application details.'));
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, [isOpen, application, t]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const loadOwners = async () => {
      setOwnerLoading(true);
      try {
        const { rows } = await fetchUsers({ start: 0, length: 100 });
        setOwnerOptions(rows.map((u) => ({ label: u.userName, value: String(u.userId) })));
      } finally {
        setOwnerLoading(false);
      }
    };

    loadOwners();
  }, [isOpen]);

  useEffect(() => {
    if (!draft.authCode && activeTab === 'advanced') {
      setActiveTab('general');
    }
  }, [draft.authCode, activeTab]);

  const handleCopy = async (text: string, field: 'key' | 'secret') => {
    try {
      await navigator.clipboard.writeText(text);
      if (field === 'key') {
        setCopiedKey(true);
        setTimeout(() => setCopiedKey(false), 2000);
      } else {
        setCopiedSecret(true);
        setTimeout(() => setCopiedSecret(false), 2000);
      }
    } catch {
      // clipboard not available
    }
  };

  const handleSave = () => {
    if (!application) {
      return;
    }

    startTransition(async () => {
      try {
        await updateApplication(application.key, {
          name: draft.name,
          authCode: draft.authCode ? 1 : 0,
          clientCredentials: draft.clientCredentials ? 1 : 0,
          isConfidential: draft.isConfidential ? 1 : 0,
          resetKeys: draft.resetKeys ? 1 : undefined,
          redirectUris: draft.redirectUris.filter((u) => u.trim() !== ''),
          description: draft.description || undefined,
          logo: draft.logo || undefined,
          coverImage: draft.coverImage || undefined,
          companyName: draft.companyName || undefined,
          termsUrl: draft.termsUrl || undefined,
          privacyUrl: draft.privacyUrl || undefined,
          selectedScopeIds: Array.from(selectedScopeIds),
          newOwnerId: selectedOwner ? parseInt(selectedOwner) : undefined,
        });

        onSuccess();
        onClose();
      } catch (err: unknown) {
        if (isAxiosError(err) && err.response?.data?.message) {
          setApiError(err.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred.'));
        }
      }
    });
  };

  const addRedirectUri = () => {
    setDraft((prev) => ({ ...prev, redirectUris: [...prev.redirectUris, ''] }));
  };

  const updateRedirectUri = (index: number, value: string) => {
    setDraft((prev) => {
      const updated = [...prev.redirectUris];
      updated[index] = value;
      return { ...prev, redirectUris: updated };
    });
  };

  const removeRedirectUri = (index: number) => {
    setDraft((prev) => ({
      ...prev,
      redirectUris: prev.redirectUris.filter((_, i) => i !== index),
    }));
  };

  const toggleScope = (scopeId: string) => {
    setSelectedScopeIds((prev) => {
      const next = new Set(prev);
      if (next.has(scopeId)) {
        next.delete(scopeId);
      } else {
        next.add(scopeId);
      }
      return next;
    });
  };

  if (!isOpen || !application) {
    return null;
  }

  const displaySecret = appDetails?.secret ?? application.secret ?? '';
  const displayKey = appDetails?.key ?? application.key ?? '';

  return (
    <Modal
      variant="tabbed"
      isOpen={isOpen}
      title={t('Edit Application')}
      onClose={onClose}
      size="lg"
      isPending={isPending || isLoading}
      error={apiError}
      scrollable={false}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending || isLoading,
        },
      ]}
    >
      <div className="flex flex-col flex-1 min-h-0 overflow-hidden px-4">
        <div
          role="tablist"
          aria-label={t('Application settings tabs')}
          className="flex px-4 overflow-x-auto shrink-0"
        >
          <button
            role="tab"
            type="button"
            aria-selected={activeTab === 'general'}
            aria-controls="tabpanel-application"
            className={tabClass(activeTab, 'general')}
            onClick={() => setActiveTab('general')}
          >
            {t('General')}
          </button>
          {draft.authCode && (
            <button
              role="tab"
              type="button"
              aria-selected={activeTab === 'advanced'}
              aria-controls="tabpanel-application"
              className={tabClass(activeTab, 'advanced')}
              onClick={() => setActiveTab('advanced')}
            >
              {t('Advanced')}
            </button>
          )}
          <button
            role="tab"
            type="button"
            aria-selected={activeTab === 'sharing'}
            aria-controls="tabpanel-application"
            className={tabClass(activeTab, 'sharing')}
            onClick={() => setActiveTab('sharing')}
          >
            {t('Sharing')}
          </button>
        </div>

        <div
          id="tabpanel-application"
          role="tabpanel"
          className="flex flex-col gap-4 flex-1 min-h-0 p-4 overflow-y-auto"
        >
          {isLoading && (
            <div className="flex items-center justify-center py-8 text-gray-400 text-sm">
              {t('Loading…')}
            </div>
          )}

          {!isLoading && activeTab === 'general' && (
            <>
              <TextInput
                name="name"
                label={t('Application Name')}
                value={draft.name}
                onChange={(val) => setDraft((prev) => ({ ...prev, name: val }))}
              />

              <div className="flex flex-col gap-1">
                <label className="text-sm font-medium text-gray-700">{t('Client ID')}</label>
                <div className="flex gap-2 items-center">
                  <input
                    readOnly
                    value={displayKey}
                    className="flex-1 py-2 px-3 text-sm bg-gray-100 border border-gray-200 rounded-lg font-mono text-gray-700 select-all"
                  />
                  <button
                    type="button"
                    onClick={() => handleCopy(displayKey, 'key')}
                    title={t('Copy to clipboard')}
                    className="shrink-0 p-2 rounded text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                  >
                    {copiedKey ? (
                      <Check size={16} className="text-green-500" />
                    ) : (
                      <ClipboardCopy size={16} />
                    )}
                  </button>
                </div>
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-sm font-medium text-gray-700">{t('Client Secret')}</label>
                <div className="flex gap-2 items-center">
                  <input
                    readOnly
                    value={displaySecret}
                    className="flex-1 py-2 px-3 text-sm bg-gray-100 border border-gray-200 rounded-lg font-mono text-gray-700 select-all"
                  />
                  <button
                    type="button"
                    onClick={() => handleCopy(displaySecret, 'secret')}
                    title={t('Copy to clipboard')}
                    className="shrink-0 p-2 rounded text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                  >
                    {copiedSecret ? (
                      <Check size={16} className="text-green-500" />
                    ) : (
                      <ClipboardCopy size={16} />
                    )}
                  </button>
                </div>
              </div>

              <InfoBanner type="info">
                {t(
                  'Selecting only one of the Authorization Code or Client Credentials grants improves security by allowing access tokens to be revoked more effectively.',
                )}
              </InfoBanner>

              <div className="flex flex-col gap-2">
                <Checkbox
                  id="authCode"
                  title={t('Authorization Code Grant')}
                  label={t('Allow the Authorization Code grant type for this application.')}
                  checked={draft.authCode}
                  onChange={() => setDraft((prev) => ({ ...prev, authCode: !prev.authCode }))}
                />
                <Checkbox
                  id="clientCredentials"
                  title={t('Client Credentials Grant')}
                  label={t('Allow the Client Credentials grant type for this application.')}
                  checked={draft.clientCredentials}
                  onChange={() =>
                    setDraft((prev) => ({ ...prev, clientCredentials: !prev.clientCredentials }))
                  }
                />
                <Checkbox
                  id="isConfidential"
                  title={t('Confidential Client')}
                  label={t('A confidential client can keep a secret (e.g. a server-side app).')}
                  checked={draft.isConfidential}
                  onChange={() =>
                    setDraft((prev) => ({ ...prev, isConfidential: !prev.isConfidential }))
                  }
                />
                <Checkbox
                  id="resetKeys"
                  title={t('Reset Secret')}
                  label={t(
                    'Generate a new client secret. The existing secret will stop working immediately.',
                  )}
                  checked={draft.resetKeys}
                  onChange={() => setDraft((prev) => ({ ...prev, resetKeys: !prev.resetKeys }))}
                />
              </div>

              <div className="flex flex-col gap-2">
                <label className="text-sm font-medium text-gray-700">{t('Redirect URIs')}</label>
                {draft.redirectUris.map((uri, index) => (
                  <div key={index} className="flex gap-2 items-center">
                    <TextInput
                      name={`redirectUri_${index}`}
                      value={uri}
                      placeholder="https://example.com/callback"
                      onChange={(val) => updateRedirectUri(index, val)}
                    />
                    <button
                      type="button"
                      onClick={() => removeRedirectUri(index)}
                      className="text-gray-400 hover:text-red-500 shrink-0"
                    >
                      <X size={16} />
                    </button>
                  </div>
                ))}
                <button
                  type="button"
                  onClick={addRedirectUri}
                  className="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 w-fit"
                >
                  <Plus size={14} />
                  {t('Add Redirect URI')}
                </button>
              </div>
            </>
          )}

          {!isLoading && activeTab === 'advanced' && (
            <>
              <p className="text-sm text-gray-500">
                {t('These fields are shown on the authorization consent screen.')}
              </p>
              <TextInput
                name="description"
                label={t('Description')}
                value={draft.description}
                onChange={(val) => setDraft((prev) => ({ ...prev, description: val }))}
              />
              <TextInput
                name="logo"
                label={t('Logo URL')}
                value={draft.logo}
                onChange={(val) => setDraft((prev) => ({ ...prev, logo: val }))}
              />
              <TextInput
                name="coverImage"
                label={t('Cover Image URL')}
                value={draft.coverImage}
                onChange={(val) => setDraft((prev) => ({ ...prev, coverImage: val }))}
              />
              <TextInput
                name="companyName"
                label={t('Company Name')}
                value={draft.companyName}
                onChange={(val) => setDraft((prev) => ({ ...prev, companyName: val }))}
              />
              <TextInput
                name="termsUrl"
                label={t('Terms URL')}
                value={draft.termsUrl}
                onChange={(val) => setDraft((prev) => ({ ...prev, termsUrl: val }))}
              />
              <TextInput
                name="privacyUrl"
                label={t('Privacy Policy URL')}
                value={draft.privacyUrl}
                onChange={(val) => setDraft((prev) => ({ ...prev, privacyUrl: val }))}
              />
            </>
          )}

          {!isLoading && activeTab === 'sharing' && (
            <>
              <InfoBanner type="info">
                <p>{t('Select the scopes (sharing) to grant to this application.')}</p>
                <p>
                  {t(
                    'Scopes grant access to specific routes — all GET, POST, and PUT calls for the selected scopes will be available to this application.',
                  )}
                </p>
                <p>
                  {t(
                    'Delete scopes are separate; without them the application cannot delete any content.',
                  )}
                </p>
              </InfoBanner>

              <SelectDropdown
                label={t('Select Owner')}
                value={selectedOwner as string}
                placeholder={ownerLoading ? t('Loading...') : t('Select Owner')}
                options={ownerOptions}
                onSelect={(value) => setSelectedOwner(value)}
                addLeftLabel
                leftLabelContent={
                  <span className="flex gap-x-2.5 items-center">
                    <UserRound size={14} /> {t('Owner')}
                  </span>
                }
                optionLabel={t('Select Owner')}
                addOptionAvatar
              />

              <div className="flex flex-col gap-2">
                <label className="text-sm font-medium text-gray-700">{t('Scopes')}</label>
                {allScopes.map((scope) => (
                  <Checkbox
                    key={scope.id}
                    id={`scope_${scope.id}`}
                    label={scope.description}
                    checked={selectedScopeIds.has(scope.id)}
                    onChange={() => toggleScope(scope.id)}
                  />
                ))}
                {allScopes.length === 0 && (
                  <p className="text-sm text-gray-400">{t('No scopes available.')}</p>
                )}
              </div>
            </>
          )}
        </div>
      </div>
    </Modal>
  );
}
