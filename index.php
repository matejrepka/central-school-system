<?php
// Simple index page: server-side load of mandatory subjects (povinne_predmety)
// This keeps the original UI (nav, dark-mode, client-side subject management)
// but also renders the mandatory subjects on the server with a single SELECT.

require_once __DIR__ . '/config.php';

// Start session so we can show current user info and use session-based API auth
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Expect $pdo to be a configured PDO instance in config.php
try {
  $subjects = [];
  if (isset($pdo) && $pdo instanceof PDO) {
    $stmt = $pdo->query('SELECT id, code, name, description FROM povinne_predmety ORDER BY name');
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $error = 'Database connection not available.';
  }
} catch (Exception $e) {
  $error = 'Chyba pri načítaní predmetov.';
}
?>

<!DOCTYPE html>
<html lang="sk">

<head>
  <meta charset="UTF-8">
  <title>Moje predmety</title>
  <link rel="stylesheet" href="style.css?v=1.0.1">
  <link rel="stylesheet" href="ui-enhancements.css?v=1.0.1">
</head>

<body>
  <button id="modeToggle">Dark / Light</button>

  <header class="site-header">
    <nav class="main-nav" aria-label="Hlavná navigácia">
      <ul>
        <li><a href="index.php" class="nav-link">Predmety</a></li>
        <li><a href="rozvrh.php" class="nav-link">Rozvrh</a></li>
        <li><a href="zadania.php" class="nav-link">Zadania</a></li>
        <li><a href="testy.php" class="nav-link">Testy</a></li>
      </ul>
    </nav>

    <div id="userBanner">
      <?php if (!empty($_SESSION['username'])): ?>
        <span class="user-text">Prihlásený ako <strong><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></span>
        <button id="logoutBtn" class="small" aria-label="Odhlásiť">Odhlásiť</button>
      <?php else: ?>
        <a href="login.php" class="small">Prihlásiť sa</a>
      <?php endif; ?>
    </div>
  </header>

  <h1>Moje predmety</h1>

  <!-- Server-rendered mandatory subjects -->
  <section id="mandatorySubjects" style="margin:16px 0;padding:12px;border:0px solid #eee">
    <h2>Povinné predmety</h2>
      <?php if (!empty($error)): ?>
        <p class="error" style="color:#900"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php else: ?>
        <?php if (empty($subjects)): ?>
          <p>Žiadne povinné predmety.</p>
        <?php else: ?>
          <?php
            // Load all links and schedules for mandatory subjects and group them by povinne_id
            $linksBy = [];
            $scheduleBy = [];
            if (isset($pdo) && $pdo instanceof PDO) {
              try {
                $stmt = $pdo->query('SELECT povinne_id, title, url, position FROM povinne_links ORDER BY povinne_id, position');
                $allLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($allLinks as $ln) {
                  $linksBy[(int)$ln['povinne_id']][] = $ln;
                }

                $stmt = $pdo->query('SELECT povinne_id, day, start_time, end_time, type, class_group, position FROM povinne_schedule ORDER BY povinne_id, position, start_time');
                $allSched = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($allSched as $sc) {
                  $scheduleBy[(int)$sc['povinne_id']][] = $sc;
                }
              } catch (Exception $e) {
                // Non-fatal: show subjects but skip links/schedule on error
              }
            }
          ?>

          <?php foreach ($subjects as $s): ?>
            <?php $id = (int)($s['id'] ?? 0); ?>
            <div class="subject">
              <h3 style="margin:0 0 6px 0"><strong><?php echo htmlspecialchars($s['name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                <?php if (!empty($s['code'])): ?>
                  <span class="code" style="color:#666;margin-left:6px"><?php echo htmlspecialchars($s['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                <?php endif; ?>
              </h3>

              <div class="content subject-inline" style="margin-top:8px">
                <?php // Links inline under title ?>
                <div class="links links-inline">
                  <?php if (!empty($linksBy[$id])): ?>
                    <?php foreach ($linksBy[$id] as $ln): ?>
                      <?php $title = $ln['title'] ?? $ln['url']; ?>
                      <div class="link-item">
                        <a href="<?php echo htmlspecialchars($ln['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" target="_blank"><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></a>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="no-links">Žiadne odkazy.</div>
                  <?php endif; ?>
                </div>

                <?php // Schedule inline under title ?>
                <div class="time sched-inline">
                  <?php if (!empty($scheduleBy[$id])): ?>
                    <?php foreach ($scheduleBy[$id] as $row): ?>
                      <?php
                        $day = $row['day'];
                        $start = isset($row['start_time']) ? substr($row['start_time'], 0, 5) : '';
                        $end = isset($row['end_time']) ? substr($row['end_time'], 0, 5) : '';
                        $typeClass = htmlspecialchars($row['type'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                      ?>
                      <div class="sched-item <?php echo $typeClass; ?>"><?php echo htmlspecialchars($day, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> <?php echo htmlspecialchars($start, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>-<?php echo htmlspecialchars($end, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> <span class="sched-type"><?php echo $typeClass; ?></span></div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="no-schedule">Žiadne časy.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>
  </section>

  <!-- User-created subjects (rendered by client-side JS) - displayed under mandatory subjects and above the form -->
  <section id="userSubjectsSection" style="margin:16px 0;padding:12px;border:0px solid #eee">
    <h2>Moje predmety (vaše)</h2>
    <div id="subjectsListMain"></div>
  </section>

  <section class="task-form" id="subjectsSection">
    <h2>Predmety (správa)</h2>
    <div class="form-rows">
      <!-- Row 1: name + class -->
      <div class="form-row form-row--split">
        <label>Názov predmetu
          <input id="newSubjectName" type="text" class="control" placeholder="Názov predmetu">
        </label>
        <label>Trieda / skupina (voliteľné)
          <input id="newSubjectClassGroup" type="text" class="control" placeholder="napr. 3, B1">
        </label>
      </div>

      <!-- Row 2: links (one line) -->
      <div class="form-row form-row--inline" id="linksEditor">
        <div style="flex:1"><strong>Odkazy (voliteľné)</strong>
          <div id="linksList"></div>
        </div>
        <div class="inline-controls">
          <input id="linkTitle" class="control" type="text" placeholder="Názov odkazu (voliteľné)">
          <input id="linkUrl" class="control" type="url" placeholder="URL (napr. https://...)">
          <button type="button" id="addLinkBtn" class="btn btn--small">Pridať odkaz</button>
        </div>
      </div>

      <!-- Row 3: schedule (one line) -->
      <div class="form-row form-row--inline" id="scheduleEditor">
        <div style="flex:1"><strong>Rozvrh (voliteľné)</strong>
          <div id="scheduleList"></div>
        </div>
        <div class="inline-controls">
          <select id="schedDay" class="control">
            <option value="">Deň</option>
            <option>Pondelok</option>
            <option>Utorok</option>
            <option>Streda</option>
            <option>Štvrtok</option>
            <option>Piatok</option>
            <option>Sobota</option>
            <option>Nedeľa</option>
          </select>
          <input id="schedStart" class="control" type="time" placeholder="Začiatok">
          <input id="schedEnd" class="control" type="time" placeholder="Koniec">
          <select id="schedType" class="control">
            <option value="lecture">Lecture</option>
            <option value="exercise">Exercise</option>
            <option value="course">Course</option>
            <option value="other">Other</option>
          </select>
          <input id="schedGroup" class="control" type="text" placeholder="Trieda/skupina (voliteľné)">
          <button type="button" id="addSchedBtn" class="btn btn--small">Pridať čas</button>
        </div>
      </div>

      <!-- Row 4: submit -->
      <div class="form-row form-row--actions">
        <button id="addSubjectBtn" class="btn">Pridať predmet</button>
      </div>
    </div>
  </section>

  
<script>
// Dark mode initializer and toggle. Uses localStorage (key: darkMode) and falls back to prefers-color-scheme.
(function(){
  const btn = document.getElementById('modeToggle');
  function applyDark(dark, save) {
    if (dark) document.body.classList.add('dark-mode'); else document.body.classList.remove('dark-mode');
    if (btn) {
      btn.textContent = dark ? 'Light mode' : 'Dark mode';
      btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
    }
    try { if (save) localStorage.setItem('darkMode', dark ? '1' : '0'); } catch(e) {}
  }

  // Initialize
  try {
    const stored = localStorage.getItem('darkMode');
    if (stored === null) {
      const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      applyDark(prefers, false);
    } else {
      applyDark(stored === '1', false);
    }
  } catch (e) {
    // ignore storage errors
  }

  if (btn) btn.addEventListener('click', function(){ applyDark(!document.body.classList.contains('dark-mode'), true); });
})();
</script>

<script>
// Logout button handler: sends POST to api/logout.php then redirects to login page
(function(){
  const btn = document.getElementById('logoutBtn');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    try {
      btn.disabled = true;
      const res = await fetch('api/logout.php', { method: 'POST', credentials: 'same-origin' });
      if (res.ok) {
        // navigate to login page after logout
        window.location.href = 'login.php';
      } else {
        // fallback: try GET redirect
        window.location.href = 'api/logout.php';
      }
    } catch (e) {
      // network issue - fallback to browser redirect
      window.location.href = 'api/logout.php';
    }
  });
})();
</script>

<script>
// Client-side subjects management: list, create, delete via api/subjects.php
(function(){
  const listEl = document.getElementById('subjectsListMain');
  const addBtn = document.getElementById('addSubjectBtn');
  const nameInput = document.getElementById('newSubjectName');
  const classInput = document.getElementById('newSubjectClassGroup');

  // Links editor elements
  const linksListEl = document.getElementById('linksList');
  const addLinkBtn = document.getElementById('addLinkBtn');
  const linkTitleInput = document.getElementById('linkTitle');
  const linkUrlInput = document.getElementById('linkUrl');
  const linksState = [];

  // Schedule editor elements
  const scheduleListEl = document.getElementById('scheduleList');
  const addSchedBtn = document.getElementById('addSchedBtn');
  const schedDay = document.getElementById('schedDay');
  const schedStart = document.getElementById('schedStart');
  const schedEnd = document.getElementById('schedEnd');
  const schedType = document.getElementById('schedType');
  const schedGroup = document.getElementById('schedGroup');
  const scheduleState = [];

  function showMessage(msg, isError) {
    const el = document.createElement('div');
    el.textContent = msg;
    el.style.margin = '8px 0';
    el.style.color = isError ? '#900' : '#060';
    listEl.parentNode.insertBefore(el, listEl);
    setTimeout(()=>el.remove(), 3000);
  }

  // Link / schedule editor helpers
  function renderLinksEditor() {
    linksListEl.innerHTML = '';
    linksState.forEach((ln, idx) => {
      const d = document.createElement('div');
      d.style.marginTop = '6px';
      d.innerHTML = '<strong>' + escapeHtml(ln.title || ln.url || '') + '</strong> <span style="color:#666">' + escapeHtml(ln.url || '') + '</span> ';
      const rem = document.createElement('button'); rem.textContent = 'Odstrániť'; rem.style.marginLeft='8px';
      rem.addEventListener('click', ()=>{ linksState.splice(idx,1); renderLinksEditor(); });
      d.appendChild(rem);
      linksListEl.appendChild(d);
    });
  }

  function renderScheduleEditor() {
    scheduleListEl.innerHTML = '';
    scheduleState.forEach((s, idx) => {
      const d = document.createElement('div');
      d.style.marginTop = '6px';
      d.textContent = (s.day||'') + ' ' + (s.start_time||'') + '-' + (s.end_time||'') + ' ' + (s.type||'') + (s.class_group?(' ('+s.class_group+')'):'');
      const rem = document.createElement('button'); rem.textContent = 'Odstrániť'; rem.style.marginLeft='8px';
      rem.addEventListener('click', ()=>{ scheduleState.splice(idx,1); renderScheduleEditor(); });
      d.appendChild(rem);
      scheduleListEl.appendChild(d);
    });
  }

  addLinkBtn.addEventListener('click', function(){
    const url = (linkUrlInput.value||'').trim();
    if (!url) { showMessage('Zadajte URL', true); return; }
    try {
      // Basic URL validation
      const u = new URL(url);
      linksState.push({ title: (linkTitleInput.value||'').trim() || null, url: u.href, position: linksState.length });
      linkTitleInput.value = ''; linkUrlInput.value = '';
      renderLinksEditor();
    } catch (e) {
      showMessage('Neplatná URL', true);
    }
  });

  addSchedBtn.addEventListener('click', function(){
    const day = (schedDay.value||'').trim();
    const start = (schedStart.value||'').trim();
    const end = (schedEnd.value||'').trim();
    if (!day || !start || !end) { showMessage('Zadajte deň, začiatok a koniec', true); return; }
    // ensure time format HH:MM
    const timeRe = /^\d{2}:\d{2}$/;
    if (!timeRe.test(start) || !timeRe.test(end)) { showMessage('Neplatný formát času (HH:MM)', true); return; }
    scheduleState.push({ day: day, start_time: start, end_time: end, type: (schedType.value||'lecture').trim(), class_group: (schedGroup.value||'').trim() || null, position: scheduleState.length });
    schedDay.value=''; schedStart.value=''; schedEnd.value=''; schedType.value='lecture'; schedGroup.value='';
    renderScheduleEditor();
  });

  async function fetchSubjects() {
    try {
      const res = await fetch('api/subjects.php', { credentials: 'same-origin' });
      if (!res.ok) {
        const j = await res.json().catch(()=>({error:'Unknown'}));
        listEl.innerHTML = '<div class="error">Chyba: '+(j.error||res.statusText)+'</div>';
        return;
      }
      const rows = await res.json();
      renderSubjects(rows);
    } catch (e) {
      listEl.innerHTML = '<div class="error">Chyba pri načítaní predmetov.</div>';
    }
  }

  function renderSubjects(rows) {
    listEl.innerHTML = '';
    if (!rows || rows.length === 0) {
      listEl.innerHTML = '<div style="color:#666">Nemáte žiadne predmety.</div>';
      return;
    }
    const wrap = document.createElement('div');
    rows.forEach(r => {
      const id = r.id;
      const name = r.name || '(bez názvu)';
      const group = r.class_group || '';
      const subj = document.createElement('div');
      subj.className = 'subject';

  const h3 = document.createElement('h3');
  h3.style.margin = '0 0 6px 0';
  h3.innerHTML = '<strong>'+escapeHtml(name)+'</strong>' + (group ? ' <span class="code" style="color:#666">('+escapeHtml(group)+')</span>' : '');
  subj.appendChild(h3);

      const content = document.createElement('div');
      content.className = 'content subject-inline';

      // links inline
      const linksDiv = document.createElement('div');
      linksDiv.className = 'links links-inline';
      if (Array.isArray(r.links) && r.links.length) {
        r.links.forEach(l => {
          const title = l.title || l.url || '';
          const li = document.createElement('div');
          li.className = 'link-item';
          li.innerHTML = '<a href="'+escapeHtml(l.url||'#')+'" target="_blank">'+escapeHtml(title)+'</a>';
          linksDiv.appendChild(li);
        });
      } else {
        const no = document.createElement('div'); no.className = 'no-links'; no.textContent = 'Žiadne odkazy.'; linksDiv.appendChild(no);
      }
      content.appendChild(linksDiv);

      // schedule inline (format times to HH:MM like server)
      const schedDiv = document.createElement('div');
      schedDiv.className = 'time sched-inline';
      if (Array.isArray(r.schedule) && r.schedule.length) {
        r.schedule.forEach(s => {
          const item = document.createElement('div');
          const typeClass = (s.type||'').toString().trim();
          item.className = 'sched-item ' + escapeHtml(typeClass);
          const start = (s.start_time||'').toString().substr(0,5);
          const end = (s.end_time||'').toString().substr(0,5);
          const typeText = escapeHtml(typeClass);
          item.innerHTML = escapeHtml((s.day||'') + ' ' + start + '-' + end) + ' <span class="sched-type">' + typeText + '</span>';
          schedDiv.appendChild(item);
        });
      } else {
        const no = document.createElement('div'); no.className = 'no-schedule'; no.textContent = 'Žiadne časy.'; schedDiv.appendChild(no);
      }
      content.appendChild(schedDiv);

      // actions - small × in top-right
      const actions = document.createElement('div'); actions.className = 'subject-actions';
      if (typeof id === 'number' || (/^\d+$/.test(String(id)))) {
        const del = document.createElement('button'); del.className = 'delete-x'; del.setAttribute('aria-label','Odstrániť predmet'); del.innerHTML = '&times;'; del.addEventListener('click', ()=>deleteSubject(id)); actions.appendChild(del);
      }
      subj.appendChild(actions);
      subj.appendChild(content);
      wrap.appendChild(subj);
    });
    listEl.appendChild(wrap);
  }

  async function deleteSubject(id) {
    if (!confirm('Naozaj odstrániť tento predmet?')) return;
    try {
      const res = await fetch('api/subjects.php?id='+encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' });
      const j = await res.json();
      if (res.ok && j.success) {
        showMessage('Predmet odstránený');
        fetchSubjects();
      } else {
        showMessage('Chyba: '+(j.error||res.statusText), true);
      }
    } catch (e) {
      showMessage('Chyba pri odstraňovaní', true);
    }
  }

  async function addSubject() {
    const name = (nameInput.value || '').trim();
    const class_group = (classInput.value || '').trim();
    if (!name) {
      showMessage('Zadajte názov predmetu', true);
      return;
    }
    const payload = { action: 'create', name: name };
    if (class_group) payload.class_group = class_group;
    if (linksState.length) payload.links = linksState.map((l, i)=> ({ title: l.title, url: l.url, position: i }));
    if (scheduleState.length) payload.schedule = scheduleState.map((s, i)=> ({ day: s.day, start_time: s.start_time, end_time: s.end_time, type: s.type, class_group: s.class_group, position: i }));
    try {
      const res = await fetch('api/subjects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const j = await res.json();
      if (res.ok && j.success) {
        nameInput.value = '';
        classInput.value = '';
        linksState.length = 0; scheduleState.length = 0; renderLinksEditor(); renderScheduleEditor();
        showMessage('Predmet pridaný');
        fetchSubjects();
      } else {
        showMessage('Chyba: '+(j.error||res.statusText), true);
      }
    } catch (e) {
      showMessage('Chyba pri pridávaní predmetu', true);
    }
  }

  function escapeHtml(s){ return String(s).replace(/[&<>\"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":"&#39;"}[c]; }); }

  addBtn.addEventListener('click', addSubject);
  // allow Enter to submit when focus is in name input
  nameInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); addSubject(); } });

  // initial load
  renderLinksEditor(); renderScheduleEditor();
  fetchSubjects();
})();
</script>

</body>
</html>