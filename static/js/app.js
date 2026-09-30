/* ============================================================
   MİKRO MOLA · Esneme & Su Takibi — Uygulama Mantığı
   - Zaman damgası tabanlı sayaç (sekme arka planda bile doğru)
   - LocalStorage ile kalıcı veri
   - Web Audio ile hafif sesler, canvas-confetti ile kutlama
   ============================================================ */
'use strict';

/* ---------- kısayollar ---------- */
const $ = (s) => document.querySelector(s);
const $$ = (s) => [...document.querySelectorAll(s)];

const LS = {
  get(key, fallback) {
    try {
      const raw = localStorage.getItem(key);
      return raw === null ? fallback : JSON.parse(raw);
    } catch { return fallback; }
  },
  set(key, val) {
    try { localStorage.setItem(key, JSON.stringify(val)); } catch { /* kota dolu olabilir */ }
  }
};

/* LocalStorage anahtarları */
const K = {
  theme: 'mm_theme', sound: 'mm_sound',
  dur: 'mm_breakDur', end: 'mm_breakEnd', paused: 'mm_breakPaused',
  water: 'mm_water', remOn: 'mm_waterRemOn', remNext: 'mm_waterRemNext'
};

const DAY_MS = 86400000;
const HOUR_MS = 3600000;
const nf = new Intl.NumberFormat('tr-TR');

function todayKey(d = new Date()) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function fmtClock(ms) {
  const s = Math.max(0, Math.ceil(ms / 1000));
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
}

/* ---------- egzersiz havuzu (her moladan rastgele 5 tanesi seçilir) ---------- */
const EXERCISES = [
  /* --- ayakta --- */
  { name: 'Boyun Esnetme', anim: 'neck', dur: 25, desc: 'Başını yavaşça sağa ve sola yatır, her tarafta birkaç saniye bekle. Omuzlar sabit kalsın.' },
  { name: 'Omuz Çevirme', anim: 'shoulders', dur: 30, desc: 'Omuzlarını önce ileri, sonra geri olacak şekilde yavaşça yuvarla. Nefesini düzenli tut.' },
  { name: 'Bilek Çevirme', anim: 'wrists', dur: 20, desc: 'Ellerini kavuştur ve bileklerinden saat yönünde, sonra tersine döndür. Klavye yorgunluğuna birebir.' },
  { name: 'Yukarı Uzanma', anim: 'stand', dur: 30, desc: 'Kollarını başının üzerine uzat, parmak uçlarına doğru esne ve derin bir nefes al.' },
  { name: 'Yan Gövde Esnetme', anim: 'side', dur: 30, desc: 'Bir kolunu yukarı kaldır ve diğer yöne doğru yana eğil; 10 saniye sonra taraf değiştir.' },
  { name: 'Göz Molası (20-20-20)', anim: 'eyes', dur: 20, desc: 'Ekrandan uzaklaş, 6 metre öfveye bakarak göz kaslarını dinlendir. Kırpıştırmayı unutma.' },
  { name: 'Göğüs Açma', anim: 'chest', dur: 25, desc: 'Kollarını yanlara aç, göğsünü iyice aç ve kürek kemiklerini birbirine yaklaştır.' },
  { name: 'Gövde Döndürme', anim: 'twist', dur: 25, desc: 'Ellerin belde, gövdeni sağa ve sola kontrollü şekilde döndür. Kalçan sabit kalsın.' },
  { name: 'Parmak Ucu Yükselme', anim: 'calves', dur: 20, desc: 'Parmak uçlarına yükselip yumuşakça in; bacaklarındaki kan dolaşımını canlandır.' },
  { name: 'Derin Nefes', anim: 'breathing', dur: 25, desc: 'Nefes alırken kollarını yavaşça yukarı aç, verirken indir. Göğsünün genişlediğini hisset.' },
  /* --- oturarak --- */
  { name: 'Oturarak Boyun Esnetme', anim: 'neck', dur: 25, seated: true, desc: 'Sandalyede dik otur, başını yavaşça sağa ve sola yatır. Omuzların kulağa yaklaşmasın.' },
  { name: 'Oturarak Omuz Çevirme', anim: 'shoulders', dur: 30, seated: true, desc: 'Kolların yanlarda serbest; omuzlarını önce ileri, sonra geri geniş dairelerle yuvarla.' },
  { name: 'Bilek & Parmak Esnetme', anim: 'wrists', dur: 20, seated: true, desc: 'Kollarını öne uzat, avuçlarını aç-kapa ve bileklerini iki yönde döndür. Tuşlardan mola ver.' },
  { name: 'Oturarak Gövde Döndürme', anim: 'twist', dur: 25, seated: true, desc: 'Ellerin belde, gövdeni sağa sola döndür. Sandalyenin sırtına yaslanmadan kontrollü dön.' },
  { name: 'Oturarak Kolları Yukarı Uzat', anim: 'stand', dur: 30, seated: true, desc: 'Otururken kollarını başının üzerine uzat, belini dik tut ve yukarı doğru esne.' },
  { name: 'Oturarak Yan Esnetme', anim: 'side', dur: 30, seated: true, desc: 'Bir kolunu yukarı kaldırıp diğer yana eğil; kürek kemiklerini sıkma, nefesini tutma.' },
  { name: 'Oturarak Derin Nefes', anim: 'breathing', dur: 25, seated: true, desc: 'Ayaklarını yere bas, dik otur; nefes alırken kollarını yukarı aç, verirken indir.' },
  { name: 'Kürek Sıkıştırma', anim: 'squeeze', dur: 20, seated: true, desc: 'Kollarını yanlara 90 derece bük, kürek kemiklerini sık ve bırak. Kamburu anında açar.' },
  { name: 'Ayak Bileği Çevirme', anim: 'ankles', dur: 20, seated: true, desc: 'Ayaklarını yerden hafifçe kesip bileklerinden daire çiz. Bacak ödemini azaltır.' },
  { name: 'Sandalyede Kalça Kaldırma', anim: 'hiplift', dur: 20, seated: true, desc: 'Ellerin koltuk kenarında, kalçanı sandalyeden hafifçe kaldır ve yavaşça otur.' }
];
const ROUTINE_SIZE = 5;

