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

import { createPortal } from 'react-dom';
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
}

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title?: string;
  children: React.ReactNode;
  actions?: ModalAction[];
  size?: 'sm' | 'md' | 'lg';
  closeOnOverlay?: boolean;
  className?: string;
  scrollable?: boolean;
  isPending?: boolean;
}

export default function Modal({
  isOpen,
  onClose,
  title,
  children,
  actions,
  size = 'md',
  className,
  closeOnOverlay,
  scrollable = true,
  isPending = false,
}: ModalProps) {
  useKeydown('Escape', onClose, isOpen);

  if (!isOpen) {
    return null;
  }

  const sizeClasses = {
    sm: 'max-w-lg',
    md: 'max-w-2xl',
    lg: 'max-w-4xl',
  };

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div
        className="absolute inset-0 bg-black/50 backdrop-blur-xs"
        onClick={closeOnOverlay ? onClose : () => {}}
      />

      <dialog
        open
        className={twMerge(
          'relative flex flex-col w-full bg-white rounded-xl overflow-hidden outline-none max-h-[90vh] shadow-lg',
          sizeClasses[size],
          className,
        )}
      >
        {/* Pending transparent overlay */}
        {isPending && (
          <div className="absolute w-full h-full flex inset-0 items-center justify-center z-50"></div>
        )}

        {/* Header */}
        {title && (
          <div className="shrink-0 p-8 pb-3">
            <div className="text-lg font-semibold">{title}</div>
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
