import React from 'react';
import { CircleAlert, CircleCheckBig, ShieldAlert, WifiOff } from 'lucide-react';

type StatusBadgeProps = {
  status: 'Normal' | 'Warning' | 'Critical' | 'Offline' | 'Healthy';
};

const statusStyles: Record<string, string> = {
  Normal: 'bg-[rgba(45,106,79,0.12)] text-[color:var(--agri-green)]',
  Healthy: 'bg-[rgba(45,106,79,0.12)] text-[color:var(--agri-green)]',
  Warning: 'bg-[rgba(233,162,59,0.14)] text-[color:var(--agri-warning)]',
  Critical: 'bg-[rgba(198,93,93,0.12)] text-[color:var(--agri-critical)]',
  Offline: 'bg-[rgba(122,138,131,0.12)] text-[color:var(--agri-offline)]',
};

const statusIcons: Record<string, React.ReactNode> = {
  Normal: <CircleCheckBig size={14} className="inline" />,
  Healthy: <CircleCheckBig size={14} className="inline" />,
  Warning: <CircleAlert size={14} className="inline" />,
  Critical: <ShieldAlert size={14} className="inline" />,
  Offline: <WifiOff size={14} className="inline" />,
};

export function StatusBadge({ status }: StatusBadgeProps) {
  return (
    <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ${statusStyles[status]}`}>
      {statusIcons[status]}
      {status}
    </span>
  );
}