function shuffleArr(arr) {
  const r = [...arr];
  for (let i = r.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [r[i], r[j]] = [r[j], r[i]];
  }
  return r;
}

/* ---------- ses efektleri (hafif seviyede) ---------- */
const AudioFX = {
  ctx: null,
  ensure() {
    if (!this.ctx) {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (AC) this.ctx = new AC();
    }
    if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume();
    return this.ctx;
  },
  tone(freq, delay, dur, gain, type = 'sine') {
    const c = this.ctx;
    const o = c.createOscillator(), g = c.createGain();
    o.type = type;
    o.frequency.value = freq;
    const t0 = c.currentTime + delay;
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(gain, t0 + 0.03);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    o.connect(g); g.connect(c.destination);
    o.start(t0); o.stop(t0 + dur + 0.05);
  },
  /* mola sonu: yumuşak üç notalı çan */
  chime() {
    if (!state.sound || !this.ensure()) return;
    [[523.25, 0], [659.25, 0.16], [783.99, 0.32]].forEach(([f, t]) => this.tone(f, t, 1.1, 0.055));
  },
  /* su damlası "gülop" */
  drop() {
    if (!state.sound || !this.ensure()) return;
    const c = this.ctx;
    const o = c.createOscillator(), g = c.createGain();
    o.type = 'sine';
    o.frequency.setValueAtTime(640, c.currentTime);
    o.frequency.exponentialRampToValueAtTime(180, c.currentTime + 0.14);
    g.gain.setValueAtTime(0.09, c.currentTime);
    g.gain.exponentialRampToValueAtTime(0.0001, c.currentTime + 0.16);
    o.connect(g); g.connect(c.destination);
    o.start(); o.stop(c.currentTime + 0.2);
  },
  /* hedef tamamlandı: neşeli arpej */
  success() {
    if (!state.sound || !this.ensure()) return;
    [[523.25, 0], [659.25, 0.12], [783.99, 0.24], [1046.5, 0.36]].forEach(([f, t]) => this.tone(f, t, 0.9, 0.06));
  },
  /* su hatırlatıcı: iki notalık çağrı */
  reminder() {
    if (!state.sound || !this.ensure()) return;
    [[880, 0], [1174.66, 0.2]].forEach(([f, t]) => this.tone(f, t, 0.7, 0.05));
  },
  /* egzersiz geçişi: tek küçük tık */
  blip() {
    if (!state.sound || !this.ensure()) return;
    this.tone(660, 0, 0.28, 0.045);
  }
};

