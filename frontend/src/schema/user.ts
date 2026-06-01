import type { TFunction } from 'i18next';
import { z } from 'zod';

export const getAddUserSchema = (t: TFunction) =>
  z.object({
    userName: z
      .string()
      .min(1, t('Username is required'))
      .max(50, t('Username must be less than 50 characters')),

    password: z.string().min(1, t('Password is required')),

    email: z.string().email(t('Please enter a valid email address')).or(z.literal('')).optional(),

    userTypeId: z.number(),

    groupId: z.number({ message: t('Initial user group is required') }),

    homePageId: z.string().optional(),

    libraryQuota: z.number().min(0, t('Library quota must be a positive number')).optional(),

    firstName: z.string().max(254).optional(),
    lastName: z.string().max(254).optional(),
    phone: z.string().max(254).optional(),
    ref1: z.string().max(254).optional(),
    ref2: z.string().max(254).optional(),
    ref3: z.string().max(254).optional(),
    ref4: z.string().max(254).optional(),
    ref5: z.string().max(254).optional(),

    isPasswordChangeRequired: z.number().optional(),
    newUserWizard: z.number().optional(),
    hideNavigation: z.number().optional(),
  });

export const getEditUserSchema = (t: TFunction) =>
  z.object({
    userName: z
      .string()
      .min(1, t('Username is required'))
      .max(50, t('Username must be less than 50 characters')),

    email: z.string().email(t('Please enter a valid email address')).or(z.literal('')).optional(),

    userTypeId: z.number().optional(),

    homePageId: z.string().optional(),

    libraryQuota: z.number().min(0, t('Library quota must be a positive number')).optional(),

    firstName: z.string().max(254).optional(),
    lastName: z.string().max(254).optional(),
    phone: z.string().max(254).optional(),
    ref1: z.string().max(254).optional(),
    ref2: z.string().max(254).optional(),
    ref3: z.string().max(254).optional(),
    ref4: z.string().max(254).optional(),
    ref5: z.string().max(254).optional(),

    retired: z.number().optional(),
    isPasswordChangeRequired: z.number().optional(),
    newUserWizard: z.number().optional(),
    hideNavigation: z.number().optional(),

    newPassword: z.string().optional(),
    retypeNewPassword: z.string().optional(),

    isSystemNotification: z.number().optional(),
    isDisplayNotification: z.number().optional(),
    isDataSetNotification: z.number().optional(),
    isCustomNotification: z.number().optional(),
    isLayoutNotification: z.number().optional(),
    isLibraryNotification: z.number().optional(),
    isReportNotification: z.number().optional(),
    isScheduleNotification: z.number().optional(),
  });

export const getAddUserWizardSchema = (t: TFunction) =>
  z.object({
    userName: z
      .string()
      .min(1, t('Username is required'))
      .max(50, t('Username must be less than 50 characters')),

    password: z.string().min(1, t('Password is required')),

    email: z.string().email(t('Please enter a valid email address')).or(z.literal('')).optional(),

    groupId: z.number({ message: t('Please select a role') }),
  });
