import { ArrowUpRight, Droplets, Gauge, Leaf, Lightbulb, ThermometerSun } from 'lucide-react';
import { SensorCardData } from '../../types';
import { StatusBadge } from '../common/StatusBadge';

const icons = {
  Temperature: ThermometerSun,
  Humidity: Droplets,
  'Soil Moisture': Gauge,
  'Soil pH': Leaf,
  'Light Intensity': Lightbulb,
};

export function SensorCard({ name, value, unit, status, updated, trend }: SensorCardData) {
  const Icon = icons[name] || Leaf;

  return (
    <div className="agri-card p-4 transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(27,67,50,0.08)]">
      <div className="mb-4 flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="rounded-xl bg-[color:var(--agri-pale)] p-2.5 text-[color:var(--agri-green)]">
            <Icon size={18} />
          </div>
          <div>
            <div className="text-sm font-medium text-[color:var(--agri-primary-text)]">{name}</div>
            <div className="text-xs text-[color:var(--agri-secondary-text)]">Last updated {updated}</div>
          </div>
        </div>
        <StatusBadge status={status} />
      </div>

      <div className="flex items-end justify-between gap-3">
        <div>
          <div className="text-2xl font-semibold text-[color:var(--agri-primary-text)]">
            {value}
            <span className="ml-1 text-base font-medium text-[color:var(--agri-secondary-text)]">{unit}</span>
          </div>
        </div>

        <div className="flex items-center gap-1 text-xs font-medium text-[color:var(--agri-green)]">
          <ArrowUpRight size={13} />
          {trend}
        </div>
      </div>
    </div>
  );
}
