import { Bell, BriefcaseBusiness, HelpCircle, History, LayoutDashboard, Leaf, LogOut, Radar, Settings, Sparkles, UserCircle2 } from 'lucide-react';

const navItems = [
  { label: 'Dashboard', icon: LayoutDashboard, active: true },
  { label: 'My Crops', icon: Leaf },
  { label: 'Monitoring', icon: Radar },
  { label: 'Alerts', icon: Bell },
  { label: 'AI Assistant', icon: Sparkles },
  { label: 'History', icon: History },
  { label: 'Profile', icon: UserCircle2 },
];

const footerItems = [
  { label: 'Settings', icon: Settings },
  { label: 'Help', icon: HelpCircle },
  { label: 'Logout', icon: LogOut },
];

export function Sidebar() {
  return (
    <aside className="flex w-full max-w-[280px] flex-col border-r border-[color:var(--agri-border)] bg-[color:var(--agri-forest)] text-white shadow-[0_8px_30px_rgba(27,67,50,0.18)] lg:min-h-screen">
      <div className="flex items-center gap-3 border-b border-white/10 px-5 py-5">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[color:var(--agri-green)] text-lg font-bold shadow-inner shadow-white/10">
          A
        </div>
        <div>
          <div className="text-lg font-semibold">AgriSense</div>
          <div className="text-xs text-emerald-100/80">Smart Agriculture Monitoring & AI Assistance</div>
        </div>
      </div>

      <nav className="flex-1 space-y-2 px-3 py-4">
        {navItems.map(({ label, icon: Icon, active }) => (
          <button
            key={label}
            className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition-all duration-200 ${
              active
                ? 'bg-[rgba(149,213,178,0.18)] text-white ring-1 ring-[rgba(149,213,178,0.45)]'
                : 'text-emerald-50/85 hover:bg-[rgba(255,255,255,0.06)]'
            }`}
          >
            <Icon size={18} />
            <span>{label}</span>
          </button>
        ))}
      </nav>

      <div className="border-t border-white/10 px-3 py-4">
        {footerItems.map(({ label, icon: Icon }) => (
          <button
            key={label}
            className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-emerald-50/85 transition hover:bg-[rgba(255,255,255,0.06)]"
          >
            <Icon size={18} />
            <span>{label}</span>
          </button>
        ))}
      </div>
    </aside>
  );
}
