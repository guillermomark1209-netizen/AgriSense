import { AlertItem, Crop, SensorCardData } from './types';

export const navItems = [
  { label: 'Dashboard', icon: 'LayoutDashboard', active: true },
  { label: 'My Crops', icon: 'Leaf' },
  { label: 'Monitoring', icon: 'Radar' },
  { label: 'Alerts', icon: 'BellRing' },
  { label: 'AI Assistant', icon: 'Sparkles' },
  { label: 'History', icon: 'History' },
  { label: 'Profile', icon: 'UserCircle2' },
];

export const sensorCards: SensorCardData[] = [
  { name: 'Temperature', value: '28', unit: '°C', status: 'Normal', updated: '2 min ago', trend: '+0.8°' },
  { name: 'Humidity', value: '68', unit: '%', status: 'Normal', updated: '1 min ago', trend: '+2.4%' },
  { name: 'Soil Moisture', value: '42', unit: '%', status: 'Normal', updated: 'just now', trend: '+1.1%' },
  { name: 'Soil pH', value: '6.4', unit: 'pH', status: 'Normal', updated: '3 min ago', trend: 'Stable' },
  { name: 'Light Intensity', value: '720', unit: 'lux', status: 'Normal', updated: '2 min ago', trend: '+80 lux' },
];

export const cropData: Crop[] = [
  {
    id: '1',
    name: 'Tomato',
    scientificName: 'Solanum lycopersicum',
    variety: 'Roma Variety',
    plantingDate: 'August 20, 2026',
    growthStage: 'Flowering',
    location: 'Garden A',
    expectedHarvest: 'October 15, 2026',
    device: 'ESP32 Monitor #1',
    status: 'Healthy',
    image:
      'https://images.unsplash.com/photo-1546094096-0df4bcaaa337?auto=format&fit=crop&w=900&q=80',
  },
  {
    id: '2',
    name: 'Rice',
    scientificName: 'Oryza sativa',
    variety: 'NSIC Rc 222',
    plantingDate: 'July 18, 2026',
    growthStage: 'Tillering',
    location: 'Field B',
    expectedHarvest: 'November 05, 2026',
    device: 'ESP32 Monitor #2',
    status: 'Warning',
    image:
      'https://images.unsplash.com/photo-1464226185955-7a9f94d0c5f4?auto=format&fit=crop&w=900&q=80',
  },
  {
    id: '3',
    name: 'Corn',
    scientificName: 'Zea mays',
    variety: 'Sweet White',
    plantingDate: 'September 02, 2026',
    growthStage: 'Vegetative',
    location: 'Field C',
    expectedHarvest: 'December 01, 2026',
    device: 'ESP32 Monitor #3',
    status: 'Healthy',
    image:
      'https://images.unsplash.com/photo-1501004318641-b39e6451bec6?auto=format&fit=crop&w=900&q=80',
  },
];

export const alertData: AlertItem[] = [
  {
    id: '1',
    title: 'Low Soil Moisture',
    crop: 'Tomato',
    location: 'Garden A',
    currentReading: '19%',
    configuredMinimum: '30%',
    detected: '10 minutes ago',
    severity: 'Warning',
  },
  {
    id: '2',
    title: 'High Temperature',
    crop: 'Rice',
    location: 'Field B',
    currentReading: '33°C',
    configuredMinimum: '26°C',
    detected: '18 minutes ago',
    severity: 'Critical',
  },
  {
    id: '3',
    title: 'pH Deviation',
    crop: 'Corn',
    location: 'Field C',
    currentReading: '5.7 pH',
    configuredMinimum: '6.0 pH',
    detected: '25 minutes ago',
    severity: 'Resolved',
  },
];

export const chatMessages = [
  {
    id: '1',
    sender: 'assistant',
    text: 'I can help review crop health, irrigation needs, and sensor readings. What would you like to check?',
    time: '09:42',
  },
  {
    id: '2',
    sender: 'user',
    text: 'Why are my leaves yellow?',
    time: '09:43',
  },
  {
    id: '3',
    sender: 'assistant',
    text: 'Observed: Yellowing appears on several leaves. Possible causes include water stress, nutrient imbalance, or a plant health issue. Current sensor conditions show soil moisture is at 42% and temperature is 28°C. I recommend checking the lower leaves and inspecting for pests or disease symptoms.',
    time: '09:44',
  },
];
