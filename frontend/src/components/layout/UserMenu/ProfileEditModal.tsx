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

import { useActionState, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import {
  fetch2FARecoveryCodes,
  fetch2FASetup,
  generate2FARecoveryCodes,
  updateUserProfile,
} from '@/services/userApi';

interface ProfileEditModalProps {
  isOpen: boolean;
  onClose: () => void;
}

interface ProfileActionState {
  apiError?: string;
  fieldErrors?: Record<string, string[]>;
}

const profileBaseSchema = z.object({
  password: z.string(),
  newPassword: z.string(),
  retypeNewPassword: z.string(),
  email: z.string(),
  twoFactorTypeId: z.string(),
  twoFactorRecoveryCodes: z.string(),
  code: z.string(),
});

function createProfileSchema(
  t: (key: string) => string,
  currentTwoFactorTypeId: string,
  currentEmail: string,
) {
  return profileBaseSchema.superRefine((data, ctx) => {
    // Check New Passwords Match
    if (data.newPassword !== '' || data.retypeNewPassword !== '') {
      if (data.newPassword !== data.retypeNewPassword) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: t('New passwords do not match.'),
          path: ['retypeNewPassword'],
        });
      }
    }

    // If email is provided, validate its format
    if (data.email !== '') {
      if (!z.string().email().safeParse(data.email).success) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: t('Please enter a valid email address.'),
          path: ['email'],
        });
      }
    }

    // Check Email Requirement for Email 2FA (Type 1)
    if (data.twoFactorTypeId === '1' && data.email === '') {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: t('Please provide valid email address'),
        path: ['email'],
      });
    }

    // Check Google Auth (Type 2) Access Code Requirement
    if (data.twoFactorTypeId === '2' && currentTwoFactorTypeId !== '2' && data.code === '') {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: t('Access Code is empty'),
        path: ['code'],
      });
    }

    // Check Current Password Requirement
    const changed2FA = data.twoFactorTypeId !== currentTwoFactorTypeId;
    const changedEmail = data.email !== currentEmail;

    // If 2FA changed, or Email changed while using Type 1, or they typed a new password...
    if (changed2FA || (changedEmail && data.twoFactorTypeId === '1') || data.newPassword !== '') {
      if (data.password === '') {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: t('Please enter your password'),
          path: ['password'],
        });
      }
    }
  });
}

export default function ProfileEditModal({ isOpen, onClose }: ProfileEditModalProps) {
  const { t } = useTranslation();
  const { user, updateUser } = useUserContext();

  const [email, setEmail] = useState(user?.email || '');
  const [twoFactorType, setTwoFactorType] = useState(
    String(user?.settings?.twoFactorTypeId ?? '0'),
  );

  const [qrCodeUrl, setQrCodeUrl] = useState<string | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [isLoading2FA, setIsLoading2FA] = useState(false);
  const [showCodesPanel, setShowCodesPanel] = useState(false);

  const twoFactorContainerRef = useRef<HTMLDivElement>(null);

  // Scroll when opening the Google Auth option
  useEffect(() => {
    if (twoFactorType === '2') {
      twoFactorContainerRef.current?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
      });
    }
  }, [twoFactorType, showCodesPanel]);

  // Fetch QR code
  useEffect(() => {
    if (twoFactorType === '2' && String(user?.settings?.twoFactorTypeId) !== '2') {
      setIsLoading2FA(true);
      fetch2FASetup()
        .then((data) => {
          if (data?.qRUrl) {
            setQrCodeUrl(data.qRUrl);
          }
        })
        .catch(console.error)
        .finally(() => setIsLoading2FA(false));
    }
  }, [twoFactorType, user?.settings?.twoFactorTypeId]);

  const handleGenerateCodes = async () => {
    setIsLoading2FA(true);
    try {
      const newCodes = await generate2FARecoveryCodes();
      setRecoveryCodes(newCodes);
      setShowCodesPanel(true);
    } catch (e) {
      console.error(e);
    } finally {
      setIsLoading2FA(false);
    }
  };

  const handleShowCodes = async () => {
    if (recoveryCodes.length > 0) {
      setShowCodesPanel(true);
      return;
    }

    if (String(user?.settings?.twoFactorTypeId) !== '2') {
      setShowCodesPanel(true);
      return;
    }

    setIsLoading2FA(true);
    try {
      const existingCodes = await fetch2FARecoveryCodes();

      setRecoveryCodes(existingCodes || []);
      setShowCodesPanel(true);
    } catch (e) {
      console.error(e);
    } finally {
      setIsLoading2FA(false);
    }
  };

  useEffect(() => {
    if (isOpen && user) {
      setTwoFactorType(String(user.settings?.twoFactorTypeId ?? '0'));
      setEmail(user.email || '');
    }
  }, [isOpen, user]);

  const [state, submitAction, isPending] = useActionState<ProfileActionState, FormData>(
    async (_prevState, formData) => {
      const rawData = {
        password: (formData.get('password') as string) || '',
        newPassword: (formData.get('newPassword') as string) || '',
        retypeNewPassword: (formData.get('retypeNewPassword') as string) || '',
        email: (formData.get('email') as string) || '',
        twoFactorTypeId: formData.get('twoFactorTypeId') as string,
        twoFactorRecoveryCodes: (formData.get('twoFactorRecoveryCodes') as string) || '',
        code: (formData.get('code') as string) || '',
      };

      const schema = createProfileSchema(
        t,
        String(user?.settings?.twoFactorTypeId ?? '0'),
        user?.email || '',
      );

      const result = schema.safeParse(rawData);

      if (!result.success) {
        return {
          fieldErrors: result.error.flatten().fieldErrors,
          apiError: undefined,
        };
      }

      try {
        await updateUserProfile(result.data);

        if (user) {
          updateUser({
            ...user,
            email: result.data.email,
            settings: {
              ...(user.settings || {}),
              twoFactorTypeId: parseInt(result.data.twoFactorTypeId, 10),
            },
          });
        }

        onClose();
        return { fieldErrors: {}, apiError: undefined };
      } catch (error: unknown) {
        console.error('Failed to update profile:', error);

        const apiError = error as { response?: { data?: { message?: string; property?: string } } };
        const message = apiError?.response?.data?.message || t('An unexpected error occurred.');
        const property = apiError?.response?.data?.property;

        if (property) {
          return { fieldErrors: { [property]: [message] }, apiError: undefined };
        }

        return { fieldErrors: {}, apiError: message };
      }
    },
    { fieldErrors: {}, apiError: undefined },
  );

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={t('Edit User Profile')}
      isPending={isPending}
      error={state.apiError}
      actions={[
        { label: t('Cancel'), variant: 'secondary', onClick: onClose, disabled: isPending },
        {
          label: t('Save'),
          variant: 'primary',
          isSubmit: true,
          formId: 'user-profile-form',
          disabled: isPending,
        },
      ]}
    >
      <div className="p-6">
        <form id="user-profile-form" action={submitAction} className="flex flex-col gap-5">
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
            helper={t(
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
                    <p className="text-xs text-gray-500 mb-4">
                      {t('Here are your recovery codes.')}
                    </p>
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
      </div>
    </Modal>
  );
}
