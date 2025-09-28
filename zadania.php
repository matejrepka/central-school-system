<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="sk">

<head>
  <meta charset="UTF-8">
  <title>Zadania</title>
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
  <h1>Zadania</h1>
  <p>Obsah stránky zadania...</p>
</body>
</html>