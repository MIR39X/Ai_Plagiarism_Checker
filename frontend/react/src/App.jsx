import { useMemo, useRef, useState } from 'react';

const DEFAULT_API_BASE = 'http://127.0.0.1:8000';

function formatPercent(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return '--';
  return `${Number(value).toFixed(2)}%`;
}

function DonutChart({ percent = 0 }) {
  const clamped = Math.max(0, Math.min(100, percent));
  const radius = 60;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (clamped / 100) * circumference;

  return (
    <div className="chart">
      <svg viewBox="0 0 150 150">
        <defs>
          <linearGradient id="donut" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stopColor="#00e7d2" />
            <stop offset="100%" stopColor="#6f7bfd" />
          </linearGradient>
        </defs>
        <circle
          cx="75"
          cy="75"
          r={radius}
          stroke="#111a2b"
          strokeWidth="16"
          fill="none"
          strokeLinecap="round"
        />
        <circle
          cx="75"
          cy="75"
          r={radius}
          stroke="url(#donut)"
          strokeWidth="16"
          fill="none"
          strokeLinecap="round"
          strokeDasharray={`${circumference} ${circumference}`}
          strokeDashoffset={offset}
          transform="rotate(-90 75 75)"
        />
        <text
          x="50%"
          y="50%"
          textAnchor="middle"
          dominantBaseline="central"
          fill="#e8ecf6"
          fontSize="18"
          fontWeight="700"
        >
          {clamped.toFixed(0)}% AI
        </text>
      </svg>
    </div>
  );
}

function SegmentCard({ segment }) {
  const {
    id = '',
    page = '-',
    tokens = '-',
    text = '',
    confidence = 0,
    is_ai: isAi = false,
  } = segment || {};

  return (
    <div className="segment-card">
      <div className="row" style={{ justifyContent: 'space-between' }}>
        <div className="small">
          {id} · Page {page ?? '-'} · Tokens {tokens ?? '-'}
        </div>
        <span className={`pill ${isAi ? 'ai' : 'human'}`}>
          {isAi ? 'AI' : 'Human'} ({confidence ?? 0})
        </span>
      </div>
      <div className="segment-text" style={{ marginTop: 8 }}>
        {text || ''}
      </div>
    </div>
  );
}