/* ---------- durum ---------- */
const state = {
  theme: LS.get(K.theme, null) || (window.matchMedia && matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'),
  sound: LS.get(K.sound, true),
  breakDurMs: LS.get(K.dur, 45 * 60 * 1000),
  breakEnd: LS.get(K.end, null),        // epoch ms — sayaç çalışırken bitiş anı
  breakPausedMs: LS.get(K.paused, null), // duraklatıldığında kalan ms
  water: LS.get(K.water, { goalMl: 2000, glassMl: 200, days: {} }),
  remOn: LS.get(K.remOn, true),
  remNext: LS.get(K.remNext, null)
};
if (!state.water || typeof state.water !== 'object') state.water = { goalMl: 2000, glassMl: 200, days: {} };
if (!state.water.days) state.water.days = {};

/* ---------- su verisi yardımcıları ---------- */
function waterDay(key = todayKey()) {
  if (!state.water.days[key]) state.water.days[key] = { total: 0, goal: state.water.goalMl, log: [], celebrated: false };
  const d = state.water.days[key];
  d.goal = state.water.goalMl; // bugünün kaydı güncel hedefi yansıtsın
  return d;
}
const saveWater = () => LS.set(K.water, state.water);

/* ---------- bildirim (toast) ---------- */
function toast(msg, opts = {}) {
  const c = $('#toastContainer');
  if (!c) return;
  const el = document.createElement('div');
  el.className = 'toast' + (opts.kind ? ' ' + opts.kind : '');
  el.innerHTML = `<span class="toast-ic">${opts.emoji || '💧'}</span><span>${msg}</span>`;
  c.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  setTimeout(() => {
    el.classList.remove('show');
    setTimeout(() => el.remove(), 350);
  }, opts.timeout || 4200);
}

/* ---------- masaüstü bildirimi (izin verilmişse) ---------- */
function notify(title, body) {
  try {
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification(title, { body, silent: true });
    }
  } catch { /* bazı tarayıcılarda kısıtlı olabilir */ }
}

/* ---------- konfeti ---------- */
function celebrate(big = true) {
  if (typeof confetti !== 'function') return;
  const base = { origin: { y: 0.75 }, colors: ['#38bdf8', '#818cf8', '#a78bfa', '#34d399', '#fbbf24', '#fb7185'], zIndex: 2000 };
  if (big) {
    confetti({ ...base, particleCount: 130, spread: 75, startVelocity: 42 });
    setTimeout(() => confetti({ ...base, particleCount: 70, spread: 110, origin: { x: 0.25, y: 0.7 }, scalar: 0.9 }), 250);
    setTimeout(() => confetti({ ...base, particleCount: 70, spread: 110, origin: { x: 0.75, y: 0.7 }, scalar: 0.9 }), 450);
  } else {
    confetti({ ...base, particleCount: 60, spread: 60 });
  }
}

/* ---------- tema ---------- */
function setIcon(sel, name) {
  const b = $(sel);
  if (!b) return;
  b.innerHTML = `<i data-lucide="${name}"></i>`;
  if (window.lucide) lucide.createIcons();
}
function applyTheme() {
  document.documentElement.setAttribute('data-theme', state.theme);
  setIcon('#themeToggle', state.theme === 'dark' ? 'sun' : 'moon');
  $('#themeToggle').title = state.theme === 'dark' ? 'Açık moda geç' : 'Koyu moda geç';
}

/* ============================================================
   MOLA SAYACI
   ============================================================ */
const ringFg = $('#ringFg');
const RING_C = 2 * Math.PI * parseFloat(ringFg.getAttribute('r'));

function breakRemaining() {
  if (state.breakPausedMs != null) return state.breakPausedMs;
  if (state.breakEnd) return state.breakEnd - Date.now();
  return state.breakDurMs;
}
const breakRunning = () => state.breakEnd != null || state.breakPausedMs != null;

function setPill(text, kind) {
  const p = $('#breakPill');
  p.textContent = text;
  p.className = 'pill' + (kind ? ' ' + kind : '');
}
function setStartBtn(icon, label) {
  $('#breakStartBtn').innerHTML = `<i data-lucide="${icon}"></i><span>${label}</span>`;
  if (window.lucide) lucide.createIcons();
}

