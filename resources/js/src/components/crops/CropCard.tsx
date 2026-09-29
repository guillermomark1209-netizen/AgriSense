import { MapPin, Sprout } from 'lucide-react';
import { Crop } from '../../types';
import { StatusBadge } from '../common/StatusBadge';

export function CropCard({ name, scientificName, variety, plantingDate, growthStage, location, expectedHarvest, device, status, image }: Crop) {
  return (
    <div className="agri-card overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_10px_28px_rgba(27,67,50,0.08)]">
      <img src={image} alt={name} className="h-40 w-full object-cover" />
      <div className="space-y-4 p-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h3 className="text-lg font-semibold text-[color:var(--agri-primary-text)]">{name}</h3>
            <p className="text-xs italic text-[color:var(--agri-secondary-text)]">{scientificName}</p>
          </div>
          <StatusBadge status={status} />
        </div>

        <div className="grid gap-2 text-sm text-[color:var(--agri-secondary-text)]">
          <div className="flex items-center justify-between"><span>Variety</span><span className="font-medium text-[color:var(--agri-primary-text)]">{variety}</span></div>
          <div className="flex items-center justify-between"><span>Growth Stage</span><span className="font-medium text-[color:var(--agri-primary-text)]">{growthStage}</span></div>
          <div className="flex items-center justify-between"><span>Plot</span><span className="font-medium text-[color:var(--agri-primary-text)]">{location}</span></div>
          <div className="flex items-center justify-between"><span>Planted</span><span className="font-medium text-[color:var(--agri-primary-text)]">{plantingDate}</span></div>
          <div className="flex items-center justify-between"><span>Harvest</span><span className="font-medium text-[color:var(--agri-primary-text)]">{expectedHarvest}</span></div>
          <div className="flex items-center justify-between"><span>Device</span><span className="font-medium text-[color:var(--agri-primary-text)]">{device}</span></div>
        </div>

        <div className="flex gap-2 pt-2">
          <button className="agri-btn-secondary flex-1">View Details</button>
          <button className="agri-btn-primary flex-1">Monitoring</button>
        </div>
      </div>
    </div>
  );
}
