<?php
// wheel.php — Single-file PHP + JS Wheel of Names (supports >1000 names, picks N winners in one spin)
// Usage: drop this on any PHP-enabled server. No DB required.

// --- Parse inputs (optional: prefill via POST) ---
$rawNames = $_POST['names'] ?? '';
$names = [];
if (!empty($rawNames)) {
    $lines = preg_split("/[\r\n]+/", $rawNames);
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l !== '') $names[] = $l;
    }
}
// Optional CSV upload
if (!empty($_FILES['csv_file']['tmp_name'])) {
    $csv = file_get_contents($_FILES['csv_file']['tmp_name']);
    $rows = preg_split("/[\r\n]+/", $csv);
    foreach ($rows as $row) {
        if (trim($row) === '') continue;
        $cols = preg_split('/[;,]/', $row);
        foreach ($cols as $c) {
            $c = trim($c);
            if ($c !== '') $names[] = $c;
        }
    }
}

// De-duplicate while preserving order
$seen = [];
$unique = [];
foreach ($names as $n) {
    if (!isset($seen[mb_strtolower($n)])) {
        $seen[mb_strtolower($n)] = true;
        $unique[] = $n;
    }
}
$names = $unique;

$winnersPerSpin = isset($_POST['winners']) ? max(1, (int)$_POST['winners']) : 10;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Wheel of Names — Multi Winner Spinner</title>
<style>
  :root {
    --bg:#fff;
    --card:#fff;
    --muted:#b91c1c;
    --accent:#d90429;
    --ok:#22c55e;
    --danger:#ef4444;
  }
  *{box-sizing:border-box}
  body {
    margin:0;
    font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;
    min-height:100vh;
    color:#b91c1c;
    background: linear-gradient(180deg, #fff 0%, #f87171 100%);
    background-image:
      linear-gradient(180deg,rgba(255,255,255,0.85) 60%,rgba(255,0,0,0.10) 100%),
      url('logo-ri80.png');
    background-repeat: no-repeat;
    background-position: center 100px;
    background-size: 480px auto;
  }
  @media (max-width:600px){
    body{background-size: 90vw auto;}
  }
  .wrap{max-width:1200px;margin:24px auto;padding:16px;position:relative;z-index:1;}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  @media (max-width:960px){.grid{grid-template-columns:1fr}}
  .card{
    background:rgba(255,255,255,0.97);
    border:2px solid #d90429;
    border-radius:18px;
    padding:16px;
    box-shadow:0 10px 30px rgba(220,38,38,.10)
  }
  .title{font-size:24px;margin:0 0 12px;color:#d90429;}
  textarea{
    width:100%;min-height:220px;
    border:1.5px solid #d90429;
    background:#fff;
    color:#b91c1c;
    border-radius:12px;
    padding:12px;
    font-size:15px;
    resize:vertical
  }
  .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
  .row > *{flex:0 0 auto}
  input[type="number"]{
    width:110px;
    border:1.5px solid #d90429;
    background:#fff;
    color:#b91c1c;
    border-radius:10px;
    padding:8px
  }
  input[type="file"]{accent-color:var(--accent)}
  label{font-size:13px;color:#b91c1c;}
  .btn{
    border:0;
    background:linear-gradient(90deg,#d90429 60%,#fff 100%);
    color:#fff;
    padding:10px 14px;
    border-radius:12px;
    font-weight:600;
    cursor:pointer;
    transition:transform .06s,filter .2s;
    box-shadow:0 2px 8px rgba(220,38,38,.10)
  }
  .btn:hover{filter:brightness(1.08)}
  .btn:active{transform:translateY(1px)}
  .btn-ghost{background:transparent;border:1.5px solid #d90429;color:#d90429;}
  .btn-ok{background:linear-gradient(90deg,#d90429 60%,#fff 100%);}
  .btn-danger{background:#ef4444;}
  .muted{color:#b91c1c;}
  .tag{
    display:inline-flex;align-items:center;gap:6px;
    background:#fff0f0;
    border:1px solid #d90429;
    border-radius:999px;
    padding:6px 10px;
    font-size:12px;
    color:#d90429;
  }
  .canvas-wrap{position:relative;aspect-ratio:1;max-width:640px;margin-inline:auto}
  canvas{width:100%;height:100%;display:block;filter:drop-shadow(0 20px 50px rgba(220,38,38,.10));transition:transform 0.3s;}
  .needle{position:absolute;inset:auto 0 50% 0;margin:auto;width:0;height:0;border-left:16px solid transparent;border-right:16px solid transparent;border-bottom:28px solid #d90429;filter:drop-shadow(0 4px 10px rgba(220,38,38,.15));animation:needle-bounce 1s ease-in-out infinite;}
  @keyframes needle-bounce{0%,100%{transform:translateY(-2px);}50%{transform:translateY(2px);}}
  @keyframes announce{from{opacity:0;transform:translate(-50%,-60%);}to{opacity:1;transform:translate(-50%,-50%);}}
  .overlay{position:absolute;inset:0;display:flex;align-items:end;justify-content:center;padding:14px;pointer-events:none}
  .overlay .stat{background:rgba(255,0,0,.08);backdrop-filter:blur(6px);border:1px solid #d90429;border-radius:12px;padding:8px 12px;font-size:12px;color:#d90429;}
  .list{max-height:280px;overflow:auto;border:1px solid #d90429;border-radius:12px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px 12px;border-bottom:1px solid #fca5a5;font-size:14px}
  th{position:sticky;top:0;background:#fff0f0;text-align:left;color:#d90429;}
  .pill{display:inline-block;padding:3px 8px;border-radius:999px;background:#fff0f0;border:1px solid #d90429;font-size:12px;color:#d90429;}
  .win{color:#d90429;font-weight:bold;}
  .footer{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:10px}

  /* Popup rolling dan winner */
  .popup-rolling, .popup-winner {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    background: rgba(255,255,255,0.98);
    color: #d90429;
    padding: 2.2rem 2.5rem 2rem 2.5rem;
    border-radius: 1rem;
    box-shadow: 0 0 20px rgba(220,38,38,0.18);
    z-index: 1001;
    min-width: 320px;
    text-align: center;
    animation: announce 0.5s;
    font-size:1.2rem;
  }
  .popup-rolling .rolling-name {
    font-size: 2.2rem;
    font-weight: bold;
    margin: 1.5rem 0 0.5rem 0;
    letter-spacing: 2px;
    animation: rolling-flash 0.2s alternate infinite;
  }
  @keyframes rolling-flash {
    from { filter: brightness(1.2);}
    to   { filter: brightness(0.8);}
  }
  .popup-winner-close {
    position: absolute;
    top: 10px; right: 16px;
    background: transparent;
    border: none;
    color: #d90429;
    font-size: 1.5rem;
    cursor: pointer;
    opacity: 0.7;
    z-index: 10;
  }
  .popup-winner-close:hover { opacity: 1; }
</style>
</head>
<body>
  <div class="wrap">
    <h1 class="title">Wheel of Names HUT RI ke 80 PT VS Technology Indonesia</h1>
    <p class="muted">Masukkan daftar nama (satu per baris) atau unggah CSV. Aplikasi ini bisa menampung ribuan nama. Tekan <b>PUTAR</b> untuk mengundi dan menghasilkan beberapa pemenang sekaligus.</p>

    <div class="grid">
      <div class="card">
        <h2 class="title">Daftar Peserta</h2>
        <form method="post" enctype="multipart/form-data" class="row" onsubmit="return false;">
          <div style="flex:1 1 100%">
            <textarea id="names" name="names" placeholder="Contoh:\nAndi\nBudi\nSiti\n..."><?php echo htmlspecialchars(implode("\n", $names)); ?></textarea>
          </div>
          <div class="row" style="gap:16px;align-items:end">
            <div>
              <label for="winners">Jumlah pemenang/putar</label><br>
              <input id="winners" name="winners" type="number" min="1" value="<?php echo (int)$winnersPerSpin; ?>">
            </div>
            <div>
              <label for="csv_file">Atau unggah CSV</label><br>
              <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv">
            </div>
            <div class="row">
              <label class="tag"><input type="checkbox" id="removeAfter" checked> Hapus pemenang dari pool</label>
              <label class="tag"><input type="checkbox" id="allowDuplicates"> Izinkan nama duplikat</label>
            </div>
            <button class="btn" id="btnLoad">Muat Daftar</button>
            <button class="btn btn-danger" id="btnClear">Bersihkan</button>
          </div>
        </form>
      </div>

      <div class="card">
        <h2 class="title">Roda Undian</h2>
        <div class="canvas-wrap">
          <canvas id="wheel" width="1200" height="1200"></canvas>
          <div class="needle"></div>
          <div class="overlay"><div class="stat"><span id="statCount">0</span> peserta</div></div>
        </div>
        <div class="footer">
          <div class="row">
            <button class="btn btn-ok" id="btnSpin">PUTAR</button>
            <div id="result"></div>
            <button class="btn btn-ghost" id="btnExport">Unduh Hasil (CSV)</button>
          </div>
          <div class="row muted">
            <span class="pill">Adil & tanpa pengulangan (kecuali diizinkan)</span>
            <span class="pill">>1000 peserta didukung</span>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <h2 class="title">Hasil Undian</h2>
      <div class="list">
        <table>
          <thead>
            <tr><th style="width:80px">#</th><th>Nama</th><th>Putaran</th><th>Tanggal</th></tr>
          </thead>
          <tbody id="resultsBody"></tbody>
        </table>
      </div>
      <div class="row" style="margin-top:10px">
        <button class="btn btn-ghost" id="btnReset">Reset Semua</button>
      </div>
    </div>
  </div>

<script>
(function(){
  // --- State ---
  const initialNames = <?php echo json_encode(array_values($names), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  let pool = [...initialNames];
  let results = []; // {name, round, ts}
  let round = 0;

  // --- Elements ---
  const elNames = document.getElementById('names');
  const elWinners = document.getElementById('winners');
  const elRemove = document.getElementById('removeAfter');
  const elAllowDup = document.getElementById('allowDuplicates');
  const elBtnLoad = document.getElementById('btnLoad');
  const elBtnClear = document.getElementById('btnClear');
  const elBtnSpin = document.getElementById('btnSpin');
  const elBtnExport = document.getElementById('btnExport');
  const elBtnReset = document.getElementById('btnReset');
  const elStat = document.getElementById('statCount');
  const wheel = document.getElementById('wheel');
  const ctx = wheel.getContext('2d');
  const tbody = document.getElementById('resultsBody');
  const fileInput = document.getElementById('csv_file');

  // --- Helpers ---
  function normalizeNames(list, allowDup){
    const out = [];
    const seen = new Set();
    for (let raw of list) {
      let s = String(raw).trim();
      if (!s) continue;
      if (allowDup) { out.push(s); continue; }
      const key = s.toLowerCase();
      if (!seen.has(key)) { seen.add(key); out.push(s); }
    }
    return out;
  }

  function rngInt(max){
    if (window.crypto && window.crypto.getRandomValues) {
      const buf = new Uint32Array(1); window.crypto.getRandomValues(buf); return buf[0] % max;
    }
    return Math.floor(Math.random()*max);
  }

  function sampleWithoutReplacement(arr, k){
    if (k > arr.length) throw new Error('Jumlah pemenang melebihi jumlah peserta.');
    const copy = arr.slice();
    for (let i = 0; i < k; i++) {
      const j = i + rngInt(copy.length - i);
      const tmp = copy[i]; copy[i] = copy[j]; copy[j] = tmp;
    }
    return copy.slice(0,k);
  }

  function drawWheel(names){
    const n = Math.max(names.length, 1);
    const W = wheel.width, H = wheel.height; const R = Math.min(W,H)/2 - 10;
    ctx.clearRect(0,0,W,H);
    ctx.save(); ctx.translate(W/2,H/2);
    const baseHue = 0;
    for (let i=0;i<n;i++){
      const a0 = (i/n)*Math.PI*2; const a1 = ((i+1)/n)*Math.PI*2;
      ctx.beginPath();
      ctx.moveTo(0,0);
      ctx.arc(0,0,R,a0,a1);
      const hue = (baseHue + (i*137.508)) % 360;
      ctx.fillStyle = `hsl(${hue}, 90%, 85%)`;
      ctx.fill();
      ctx.strokeStyle = 'rgba(220,38,38,.25)'; ctx.lineWidth = 2; ctx.stroke();
      if (names.length <= 24) {
        const mid = (a0+a1)/2; const r = R*0.72;
        ctx.save();
        ctx.rotate(mid);
        ctx.translate(r,0);
        ctx.rotate(Math.PI/2);
        ctx.fillStyle = '#d90429';
        ctx.font = '24px system-ui, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(String(names[i]).slice(0,24),0,0);
        ctx.restore();
      }
    }
    ctx.beginPath(); ctx.arc(0,0,R*0.18,0,Math.PI*2); ctx.fillStyle='#fff'; ctx.fill();
    ctx.beginPath(); ctx.arc(0,0,R*0.16,0,Math.PI*2); ctx.fillStyle='#d90429'; ctx.fill();
    ctx.fillStyle='#d90429';
    ctx.font = 'bold 36px system-ui, sans-serif'; ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillText('WHEEL',0,-8);
    ctx.font = '14px system-ui, sans-serif'; ctx.fillText(`${names.length} peserta`,0,22);

    ctx.restore();
    elStat.textContent = names.length;
  }

  function renderResults(){
    tbody.innerHTML = '';
    results.forEach((r,i)=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${i+1}</td><td class="win">${escapeHtml(r.name)}</td><td>Putaran ${r.round}</td><td>${new Date(r.ts).toLocaleString()}</td>`;
      tbody.appendChild(tr);
    });
  }

  function escapeHtml(s){
    return s.replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));
  }

  function exportCSV(){
    const header = 'No,Nama,Putaran,Tanggal\n';
    const lines = results.map((r,i)=>`${i+1},"${r.name.replace(/"/g,'""')}",${r.round},"${new Date(r.ts).toLocaleString()}"`).join('\n');
    const blob = new Blob([header+lines],{type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href=url; a.download='hasil_undian.csv'; a.click();
    setTimeout(()=>URL.revokeObjectURL(url), 1000);
  }

  function ingestTextarea(){
    const raw = elNames.value.split(/\r?\n/);
    pool = normalizeNames(raw, elAllowDup.checked);
    saveLocal();
    drawWheel(pool);
  }

  function saveLocal(){
    const state = { pool, results, round, remove: elRemove.checked, allowDup: elAllowDup.checked, winnersPerSpin: parseInt(elWinners.value||'1',10) };
    localStorage.setItem('wheel_state_v2', JSON.stringify(state));
  }
  function loadLocal(){
    const s = localStorage.getItem('wheel_state_v2');
    if (!s) return;
    try{
      const st = JSON.parse(s);
      pool = Array.isArray(st.pool) ? st.pool : pool;
      results = Array.isArray(st.results) ? st.results : results;
      round = Number.isFinite(st.round) ? st.round : round;
      if (typeof st.remove === 'boolean') elRemove.checked = st.remove;
      if (typeof st.allowDup === 'boolean') elAllowDup.checked = st.allowDup;
      if (Number.isFinite(st.winnersPerSpin)) elWinners.value = st.winnersPerSpin;
      elNames.value = pool.join('\n');
    }catch(e){}
  }

  // --- Animasi dan Suara (tanpa suara, bisa ditambah jika ingin) ---
  function animateSpin(durationMs=3200){
    return new Promise(resolve=>{
      const start = performance.now();
      function frame(now){
        const t = Math.min(1, (now-start)/durationMs);
        const ease = 1 - Math.pow(1-t, 3);
        const revolutions = 6 + Math.random()*3;
        const angle = ease * Math.PI*2*revolutions;
        wheel.style.transform = `rotate(${angle}rad) scale(${1 + Math.sin(t * Math.PI) * 0.02})`;
        if (t < 1) requestAnimationFrame(frame); else {
          wheel.style.transform = '';
          resolve();
        }
      }
      requestAnimationFrame(frame);
    });
  }

  // --- Efek animasi popup rolling nama ---
  async function doSpin(){
    const k = Math.max(1, parseInt(elWinners.value||'1',10));
    if (pool.length === 0){ alert('Belum ada peserta.'); return; }
    if (!elAllowDup.checked && k > pool.length){ alert('Jumlah pemenang melebihi jumlah peserta. Kurangi jumlah pemenang atau tambah peserta.'); return; }

    elBtnSpin.disabled = true;
    await animateSpin();

    // --- Rolling Animation ---
    const rollingPopup = document.createElement('div');
    rollingPopup.className = 'popup-rolling';
    rollingPopup.innerHTML = `
      <div style="font-size:1.2rem;">Mengacak nama...</div>
      <div class="rolling-name">-</div>
    `;
    document.body.appendChild(rollingPopup);

    // Rolling effect: tampilkan nama acak selama 1.5 detik
    let rolling = true;
    const rollingName = rollingPopup.querySelector('.rolling-name');
    let rollingInterval = setInterval(()=>{
      const name = pool.length ? pool[Math.floor(Math.random()*pool.length)] : '-';
      rollingName.textContent = name;
    }, 60);

    setTimeout(()=>{
      rolling = false;
      clearInterval(rollingInterval);

      // Pilih pemenang
      round += 1;
      const now = Date.now();
      const winners = elAllowDup.checked ?
        Array.from({length:k}, ()=> pool[rngInt(pool.length)]) :
        sampleWithoutReplacement(pool, k);

      // Ganti popup menjadi hasil pemenang
      rollingPopup.className = 'popup-winner';
      rollingPopup.innerHTML = `
        <button class="popup-winner-close" title="Tutup">&times;</button>
        <h2 style="margin:0 0 1rem;color:#d90429;">🎉 Pemenang Putaran ${round}!</h2>
        <ul style="margin:0;padding-left:1.5rem;text-align:left;">
          ${winners.map(w => `<li style="font-size:1.5rem;font-weight:bold;">${escapeHtml(w)}</li>`).join('')}
        </ul>
      `;

      // Tombol close
      rollingPopup.querySelector('.popup-winner-close').onclick = function() {
        rollingPopup.style.animation = 'announce 0.5s ease-in reverse';
        rollingPopup.addEventListener('animationend', () => rollingPopup.remove());
      };

      for (const name of winners){ results.push({name, round, ts: now}); }

      if (elRemove.checked && !elAllowDup.checked){
        const removeSet = new Set(winners.map(w=>w.toLowerCase()));
        pool = pool.filter(n=>!removeSet.has(String(n).toLowerCase()));
        elNames.value = pool.join('\n');
      }

      drawWheel(pool);
      renderResults();
      saveLocal();
      elBtnSpin.disabled = false;

      // Auto-close popup setelah 3 detik jika belum di-close manual
      setTimeout(() => {
        if (document.body.contains(rollingPopup)) {
          rollingPopup.style.animation = 'announce 0.5s ease-in reverse';
          rollingPopup.addEventListener('animationend', () => rollingPopup.remove());
        }
      }, 3000);

    }, 1500);
  }

  // --- Events ---
  elBtnLoad.addEventListener('click', ()=>{ ingestTextarea(); });
  elBtnClear.addEventListener('click', ()=>{ if(confirm('Bersihkan daftar peserta?')){ elNames.value=''; pool=[]; drawWheel(pool); saveLocal(); }});
  elBtnSpin.addEventListener('click', ()=>{ doSpin(); });
  elBtnExport.addEventListener('click', ()=>{ if(results.length===0){alert('Belum ada hasil.');return;} exportCSV(); });
  elBtnReset.addEventListener('click', ()=>{
    if (!confirm('Reset semua data hasil dan putaran?')) return;
    results = []; round = 0; saveLocal(); renderResults();
  });

  fileInput.addEventListener('change', async (e)=>{
    const file = e.target.files[0]; if(!file) return;
    const text = await file.text();
    const rows = text.split(/\r?\n/).filter(Boolean);
    const names = [];
    for (const row of rows){
      const cols = row.split(/[;,]/).map(s=>s.trim()).filter(Boolean);
      if (cols.length) names.push(...cols);
    }
    const normalized = normalizeNames(names, elAllowDup.checked);
    elNames.value = normalized.join('\n');
    pool = normalized; drawWheel(pool); saveLocal();
  });

  elRemove.addEventListener('change', saveLocal);
  elAllowDup.addEventListener('change', ()=>{ ingestTextarea(); });
  elWinners.addEventListener('change', saveLocal);

  // --- Init ---
  loadLocal();
  if (pool.length===0 && initialNames.length>0) pool = normalizeNames(initialNames, false);
  drawWheel(pool);
  renderResults();
})();
</script>
</body>
</html>