function renderBreak() {
  const rem = breakRemaining();
  const frac = Math.min(1, Math.max(0, rem / state.breakDurMs));
  ringFg.style.strokeDasharray = RING_C;
  ringFg.style.strokeDashoffset = RING_C * (1 - frac);
  $('#breakTime').textContent = fmtClock(rem);

  const warn = state.breakEnd != null && rem < 60000 && rem > 0;
  $('#timerWrap').classList.toggle('warning', warn);

  if (state.breakEnd != null) {
    setPill('Çalışıyor', 'ok');
    $('#breakState').textContent = 'molaya kadar';
    setStartBtn('pause', 'Duraklat');
  } else if (state.breakPausedMs != null) {
    setPill('Duraklatıldı', 'warn');
    $('#breakState').textContent = 'sen dönene kadar';
    setStartBtn('play', 'Devam Et');
  } else {
    setPill('Hazır', '');
    $('#breakState').textContent = 'başlatılmayı bekliyor';
    setStartBtn('play', 'Başlat');
  }

  document.title = exOpen
    ? '🧘 Mola Zamanı · Mikro Mola'
    : (state.breakEnd != null ? `${fmtClock(rem)} ⏳ Mikro Mola` : 'Mikro Mola · Esneme & Su Takibi');
}

$('#breakStartBtn').addEventListener('click', () => {
  AudioFX.ensure(); // ilk kullanıcı etkileşiminde sesi aç
  try {
    if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
  } catch { /* yoksay */ }

  if (state.breakEnd != null) { // duraklat
    state.breakPausedMs = Math.max(0, state.breakEnd - Date.now());
    state.breakEnd = null;
    LS.set(K.end, null); LS.set(K.paused, state.breakPausedMs);
    toast('Sayaç duraklatıldı', { emoji: '⏸️' });
  } else if (state.breakPausedMs != null) { // devam et
    state.breakEnd = Date.now() + state.breakPausedMs;
    state.breakPausedMs = null;
    LS.set(K.end, state.breakEnd); LS.set(K.paused, null);
    toast('Devam ediyor 💪', { emoji: '▶️' });
  } else { // başlat
    state.breakEnd = Date.now() + state.breakDurMs;
    LS.set(K.end, state.breakEnd);
    toast(`Sayaç başladı: ${Math.round(state.breakDurMs / 60000)} dk sonra mola`, { emoji: '⏱️' });
  }
  renderBreak();
});

$('#breakResetBtn').addEventListener('click', () => {
  state.breakEnd = null; state.breakPausedMs = null;
  LS.set(K.end, null); LS.set(K.paused, null);
  renderBreak();
  toast('Sayaç sıfırlandı', { emoji: '↩️' });
});

$('#durationChips').addEventListener('click', (e) => {
  const chip = e.target.closest('.chip');
  if (!chip) return;
  const min = parseInt(chip.dataset.min, 10);
  state.breakDurMs = min * 60000;
  LS.set(K.dur, state.breakDurMs);
  $$('#durationChips .chip').forEach(c => c.classList.toggle('active', c === chip));
  if (breakRunning()) { // süre değişirse sayacı tazele
    state.breakEnd = null; state.breakPausedMs = null;
    LS.set(K.end, null); LS.set(K.paused, null);
  }
  renderBreak();
  toast(`Mola süresi ${min} dk olarak ayarlandı`, { emoji: '⏱️' });
});

/* ---------- mola süresi dolduğunda ---------- */
function onBreakDue() {
  state.breakEnd = null;
  LS.set(K.end, null);
  AudioFX.chime();
  notify('🧘 Mola zamanı!', 'Ekrandan uzaklaşıp biraz esneme vakti.');
  openExercises();
  renderBreak();
}

/* ============================================================
   ANA DÖNGÜ — zaman damgası tabanlı olduğu için sekme arka
   plandaken veya sayfa yenilendiğinde bile doğru çalışır
   ============================================================ */
let lastDay = todayKey();
let exOpen = false;

function tick() {
  const now = Date.now();

  if (state.breakEnd != null && now >= state.breakEnd) {
    if (now - state.breakEnd > 2 * HOUR_MS) {
      // çok eski bir sayaç: sessizce sıfırla
      state.breakEnd = null; LS.set(K.end, null);
    } else {
      onBreakDue();
    }
  }

  if (state.remOn) {
    if (state.remNext == null || state.remNext < now - HOUR_MS) {
      state.remNext = now + HOUR_MS;
      LS.set(K.remNext, state.remNext);
    } else if (state.remNext <= now) {
      onWaterReminder();
    }
  }

  if (todayKey() !== lastDay) { // gece yarısı geçti
    lastDay = todayKey();
    renderWater();
    renderHistory();
  }

  // egzersiz modalı açıksa ve animasyon döngüsü durmuşsa yeniden başlat
  if (figTimerId == null && $('#exerciseModal').classList.contains('show')) startFigLoop();

  renderBreak();
}
setInterval(tick, 500);
document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });

