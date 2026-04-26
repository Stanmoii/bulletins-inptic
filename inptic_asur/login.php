<?php
session_start();
require 'connexion.php';

// Si déjà connecté, rediriger selon le rôle
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'etudiant') {
        header('Location: dashboard.php');
    } elseif ($_SESSION['role'] === 'enseignant') {
        header('Location: enseignant.php');
    } elseif ($_SESSION['role'] === 'secretariat') {
        header('Location: secretariat.php');
    } elseif ($_SESSION['role'] === 'admin') {
        header('Location: dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$erreur = "";

if (isset($_POST['connexion'])) {
    $login    = trim($_POST['login']);
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM utilisateur WHERE login = ? AND actif = 1");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérification du mot de passe (supporte BCrypt ET MD5)
    $password_valid = false;
    
    if ($user) {
        // Vérifier si c'est du BCrypt (commence par $2y$)
        if (strpos($user['password_hash'], '$2y$') === 0) {
            $password_valid = password_verify($password, $user['password_hash']);
        } else {
            // Sinon, vérifier avec MD5
            $password_valid = (md5($password) === $user['password_hash']);
        }
    }
    
    if ($user && $password_valid) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['nom']      = $user['nom'];
        $_SESSION['prenom']   = $user['prenom'];
        $_SESSION['login']    = $user['login'];
        $_SESSION['role']     = $user['role'];
        $_SESSION['user_nom'] = $user['nom'] . ' ' . $user['prenom'];
        
        // Redirection selon le rôle
        if ($user['role'] === 'etudiant') {
            header('Location: dashboard.php');
        } elseif ($user['role'] === 'enseignant') {
            header('Location: enseignant.php');
        } elseif ($user['role'] === 'secretariat') {
            header('Location: secretariat.php');
        } elseif ($user['role'] === 'admin') {
            header('Location: dashboard.php');
        } else {
            header('Location: dashboard.php');
        }
        exit();
    } else {
        $erreur = "Identifiant ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion — INPTIC LP ASUR</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a2540 0%, #1a5276 50%, #0e6655 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            display: flex;
            width: 900px;
            min-height: 520px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }

        .left-panel {
            flex: 1;
            background: linear-gradient(160deg, rgba(10,37,64,0.88), rgba(14,102,85,0.88));
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            top: -80px; left: -80px;
        }

        .left-panel::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            bottom: -50px; right: -50px;
        }

        .left-panel > * { position: relative; z-index: 1; }

        .logo-box {
            width: 120px;
            height: 120px;
            border-radius: 16px;
            overflow: hidden;
            border: 3px solid rgba(255,255,255,0.25);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            margin-bottom: 24px;
            background: white;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .left-panel h2 {
            color: #1abc9c;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .left-panel h1 {
            color: white;
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .divider {
            width: 50px; height: 3px;
            background: linear-gradient(90deg, #1abc9c, #16a085);
            border-radius: 3px;
            margin: 16px auto;
        }

        .left-panel p {
            color: rgba(255,255,255,0.75);
            font-size: 13px;
            line-height: 1.8;
        }

        .right-panel {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 50px 45px;
        }

        .right-panel h3 {
            font-size: 28px;
            color: #0a2540;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .right-panel p.subtitle {
            color: #999;
            font-size: 14px;
            margin-bottom: 35px;
        }

        .form-group { margin-bottom: 22px; }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #eef0f3;
            border-radius: 10px;
            font-size: 15px;
            color: #333;
            transition: border-color 0.3s, box-shadow 0.3s;
            outline: none;
            background: #fafbfc;
        }

        .form-group input:focus {
            border-color: #1a5276;
            box-shadow: 0 0 0 4px rgba(26,82,118,0.08);
            background: white;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0a2540, #1a5276);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: opacity 0.2s, transform 0.1s;
            margin-top: 8px;
        }

        .btn-login:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(10,37,64,0.3);
        }

        .btn-back {
            display: inline-block;
            margin-top: 15px;
            text-align: center;
            color: #1abc9c;
            text-decoration: none;
            font-size: 12px;
        }

        .btn-back:hover {
            text-decoration: underline;
        }

        .erreur {
            background: #fff5f5;
            border-left: 4px solid #e74c3c;
            color: #c0392b;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .footer-note {
            text-align: center;
            margin-top: 35px;
            font-size: 11px;
            color: #ccc;
            letter-spacing: 0.5px;
        }

        .accent { color: #1abc9c; }
    </style>
</head>
<body>

<div class="login-wrapper">

    <div class="left-panel">
        <div class="logo-box">
            <img src="logo_inptic.png" alt="Logo INPTIC">
        </div>
        <h2>INPTIC</h2>
        <h1>LP ASUR</h1>
        <div class="divider"></div>
        <p>Administration et Sécurité<br>des Réseaux</p>
        <br>
        <p>Gestion des bulletins de notes<br>Année universitaire 2025-2026</p>
    </div>

    <div class="right-panel">
        <h3>Bon retour <span class="accent">👋</span></h3>
        <p class="subtitle">Connectez-vous à votre espace personnel</p>

        <?php if ($erreur): ?>
            <div class="erreur">⚠️ <?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Identifiant</label>
                <input type="text" name="login"
                       placeholder="Entrez votre identifiant"
                       value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                       required autofocus>
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" name="connexion" class="btn-login">
                Se connecter →
            </button>
            <a href="index.php" class="btn-back">← Retour à l'accueil</a>
        </form>

        <div class="footer-note">
            Institut National de la Poste, des Technologies de l'Information et de la Communication © 2026
        </div>
    </div>

</div>

</body>
</html>