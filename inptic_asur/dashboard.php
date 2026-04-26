<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role    = $_SESSION['role'];
$nomUser = $_SESSION['user_nom'];

$nb_etudiants = 0;
$nb_notes     = 0;
$nb_matieres  = 0;
if (in_array($role, ['admin', 'secretariat'])) {
    $nb_etudiants = $db->query("SELECT COUNT(*) FROM etudiant")->fetchColumn();
    $nb_notes     = $db->query("SELECT COUNT(*) FROM evaluation")->fetchColumn();
    $nb_matieres  = $db->query("SELECT COUNT(*) FROM matiere")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>INPTIC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* ── LOGO EN ARRIÈRE-PLAN (MODIFIÉ) ── */
        .page-bg {
            position: relative;
            min-height: 100vh;
        }
        .page-bg::before {
            content: '';
            position: fixed;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 900px;
            height: 900px;
            background: url('logo_inptic.png') center/contain no-repeat;
            opacity: 0.12;
            filter: blur(2px);
            pointer-events: none;
            z-index: 0;
        }

        /* ── NAVBAR ── */
        .navbar {
            background: linear-gradient(135deg, #0a2540, #1a5276);
            padding: 0 30px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-left { display: flex; align-items: center; gap: 14px; }
        .nav-logo {
            width: 40px; height: 40px; background: white;
            border-radius: 8px; padding: 4px;
            display: flex; align-items: center; justify-content: center;
        }
        .nav-logo img { width: 100%; height: 100%; object-fit: contain; }
        .nav-title { color: white; font-size: 16px; font-weight: 700; }
        .nav-title span { color: #1abc9c; }
        .nav-right { display: flex; align-items: center; gap: 14px; }
        .badge-role {
            background: rgba(26,188,156,0.2);
            color: #1abc9c;
            border: 1px solid rgba(26,188,156,0.3);
            padding: 5px 14px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
        }
        .nav-user { color: rgba(255,255,255,0.85); font-size: 14px; }
        .btn-logout {
            background: rgba(231,76,60,0.15); color: #e74c3c;
            border: 1px solid rgba(231,76,60,0.3);
            padding: 7px 16px; border-radius: 8px;
            font-size: 13px; font-weight: 600;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-logout:hover { background: rgba(231,76,60,0.3); }

        /* ── HERO ── */
        .hero {
            background: linear-gradient(135deg, #0a2540 0%, #1a5276 60%, #0e6655 100%);
            padding: 40px 0 60px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .hero::before {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            top: -100px; right: -100px;
        }
        .hero::after {
            content: '';
            position: absolute;
            width: 250px; height: 250px;
            background: rgba(26,188,156,0.06);
            border-radius: 50%;
            bottom: -60px; left: 10%;
        }
        .hero-content {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px;
            position: relative;
            z-index: 1;
        }
        .hero h1 { color: white; font-size: 30px; font-weight: 800; margin-bottom: 6px; }
        .hero p   { color: rgba(255,255,255,0.6); font-size: 15px; }
        .hero-accent { color: #1abc9c; }

        /* ── STATS ── */
        .stats-wrapper {
            max-width: 1100px;
            margin: -36px auto 0;
            padding: 0 24px;
            position: relative;
            z-index: 10;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-icon.blue   { background: #E6F1FB; }
        .stat-icon.green  { background: #EAF3DE; }
        .stat-icon.orange { background: #FAEEDA; }
        .stat-icon.purple { background: #EEEDFE; }
        .stat-info .val { font-size: 26px; font-weight: 800; color: #0a2540; line-height: 1; }
        .stat-info .lbl { font-size: 12px; color: #999; margin-top: 4px; }

        /* ── CONTAINER ── */
        .container {
            max-width: 1100px;
            margin: 32px auto;
            padding: 0 24px;
            position: relative;
            z-index: 1;
        }

        .section-title {
            font-size: 13px; font-weight: 700;
            color: #999; text-transform: uppercase;
            letter-spacing: 1.5px; margin-bottom: 16px;
        }

        /* ── CARDS GRID ── */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 28px 20px;
            text-align: center;
            text-decoration: none;
            color: #222;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 2px solid transparent;
            transition: all 0.25s;
            position: relative;
            overflow: hidden;
        }

        /* Barre colorée en haut de chaque card */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            border-radius: 16px 16px 0 0;
            transition: opacity 0.25s;
        }

        /* Couleurs spécifiques par card */
        .card-etudiants::before  { background: linear-gradient(90deg, #1a5276, #2e86c1); opacity: 1; }
        .card-notes::before      { background: linear-gradient(90deg, #1abc9c, #0e6655); opacity: 1; }
        .card-absences::before   { background: linear-gradient(90deg, #f39c12, #d68910); opacity: 1; }
        .card-resultats::before  { background: linear-gradient(90deg, #8e44ad, #6c3483); opacity: 1; }
        .card-bulletins::before  { background: linear-gradient(90deg, #2e86c1, #1a5276); opacity: 1; }
        .card-jury::before       { background: linear-gradient(90deg, #e74c3c, #c0392b); opacity: 1; }
        .card-import::before     { background: linear-gradient(90deg, #1abc9c, #16a085); opacity: 1; }
        .card-admin::before      { background: linear-gradient(90deg, #e74c3c, #922b21); opacity: 1; }
        .card-mes-notes::before  { background: linear-gradient(90deg, #1a5276, #1abc9c); opacity: 1; }
        .card-bulletin::before   { background: linear-gradient(90deg, #0e6655, #1abc9c); opacity: 1; }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 32px rgba(0,0,0,0.12);
        }

        .card-etudiants:hover { border-color: #2e86c1; }
        .card-notes:hover     { border-color: #1abc9c; }
        .card-absences:hover  { border-color: #f39c12; }
        .card-resultats:hover { border-color: #8e44ad; }
        .card-bulletins:hover { border-color: #2e86c1; }
        .card-jury:hover      { border-color: #e74c3c; }
        .card-import:hover    { border-color: #1abc9c; }
        .card-admin:hover     { border-color: #e74c3c; }
        .card-mes-notes:hover { border-color: #1a5276; }
        .card-bulletin:hover  { border-color: #0e6655; }

        .card-icon  { font-size: 40px; margin-bottom: 14px; display: block; }
        .card-title { font-size: 14px; font-weight: 700; color: #0a2540; margin-bottom: 6px; }
        .card-desc  { font-size: 12px; color: #aaa; line-height: 1.5; }

        /* ── ÉTUDIANT HERO ── */
        .student-hero {
            background: linear-gradient(135deg, #0a2540, #1a5276);
            border-radius: 16px; padding: 28px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 20px;
            box-shadow: 0 4px 20px rgba(10,37,64,0.3);
        }
        .student-avatar {
            width: 64px; height: 64px; border-radius: 50%;
            background: linear-gradient(135deg, #1abc9c, #0e6655);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 24px;
            border: 3px solid rgba(255,255,255,0.2); flex-shrink: 0;
        }
        .student-info h2 { color: white; font-size: 20px; font-weight: 800; margin-bottom: 4px; }
        .student-info p  { color: rgba(255,255,255,0.6); font-size: 13px; }

        /* ── FOOTER ── */
        .footer {
            text-align: center; padding: 30px;
            color: #ccc; font-size: 12px;
            position: relative; z-index: 1;
        }
    </style>
</head>
<body>
<div class="page-bg">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>LP ASUR</span></div>
    </div>
    <div class="nav-right">
        <span class="badge-role">
            <?= match($role) {
                'admin'       => '⚙️ Administrateur',
                'enseignant'  => '👨‍🏫 Enseignant',
                'secretariat' => '📋 Secrétariat',
                'etudiant'    => '👨‍🎓 Étudiant',
                default       => $role
            } ?>
        </span>
        <span class="nav-user">👤 <?= htmlspecialchars($nomUser) ?></span>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<!-- HERO -->
<div class="hero">
    <div class="hero-content">
        <h1>Bonjour, <span class="hero-accent"><?= htmlspecialchars(explode(' ', $nomUser)[0]) ?></span> 👋</h1>
        <p>Bienvenue sur la plateforme de gestion des bulletins de notes — Année universitaire 2025-2026</p>
    </div>
</div>

<!-- STATS -->
<?php if (in_array($role, ['admin', 'secretariat'])): ?>
<div class="stats-wrapper">
    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon blue">👨‍🎓</div>
            <div class="stat-info">
                <div class="val"><?= $nb_etudiants ?></div>
                <div class="lbl">Étudiants inscrits</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✏️</div>
            <div class="stat-info">
                <div class="val"><?= $nb_notes ?></div>
                <div class="lbl">Notes saisies</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">📚</div>
            <div class="stat-info">
                <div class="val"><?= $nb_matieres ?></div>
                <div class="lbl">Matières au total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">📅</div>
            <div class="stat-info">
                <div class="val" style="font-size:18px;">2025-2026</div>
                <div class="lbl">Année universitaire</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CONTENU -->
<div class="container" style="margin-top:<?= in_array($role,['admin','secretariat']) ? '32px' : '0' ?>;">

    <?php if ($role === 'etudiant'): ?>
    <div class="student-hero">
        <div class="student-avatar"><?= strtoupper(substr($nomUser,0,1)) ?></div>
        <div class="student-info">
            <h2><?= htmlspecialchars($nomUser) ?></h2>
            <p>LP ASUR — Institut National de la Poste, des TIC</p>
        </div>
    </div>
    <p class="section-title">Mon espace</p>
    <div class="cards-grid">
        <a href="mes_notes.php" class="card card-mes-notes">
            <span class="card-icon">📊</span>
            <div class="card-title">Mes notes</div>
            <div class="card-desc">Consulter mes résultats et mes moyennes</div>
        </a>
        <a href="bulletin.php" class="card card-bulletin">
            <span class="card-icon">📄</span>
            <div class="card-title">Mon bulletin</div>
            <div class="card-desc">Voir et imprimer mon bulletin officiel</div>
        </a>
    </div>

    <?php else: ?>
    <p class="section-title">Menu principal</p>
    <div class="cards-grid">

        <?php if (in_array($role, ['admin','secretariat','enseignant'])): ?>
        <a href="etudiants.php" class="card card-etudiants">
            <span class="card-icon">👨‍🎓</span>
            <div class="card-title">Étudiants</div>
            <div class="card-desc">Ajouter et gérer les étudiants</div>
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['admin','secretariat','enseignant'])): ?>
        <a href="notes.php" class="card card-notes">
            <span class="card-icon">✏️</span>
            <div class="card-title">Saisie des notes</div>
            <div class="card-desc">CC, Examen final, Rattrapage</div>
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['admin','secretariat'])): ?>
        <a href="absences.php" class="card card-absences">
            <span class="card-icon">📅</span>
            <div class="card-title">Absences</div>
            <div class="card-desc">Suivi des heures d'absence</div>
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['admin','secretariat'])): ?>
        <a href="resultats.php" class="card card-resultats">
            <span class="card-icon">📊</span>
            <div class="card-title">Résultats</div>
            <div class="card-desc">Moyennes, crédits et compensation</div>
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['admin','secretariat'])): ?>
        <a href="bulletin.php" class="card card-bulletins">
            <span class="card-icon">📄</span>
            <div class="card-title">Bulletins</div>
            <div class="card-desc">Générer les bulletins officiels</div>
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['admin','secretariat'])): ?>
        <a href="jury.php" class="card card-jury">
            <span class="card-icon">⚖️</span>
            <div class="card-title">Décisions du jury</div>
            <div class="card-desc">Récapitulatif de la promotion</div>
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['admin','secretariat'])): ?>
        <a href="import_notes.php" class="card card-import">
            <span class="card-icon">📥</span>
            <div class="card-title">Import des notes</div>
            <div class="card-desc">Importer depuis Excel / CSV</div>
        </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <a href="admin.php" class="card card-admin">
            <span class="card-icon">⚙️</span>
            <div class="card-title">Administration</div>
            <div class="card-desc">Utilisateurs, paramètres système</div>
        </a>
        <?php endif; ?>

    </div>
    <?php endif; ?>

</div>

<div class="footer">
    Institut National de la Poste, des Technologies de l'Information et de la Communication © 2026
</div>

</div>
</body>
</html>