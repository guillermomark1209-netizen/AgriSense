import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { CloudLightning, Plus, TrendingUp } from 'lucide-react';
import { sensorCards, cropData, alertData } from './src/data';
import { Sidebar } from './src/components/layout/Sidebar';
import { TopHeader } from './src/components/layout/TopHeader';
import { WelcomePanel } from './src/components/dashboard/WelcomePanel';
import { SensorCard } from './src/components/sensors/SensorCard';
import { EnvironmentalOverviewChart } from './src/components/charts/EnvironmentalOverviewChart';
import { CropCard } from './src/components/crops/CropCard';
import { AlertCard } from './src/components/alerts/AlertCard';
import { AIAssistantPanel } from './src/components/ai/AIAssistantPanel';
import { AuthPage } from './src/pages/AuthPage';

function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  if (!isAuthenticated) {
    return <AuthPage onContinue={() => setIsAuthenticated(true)} />;
  }

  return (
    <div className="min-h-screen bg-[color:var(--agri-bg)] text-[color:var(--agri-primary-text)]">
      <div className="mx-auto flex min-h-screen max-w-[1800px] flex-col lg:flex-row">
        <Sidebar />

        <main className="flex-1">
          <TopHeader />

          <div className="space-y-6 px-4 pb-8 sm:px-6">
            <WelcomePanel />

            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
              {sensorCards.map((sensor) => (
                <SensorCard key={sensor.name} {...sensor} />
              ))}
            </section>

            <EnvironmentalOverviewChart />

            <section className="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
              <div className="agri-card p-5">
                <div className="mb-4 flex items-center justify-between gap-3">
                  <div>
                    <h3 className="text-xl font-semibold text-[color:var(--agri-primary-text)]">My Crops</h3>
                    <p className="text-sm text-[color:var(--agri-secondary-text)]">Track growth and crop health.</p>
                  </div>
                  <button className="agri-btn-primary">
                    <Plus size={16} className="mr-2" />
                    Add Crop
                  </button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {cropData.map((crop) => (
                    <CropCard key={crop.id} {...crop} />
                  ))}
                </div>
              </div>

              <div className="space-y-6">
                <div className="agri-card p-5">
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <h3 className="text-xl font-semibold text-[color:var(--agri-primary-text)]">Alerts</h3>
                    <button className="agri-btn-secondary px-3 py-1.5 text-xs">View all</button>
                  </div>
                  <div className="space-y-3">
                    {alertData.map((alert) => (
                      <AlertCard key={alert.id} {...alert} />
                    ))}
                  </div>
                </div>

                <div className="agri-card p-5">
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <h3 className="text-xl font-semibold text-[color:var(--agri-primary-text)]">Quick Tips</h3>
                  </div>
                  <div className="space-y-3 text-sm text-[color:var(--agri-secondary-text)]">
                    <div className="rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] p-3">
                      <div className="mb-1 flex items-center gap-2 text-[color:var(--agri-green)]">
                        <TrendingUp size={16} />
                        <span className="font-medium">Crop Health</span>
                      </div>
                      Tomato plants are responding well to current moisture conditions.
                    </div>
                    <div className="rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] p-3">
                      <div className="mb-1 flex items-center gap-2 text-[color:var(--agri-warning)]">
                        <CloudLightning size={16} />
                        <span className="font-medium">Weather Watch</span>
                      </div>
                      Light intensity peaked today, which may affect irrigation timing.
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <AIAssistantPanel />
          </div>
        </main>
      </div>
    </div>
  );
}

const rootElement = document.getElementById('app');

if (rootElement) {
  createRoot(rootElement).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}
