import React, { useState } from 'react';
import {
  ArrowRight,
  Eye,
  EyeOff,
  Leaf,
  LockKeyhole,
  Mail,
  Sprout,
  UserRound,
} from 'lucide-react';

type AuthMode = 'login' | 'register';

type AuthPageProps = {
  onContinue: () => void;
};

export function AuthPage({ onContinue }: AuthPageProps) {
  const [mode, setMode] = useState<AuthMode>('login');
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  return (
    <div className="min-h-screen bg-[color:var(--agri-bg)]">
      <div className="mx-auto grid min-h-screen max-w-6xl grid-cols-1 lg:grid-cols-2">
        <div className="relative overflow-hidden bg-[color:var(--agri-forest)] p-8 text-white lg:p-12">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(149,213,178,0.28),transparent_35%)]" />
          <div className="absolute -bottom-10 -left-10 h-44 w-44 rounded-full bg-[rgba(149,213,178,0.16)] blur-3xl" />
          <div className="absolute -top-6 right-0 h-56 w-56 rounded-full bg-[rgba(244,185,66,0.12)] blur-3xl" />

          <div className="relative z-10 flex h-full flex-col justify-between">
            <div>
              <div className="mb-8 flex items-center gap-3">
                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[color:var(--agri-green)] text-lg font-bold shadow-inner shadow-white/10">
                  A
                </div>
                <div>
                  <div className="text-xl font-semibold">AgriSense</div>
                  <div className="text-xs text-emerald-100/80">Smart Agriculture Monitoring & AI Assistance</div>
                </div>
              </div>

              <div className="mb-8">
                <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/5 px-3 py-1.5 text-xs uppercase tracking-[0.2em] text-emerald-100">
                  <Leaf size={14} />
                  Grow smarter
                </div>
                <h1 className="max-w-md text-4xl font-semibold leading-tight text-white">
                  Monitor crops, understand environmental conditions, and receive evidence-based agricultural assistance.
                </h1>
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
              <div className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                <div className="mb-2 flex h-9 w-9 items-center justify-center rounded-xl bg-[color:var(--agri-light)] text-[color:var(--agri-forest)]">
                  <Sprout size={18} />
                </div>
                <div className="text-2xl font-semibold">12</div>
                <div className="text-xs text-emerald-100/80">Registered crops</div>
              </div>

              <div className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                <div className="mb-2 flex h-9 w-9 items-center justify-center rounded-xl bg-[color:var(--agri-light)] text-[color:var(--agri-forest)]">
                  <LockKeyhole size={18} />
                </div>
                <div className="text-2xl font-semibold">8</div>
                <div className="text-xs text-emerald-100/80">Connected devices</div>
              </div>

              <div className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                <div className="mb-2 flex h-9 w-9 items-center justify-center rounded-xl bg-[color:var(--agri-light)] text-[color:var(--agri-forest)]">
                  <Leaf size={18} />
                </div>
                <div className="text-2xl font-semibold">97%</div>
                <div className="text-xs text-emerald-100/80">Crop health score</div>
              </div>
            </div>
          </div>
        </div>

        <div className="flex items-center justify-center bg-white p-6 sm:p-10 lg:p-12">
          <div className="w-full max-w-md">
            <div className="mb-8 flex rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] p-1">
              <button
                type="button"
                onClick={() => setMode('login')}
                className={`flex-1 rounded-lg px-4 py-2 text-sm font-medium transition ${
                  mode === 'login'
                    ? 'bg-[color:var(--agri-green)] text-white shadow-sm'
                    : 'text-[color:var(--agri-secondary-text)]'
                }`}
              >
                Sign In
              </button>
              <button
                type="button"
                onClick={() => setMode('register')}
                className={`flex-1 rounded-lg px-4 py-2 text-sm font-medium transition ${
                  mode === 'register'
                    ? 'bg-[color:var(--agri-green)] text-white shadow-sm'
                    : 'text-[color:var(--agri-secondary-text)]'
                }`}
              >
                Create Account
              </button>
            </div>

            {mode === 'login' ? (
              <form
                className="space-y-5"
                onSubmit={(event) => {
                  event.preventDefault();
                  onContinue();
                }}
              >
                <div>
                  <h2 className="text-3xl font-semibold text-[color:var(--agri-primary-text)]">Welcome back</h2>
                  <p className="mt-2 text-sm text-[color:var(--agri-secondary-text)]">
                    Sign in to monitor your farm and AI insights.
                  </p>
                </div>

                <div className="space-y-1">
                  <label className="text-sm font-medium text-[color:var(--agri-primary-text)]">Email</label>
                  <div className="flex items-center gap-3 rounded-xl border border-[color:var(--agri-border)] bg-white px-3 py-3">
                    <Mail size={16} className="text-[color:var(--agri-secondary-text)]" />
                    <input
                      type="email"
                      placeholder="farmer@agrisense.com"
                      className="w-full border-none bg-transparent text-sm text-[color:var(--agri-primary-text)] outline-none placeholder:text-[color:var(--agri-muted)]"
                    />
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="text-sm font-medium text-[color:var(--agri-primary-text)]">Password</label>
                  <div className="flex items-center gap-3 rounded-xl border border-[color:var(--agri-border)] bg-white px-3 py-3">
                    <LockKeyhole size={16} className="text-[color:var(--agri-secondary-text)]" />
                    <input
                      type={showPassword ? 'text' : 'password'}
                      placeholder="Enter your password"
                      className="w-full border-none bg-transparent text-sm text-[color:var(--agri-primary-text)] outline-none placeholder:text-[color:var(--agri-muted)]"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((value) => !value)}
                      className="text-[color:var(--agri-secondary-text)]"
                    >
                      {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                </div>

                <div className="flex items-center justify-between text-sm">
                  <label className="inline-flex items-center gap-2 text-[color:var(--agri-secondary-text)]">
                    <input type="checkbox" className="h-4 w-4 rounded border-[color:var(--agri-border)]" />
                    Remember me
                  </label>
                  <button type="button" className="font-medium text-[color:var(--agri-green)]">
                    Forgot password?
                  </button>
                </div>

                <button type="submit" className="agri-btn-primary w-full justify-center gap-2">
                  Sign In
                  <ArrowRight size={16} />
                </button>
              </form>
            ) : (
              <form
                className="space-y-5"
                onSubmit={(event) => {
                  event.preventDefault();
                  onContinue();
                }}
              >
                <div>
                  <h2 className="text-3xl font-semibold text-[color:var(--agri-primary-text)]">Create account</h2>
                  <p className="mt-2 text-sm text-[color:var(--agri-secondary-text)]">
                    Register your farm and start monitoring today.
                  </p>
                </div>

                <div className="space-y-1">
                  <label className="text-sm font-medium text-[color:var(--agri-primary-text)]">Full name</label>
                  <div className="flex items-center gap-3 rounded-xl border border-[color:var(--agri-border)] bg-white px-3 py-3">
                    <UserRound size={16} className="text-[color:var(--agri-secondary-text)]" />
                    <input
                      type="text"
                      placeholder="Your full name"
                      className="w-full border-none bg-transparent text-sm text-[color:var(--agri-primary-text)] outline-none placeholder:text-[color:var(--agri-muted)]"
                    />
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="text-sm font-medium text-[color:var(--agri-primary-text)]">Email</label>
                  <div className="flex items-center gap-3 rounded-xl border border-[color:var(--agri-border)] bg-white px-3 py-3">
                    <Mail size={16} className="text-[color:var(--agri-secondary-text)]" />
                    <input
                      type="email"
                      placeholder="you@example.com"
                      className="w-full border-none bg-transparent text-sm text-[color:var(--agri-primary-text)] outline-none placeholder:text-[color:var(--agri-muted)]"
                    />
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="text-sm font-medium text-[color:var(--agri-primary-text)]">Password</label>
                  <div className="flex items-center gap-3 rounded-xl border border-[color:var(--agri-border)] bg-white px-3 py-3">
                    <LockKeyhole size={16} className="text-[color:var(--agri-secondary-text)]" />
                    <input
                      type={showPassword ? 'text' : 'password'}
                      placeholder="Create a password"
                      className="w-full border-none bg-transparent text-sm text-[color:var(--agri-primary-text)] outline-none placeholder:text-[color:var(--agri-muted)]"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((value) => !value)}
                      className="text-[color:var(--agri-secondary-text)]"
                    >
                      {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="text-sm font-medium text-[color:var(--agri-primary-text)]">Confirm password</label>
                  <div className="flex items-center gap-3 rounded-xl border border-[color:var(--agri-border)] bg-white px-3 py-3">
                    <LockKeyhole size={16} className="text-[color:var(--agri-secondary-text)]" />
                    <input
                      type={showConfirmPassword ? 'text' : 'password'}
                      placeholder="Repeat your password"
                      className="w-full border-none bg-transparent text-sm text-[color:var(--agri-primary-text)] outline-none placeholder:text-[color:var(--agri-muted)]"
                    />
                    <button
                      type="button"
                      onClick={() => setShowConfirmPassword((value) => !value)}
                      className="text-[color:var(--agri-secondary-text)]"
                    >
                      {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                </div>

                <button type="submit" className="agri-btn-primary w-full justify-center gap-2">
                  Create Account
                  <ArrowRight size={16} />
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
