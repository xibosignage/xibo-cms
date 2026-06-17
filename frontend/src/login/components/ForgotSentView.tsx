interface Props {
  onBack: () => void;
}

export function ForgotSentView({ onBack }: Props) {
  return (
    <div>
      <p className="mb-3 text-sm text-gray-600">
        A reminder email will be sent to the associated email address if this user exists.
      </p>
      <p className="text-center text-sm">
        <button
          type="button"
          onClick={onBack}
          className="text-blue-600 hover:underline bg-transparent border-0 p-0 cursor-pointer"
        >
          Return to login
        </button>
      </p>
    </div>
  );
}
