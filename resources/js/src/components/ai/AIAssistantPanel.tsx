import { SendHorizonal, Upload, Sparkles } from 'lucide-react';
import { chatMessages } from '../../data';

export function AIAssistantPanel() {
  return (
    <div className="agri-card overflow-hidden">
      <div className="border-b border-[color:var(--agri-border)] bg-[rgba(149,213,178,0.08)] p-5">
        <div className="flex items-center justify-between gap-3">
          <div>
            <h3 className="text-xl font-semibold text-[color:var(--agri-primary-text)]">🌱 AI Agricultural Assistant</h3>
            <p className="text-sm text-[color:var(--agri-secondary-text)]">
              Ask about crop care, plant health, soil, irrigation, nutrients, or sensor conditions.
            </p>
          </div>
          <div className="rounded-full bg-[color:var(--agri-green)] p-2 text-white">
            <Sparkles size={18} />
          </div>
        </div>
      </div>

      <div className="space-y-4 bg-[color:var(--agri-secondary-bg)] p-4">
        {chatMessages.map((message) => (
          <div key={message.id} className={`flex ${message.sender === 'user' ? 'justify-end' : 'justify-start'}`}>
            <div
              className={`max-w-[85%] rounded-2xl px-3 py-2 text-sm ${
                message.sender === 'user'
                  ? 'bg-[color:var(--agri-green)] text-white'
                  : 'border border-[color:var(--agri-border)] bg-white text-[color:var(--agri-primary-text)]'
              }`}
            >
              <div>{message.text}</div>
              <div className={`mt-1 text-[10px] ${message.sender === 'user' ? 'text-emerald-100' : 'text-[color:var(--agri-secondary-text)]'}`}>
                {message.time}
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="border-t border-[color:var(--agri-border)] p-4">
        <div className="mb-3 flex flex-wrap gap-2">
          {['Why are my leaves yellow?', 'Is my soil moisture okay?', 'How should I care for this crop?', 'What could cause wilting?'].map((chip) => (
            <button key={chip} className="rounded-full border border-[color:var(--agri-border)] bg-white px-3 py-1.5 text-xs text-[color:var(--agri-secondary-text)] transition hover:bg-[color:var(--agri-secondary-bg)]">
              {chip}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-2 rounded-xl border border-[color:var(--agri-border)] bg-white p-2">
          <button className="rounded-lg bg-[color:var(--agri-secondary-bg)] p-2 text-[color:var(--agri-green)]">
            <Upload size={16} />
          </button>
          <input
            type="text"
            placeholder="Ask about your crop..."
            className="flex-1 border-none bg-transparent text-sm text-[color:var(--agri-primary-text)] outline-none placeholder:text-[color:var(--agri-muted)]"
          />
          <button className="agri-btn-primary rounded-lg px-3 py-2">
            <SendHorizonal size={16} />
          </button>
        </div>
      </div>
    </div>
  );
}
