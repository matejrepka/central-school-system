<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// simple admin gate - allow only session user 'admin' or is_admin flag
$isAdmin = false;
if (isset($_SESSION['username']) && $_SESSION['username'] === 'admin') $isAdmin = true;
// Note: api/admin_users.php performs a more robust check for requests
if (!$isAdmin) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="sk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Users</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .admin-wrap{max-width:900px;margin:24px auto;padding:16px;background:#fff;border-radius:8px}
    .grid{display:grid;grid-template-columns:1fr 320px;gap:16px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid #eee;text-align:left}
    .small{font-size:0.9rem}
    .stat{background:#f7f7fb;padding:8px;border-radius:6px;margin-bottom:8px}
  </style>
</head>
<body>
  <div class="admin-wrap">
    <h1>Admin — správa používateľov</h1>
    <div class="grid">
      <div>
        <section>
          <h2>Existujúci používatelia</h2>
          <div id="usersContainer">Načítavam...</div>
        </section>

        <section style="margin-top:16px">
          <h2>Štatistiky</h2>
          <div id="statsContainer">Načítavam štatistiky...</div>
        </section>
      </div>

      <aside>
        <section>
          <h2>Pridať používateľa</h2>
          <div>
            <label>Username<br><input id="u_username" type="text"></label><br>
            <label>Email<br><input id="u_email" type="email"></label><br>
            <label>Heslo<br><input id="u_password" type="password"></label><br>
            <label>Trieda / Skupina<br><input id="u_class" type="text"></label><br>
            <button id="createBtn">Vytvoriť používateľa</button>
            <div id="createMsg" class="small"></div>
          </div>
        </section>
      </aside>
    </div>
  </div>

  <script>
    async function loadUsers(){
      const c = document.getElementById('usersContainer');
      c.textContent = 'Načítavam...';
      try{
        const res = await fetch('api/admin_users.php', { credentials: 'same-origin' });
        if (!res.ok) { c.textContent = 'Chyba pri načítaní ('+res.status+')'; return; }
        const rows = await res.json();
        if (!rows || rows.length === 0) { c.innerHTML = '<div class="small">Žiadni používatelia</div>'; return; }
        let html = '<table><thead><tr><th>#</th><th>username</th><th>email</th><th>class</th><th>created</th></tr></thead><tbody>';
        rows.forEach(r=>{ html += `<tr><td>${r.id}</td><td>${escapeHtml(r.username)}</td><td>${escapeHtml(r.email)}</td><td>${escapeHtml(r.class_group||'')}</td><td>${escapeHtml(r.created_at||'')}</td></tr>`; });
        html += '</tbody></table>';
        c.innerHTML = html;
      }catch(e){ c.textContent = 'Chyba spojenia'; }
    }

    async function loadStats(){
      const c = document.getElementById('statsContainer');
      c.textContent = 'Načítavam...';
      try{
        const res = await fetch('api/admin_users.php?action=stats', { credentials: 'same-origin' });
        if (!res.ok) { c.textContent = 'Chyba pri načítaní štatistík'; return; }
        const j = await res.json();
        let html = '';
        html += `<div class="stat">Celkový počet používateľov: <strong>${j.total}</strong></div>`;
        html += '<div class="stat"><strong>Podľa triedy / skupiny</strong><br>';
        if (j.by_group && j.by_group.length){ j.by_group.forEach(g=> html += `<div>${escapeHtml(g.class_group||'(no group)')} — ${g.cnt}</div>`); } else { html += '<div>Žiadne dáta</div>'; }
        html += '</div>';
        html += '<div class="stat"><strong>Prihlásenia / registrácie (posledných 30 dní)</strong><br>';
        if (j.trend && j.trend.length){ j.trend.forEach(t => html += `<div>${escapeHtml(t.day)} — ${t.cnt}</div>`); } else { html += '<div>Žiadne dáta</div>'; }
        html += '</div>';
        c.innerHTML = html;
      }catch(e){ c.textContent = 'Chyba spojenia'; }
    }

    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }

    document.getElementById('createBtn').addEventListener('click', async function(){
      const u = document.getElementById('u_username').value.trim();
      const e = document.getElementById('u_email').value.trim();
      const p = document.getElementById('u_password').value;
      const g = document.getElementById('u_class').value.trim();
      const msg = document.getElementById('createMsg'); msg.textContent = '';
      if (!u || !e || !p) { msg.textContent = 'Vyplňte username, email a heslo'; return; }
      try{
        const res = await fetch('api/admin_users.php', { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ username: u, email: e, password: p, class_group: g }) });
        const j = await res.json();
        if (!res.ok) { msg.textContent = j.error || 'Chyba'; return; }
        msg.textContent = 'Používateľ vytvorený (id=' + j.id + ')';
        document.getElementById('u_username').value=''; document.getElementById('u_email').value=''; document.getElementById('u_password').value=''; document.getElementById('u_class').value='';
        loadUsers(); loadStats();
      }catch(e){ msg.textContent = 'Chyba spojenia'; }
    });

    loadUsers(); loadStats();
  </script>
</body>
</html>
