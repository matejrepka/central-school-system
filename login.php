<?php
session_start();

// If the user is already logged in, redirect to the index page
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="sk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prihlásenie</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Ensure full-height works reliably */
    html, body {
    }

    body {
      font-family: Arial, sans-serif;
      background-color: #f4f4f9;
      margin: 0;
      padding: 0;
      min-height: 100vh;
      max-width: none;
      width: 100%;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .login-container {
      background: #fff;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 420px;
      display: flex;
      flex-direction: column;
      align-items: center;
      box-sizing: border-box;
    }

    h1 {
      text-align: center;
      color: #333;
      margin-bottom: 1.5rem;
    }

    .form-group {
      margin-bottom: 1rem;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      box-sizing: border-box;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      color: #555;
      text-align: center;
      width: 100%;
      max-width: 360px;
    }

    input {
      width: 100%;
      max-width: 500px;
      padding: 0.75rem;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
      text-align: center;
      box-sizing: border-box;
    }

    .actions {
      text-align: center;
      width: 100%;
      display: flex;
      justify-content: center;
    }

    button {
      background-color: #007bff;
      color: #fff;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 4px;
      font-size: 1rem;
      cursor: pointer;
    }

    button:hover {
      background-color: #0056b3;
    }

    #msg {
      margin-top: 1rem;
      text-align: center;
      color: red;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h1>Prihlásenie</h1>
    <form id="authForm">
      <div class="form-group">
        <label for="username">Používateľské meno</label>
        <input id="username" type="text" autocomplete="username" placeholder="Zadajte používateľské meno">
      </div>
      <div class="form-group">
        <label for="password">Heslo</label>
        <input id="password" type="password" autocomplete="current-password" placeholder="Zadajte heslo">
      </div>
      <div class="actions">
        <button type="button" id="login">Prihlásiť sa</button>
      </div>
    </form>
    <div id="msg"></div>
  </div>
  <script>
    (function(){
      const loginBtn = document.getElementById('login');
      const msg = document.getElementById('msg');
      const userInp = document.getElementById('username');
      const passInp = document.getElementById('password');

      async function doLogin(){
        msg.textContent = '';
        const username = userInp.value.trim();
        const password = passInp.value;
        if(!username || !password){ 
          msg.textContent = 'Vyplnte meno a heslo'; 
          console.error('Debug: Username or password is empty');
          return; 
        }
        try{
          const res = await fetch('./api/login.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
          });
          const response = await res.json();
          if (!res.ok) {
            msg.textContent = response.error || 'Prihlasenie zlyhalo';
            console.error('Debug:', response.debug || 'Unknown error');
            return;
          }
          // success -> redirect to index
          console.log('Debug: Login successful, redirecting to', response.redirect);
          window.location.href = response.redirect;
        }catch(e){ 
          msg.textContent = 'Chyba spojenia k serveru'; 
          console.error('Debug: Connection error', e);
        }
      }

      loginBtn.addEventListener('click', doLogin);
      // also allow Enter key
      [userInp, passInp].forEach(el=> el.addEventListener('keydown', (e)=>{ if(e.key === 'Enter') doLogin(); }));
    })();
  </script>

</body>
</html>