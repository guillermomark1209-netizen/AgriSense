type PageHeaderProps = {
  title: string;
  subtitle: string;
};

export function PageHeader({ title, subtitle }: PageHeaderProps) {
  return (
    <div className="mb-6 flex flex-col gap-1">
      <h1 className="text-2xl font-semibold text-[color:var(--agri-primary-text)] sm:text-3xl">{title}</h1>
      <p className="text-sm text-[color:var(--agri-secondary-text)]">{subtitle}</p>
    </div>
  );
}
