// CaptainCore v3 — real job engine (mixin).
// Dispatch a CLI job (REST returns a daemon-minted token), attach the
// WebSocket (send {token, action:"start"}), stream plain-text frames, and
// detect completion via the "Finished." sentinel — the same contract as v1's
// runCommand (templates/core.php:25024) and the Go daemon's wsHandler.
// Job entries in state.jobs stay serializable; streams + sockets live on the
// instance in this._jobObjs keyed by job id.

Object.assign(Component.prototype, {

  // dispatch: () => Promise resolving to the token (string) or {token} object.
  // Omit dispatch for a "manual" job the caller resolves itself via finishJob.
  // onFinish: called after a clean "Finished." close.
  // `expand: true` opens the full terminal dock (developer terminal only).
  // By default a job runs "mini": the collapsed activity pill shows its live
  // tail without forcing customers into the full console.
  startJob({ label, target, command, siteId, environment, dispatch, onFinish, expand }) {
    const id = 'job' + Date.now() + Math.floor(Math.random() * 1000);
    this._jobObjs = this._jobObjs || {};
    const job = { id, label, target, command, siteId, environment, onFinish, stream: [], ws: null, token: null };
    this._jobObjs[id] = job;
    // Non-terminal actions get a loading toast (immediate feedback); the
    // developer terminal ('run') streams into the console instead.
    if (!expand && this.toast) job._toastId = this.toast((this.JOB_VERBS[label] || label || 'Working') + '…', { kind: 'loading' });
    this.setState(st => ({ jobs: [{ id, label, target, state: 'running', pct: 6, real: true }, ...st.jobs], dockOpen: expand ? true : st.dockOpen, jobSel: id }));
    if (!dispatch) return id;
    Promise.resolve().then(dispatch).then(res => {
      const token = typeof res === 'string' ? res : (res && res.token);
      if (!token || typeof token !== 'string') {
        throw new Error('dispatch returned no job token: ' + JSON.stringify(res).slice(0, 160));
      }
      job.token = token;
      this.attachJobSocket(job);
    }).catch(err => {
      job.stream.push('Error starting command: ' + (err && err.message || err));
      this.finishJob(job, 'error');
    });
    return id;
  },

  attachJobSocket(job) {
    const boot = window.CC_BOOT || {};
    let ws;
    try {
      ws = new WebSocket(boot.socket);
    } catch (e) {
      job.stream.push('Error: could not open socket ' + boot.socket);
      this.finishJob(job, 'error');
      return;
    }
    job.ws = ws;
    ws.onopen = () => {
      if (ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify({ token: job.token, action: 'start' }));
    };
    ws.onmessage = (session) => {
      job.stream.push(String(session.data));
      this.patchJob(job.id, st => ({ pct: Math.min(95, (st.pct || 6) + 4) }));
    };
    ws.onclose = () => {
      const last = job.stream.length ? String(job.stream[job.stream.length - 1]).trim() : '';
      if (last === 'Finished.') {
        this.finishJob(job, 'done');
        if (typeof job.onFinish === 'function') { try { job.onFinish(); } catch (e) { console.warn(e); } }
      } else {
        this.finishJob(job, 'error');
      }
    };
    ws.onerror = () => { /* onclose fires next and resolves state */ };
  },

  killJob(id) {
    const job = this._jobObjs && this._jobObjs[id];
    if (job && job.ws && job.ws.readyState === WebSocket.OPEN) {
      job.ws.send(JSON.stringify({ token: job.token, action: 'kill' }));
      job.stream.push('➜ Process terminated by user.');
    }
  },

  patchJob(id, patch) {
    this.setState(st => ({ jobs: st.jobs.map(j => j.id === id ? { ...j, ...(typeof patch === 'function' ? patch(j) : patch) } : j) }));
  },

  JOB_VERBS: { 'sync-data': 'Sync', 'update-wp': 'Update', backup: 'Backup', quicksave: 'Quicksave',
    activate: 'Activate', deactivate: 'Deactivate', 'push-staging': 'Push to production',
    'pull-staging': 'Pull to staging', restore: 'Restore', rollback: 'Rollback', deploy: 'Deploy' },

  finishJob(job, state) {
    this.patchJob(job.id, { state, pct: 100, right: state === 'done' ? 'just now' : 'error' });
    // Resolve the loading toast a background action created on dispatch.
    if (job._toastId && this.updateToast) {
      const verb = this.JOB_VERBS[job.label] || (job.label || 'Job');
      this.updateToast(job._toastId, verb + (state === 'done' ? ' complete' : ' failed'), { kind: state === 'done' ? 'success' : 'error' });
    }
  },

  // Console feed: the user-selected job, else the most recent running real
  // job, else the last real job with output.
  activeJob() {
    if (!this._jobObjs) return null;
    if (this.state.jobSel && this._jobObjs[this.state.jobSel]) return this._jobObjs[this.state.jobSel];
    const order = this.state.jobs.filter(j => j.real);
    const running = order.find(j => j.state === 'running' && this._jobObjs[j.id]);
    const withOutput = order.find(j => this._jobObjs[j.id] && this._jobObjs[j.id].stream.length);
    const pick = running || withOutput;
    return pick ? this._jobObjs[pick.id] : null;
  },

  // Terminal input lives in terminal.js (multi-target termRun override).

  realConsoleLines() {
    const job = this.activeJob();
    if (!job) {
      return [{ text: '$ idle — run Sync or a command from a site to stream output here', fg: 'var(--ink-dim)' }];
    }
    const head = { text: '$ ' + (job.label || 'job') + (job.target ? ' · ' + job.target : ''), fg: 'var(--ink-dim)' };
    const lines = job.stream
      .flatMap(chunk => String(chunk).split('\n'))
      .filter(l => l.trim() !== 'Finished.' && l.trim() !== '')
      .slice(-30)
      .map(text => ({ text,
        fg: /^(✓|Success|Done)/.test(text.trim()) ? 'var(--ok)'
          : /^(Error|Warning|✗)/i.test(text.trim()) ? 'var(--bad)' : 'var(--ink)' }));
    return [head, ...lines];
  }

});
