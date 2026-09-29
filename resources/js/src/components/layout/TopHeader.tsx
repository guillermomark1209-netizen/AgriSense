import { Bell, ChevronDown, Wifi } from 'lucide-react';

export function TopHeader() {
  return (
    <header className="mb-6 flex flex-col gap-4 border-b border-[color:var(--agri-border)] bg-[rgba(255,255,255,0.55)] px-4 py-4 backdrop-blur-sm sm:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-semibold text-[color:var(--agri-primary-text)]">Dashboard</h2>
          <p className="text-sm text-[color:var(--agri-secondary-text)]">Here&apos;s what&apos;s happening with your crops today.</p>
        </div>

        <div className="flex items-center gap-3 self-start sm:self-auto">
          <div className="inline-flex items-center gap-2 rounded-full bg-[rgba(45,106,79,0.1)] px-3 py-1.5 text-xs font-medium text-[color:var(--agri-green)]">
            <Wifi size={12} />
            System Online
          </div>

          <button className="relative rounded-full border border-[color:var(--agri-border)] bg-white p-2 text-[color:var(--agri-primary-text)] transition hover:bg-[color:var(--agri-secondary-bg)]">
            <Bell size={16} />
            <span className="absolute -right-0.5 -top-0.5 flex h-2.5 w-2.5 items-center justify-center rounded-full bg-[color:var(--agri-warning)]" />
          </button>

          <div className="flex items-center gap-2 rounded-full border border-[color:var(--agri-border)] bg-white px-2.5 py-1.5">
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[color:var(--agri-pale)] text-xs font-semibold text-[color:var(--agri-green)]">
              F
            </div>
            <div className="text-sm">
              <div className="font-medium text-[color:var(--agri-primary-text)]">Farmer</div>
              <div className="text-[11px] text-[color:var(--agri-secondary-text)]">Good morning!</div>
            </div>
            <ChevronDown size={16} className="text-[color:var(--agri-secondary-text)]" />
          </div>
        </div>
      </div>
    </header>
  );
}
