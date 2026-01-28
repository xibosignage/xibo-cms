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

import { CheckCircle, Info, AlertTriangle, XCircle, X } from 'lucide-react';
import { Toaster as SonnerToaster, toast } from 'sonner';

const VARIANTS = {
  info: { base: 'bg-xibo-blue-600', icon: Info },
  success: { base: 'bg-teal-500', icon: CheckCircle },
  warning: { base: 'bg-yellow-500', icon: AlertTriangle },
  error: { base: 'bg-red-500', icon: XCircle },
};
type NotifyOptions = {
  description?: string;
  action?: { label: string; onClick: () => void };
};

type ToastCardProps = {
  id: string | number;
  type: keyof typeof VARIANTS;
  title: string;
  description?: string;
  action?: { label: string; onClick: () => void };
};

const createToast =
  (type: 'success' | 'error' | 'warning' | 'info') => (title: string, opts?: NotifyOptions) => {
    toast.custom((id) => <ToastCard id={id} type={type} title={title} {...opts} />);
  };

const ToastCard = ({ id, type, title, description, action }: ToastCardProps) => {
  const variant = VARIANTS[type];
  const Icon = variant.icon;

  return (
    <div className="flex w-full max-w-[400px] overflow-hidden rounded-sm bg-slate-900 shadow-xl ring-1 ring-black/5">
      <div className={`flex w-12 items-center justify-center ${variant.base}`}>
        <Icon className="h-5 w-5 text-slate-200" />
      </div>

      <div className="flex flex-1 items-center justify-between p-4">
        <div className="flex flex-col items-center">
          <p className="text-sm font-semibold text-slate-200">{title}</p>
          {description && <p className="text-xs text-slate-200">{description}</p>}
        </div>

        <div className="flex items-center gap-3 pl-4">
          {action && (
            <button
              onClick={(e) => {
                e.stopPropagation();
                action.onClick();
                toast.dismiss(id);
              }}
              className="whitespace-nowrap text-sm font-medium text-xibo-blue-100"
            >
              <span className="opacity-50 mr-1">[Ctrl+Z]</span>
              {action.label}
            </button>
          )}
          <button
            onClick={() => toast.dismiss(id)}
            className="text-gray-500 hover:text-gray-100 transition-colors"
          >
            <X size={16} />
          </button>
        </div>
      </div>
    </div>
  );
};

export const Notification = () => {
  return (
    <SonnerToaster
      position="bottom-right"
      toastOptions={{
        unstyled: true,
        className: 'bg-transparent w-full p-0',
      }}
    />
  );
};

export const notify = {
  success: createToast('success'),
  error: createToast('error'),
  warning: createToast('warning'),
  info: createToast('info'),
};