/* ============================================================
   SU TAKİBİ
   ============================================================ */
const LIQ_TOP = 64, LIQ_BOTTOM = 208, LIQ_H = LIQ_BOTTOM - LIQ_TOP;

function renderWater() {
  const d = waterDay();
  const pct = Math.min(1, d.total / Math.max(1, d.goal));
  const y = LIQ_BOTTOM - LIQ_H * pct;

  $('#liquidRect').setAttribute('y', y.toFixed(1));
  $('#liquidRect').setAttribute('height', Math.max(0, LIQ_BOTTOM - y).toFixed(1));
  const clip = $('#liquidClipRect');
  clip.setAttribute('y', y.toFixed(1));
  clip.setAttribute('height', Math.max(0, LIQ_BOTTOM - y).toFixed(1));

  const surf = $('#liquidSurfacePos');
  surf.setAttribute('transform', `translate(0 ${y.toFixed(1)})`);
  surf.style.opacity = pct > 0.02 ? 1 : 0;

  $('#waterCurrent').textContent = nf.format(d.total);
  $('#waterGoal').textContent = nf.format(d.goal);
  const pctTxt = '%' + Math.round(pct * 100);
  $('#waterPercent').textContent = pctTxt;
  $('#bottlePct').textContent = pctTxt;

  const remain = Math.max(0, d.goal - d.total);
  const glasses = Math.ceil(remain / Math.max(1, state.water.glassMl));
  $('#waterRemaining').textContent = remain > 0
    ? `Hedefe kalan: ${nf.format(remain)} ml (≈ ${glasses} bardak)`
    : 'Bugünlük hedef tamam! 🎉';

  $('#glassLabel').textContent = `(+${nf.format(state.water.glassMl)} ml)`;
  $('#bottleBtn').classList.toggle('full', pct >= 1);
}

function pourAnim() {
  const btn = $('#addWaterBtn');
  btn.classList.remove('pouring');
  void btn.offsetWidth; // animasyonu yeniden tetikle
  btn.classList.add('pouring');
  setTimeout(() => btn.classList.remove('pouring'), 750);
}

function addWater(ml = state.water.glassMl) {
  const d = waterDay();
  d.total = Math.max(0, d.total + ml);
  d.log.push({ t: Date.now(), ml });

  if (d.total >= d.goal && !d.celebrated) {
    d.celebrated = true;
    AudioFX.drop();
    setTimeout(() => {
      celebrate(true);
      AudioFX.success();
      toast('Tebrikler, günlük su hedefini tamamladın! 🏆', { emoji: '🎉', kind: 'success', timeout: 6000 });
    }, 400);
  } else {
    AudioFX.drop();
  }
  if (d.total < d.goal) d.celebrated = false; // geri alınırsa tekrar kutlanabilir

  saveWater();
  renderWater();
  renderHistory();
}

function undoWater() {
  const d = waterDay();
  if (!d.log.length) { toast('Geri alınacak yudum yok', { emoji: '🤷' }); return; }
  const last = d.log.pop();
  d.total = Math.max(0, d.total - last.ml);
  if (d.total < d.goal) d.celebrated = false;
  saveWater();
  renderWater();
  renderHistory();
  toast(`${nf.format(last.ml)} ml geri alındı`, { emoji: '↩️' });
}

function maybeCelebrateGoal() {
  const d = waterDay();
  if (d.total >= d.goal && !d.celebrated) {
    d.celebrated = true;
    saveWater();
    setTimeout(() => {
      celebrate(true);
      AudioFX.success();
      toast('Hedef tamam! Harikasın 🏆', { emoji: '🎉', kind: 'success' });
    }, 350);
  }
}

$('#addWaterBtn').addEventListener('click', () => { pourAnim(); addWater(); });
$('#bottleBtn').addEventListener('click', () => { pourAnim(); addWater(); });
$('#undoWaterBtn').addEventListener('click', undoWater);

$('#goalChips').addEventListener('click', (e) => {
  const chip = e.target.closest('.chip');
  if (!chip) return;
  state.water.goalMl = parseInt(chip.dataset.ml, 10);
  waterDay().goal = state.water.goalMl;
  saveWater();
  $$('#goalChips .chip').forEach(c => c.classList.toggle('active', c === chip));
  renderWater();
  renderHistory();
  toast(`Günlük hedef: ${nf.format(state.water.goalMl)} ml`, { emoji: '🎯' });
  maybeCelebrateGoal();
});

