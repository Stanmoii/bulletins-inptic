<?php
session_start();
// Si déjà connecté, rediriger directement
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'etudiant') {
        header('Location: mes_notes.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>INPTIC LP ASUR — Plateforme de gestion des notes</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a2540 0%, #1a5276 50%, #0e6655 100%);
            overflow-x: hidden;
        }

        /* Animation de fond */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .bg-animation .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            animation: float 20s infinite ease-in-out;
        }

        .circle-1 { width: 300px; height: 300px; top: -100px; left: -100px; animation-delay: 0s; }
        .circle-2 { width: 500px; height: 500px; bottom: -200px; right: -150px; animation-delay: 2s; }
        .circle-3 { width: 200px; height: 200px; top: 50%; left: 50%; animation-delay: 5s; }
        .circle-4 { width: 150px; height: 150px; bottom: 20%; left: 10%; animation-delay: 1s; }
        .circle-5 { width: 250px; height: 250px; top: 20%; right: 15%; animation-delay: 3s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0) rotate(0deg); }
            25% { transform: translateY(-30px) translateX(20px) rotate(5deg); }
            50% { transform: translateY(0) translateX(40px) rotate(10deg); }
            75% { transform: translateY(30px) translateX(10px) rotate(5deg); }
        }

        /* Contenu principal */
        .main-content {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 40px 20px;
        }

        /* Logo animé */
        .logo-wrapper {
            animation: fadeInUp 1s ease-out;
        }

        .logo-box {
            width: 180px;
            height: 180px;
            background: white;
            border-radius: 30px;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            animation: pulse 2s infinite;
        }

        .logo-box img {
            width: 140px;
            height: 140px;
            object-fit: contain;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
            50% { transform: scale(1.02); box-shadow: 0 25px 60px rgba(0,0,0,0.4); }
        }

        /* Titres */
        h1 {
            font-size: 48px;
            font-weight: 800;
            color: white;
            margin-bottom: 10px;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .subtitle {
            font-size: 18px;
            color: rgba(255,255,255,0.8);
            margin-bottom: 15px;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .accent {
            color: #1abc9c;
        }

        /* Bouton CTA */
        .btn-login {
            display: inline-block;
            background: linear-gradient(135deg, #1abc9c, #0e6655);
            color: white;
            font-size: 18px;
            font-weight: 700;
            padding: 16px 48px;
            border-radius: 50px;
            text-decoration: none;
            margin-top: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            animation: fadeInUp 0.8s ease-out 0.6s both;
            border: none;
            cursor: pointer;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            background: linear-gradient(135deg, #1abc9c, #16a085);
        }

        /* Info année */
        .year {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            text-align: center;
            color: rgba(255,255,255,0.4);
            font-size: 12px;
            animation: fadeInUp 0.8s ease-out 0.8s both;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Effet de texte qui apparaît progressivement */
        .stats-badge {
            display: flex;
            gap: 30px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.5s both;
        }

        .stat-item {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 15px;
            text-align: center;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: #1abc9c;
        }

        .stat-label {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
        }

        /* Responsive */
        @media (max-width: 768px) {
            h1 { font-size: 32px; }
            .subtitle { font-size: 14px; }
            .logo-box { width: 120px; height: 120px; }
            .logo-box img { width: 90px; height: 90px; }
            .btn-login { font-size: 14px; padding: 12px 32px; }
            .stat-number { font-size: 20px; }
            .stat-item { padding: 10px 18px; }
            .stats-badge { gap: 15px; }
        }
    </style>
</head>
<body>

<div class="bg-animation">
    <div class="circle circle-1"></div>
    <div class="circle circle-2"></div>
    <div class="circle circle-3"></div>
    <div class="circle circle-4"></div>
    <div class="circle circle-5"></div>
</div>

<div class="main-content">
    <div class="logo-wrapper">
        <div class="logo-box">
            <img src="logo_inptic.png" alt="INPTIC">
        </div>
    </div>

    <h1>INPTIC <span class="accent">LP ASUR</span></h1>
    <div class="subtitle">
        Administration et Sécurité des Réseaux<br>
        Plateforme de gestion des bulletins de notes
    </div>

    <div class="stats-badge">
        <div class="stat-item">
            <div class="stat-number">📚 15+</div>
            <div class="stat-label">Matières</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">👨‍🎓 25+</div>
            <div class="stat-label">Étudiants</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">📅 2025-2026</div>
            <div class="stat-label">Année universitaire</div>
        </div>
    </div>

    <a href="login.php" class="btn-login">
        🔐 Accéder à mon espace →
    </a>

    <div class="year">
        Institut National de la Poste, des Technologies de l'Information et de la Communication © 2026
    </div>
</div>

</body>
</html>