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

import { isAxiosError } from 'axios';
import { useActionState, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';

import { useUserContext } from '@/context/UserContext';
import {
  fetch2FARecoveryCodes,
  fetch2FASetup,
  generate2FARecoveryCodes,
  updateUserProfile,
} from '@/services/userApi';

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

export function useProfileForm({ onSaved }: { onSaved: () => void }) {
  const { t } = useTranslation();
  const { user, updateUser } = useUserContext();

  const [email, setEmail] = useState(user?.email || '');
  const [twoFactorType, setTwoFactorType] = useState(String(user?.twoFactorTypeId ?? '0'));

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
    if (twoFactorType === '2' && String(user?.twoFactorTypeId) !== '2') {
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
  }, [twoFactorType, user?.twoFactorTypeId]);

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

    if (String(user?.twoFactorTypeId) !== '2') {
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
    if (user) {
      setTwoFactorType(String(user.twoFactorTypeId ?? '0'));
      setEmail(user.email || '');
    }
  }, [user]);

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
        String(user?.twoFactorTypeId ?? '0'),
        user?.email || '',
      );

      const result = schema.safeParse(rawData);

      if (!result.success) {
        return {
          fieldErrors: result.error.flatten().fieldErrors,
          apiError: t('Please fix the highlighted errors before saving.'),
        };
      }

      try {
        await updateUserProfile(result.data);

        if (user) {
          updateUser({
            ...user,
            email: result.data.email,
            twoFactorTypeId: parseInt(result.data.twoFactorTypeId, 10),
          });
        }

        setRecoveryCodes([]);
        setShowCodesPanel(false);
        onSaved();
        return { fieldErrors: {}, apiError: undefined };
      } catch (error: unknown) {
        console.error('Failed to update profile:', error);

        const message =
          isAxiosError(error) && error.response?.data?.message
            ? error.response.data.message
            : t('An unexpected error occurred.');
        const property =
          isAxiosError(error) && error.response?.data?.property
            ? error.response.data.property
            : undefined;

        if (property) {
          return { fieldErrors: { [property]: [message] }, apiError: undefined };
        }

        return { fieldErrors: {}, apiError: message };
      }
    },
    { fieldErrors: {}, apiError: undefined },
  );

  return {
    user,
    state,
    submitAction,
    isPending,
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
  };
}

export type ProfileFormState = ReturnType<typeof useProfileForm>;