$('#glassChips').addEventListener('click', (e) => {
  const chip = e.target.closest('.chip');
  if (!chip) return;
  state.water.glassMl = parseInt(chip.dataset.ml, 10);
  saveWater();
  $$('#glassChips .chip').forEach(c => c.classList.toggle('active', c === chip));
  renderWater();
  toast(`Bardak boyutu: ${nf.format(state.water.glassMl)} ml`, { emoji: '🥤' });
});

/* ---------- saatlik su hatırlatıcısı ---------- */
function onWaterReminder() {
  state.remNext = Date.now() + HOUR_MS;
  LS.set(K.remNext, state.remNext);
  AudioFX.reminder();
  const d = waterDay();
  toast(`Su içme zamanı! Bugün ${nf.format(d.total)} ml içtin.`, { emoji: '🚰', timeout: 6000 });
  notify('💧 Su molası', `Bir bardak su iç. Bugün: ${nf.format(d.total)} / ${nf.format(state.water.goalMl)} ml`);
}

$('#waterReminderToggle').addEventListener('change', (e) => {
  state.remOn = e.target.checked;
  LS.set(K.remOn, state.remOn);
  if (state.remOn) {
    state.remNext = Date.now() + HOUR_MS;
    LS.set(K.remNext, state.remNext);
    toast('Saatlik su hatırlatıcısı açık', { emoji: '✅' });
  } else {
    toast('Su hatırlatıcısı kapatıldı', { emoji: '🔕' });
  }
});

/* ============================================================
   GEÇMİŞ — son 7 gün grafiği + seri (streak)
   ============================================================ */
const DAY_NAMES = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];

function renderHistory() {
  const chart = $('#historyChart');
  chart.innerHTML = '';
  const days = [];
  let scale = 1;

  for (let i = 6; i >= 0; i--) {
    const dt = new Date(Date.now() - i * DAY_MS);
    const key = todayKey(dt);
    const rec = state.water.days[key];
    const total = rec ? rec.total : 0;
    const goal = rec ? rec.goal : state.water.goalMl;
    scale = Math.max(scale, total, goal);
    days.push({ dt, key, total, goal, isToday: i === 0 });
  }

  days.forEach(d => {
    const col = document.createElement('div');
    col.className = 'bar-col' + (d.isToday ? ' today' : '');
    col.title = `${d.key}: ${nf.format(d.total)} ml / hedef ${nf.format(d.goal)} ml`;
    const h = Math.round((d.total / scale) * 100);
    const goalPct = Math.min(100, Math.round((d.goal / scale) * 100));
    col.innerHTML =
      `<span class="bar-val">${d.total ? nf.format(d.total) : ''}</span>` +
      `<div class="bar-track"><div class="bar" style="height:${h}%"></div><div class="goal-mark" style="bottom:${goalPct}%"></div></div>` +
      `<span class="bar-label">${DAY_NAMES[d.dt.getDay()]}</span>`;
    chart.appendChild(col);
  });

  const d = waterDay();
  $('#todayGlasses').textContent = `${d.log.length} bardak · ${nf.format(d.total)} ml`;

  const streak = computeStreak();
  $('#streakPill').textContent = streak > 0 ? `🔥 ${streak} günlük seri` : '💧 seri yok';
}

function computeStreak() {
  let streak = 0;
  for (let i = 0; i < 365; i++) {
    const dt = new Date(Date.now() - i * DAY_MS);
    const rec = state.water.days[todayKey(dt)];
    const done = rec && rec.goal > 0 && rec.total >= rec.goal;
    if (done) streak++;
    else if (i > 0) break; // bugün tamamlanmadıysa seri dünden sayılır
  }
  return streak;
}

/* ============================================================
   SİLÜET ANİMASYON MOTORU
   Eklem dönüşleri SVG 'rotate(a cx cy)' özniteliğiyle uygulanır —
   pivot koordinatları kesin olduğu için her tarayıcıda doğru çalışır.
   ============================================================ */
const FIG = {
  armL:  document.querySelector('.fig-arm-l'),
  armR:  document.querySelector('.fig-arm-r'),
  foreL: document.querySelector('.fig-fore-l'),
  foreR: document.querySelector('.fig-fore-r'),
  head:  document.querySelector('.fig-head'),
  upper: document.querySelector('.fig-upper'),
  all:   document.querySelector('.fig-all'),
  sitShinL: document.querySelector('.sit-shin-l'),
  sitShinR: document.querySelector('.sit-shin-r'),
  stage: document.getElementById('exerciseStage')
};
const TAU = Math.PI * 2;
/* 0→1→0 dalga (yumuşak gidiş dönüş) */
const wave = (t, p) => { const m = ((t % p) + p) % p; return (1 - Math.cos(TAU * m / p)) / 2; };

