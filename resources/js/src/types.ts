export type NavItem = {
  label: string;
  icon: string;
  active?: boolean;
};

export type SensorStatus = 'Normal' | 'Warning' | 'Critical' | 'Offline';

export type SensorCardData = {
  name: string;
  value: string;
  unit: string;
  status: SensorStatus;
  updated: string;
  trend: string;
};

export type Crop = {
  id: string;
  name: string;
  scientificName: string;
  variety: string;
  plantingDate: string;
  growthStage: string;
  location: string;
  expectedHarvest: string;
  device: string;
  status: 'Healthy' | 'Warning' | 'Critical';
  image: string;
};

export type AlertItem = {
  id: string;
  title: string;
  crop: string;
  location: string;
  currentReading: string;
  configuredMinimum: string;
  detected: string;
  severity: 'Critical' | 'Warning' | 'Resolved';
};

export type Message = {
  id: string;
  sender: 'user' | 'assistant';
  text: string;
  time: string;
};