function App() {
  const [apiBase, setApiBase] = useState(DEFAULT_API_BASE);
  const [mode, setMode] = useState('analyze'); // analyze | mock
  const [segmentTokens, setSegmentTokens] = useState('');
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState('');
  const [result, setResult] = useState(null);
  const [debugLog, setDebugLog] = useState('Ready.');
  const [showDebug, setShowDebug] = useState(true);

  const fileRef = useRef(null);

  const topAiSegments = useMemo(() => {
    if (!result?.segments) return [];
    const sorted = [...result.segments].sort((a, b) => (b.confidence || 0) - (a.confidence || 0));
    return sorted.slice(0, 3);
  }, [result]);

  async function handleRun() {
    const file = fileRef.current?.files?.[0];
    if (mode === 'analyze' && !file) {
      setStatus('Choose a PDF or DOCX file.');
      return;
    }

    const url = `${apiBase.replace(/\/$/, '')}/${mode === 'mock' ? 'mock.php' : 'analyze.php'}`;
    const isAnalyze = mode === 'analyze';

    setLoading(true);
    setResult(null);
    setStatus('Running...');
    setDebugLog((prev) => `${prev}\n\nRunning ${isAnalyze ? 'analyze' : 'mock'}...`);

    try {
      let response;
      let raw;

      if (isAnalyze) {
        const form = new FormData();
        form.append('file', file);
        if (segmentTokens) form.append('segment_tokens', segmentTokens);
        response = await fetch(url, { method: 'POST', body: form, cache: 'no-cache' });
      } else {
        response = await fetch(url, { method: 'GET', cache: 'no-cache' });
      }

      raw = await response.text();
      setDebugLog((prev) => `${prev}\n${isAnalyze ? 'Analyze' : 'Mock'} raw response:\n${raw}`);

      if (!response.ok) {
        let msg = raw || 'Request failed';
        try {
          const parsedErr = JSON.parse(raw);
          msg = parsedErr.error || JSON.stringify(parsedErr);
        } catch (err) {
          // ignore parse error, use raw
        }
        throw new Error(msg);
      }

      let data;
      try {
        data = JSON.parse(raw);
      } catch (err) {
        throw new Error('Failed to parse JSON response.');
      }

      setResult(data);
      setStatus('Done');
    } catch (err) {
      const msg = err?.message || 'Unknown error';
      setStatus(`Error: ${msg}`);
      setResult(null);
      setDebugLog((prev) => `${prev}\nError: ${msg}`);
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <header>
        <h1>AI Plagiarism Checker</h1>
        <p>Upload, analyze, and inspect per-segment confidences with a polished React dashboard.</p>
      </header>

      <main>
        <section className="card">
          <div className="grid">
            <div>
              <label>API base</label>
              <input
                type="text"
                value={apiBase}
                onChange={(e) => setApiBase(e.target.value)}
                placeholder="http://127.0.0.1:8000"
              />
            </div>
            <div>
              <label>Mode</label>
              <select value={mode} onChange={(e) => setMode(e.target.value)}>
                <option value="analyze">Analyze (POST /analyze.php)</option>
                <option value="mock">Mock (GET /mock.php)</option>
              </select>
            </div>
            <div>
              <label>Segment tokens (optional)</label>
              <input
                type="number"
                min="20"
                max="400"
                placeholder="default 80"
                value={segmentTokens}
                onChange={(e) => setSegmentTokens(e.target.value)}
              />
            </div>
            <div>
              <label>Document</label>
              <input type="file" ref={fileRef} accept=".pdf,.doc,.docx" />
              <div className="small" style={{ marginTop: 6 }}>
                Required for analyze; ignored for mock.
              </div>
            </div>
          </div>

          <div className="row" style={{ marginTop: 14 }}>
            <button type="button" style={{ minWidth: 180 }} onClick={handleRun} disabled={loading}>
              {loading ? 'Running...' : 'Run Analysis'}
            </button>
            <span className="small">{status}</span>
            <button
              type="button"
              className="ghost"
              onClick={() => setShowDebug((v) => !v)}
            >
              {showDebug ? 'Hide Debug' : 'Show Debug'}
            </button>
          </div>
        </section>

        {result && (
          <>
            <section className="card">
              <h2 style={{ margin: '0 0 10px' }}>Summary</h2>
              <div className="layout-split">
                <DonutChart percent={Number(result.ai_percent) || 0} />
                <div className="summary-grid">
                  <div className="stat">
                    <div className="small">AI Percentage</div>
                    <div className="value">{formatPercent(result.ai_percent)}</div>
                  </div>
                  <div className="stat">
                    <div className="small">Segments</div>
                    <div className="value">{result.num_segments ?? '--'}</div>
                  </div>
                  <div className="stat">
                    <div className="small">Total Tokens</div>
                    <div className="value">{result.total_tokens ?? '--'}</div>
                  </div>
                  <div className="stat">
                    <div className="small">Download Token</div>
                    <div className="value" style={{ fontSize: 18 }}>
                      {result.download_token ?? '--'}
                    </div>
                    <div className="row" style={{ marginTop: 8 }}>
                      <span className="badge">Placeholder</span>
                      <button
                        className="ghost"
                        style={{ color: 'var(--text)' }}
                        type="button"
                        onClick={() => {
                          if (result.download_token) {
                            const url = `${apiBase.replace(/\/$/, '')}/download.php?token=${encodeURIComponent(
                              result.download_token
                            )}`;
                            window.open(url, '_blank');
                          }
                        }}
                      >
                        Download
                      </button>
                    </div>
                  </div>
                  <div className="stat">
                    <div className="small">Top AI segments</div>
                    <div className="small">
                      {topAiSegments.length === 0
                        ? 'None'
                        : topAiSegments
                            .map((seg) => `${seg.id || ''} (${seg.confidence ?? 0})`)
                            .join(' · ')}
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section className="card">
              <h2 style={{ margin: '0 0 10px' }}>Segments</h2>
              <div className="grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))' }}>
                {(result.segments || []).map((seg) => (
                  <SegmentCard key={seg.id} segment={seg} />
                ))}
              </div>
            </section>
          </>
        )}

        {showDebug && (
          <section className="card">
            <h2 style={{ marginBottom: 8 }}>Debug (raw responses & errors)</h2>
            <pre className="debug">{debugLog || 'No debug yet.'}</pre>
          </section>
        )}
      </main>
    </>
  );
}

export default App;