const ANIMS = {
  neck(t)       { return { head: 16 * Math.sin(TAU * t / 2.8) }; },
  shoulders(t)  { return { armL: -(360 / 2.4) * t, armR: (360 / 2.4) * t, allY: 2.5 * wave(t, 1.2) }; },
  wrists(t)     { const w = 45 + 7 * Math.sin(TAU * t / 1.4); return { armL: w, armR: -w, foreL: (360 / 1.3) * t, foreR: -(360 / 1.3) * t }; },
  stand(t)      { const s = wave(t, 3.2); return { armL: 150 * s, armR: -150 * s, upperY: -2.5 * s }; },
  side(t)       { const s = wave(t, 3);   return { armR: -150, allR: 18 * (s - 0.5) }; },
  chest(t)      { const s = wave(t, 2.6); return { armL: 15 + 63 * s, armR: -(15 + 63 * s) }; },
  twist(t)      { const s = wave(t, 2.8); return { armL: 55, armR: -55, foreL: -50, foreR: 50, upperR: 26 * (s - 0.5) }; },
  calves(t)     { return { allY: -8 * wave(t, 1.7) }; },
  breathing(t)  { const s = wave(t, 5.4); return { armL: 140 * s, armR: -140 * s, head: 4 * s }; },
  eyes()        { return {}; },
  /* oturma hareketleri */
  squeeze(t)    { const s = wave(t, 2.2); return { armL: 46 + 10 * s, armR: -(46 + 10 * s), foreL: -78, foreR: 78 }; },
  ankles(t)     { const a = 20 * Math.sin(TAU * t / 1.6); return { sitShinL: a, sitShinR: -a }; },
  hiplift(t)    { return { allY: -7 * wave(t, 1.8) }; }
};

function applyFigPose(p) {
  const set = (el, cx, cy, rot, ty) => {
    if (!el) return;
    let tf = '';
    if (typeof ty === 'number') tf += `translate(0 ${ty.toFixed(2)}) `;
    if (typeof rot === 'number') tf += `rotate(${rot.toFixed(2)} ${cx} ${cy})`;
    el.setAttribute('transform', tf.trim());
  };
  set(FIG.armL,  0, 0,   p.armL);
  set(FIG.armR,  0, 0,   p.armR);
  set(FIG.foreL, 0, 0,   p.foreL);
  set(FIG.foreR, 0, 0,   p.foreR);
  set(FIG.head,  0, -78, p.head);
  set(FIG.upper, 0, -4,  p.upperR, p.upperY);
  set(FIG.all,   0, 61,  p.allR,  p.allY);
  // oturur pozisyondaki ayak bilekleri (diz eklemlerinden döner)
  set(FIG.sitShinL, 0, 0, p.sitShinL);
  set(FIG.sitShinR, 0, 0, p.sitShinR);
}

let figTimerId = null, figStart = 0;
function figPulse() {
  if (!$('#exerciseModal').classList.contains('show')) { figTimerId = null; return; }
  const t = (performance.now() - figStart) / 1000;
  const fn = ANIMS[FIG.stage.getAttribute('data-anim')] || ANIMS.neck;
  applyFigPose(fn(t));
}
function startFigLoop() {
  if (figTimerId != null) return;
  figStart = performance.now();
  figTimerId = setInterval(figPulse, 50); // rAF yerine interval: her ortamda kararlı
}

/* ============================================================
   EGZERSİZ MODALI
   ============================================================ */
let exIndex = 0, exLeft = 0, exTimerId = null;
let currentRoutine = [];
const exRingFg = $('#exRingFg');
const EX_RING_C = 2 * Math.PI * parseFloat(exRingFg.getAttribute('r'));

function openExercises() {
  exOpen = true;
  currentRoutine = shuffleArr(EXERCISES).slice(0, ROUTINE_SIZE);
  $('#exerciseModal').classList.add('show');
  $('#exerciseModal').setAttribute('aria-hidden', 'false');
  const totalS = currentRoutine.reduce((a, e) => a + e.dur, 0);
  $('#exTotalInfo').textContent = `${currentRoutine.length} hareket · yaklaşık ${Math.max(1, Math.round(totalS / 60))} dk · her mola farklı rutin`;
  buildDots();
  startFigLoop();
  startEx(0);
}

