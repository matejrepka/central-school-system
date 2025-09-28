<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
// Load mandatory subjects and schedule (if available)
$mandatorySubjects = [];
$scheduleByDay = [];
require_once __DIR__ . '/config.php';
try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $stmt = $pdo->query('SELECT id, name, code FROM povinne_predmety ORDER BY name');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $mandatorySubjects[(int)$r['id']] = $r;
    }

    $stmt = $pdo->query('SELECT povinne_id, day, start_time, end_time, type, class_group FROM povinne_schedule ORDER BY day, start_time');
    $sched = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sched as $s) {
      $day = $s['day'] ?? '';
      $pid = (int)($s['povinne_id'] ?? 0);
      $entry = [
        'povinne_id' => $pid,
        'name' => isset($mandatorySubjects[$pid]) ? $mandatorySubjects[$pid]['name'] : ('Predmet ' . $pid),
        'code' => isset($mandatorySubjects[$pid]) ? $mandatorySubjects[$pid]['code'] : '',
        'start' => isset($s['start_time']) ? substr($s['start_time'],0,5) : '',
        'end' => isset($s['end_time']) ? substr($s['end_time'],0,5) : '',
        'type' => $s['type'] ?? '',
        'class_group' => $s['class_group'] ?? null,
      ];
      $scheduleByDay[$day][] = $entry;
    }
    // Also load user's own subjects and schedules so they appear server-side
    if (!empty($_SESSION['user_id'])) {
      $uid = (int)$_SESSION['user_id'];
      try {
        $ustmt = $pdo->prepare('SELECT id, name, class_group FROM subjects WHERE user_id = ?');
        $ustmt->execute([$uid]);
        $urows = $ustmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($urows as $ur) {
          $sid = (int)$ur['id'];
          $sstmt = $pdo->prepare('SELECT day, start_time, end_time, type, class_group FROM subject_schedule WHERE subject_id = ? ORDER BY position');
          $sstmt->execute([$sid]);
          $usched = $sstmt->fetchAll(PDO::FETCH_ASSOC);
          foreach ($usched as $us) {
            $day = $us['day'] ?? '';
            $entry = [
              'subject_id' => $sid,
              'name' => $ur['name'] ?? ('Predmet ' . $sid),
              'code' => '',
              'start' => isset($us['start_time']) ? substr($us['start_time'],0,5) : '',
              'end' => isset($us['end_time']) ? substr($us['end_time'],0,5) : '',
              'type' => $us['type'] ?? '',
              'class_group' => $us['class_group'] ?? $ur['class_group'] ?? null,
            ];
            $scheduleByDay[$day][] = $entry;
          }
        }
      } catch (Exception $e) {
        // non-fatal
      }
    }
  }
} catch (Exception $e) {
  // non-fatal - leave schedules empty
}
?>
<!DOCTYPE html>
<html lang="sk">

