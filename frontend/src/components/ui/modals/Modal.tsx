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

import { CircleX, type LucideIcon, X } from 'lucide-react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import type { ButtonProps } from '../Button';
import Button from '../Button';

import { useKeydown } from '@/hooks/useKeydown';

export interface ModalAction {
  label: string;
  onClick?: () => void;
  variant?: ButtonProps['variant'];
  isSubmit?: boolean;
  formId?: string;
  disabled?: boolean;
  className?: string;
  leftIcon?: LucideIcon;
  rightIcon?: LucideIcon;
}

interface ModalProps {
  isOpen?: boolean;
  onClose: () => void;
  title?: string;
  children: React.ReactNode;
  actions?: ModalAction[];
  size?: 'sm' | 'md' | 'lg' | 'xl';
  closeOnOverlay?: boolean;
  className?: string;
  scrollable?: boolean;
  isPending?: boolean;
  error?: string;
  variant?: 'standard' | 'tabbed' | 'confirmation';
  showCloseButton?: boolean;
}

export default function Modal({
  isOpen = true,
  onClose,
  title,
  children,
  actions,
  size = 'md',
  className,
  closeOnOverlay,
  scrollable = true,
  isPending = false,
  error,
  variant = 'standard',
  showCloseButton = false,
}: ModalProps) {
  const { t } = useTranslation();
  useKeydown('Escape', onClose, isOpen);

  const titleId = title ? `modal-title-${title.replace(/\s+/g, '-').toLowerCase()}` : undefined;

  if (!isOpen) {
    return null;
  }

  const sizeClasses = {
    sm: 'max-w-lg',
    md: 'max-w-2xl',
    lg: 'max-w-4xl',
    xl: 'max-w-300',
  };

  return createPortal(
    <div
      className={twMerge(
        'fixed inset-0 z-50 flex justify-center p-4',
        variant === 'confirmation' ? 'items-center' : 'items-start pt-[5vh]',
      )}
    >
      <div
        className="absolute inset-0 bg-black/50 backdrop-blur-xs"
        onClick={closeOnOverlay ? onClose : () => {}}
      />

      <dialog
        open
        aria-labelledby={titleId}
        className={twMerge(
          'relative flex flex-col w-full bg-white rounded-xl overflow-hidden outline-none shadow-lg',
          variant === 'tabbed' ? 'h-[90vh]' : 'max-h-[90vh]',
          sizeClasses[size],
          className,
        )}
      >
        {/* Pending transparent overlay */}
        {isPending && (
          <div className="absolute w-full h-full flex inset-0 items-center justify-center z-50"></div>
        )}

        {/* Header */}
        {(title || showCloseButton) && (
          <div className="shrink-0 flex items-start justify-between align-middle p-8 pb-3">
            {title && (
              <h2 id={titleId} className="text-lg font-semibold truncate">
                {title}
              </h2>
            )}
            {showCloseButton && (
              <button
                type="button"
                aria-label={t('Close')}
                onClick={onClose}
                className="size-6 shrink-0 text-gray-500 cursor-pointer hover:text-gray-600 transition-colors"
              >
                <X className="w-4 h-4" aria-hidden="true" />
              </button>
            )}
          </div>
        )}

        {/* Body */}
        <div
          className={twMerge(
            'flex-1 min-h-0 w-full flex flex-col',
            scrollable ? 'overflow-y-auto' : 'overflow-hidden',
          )}
        >
          {children}
        </div>

        {/* Error */}
        {error && (
          <div
            className="bg-red-50 border border-red-200 text-sm text-red-800 rounded-lg mx-4 my-3 p-4"
            role="alert"
            aria-labelledby="hs-with-list-label"
          >
            <div className="flex">
              <div className="shrink-0">
                <CircleX size={18} />
              </div>
              <div className="ms-4">
                <h3 id="hs-with-list-label" className="text-sm font-semibold">
                  {error}
                </h3>
              </div>
            </div>
          </div>
        )}

        {/* Footer */}
        {actions && actions.length > 0 && (
          <div className="shrink-0 flex flex-row justify-end bg-gray-50 gap-3 px-8 py-4 border-t border-gray-100">
            {actions.map((action, idx) => (
              <Button
                key={idx}
                type={action.isSubmit ? 'submit' : 'button'}
                form={action.formId}
                variant={action.variant}
                disabled={action.disabled}
                className={action.className}
                leftIcon={action.leftIcon}
                rightIcon={action.rightIcon}
                onClick={() => {
                  if (action.disabled) {
                    return;
                  }

                  if (action.onClick) {
                    action.onClick();
                  } else if (!action.isSubmit) {
                    onClose();
                  }
                }}
              >
                {action.label}
              </Button>
            ))}
          </div>
        )}
      </dialog>
    </div>,
    document.body,
  );
}