function buildDots() {
  const dots = $('#exDots');
  dots.innerHTML = '';
  currentRoutine.forEach((ex, i) => {
    const s = document.createElement('span');
    s.className = 'dot';
    s.title = ex.name;
    s.addEventListener('click', () => startEx(i));
    dots.appendChild(s);
  });
}

function startEx(i) {
  if (exTimerId) clearInterval(exTimerId);
  exIndex = i;
  const ex = currentRoutine[i];
  $('#exerciseStage').setAttribute('data-anim', ex.anim);
  $('#exerciseStage').setAttribute('data-pos', ex.seated ? 'sit' : 'stand');
  $('#exName').textContent = ex.name;
  $('#exDesc').textContent = ex.desc;
  $$('#exDots .dot').forEach((d, j) => {
    d.classList.toggle('done', j < i);
    d.classList.toggle('current', j === i);
  });
  $('#exPrev').disabled = i === 0;
  exLeft = ex.dur;
  updateExUI();
  exTimerId = setInterval(() => {
    exLeft--;
    if (exLeft <= 0) startNext();
    else updateExUI();
  }, 1000);
}

function updateExUI() {
  $('#exTime').textContent = exLeft;
  const ex = currentRoutine[exIndex];
  exRingFg.style.strokeDasharray = EX_RING_C;
  exRingFg.style.strokeDashoffset = EX_RING_C * (1 - exLeft / ex.dur);
}

function startNext() {
  if (exIndex >= currentRoutine.length - 1) finishRoutine();
  else { AudioFX.blip(); startEx(exIndex + 1); }
}

function finishRoutine(fromClose = false) {
  if (exTimerId) clearInterval(exTimerId);
  exTimerId = null;
  exOpen = false;
  $('#exerciseModal').classList.remove('show');
  $('#exerciseModal').setAttribute('aria-hidden', 'true');
  if (!fromClose) {
    celebrate(false);
    AudioFX.success();
    toast('Harikaydı! Sayaç yeniden başladı 💪', { emoji: '✨', kind: 'success' });
  } else {
    toast('Mola tamam, sayacı yeniden başlattım', { emoji: '✅' });
  }
  // moladan sonra sayaç otomatik yeniden başlar
  state.breakEnd = Date.now() + state.breakDurMs;
  LS.set(K.end, state.breakEnd);
  renderBreak();
}

$('#exSkip').addEventListener('click', startNext);
$('#exPrev').addEventListener('click', () => { if (exIndex > 0) startEx(exIndex - 1); });
$('#exClose').addEventListener('click', () => finishRoutine(true));
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && exOpen) finishRoutine(true);
});

/* ============================================================
   ÜST BAR — TEMA & SES
   ============================================================ */
$('#themeToggle').addEventListener('click', () => {
  state.theme = state.theme === 'dark' ? 'light' : 'dark';
  LS.set(K.theme, state.theme);
  applyTheme();
});

$('#soundToggle').addEventListener('click', () => {
  state.sound = !state.sound;
  LS.set(K.sound, state.sound);
  setIcon('#soundToggle', state.sound ? 'volume-2' : 'volume-x');
  if (state.sound) { AudioFX.ensure(); AudioFX.blip(); }
  toast(state.sound ? 'Ses açık' : 'Ses kapalı', { emoji: state.sound ? '🔔' : '🔇' });
});

/* ============================================================
   BAŞLATMA
   ============================================================ */
function init() {
  applyTheme();
  setIcon('#soundToggle', state.sound ? 'volume-2' : 'volume-x');

  $$('#durationChips .chip').forEach(c => c.classList.toggle('active', parseInt(c.dataset.min, 10) * 60000 === state.breakDurMs));
  $$('#goalChips .chip').forEach(c => c.classList.toggle('active', parseInt(c.dataset.ml, 10) === state.water.goalMl));
  $$('#glassChips .chip').forEach(c => c.classList.toggle('active', parseInt(c.dataset.ml, 10) === state.water.glassMl));
  $('#waterReminderToggle').checked = state.remOn;

  if (window.lucide) lucide.createIcons();
  renderBreak();
  renderWater();
  renderHistory();
  tick();
}

init();

/* hata ayıklama / hızlı deneme için (konsoldan çağrılabilir) */
window.mikroMola = { addWater, triggerBreak: onBreakDue, celebrate, state };
