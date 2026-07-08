interface Props {
  logoUrl: string;
  supportUrl: string;
  children: React.ReactNode;
}

export function LoginCard({ logoUrl, supportUrl, children }: Props) {
  return (
    <div className="login-card">
      <p className="login-card-logo">
        <a href={supportUrl}>
          <img
            src={logoUrl}
            alt="Logo"
            onError={(e) => {
              const img = e.currentTarget;
              if (!img.src.endsWith('.png')) {
                img.src = img.src.replace(/\.[^.]+$/, '.png');
              }
            }}
          />
        </a>
      </p>
      {children}
    </div>
  );
}
