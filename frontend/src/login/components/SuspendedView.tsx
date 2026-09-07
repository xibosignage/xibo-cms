import { MessageView } from './MessageView';

export function SuspendedView() {
  return (
    <MessageView
      icon={
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="10" />
          <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
        </svg>
      }
      titleKey="suspendedTitle"
      messageKey="suspendedMessage"
    />
  );
}
