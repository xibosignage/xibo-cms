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

import { useTranslation } from 'react-i18next';

import type { ProfileFormState } from '../hooks/useProfileForm';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';

interface ProfileFormFieldsProps {
  formId: string;
  profileForm: ProfileFormState;
}

export default function ProfileFormFields({ formId, profileForm }: ProfileFormFieldsProps) {
  const { t } = useTranslation();
  const {
    user,
    state,
    submitAction,
    email,
    setEmail,
    twoFactorType,
    setTwoFactorType,
    qrCodeUrl,
    recoveryCodes,
    isLoading2FA,
    showCodesPanel,
    twoFactorContainerRef,
    handleGenerateCodes,
    handleShowCodes,
  } = profileForm;

  return (
    <form id={formId} action={submitAction} className="flex flex-col gap-5">
      <TextInput
        name="userName"
        label={t('User Name')}
        value={user?.userName || ''}
        onChange={() => {}}
        disabled
      />

      <hr className="border-gray-100" />

      <TextInput
        name="password"
        type="password"
        label={t('Current Password')}
        error={state.fieldErrors?.password?.[0]}
        helpText={t(
          'If you are changing your password or two factor settings, then please enter your current password',
        )}
      />
      <TextInput
        name="newPassword"
        type="password"
        label={t('New Password')}
        error={state.fieldErrors?.newPassword?.[0]}
        helpText={t('Please enter your new password')}
      />
      <TextInput
        name="retypeNewPassword"
        type="password"
        label={t('Retype New Password')}
        error={state.fieldErrors?.retypeNewPassword?.[0]}
        helpText={t('Please repeat the new Password')}
      />

      <hr className="border-gray-100" />

      <TextInput
        name="email"
        type="email"
        label={t('Email')}
        value={email}
        onChange={setEmail}
        error={state.fieldErrors?.email?.[0]}
        helpText={t('The Email Address for this user.')}
      />

      <hr className="border-gray-100" />

      <SelectDropdown
        label={t('Two Factor Authentication')}
        value={twoFactorType}
        onSelect={setTwoFactorType}
        helpText={t(
          'Enable an option to provide a two factor authentication code to log into the CMS for added security.',
        )}
        options={[
          { label: t('Off'), value: '0' },
          { label: t('Email'), value: '1' },
          { label: t('Google Authenticator'), value: '2' },
        ]}
      />

      <input type="hidden" name="twoFactorTypeId" value={twoFactorType} />

      <input
        type="hidden"
        name="twoFactorRecoveryCodes"
        value={recoveryCodes.length > 0 ? JSON.stringify(recoveryCodes) : ''}
      />

      {twoFactorType === '2' && (
        <div
          ref={twoFactorContainerRef}
          className="mt-4 flex flex-col items-center rounded-xl bg-gray-50 p-6 border border-gray-200"
        >
          {user?.twoFactorTypeId !== 2 && (
            <>
              <p className="text-sm font-medium text-gray-900 mb-4">
                {t('Please scan the following image with your app:')}
              </p>

              {/* QR Code Rendering */}
              <div className="h-40 w-40 bg-white border border-gray-300 flex items-center justify-center mb-6 overflow-hidden">
                {isLoading2FA && !qrCodeUrl ? (
                  <span className="text-xs text-gray-400">{t('Loading...')}</span>
                ) : qrCodeUrl ? (
                  <img src={qrCodeUrl} alt="2FA QR Code" className="h-full w-full object-contain" />
                ) : (
                  <span className="text-xs text-gray-400">{t('No QR Code available')}</span>
                )}
              </div>

              <div className="w-full max-w-xs">
                <TextInput
                  name="code"
                  label={t('Access Code')}
                  error={state.fieldErrors?.code?.[0]}
                />
              </div>
            </>
          )}

          {/* Recovery Codes Section */}
          <div className="w-full mt-6 pt-6 border-t border-gray-200 flex flex-col gap-4">
            <p className="text-xs text-gray-500 text-center">
              {t(
                'Please use the buttons below to generate or show your two factor recovery codes.',
              )}
            </p>

            <div className="flex justify-center gap-3">
              <button
                type="button"
                onClick={handleGenerateCodes}
                disabled={isLoading2FA}
                className="rounded-md bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700 hover:bg-green-100 border border-green-200 disabled:opacity-50"
              >
                + {t('Generate')}
              </button>
              <button
                type="button"
                onClick={handleShowCodes}
                disabled={isLoading2FA}
                className="rounded-md bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100 border border-blue-200 disabled:opacity-50"
              >
                + {t('Show')}
              </button>
            </div>

            {/* Recovery Codes */}
            {showCodesPanel && (
              <div className="bg-white p-4 rounded border border-gray-200 mt-2 text-center">
                <p className="text-xs text-gray-500 mb-4">{t('Here are your recovery codes.')}</p>
                {isLoading2FA ? (
                  <p className="text-sm text-gray-400">{t('Loading...')}</p>
                ) : recoveryCodes.length > 0 ? (
                  <div className="flex flex-col gap-1 font-mono text-sm text-gray-800 bg-gray-50 p-3 rounded border">
                    {recoveryCodes.map((rc, idx) => (
                      <span key={idx}>{rc}</span>
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-red-500">{t('No codes found.')}</p>
                )}
              </div>
            )}
          </div>
        </div>
      )}
    </form>
  );
}
