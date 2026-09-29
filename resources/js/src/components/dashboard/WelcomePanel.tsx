import { CalendarDays, Sprout, Activity, AlertTriangle } from 'lucide-react';

const stats = [
  { label: 'Crops', value: '12', icon: Sprout },
  { label: 'Active IoT Devices', value: '08', icon: Activity },
  { label: 'Current Alerts', value: '03', icon: AlertTriangle },
];

export function WelcomePanel() {
  return (
    <section className="agri-card overflow-hidden">
      <div className="flex flex-col gap-6 bg-[linear-gradient(135deg,rgba(149,213,178,0.28),rgba(255,255,255,0.74))] p-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <p className="mb-1 text-sm font-medium uppercase tracking-[0.15em] text-[color:var(--agri-green)]">AgriSense</p>
          <h3 className="text-2xl font-semibold text-[color:var(--agri-primary-text)]">Good morning! 🌱</h3>
          <p className="mt-2 max-w-xl text-sm text-[color:var(--agri-secondary-text)]">
            Monitor your crops and keep your farm healthy.
          </p>
        </div>

        <div className="flex items-center gap-2 rounded-full border border-[color:var(--agri-border)] bg-white/80 px-3 py-2 text-sm text-[color:var(--agri-secondary-text)]">
          <CalendarDays size={16} className="text-[color:var(--agri-green)]" />
          {new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
        </div>
      </div>

      <div className="grid gap-4 border-t border-[color:var(--agri-border)] p-6 md:grid-cols-3">
        {stats.map(({ label, value, icon: Icon }) => (
          <div key={label} className="rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] p-4">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-sm text-[color:var(--agri-secondary-text)]">{label}</span>
              <div className="rounded-full bg-[color:var(--agri-pale)] p-2 text-[color:var(--agri-green)]">
                <Icon size={16} />
              </div>
            </div>
            <div className="text-2xl font-semibold text-[color:var(--agri-primary-text)]">{value}</div>
          </div>
        ))}
      </div>
    </section>
  );
}
