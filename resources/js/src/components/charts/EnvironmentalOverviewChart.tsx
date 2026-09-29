import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const data = [
  { name: 'Mon', temp: 26, humidity: 60, moisture: 41, ph: 6.3, light: 650 },
  { name: 'Tue', temp: 27, humidity: 64, moisture: 40, ph: 6.2, light: 690 },
  { name: 'Wed', temp: 28, humidity: 68, moisture: 42, ph: 6.4, light: 720 },
  { name: 'Thu', temp: 29, humidity: 67, moisture: 45, ph: 6.5, light: 740 },
  { name: 'Fri', temp: 30, humidity: 65, moisture: 44, ph: 6.4, light: 730 },
  { name: 'Sat', temp: 29, humidity: 66, moisture: 43, ph: 6.3, light: 710 },
  { name: 'Sun', temp: 28, humidity: 68, moisture: 42, ph: 6.4, light: 720 },
];

export function EnvironmentalOverviewChart() {
  return (
    <div className="agri-card p-5">
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="text-lg font-semibold text-[color:var(--agri-primary-text)]">Environmental Overview</h3>
          <p className="text-sm text-[color:var(--agri-secondary-text)]">Crop condition and field environment</p>
        </div>

        <div className="flex flex-wrap gap-2">
          {['Temperature', 'Humidity', 'Soil Moisture', 'Soil pH', 'Light'].map((option) => (
            <button
              key={option}
              className={`rounded-full px-3 py-1.5 text-xs font-medium ${
                option === 'Temperature'
                  ? 'bg-[color:var(--agri-green)] text-white'
                  : 'border border-[color:var(--agri-border)] bg-white text-[color:var(--agri-secondary-text)]'
              }`}
            >
              {option}
            </button>
          ))}
        </div>
      </div>

      <div className="h-72">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={data}>
            <defs>
              <linearGradient id="tempGradient" x1="0" x2="0" y1="0" y2="1">
                <stop offset="5%" stopColor="#2d6a4f" stopOpacity={0.45} />
                <stop offset="95%" stopColor="#2d6a4f" stopOpacity={0.06} />
              </linearGradient>
            </defs>
            <CartesianGrid strokeDasharray="4 4" stroke="#e4e7e1" vertical={false} />
            <XAxis dataKey="name" tickLine={false} axisLine={false} tick={{ fill: '#68736c', fontSize: 12 }} />
            <YAxis tickLine={false} axisLine={false} tick={{ fill: '#68736c', fontSize: 12 }} />
            <Tooltip />
            <Area type="monotone" dataKey="temp" stroke="#2d6a4f" fill="url(#tempGradient)" strokeWidth={2} />
          </AreaChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
