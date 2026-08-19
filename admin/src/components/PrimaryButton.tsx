export function PrimaryButton({
  children,
  onClick,
  disabled,
  type = 'button',
  href,
}: {
  children: React.ReactNode;
  onClick?: () => void;
  disabled?: boolean;
  type?: 'button' | 'submit';
  href?: string;
}) {
  if (href) {
    return (
      <a className="revit-btn revit-btn--primary" href={href}>
        {children}
      </a>
    );
  }
  return (
    <button type={type} className="revit-btn revit-btn--primary" onClick={onClick} disabled={disabled}>
      {children}
    </button>
  );
}

export function SecondaryButton({
  children,
  onClick,
  disabled,
  type = 'button',
  href,
}: {
  children: React.ReactNode;
  onClick?: () => void;
  disabled?: boolean;
  type?: 'button' | 'submit';
  href?: string;
}) {
  if (href) {
    return (
      <a className="revit-btn revit-btn--secondary" href={href}>
        {children}
      </a>
    );
  }
  return (
    <button type={type} className="revit-btn revit-btn--secondary" onClick={onClick} disabled={disabled}>
      {children}
    </button>
  );
}
