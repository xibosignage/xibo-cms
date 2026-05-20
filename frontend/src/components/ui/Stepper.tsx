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

import { Check } from 'lucide-react';
import { twMerge } from 'tailwind-merge';

export type StepStatus = 'completed' | 'active' | 'reachable' | 'inactive';

export interface StepDefinition {
  label: string;
  status: StepStatus;
}

interface StepperProps {
  steps: StepDefinition[];
  activeIndex?: number;
  className?: string;
  onStepClick?: (index: number) => void;
}

function StepCircle({ step, index }: { step: StepDefinition; index: number }) {
  if (step.status === 'completed') {
    return (
      <div className="flex size-8 items-center justify-center rounded-full bg-teal-500 text-white mx-2">
        <Check size={16} />
      </div>
    );
  }

  if (step.status === 'active') {
    return (
      <div className="flex size-8 items-center justify-center rounded-full bg-xibo-blue-600 text-white text-sm font-semibold mx-2">
        {index + 1}
      </div>
    );
  }

  if (step.status === 'reachable') {
    return (
      <div className="flex size-8 items-center justify-center rounded-full border-2 border-teal-500 bg-white text-teal-600 text-sm font-semibold mx-2">
        {index + 1}
      </div>
    );
  }

  return (
    <div className="flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 text-sm font-semibold mx-2">
      {index + 1}
    </div>
  );
}

function getLineColor(leftStatus: StepStatus, rightStatus: StepStatus): string {
  if (leftStatus === 'completed' && rightStatus === 'completed') return 'bg-teal-500';
  if (leftStatus === 'completed' && rightStatus === 'active') return 'bg-xibo-blue-600';
  if (leftStatus === 'completed' && rightStatus === 'reachable') return 'bg-teal-500';
  return 'bg-gray-200';
}

export default function Stepper({ steps, activeIndex, className, onStepClick }: StepperProps) {
  return (
    <div className={twMerge('flex', className)}>
      {steps.map((step, index) => {
        const isActive = index === activeIndex;
        const isFirst = index === 0;
        const isLast = index === steps.length - 1;
        const isClickable =
          !!onStepClick && (step.status === 'completed' || step.status === 'reachable');

        const prevStep = steps[index - 1];
        const nextStep = steps[index + 1];

        const leftLineColor =
          !isFirst && prevStep ? getLineColor(prevStep.status, step.status) : '';

        const rightLineColor =
          !isLast && nextStep ? getLineColor(step.status, nextStep.status) : '';

        return (
          <div
            key={index}
            className={twMerge(
              'flex-1 flex flex-col items-center gap-1.5',
              isClickable && 'cursor-pointer',
            )}
            onClick={isClickable ? () => onStepClick(index) : undefined}
          >
            <div className="flex items-center w-full">
              {isFirst ? (
                <div className="flex-1" />
              ) : (
                <div className={twMerge('flex-1 h-0.5', leftLineColor)} />
              )}
              <StepCircle step={step} index={index} />
              {isLast ? (
                <div className="flex-1" />
              ) : (
                <div className={twMerge('flex-1 h-0.5', rightLineColor)} />
              )}
            </div>
            <span
              className={twMerge(
                'text-sm whitespace-nowrap',
                (step.status === 'active' || isActive) && 'font-semibold text-gray-900',
                step.status === 'completed' && 'text-gray-600',
                step.status === 'reachable' && 'text-teal-600',
                isClickable && 'hover:underline',
                step.status === 'inactive' && 'text-gray-400',
              )}
            >
              {step.label}
            </span>
          </div>
        );
      })}
    </div>
  );
}