<head>
  <meta charset="UTF-8">
  <title>Rozvrh</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
  <button id="modeToggle">Dark / Light</button>

  <nav class="main-nav" aria-label="Hlavná navigácia">
    <ul>
      <li><a href="index.php" class="nav-link">Predmety</a></li>
      <li><a href="rozvrh.php" class="nav-link">Rozvrh</a></li>
      <li><a href="zadania.php" class="nav-link">Zadania</a></li>
      <li><a href="testy.php" class="nav-link">Testy</a></li>
    </ul>
  </nav>
  <h1>Rozvrh</h1>

  <p>Nižšie je zobrazený týždenný rozvrh. Predmety z povinných predmetov sú načítané zo servera. Vaše vlastné predmety sa pridávajú klientsky.</p>

  <section id="timetableSection">
    <table id="timetable" class="timetable" border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:1000px">
      <thead>
        <tr>
          <th style="width:14%">Čas</th>
          <th>Pondelok</th>
          <th>Utorok</th>
          <th>Streda</th>
          <th>Štvrtok</th>
          <th>Piatok</th>
        </tr>
      </thead>
      <tbody>
        <?php
        // Period template (from your image): each period starts at these times and lasts 50 minutes
        $periodStarts = [
          '08:10','09:00','09:50','10:40','11:30','12:20','13:10','14:00','14:50','15:40','16:30','17:20','18:10','19:00'
        ];
        $periodDurationMin = 50;

        // helper to add minutes to HH:MM
        function add_minutes($time, $minutes) {
          $dt = DateTime::createFromFormat('H:i', $time);
          if (!$dt) return $time;
          $dt->modify('+' . intval($minutes) . ' minutes');
          return $dt->format('H:i');
        }

        // returns true if event overlaps the period [pstart, pend)
        function overlaps_period($pstart, $pend, $estart, $eend) {
          return ($estart < $pend) && ($eend > $pstart);
        }

        // Prepare occupied map to support rowspans and compute per-row heights
        $days = ['Pondelok','Utorok','Streda','Štvrtok','Piatok'];
        $periodCount = count($periodStarts);
        $occupied = [];
        foreach ($days as $d) $occupied[$d] = array_fill(0, $periodCount, false);

        // px per minute for sizing
        $pxPerMinute = 1.2; // tweakable

        // helper: minutes difference between HH:MM strings
        function minutes_diff($a, $b) {
          $da = DateTime::createFromFormat('H:i', $a);
          $db = DateTime::createFromFormat('H:i', $b);
          if (!$da || !$db) return 0;
          $diff = $db->getTimestamp() - $da->getTimestamp();
          return intval(round($diff / 60));
        }

        for ($i = 0; $i < $periodCount; $i++) {
          $periodStart = $periodStarts[$i];
          // compute slot minutes to next period start (if exists)
          if (isset($periodStarts[$i+1])) {
            $slotMinutes = minutes_diff($periodStart, $periodStarts[$i+1]);
          } else {
            $slotMinutes = $periodDurationMin; // fallback
          }
          // row height corresponds to the full slot (lesson + break)
          $rowHeightPx = round($slotMinutes * $pxPerMinute);

          echo "<tr style=\"height:{$rowHeightPx}px\">";
          echo "<td style=\"font-weight:600;white-space:nowrap;\">" . htmlspecialchars($periodStart, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . "</td>";

          foreach ($days as $col => $d) {
            if (!empty($occupied[$d][$i])) {
              // this slot is covered by a rowspan from a previous period - skip output
              continue;
            }

            // determine lesson interval for this period: lesson ends 10 minutes before next period start
            if (isset($periodStarts[$i+1])) {
              $lessonEnd = add_minutes($periodStarts[$i+1], -10);
            } else {
              // fallback lesson end = start + (slotMinutes - 10)
              $lessonEnd = add_minutes($periodStart, max(0, $slotMinutes - 10));
            }

            // find any event overlapping this lesson interval
            $foundEvent = null;
            if (!empty($scheduleByDay[$d])) {
              foreach ($scheduleByDay[$d] as $e) {
                if (overlaps_period($periodStart, $lessonEnd, $e['start'], $e['end'])) { $foundEvent = $e; break; }
              }
            }

            if (!$foundEvent) {
              // empty cell; include data attributes for client-side placement
              echo '<td data-col="' . $col . '" data-day="' . htmlspecialchars($d, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '" data-time="' . htmlspecialchars($periodStart, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '" data-rowstart="' . $i . '"></td>';
            } else {
              // compute how many periods this event spans (based on lesson intervals)
              $span = 1;
              for ($j = $i + 1; $j < $periodCount; $j++) {
                $pjStart = $periodStarts[$j];
                if (isset($periodStarts[$j+1])) {
                  $pjLessonEnd = add_minutes($periodStarts[$j+1], -10);
                } else {
                  $pjSlot = minutes_diff($pjStart, $periodStarts[$j+1] ?? add_minutes($pjStart, $periodDurationMin));
                  $pjLessonEnd = add_minutes($pjStart, max(0, $pjSlot - 10));
                }
                if (overlaps_period($pjStart, $pjLessonEnd, $foundEvent['start'], $foundEvent['end'])) { $span++; $occupied[$d][$j] = true; } else break;
              }
              $occupied[$d][$i] = true;

              // compute event duration in minutes (clamp to periods range)
              $eventStart = $foundEvent['start']; $eventEnd = $foundEvent['end'];
              $durationMin = minutes_diff($eventStart, $eventEnd);
              $heightPx = round($durationMin * $pxPerMinute);

              $title = htmlspecialchars($foundEvent['name'] . ($foundEvent['code'] ? ' (' . $foundEvent['code'] . ')' : ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
              $meta = htmlspecialchars(($foundEvent['class_group'] ? $foundEvent['class_group'] . ' ' : '') . $foundEvent['type'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
              // sanitize type to safe classname
              $rawType = $foundEvent['type'] ?? 'other';
              $typeClass = preg_replace('/[^a-z0-9_-]/i','', strtolower($rawType));
              if ($typeClass === '') $typeClass = 'other';
              echo '<td class="tcell" rowspan="' . $span . '" data-col="' . $col . '" data-day="' . htmlspecialchars($d, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '" data-time="' . htmlspecialchars($periodStart, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '" data-rowstart="' . $i . '">';
              echo '<div class="tentry tentry--' . $typeClass . '" style="height:' . $heightPx . 'px"><div class="tentry-title">' . $title . '</div><div class="tentry-meta">' . $meta . '</div><div class="tentry-time">' . htmlspecialchars($foundEvent['start']) . '-' . htmlspecialchars($foundEvent['end']) . '</div></div>';
              echo '</td>';
            }
          }

          echo "</tr>\n";
        }
        ?>
      </tbody>
    </table>
  </section>

  <style>
    /* Minimal timetable styles */
    .tcell { background:#f9f9f9; vertical-align:top; }
    .tentry { background:#dfeffd; padding:6px; border-radius:4px; margin:2px 0; font-size:0.95em }
    .tentry-title { font-weight:700 }
    .tentry-meta { color:#444; font-size:0.85em }
    .tentry-time { color:#333; font-size:0.85em }
    /* Type color variants */
    .tentry--lecture { background:#dfeffd; border-left:4px solid #2b78ff }
    .tentry--exercise { background:#ffdfe0; border-left:4px solid #d9534f }
    .tentry--course { background:#fff4d6; border-left:4px solid #d4a017 }
    .tentry--other { background:#e9eef6; border-left:4px solid #777 }
    body.dark-mode .tentry { background:#2b3a48; color:#fff }
  </style>

  <script>
  // Fetch user-created subjects and inject their schedule entries into the table
  (function(){
    async function loadUserSubjects() {
      try {
        const res = await fetch('api/subjects.php', { credentials: 'same-origin' });
        if (!res.ok) return;
        const rows = await res.json();
        rows.forEach(r => {
          if (!Array.isArray(r.schedule)) return;
          r.schedule.forEach(s => {
            const day = s.day;
            const est = (s.start_time||'').substr(0,5);
            const eet = (s.end_time||'').substr(0,5);
            if (!est || !eet) return;
            // period starts (must match server template)
            const periodStarts = ['08:10','09:00','09:50','10:40','11:30','12:20','13:10','14:00','14:50','15:40','16:30','17:20','18:10','19:00'];
            // find first period that overlaps [est,eet)
            // find first overlapping period index
            let startIdx = -1;
            for (let idx = 0; idx < periodStarts.length; idx++) {
              const p = periodStarts[idx]; const pend = addMinutes(p, 50);
              if (est < pend && eet > p) { startIdx = idx; break; }
            }
            if (startIdx === -1) return;
            // compute span count
            let span = 0;
            for (let idx = startIdx; idx < periodStarts.length; idx++) {
              const p = periodStarts[idx]; const pend = addMinutes(p, 50);
              if (est < pend && eet > p) span++; else break;
            }

            // try to find an existing server-rendered cell at the start period
            const startP = periodStarts[startIdx];
            let target = document.querySelector('#timetable td[data-day="'+day+''][data-time="'+startP+'"]');
            const pxPerMinute = 1.2;
            const durationMin = (function(a,b){ const pa=a.split(':'), pb=b.split(':'); return (parseInt(pb[0],10)*60+parseInt(pb[1],10)) - (parseInt(pa[0],10)*60+parseInt(pa[1],10)); })(est,eet);
            const heightPx = Math.round(durationMin * pxPerMinute);
            // sanitize type for classname
            const rawTypeStr = (s.type||'other').toString().trim().toLowerCase();
            const typeClassSafe = rawTypeStr.replace(/[^a-z0-9_-]/g,'') || 'other';
            if (target && target.hasAttribute('rowspan')) {
              // server already rendered a spanning cell - append inside and adjust height if needed
              const title = (r.name||'(bez názvu)') + (r.class_group ? ' ('+r.class_group+')' : '');
              const div = document.createElement('div'); div.className='tentry tentry--'+typeClassSafe;
              div.style.height = heightPx + 'px';
              div.innerHTML = '<div class="tentry-title">'+escapeHtml(title)+'</div><div class="tentry-meta">'+escapeHtml(s.type||'')+'</div><div class="tentry-time">'+escapeHtml(est)+'-'+escapeHtml(eet)+'</div>';
              target.appendChild(div);
            } else {
              // create a new td with rowspan and insert into the correct place in the table
              // find a reference cell to insert before: query for the td that has data-time = startP (may be empty)
              let ref = document.querySelector('#timetable td[data-day="'+day+''][data-time="'+startP+'"]');
              // If ref is null, the start cell might be covered by an earlier rowspan; try to find the cell in the same row with matching data-col
              if (!ref) {
                // find any td with same data-col and data-rowstart <= startIdx
                const col = (function(){ const el = document.querySelector('#timetable td[data-time="'+startP+'"]'); return el ? el.getAttribute('data-col') : null; })();
                if (col !== null) {
                  ref = document.querySelector('#timetable td[data-col="'+col+'"]');
                }
              }
              // create td
              const td = document.createElement('td'); td.className='tcell'; td.setAttribute('rowspan', String(span)); td.setAttribute('data-day', day); td.setAttribute('data-time', startP);
              const title = (r.name||'(bez názvu)') + (r.class_group ? ' ('+r.class_group+')' : '');
              td.innerHTML = '<div class="tentry tentry--'+typeClassSafe+'" style="height:'+heightPx+'px"><div class="tentry-title">'+escapeHtml(title)+'</div><div class="tentry-meta">'+escapeHtml(s.type||'')+'</div><div class="tentry-time">'+escapeHtml(est)+'-'+escapeHtml(eet)+'</div></div>';
              if (ref && ref.parentNode) {
                ref.parentNode.insertBefore(td, ref);
                // remove the subsequent covered empty cells in following rows (they are now spanned)
                for (let k = 1; k < span; k++) {
                  const nxtP = periodStarts[startIdx + k];
                  const cellToRemove = document.querySelector('#timetable td[data-day="'+day+''][data-time="'+nxtP+'"]');
                  if (cellToRemove && cellToRemove.parentNode) cellToRemove.parentNode.removeChild(cellToRemove);
                }
              } else {
                // fallback: append to first tbody row
                const tb = document.querySelector('#timetable tbody');
                if (tb) tb.rows[0].appendChild(td);
              }
            }
          });
        });
      } catch (e) {
        // ignore
      }
    }
    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }
    function addMinutes(time, minutes) {
      // time in HH:MM
      const parts = time.split(':');
      if (parts.length !== 2) return time;
      const d = new Date(); d.setHours(parseInt(parts[0],10), parseInt(parts[1],10), 0, 0);
      d.setMinutes(d.getMinutes() + parseInt(minutes,10));
      const hh = String(d.getHours()).padStart(2,'0');
      const mm = String(d.getMinutes()).padStart(2,'0');
      return hh + ':' + mm;
    }
    loadUserSubjects();
  })();
  </script>
</body>
</html>