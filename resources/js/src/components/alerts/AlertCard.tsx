import { ShieldAlert, TriangleAlert } from 'lucide-react';
import { AlertItem } from '../../types';

const severityStyles = {
  Critical: 'bg-[rgba(198,93,93,0.12)] text-[color:var(--agri-critical)]',
  Warning: 'bg-[rgba(233,162,59,0.14)] text-[color:var(--agri-warning)]',
  Resolved: 'bg-[rgba(45,106,79,0.12)] text-[color:var(--agri-green)]',
};

export function AlertCard({ title, crop, location, currentReading, configuredMinimum, detected, severity }: AlertItem) {
  return (
    <div className="agri-card p-4">
      <div className="mb-3 flex items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <div className="rounded-xl bg-[color:var(--agri-secondary-bg)] p-2.5 text-[color:var(--agri-warning)]">
            <TriangleAlert size={18} />
          </div>
          <div>
            <h3 className="text-base font-semibold text-[color:var(--agri-primary-text)]">{title}</h3>
            <p className="text-sm text-[color:var(--agri-secondary-text)]">{crop} — {location}</p>
          </div>
        </div>
        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${severityStyles[severity]}`}>{severity}</span>
      </div>

      <div className="grid gap-2 border-t border-[color:var(--agri-border)] pt-3 text-sm text-[color:var(--agri-secondary-text)]">
        <div className="flex justify-between"><span>Current reading</span><span className="font-medium text-[color:var(--agri-primary-text)]">{currentReading}</span></div>
        <div className="flex justify-between"><span>Configured minimum</span><span className="font-medium text-[color:var(--agri-primary-text)]">{configuredMinimum}</span></div>
        <div className="flex justify-between"><span>Detected</span><span className="font-medium text-[color:var(--agri-primary-text)]">{detected}</span></div>
      </div>

      <div className="mt-4 flex gap-2">
        <button className="agri-btn-secondary flex-1">View Details</button>
        <button className="agri-btn-primary flex-1">Ask AI</button>
      </div>
    </div>
  );
}
