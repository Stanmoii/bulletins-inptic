<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header('Location: login.php');
    exit();
}

$enseignant_id = $_SESSION['user_id'];
$message = "";
$erreur  = "";

// ── Enregistrer une note ──
if (isset($_POST['enregistrer'])) {
    $etudiant_id = intval($_POST['etudiant_id']);
    $matiere_id  = intval($_POST['matiere_id']);
    $type        = $_POST['type_eval'];
    $note        = floatval($_POST['note']);

    if ($note < 0 || $note > 20) {
        $erreur = "La note doit être entre 0 et 20.";
    } else {
        $check_droit = $db->prepare("
            SELECT id FROM enseignant_matiere 
            WHERE enseignant_id = ? AND matiere_id = ?
        ");
        $check_droit->execute([$enseignant_id, $matiere_id]);

        if (!$check_droit->fetch()) {
            $erreur = "Vous n'êtes pas autorisé à saisir des notes pour cette matière.";
        } else {
            $check = $db->prepare("SELECT id FROM evaluation WHERE etudiant_id=? AND matiere_id=? AND type_eval=?");
            $check->execute([$etudiant_id, $matiere_id, $type]);
            if ($check->fetch()) {
                $db->prepare("UPDATE evaluation SET note=? WHERE etudiant_id=? AND matiere_id=? AND type_eval=?")
                   ->execute([$note, $etudiant_id, $matiere_id, $type]);
                $message = "Note mise à jour !";
            } else {
                $db->prepare("INSERT INTO evaluation (etudiant_id, matiere_id, type_eval, note, date_saisie) VALUES (?,?,?,?, NOW())")
                   ->execute([$etudiant_id, $matiere_id, $type, $note]);
                $message = "Note enregistrée !";
            }
        }
    }
}

// ── Ajouter une matière à son programme ──
if (isset($_POST['action_ajouter_matiere'])) {
    $nouvelle_matiere_id = intval($_POST['nouvelle_matiere_id']);
    $filiere_id_ajout = intval($_POST['filiere_id_ajout']);
    
    if ($nouvelle_matiere_id && $filiere_id_ajout) {
        try {
            $stmt = $db->prepare("
                INSERT INTO enseignant_matiere (enseignant_id, matiere_id, filiere_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$enseignant_id, $nouvelle_matiere_id, $filiere_id_ajout]);
            $message = "✅ Matière ajoutée avec succès !";
            header("Location: enseignant.php?filiere_id=" . ($filiere_sel ?? $filiere_id_ajout));
            exit();
        } catch (PDOException $e) {
            $erreur = "Cette matière est déjà dans votre programme.";
        }
    }
}

// ── Supprimer une matière de son programme ──
if (isset($_GET['supprimer_matiere'])) {
    $matiere_a_supprimer = intval($_GET['supprimer_matiere']);
    $filiere_id_suppr = intval($_GET['filiere_id_suppr'] ?? 0);
    $stmt = $db->prepare("DELETE FROM enseignant_matiere WHERE enseignant_id = ? AND matiere_id = ? AND filiere_id = ?");
    $stmt->execute([$enseignant_id, $matiere_a_supprimer, $filiere_id_suppr]);
    $message = "🗑️ Matière supprimée de votre programme.";
    header("Location: enseignant.php?filiere_id=" . $filiere_id_suppr);
    exit();
}

// ── Créer une nouvelle matière ──
if (isset($_POST['action_creer_matiere'])) {
    $nom_matiere = trim($_POST['nouvelle_matiere_nom']);
    $coefficient = floatval($_POST['coefficient']);
    $credits = intval($_POST['credits']);
    $ue_id = intval($_POST['ue_id']);
    $filiere_id_ajout = intval($_POST['filiere_id_ajout'] ?? 0);
    
    if ($nom_matiere && $coefficient && $credits && $ue_id && $filiere_id_ajout) {
        $check = $db->prepare("SELECT id FROM matiere WHERE libelle = ?");
        $check->execute([$nom_matiere]);
        $existing = $check->fetch();
        
        if ($existing) {
            $nouvelle_matiere_id = $existing['id'];
            $info = "existante";
        } else {
            $stmt = $db->prepare("INSERT INTO matiere (libelle, coefficient, credits, ue_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nom_matiere, $coefficient, $credits, $ue_id]);
            $nouvelle_matiere_id = $db->lastInsertId();
            $info = "créée";
        }
        
        $stmt = $db->prepare("INSERT IGNORE INTO enseignant_matiere (enseignant_id, matiere_id, filiere_id) VALUES (?, ?, ?)");
        $stmt->execute([$enseignant_id, $nouvelle_matiere_id, $filiere_id_ajout]);
        
        $message = "✅ Matière '$nom_matiere' $info et ajoutée à votre programme !";
        header("Location: enseignant.php?filiere_id=" . $filiere_id_ajout);
        exit();
    } else {
        $erreur = "Veuillez remplir tous les champs.";
    }
}

// ── Récupérer filières de l'enseignant ──
$filiere_sel  = intval($_GET['filiere_id']  ?? 0);
$matiere_sel  = intval($_GET['matiere_id']  ?? 0);
$semestre_sel = intval($_GET['semestre_id'] ?? 1);

$stmt = $db->prepare("
    SELECT DISTINCT f.id, f.code, f.nom, f.niveau
    FROM enseignant_matiere em
    JOIN filiere f ON f.id = em.filiere_id
    WHERE em.enseignant_id = ?
    ORDER BY f.niveau, f.code
");
$stmt->execute([$enseignant_id]);
$filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

$matieres_ens = [];
if ($filiere_sel) {
    $stmt = $db->prepare("
        SELECT m.id, m.libelle, m.coefficient, m.credits,
               ue.libelle as ue_libelle, ue.code as ue_code, ue.semestre_id
        FROM enseignant_matiere em
        JOIN matiere m  ON m.id  = em.matiere_id
        JOIN ue         ON ue.id = m.ue_id
        WHERE em.enseignant_id = ? AND em.filiere_id = ? AND ue.semestre_id = ?
        ORDER BY ue.id, m.libelle
    ");
    $stmt->execute([$enseignant_id, $filiere_sel, $semestre_sel]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $matieres_ens[$row['id']] = $row;
    }
}

$etudiants = [];
if ($filiere_sel) {
    $stmt = $db->prepare("
        SELECT id, nom, prenom 
        FROM etudiant 
        WHERE filiere_id = ? 
        ORDER BY nom
    ");
    $stmt->execute([$filiere_sel]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$notes_existantes = [];
if ($matiere_sel && $filiere_sel) {
    $stmt = $db->prepare("
        SELECT e.etudiant_id, e.type_eval, e.note, e.id
        FROM evaluation e
        JOIN etudiant et ON et.id = e.etudiant_id
        WHERE e.matiere_id = ? AND et.filiere_id = ?
    ");
    $stmt->execute([$matiere_sel, $filiere_sel]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $notes_existantes[$n['etudiant_id']][$n['type_eval']] = $n['note'];
    }
}

$semestres = $db->query("SELECT * FROM semestre ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT nom, prenom FROM utilisateur WHERE id = ?");
$stmt->execute([$enseignant_id]);
$ens_info = $stmt->fetch(PDO::FETCH_ASSOC);

$filieres_all = $db->query("SELECT id, code, nom FROM filiere ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Enseignant — INPTIC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
        .navbar {
            background: linear-gradient(135deg, #0a2540, #1a5276);
            padding: 0 30px; height: 64px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            position: sticky; top: 0; z-index: 100;
        }
        .nav-left { display: flex; align-items: center; gap: 14px; }
        .nav-logo { width: 40px; height: 40px; border-radius: 8px; background: white;
                    padding: 4px; display: flex; align-items: center; justify-content: center; }
        .nav-logo img { width: 100%; height: 100%; object-fit: contain; }
        .nav-title { color: white; font-size: 16px; font-weight: 700; }
        .nav-title span { color: #1abc9c; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .badge-role { background: rgba(26,188,156,0.2); color: #1abc9c;
                      border: 1px solid rgba(26,188,156,0.3); padding: 5px 14px;
                      border-radius: 20px; font-size: 12px; font-weight: 600; }
        .nav-user { color: rgba(255,255,255,0.85); font-size: 14px; }
        .btn-logout { background: rgba(231,76,60,0.15); color: #e74c3c;
                      border: 1px solid rgba(231,76,60,0.3); padding: 7px 16px;
                      border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }
        .hero {
            background: linear-gradient(135deg, #0a2540, #1a5276, #0e6655);
            padding: 30px 0 50px; position: relative; overflow: hidden;
        }
        .hero-content { max-width: 1100px; margin: 0 auto; padding: 0 24px; }
        .hero h1 { color: white; font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .hero p   { color: rgba(255,255,255,0.6); font-size: 14px; }
        .hero-accent { color: #1abc9c; }
        .stats-wrapper { max-width: 1100px; margin: -28px auto 0; padding: 0 24px; z-index: 10; position: relative; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .stat-card { background: white; border-radius: 14px; padding: 18px 20px;
                     box-shadow: 0 8px 24px rgba(0,0,0,0.1);
                     display: flex; align-items: center; gap: 14px; }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px;
                     display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-icon.blue   { background: #E6F1FB; }
        .stat-icon.green  { background: #EAF3DE; }
        .stat-icon.orange { background: #FAEEDA; }
        .stat-icon.purple { background: #EEEDFE; }
        .stat-val { font-size: 22px; font-weight: 800; color: #0a2540; }
        .stat-lbl { font-size: 11px; color: #999; margin-top: 2px; }
        .container { max-width: 1100px; margin: 28px auto; padding: 0 24px; }
        .filter-card {
            background: white; border-radius: 16px; padding: 22px 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 24px;
        }
        .filter-title { font-size: 13px; font-weight: 700; color: #999;
                        text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 16px; }
        .filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; align-items: end; }
        .filter-group label { display: block; font-size: 11px; font-weight: 700;
                              color: #666; text-transform: uppercase; letter-spacing: 1px;
                              margin-bottom: 7px; }
        .filter-group select { width: 100%; padding: 10px 14px; border: 2px solid #eef0f3;
                               border-radius: 10px; font-size: 14px; outline: none; background: #fafbfc; }
        .filter-group select:focus { border-color: #1a5276; }
        .btn-filter { width: 100%; padding: 11px; background: linear-gradient(135deg, #0a2540, #1a5276);
                      color: white; border: none; border-radius: 10px; font-size: 14px;
                      font-weight: 700; cursor: pointer; }
        .filieres-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px,1fr));
                         gap: 16px; margin-bottom: 24px; }
        .filiere-card { background: white; border-radius: 12px; padding: 20px;
                        text-align: center; cursor: pointer; text-decoration: none;
                        border: 2px solid transparent; transition: all 0.2s;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .filiere-card:hover { border-color: #1a5276; transform: translateY(-3px); }
        .filiere-card.active { border-color: #1a5276; background: #E6F1FB; }
        .filiere-code { font-size: 22px; font-weight: 800; color: #0a2540; margin-bottom: 6px; }
        .filiere-nom  { font-size: 11px; color: #888; line-height: 1.4; }
        .filiere-badge { display: inline-block; margin-top: 8px; padding: 2px 10px;
                         border-radius: 20px; font-size: 10px; font-weight: 700; }
        .badge-dts { background: #FAEEDA; color: #633806; }
        .badge-l3  { background: #EAF3DE; color: #27500A; }
        .table-card { background: white; border-radius: 16px;
                      box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
        .table-header { padding: 20px 28px; border-bottom: 2px solid #f0f2f5;
                        display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { font-size: 17px; color: #0a2540; font-weight: 700; }
        .mat-badge { background: #E6F1FB; color: #1a5276; padding: 4px 12px;
                     border-radius: 20px; font-size: 12px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #0a2540; }
        thead th { padding: 12px 16px; font-size: 11px; font-weight: 700;
                   color: rgba(255,255,255,0.7); text-transform: uppercase;
                   letter-spacing: 1px; text-align: left; }
        .tc { text-align: center; }
        tbody tr { border-bottom: 1px solid #f5f5f5; }
        tbody tr:hover { background: #f7faff; }
        tbody td { padding: 10px 16px; font-size: 14px; }
        .avatar { width: 34px; height: 34px; border-radius: 50%;
                  background: linear-gradient(135deg, #1a5276, #1abc9c);
                  display: inline-flex; align-items: center; justify-content: center;
                  color: white; font-weight: 700; font-size: 12px; margin-right: 10px; }
        .td-nom { font-weight: 600; color: #0a2540; }
        .note-input { width: 70px; padding: 6px 8px; border: 2px solid #eef0f3;
                      border-radius: 8px; font-size: 13px; text-align: center; outline: none; }
        .note-input:focus { border-color: #1a5276; }
        .note-input.has-note { border-color: #1abc9c; background: #f0fff8;
                               color: #1e8449; font-weight: 700; }
        .note-input.note-fail { border-color: #e74c3c; background: #fff5f5; color: #c0392b; }
        .btn-save { padding: 6px 12px; background: #1a5276; color: white;
                    border: none; border-radius: 7px; cursor: pointer;
                    font-size: 12px; font-weight: 700; }
        .moy-cell { font-weight: 800; font-size: 14px; }
        .moy-ok  { color: #1e8449; }
        .moy-nok { color: #c0392b; }
        .moy-nd  { color: #ccc; }
        .alert { padding: 13px 18px; border-radius: 10px; margin-bottom: 20px;
                 font-size: 14px; font-weight: 500; }
        .alert-success { background: #eafaf1; color: #1e8449; border-left: 4px solid #1abc9c; }
        .alert-error   { background: #fdecea; color: #c0392b; border-left: 4px solid #e74c3c; }
        .empty-state { text-align: center; padding: 60px; color: #bbb; }
        .empty-state .icon { font-size: 52px; margin-bottom: 16px; }
        .no-classes { background: white; border-radius: 16px; padding: 50px;
                      text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .gestion-matiere { background: white; border-radius: 16px; margin-top: 30px; overflow: hidden; }
        .gestion-header { background: #0a2540; padding: 15px 25px; }
        .gestion-header h3 { color: white; margin: 0; }
        .gestion-body { padding: 25px; }
        .matiere-tag { background: #f0f2f5; padding: 8px 15px; border-radius: 25px; display: inline-flex; align-items: center; gap: 10px; margin: 5px; }
        .btn-ajouter { background: #27ae60; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-creer { background: #1a5276; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .form-group { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .form-field label { display: block; font-size: 12px; color: #666; margin-bottom: 5px; }
        .form-field input, .form-field select { padding: 10px 15px; border-radius: 8px; border: 1px solid #ddd; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>Espace Enseignant</span></div>
    </div>
    <div class="nav-right">
        <span class="badge-role">👨‍🏫 Enseignant</span>
        <span class="nav-user">👤 <?= htmlspecialchars($ens_info['nom'].' '.$ens_info['prenom']) ?></span>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="hero">
    <div class="hero-content">
        <h1>Bonjour, <span class="hero-accent"><?= htmlspecialchars($ens_info['prenom']) ?></span> 👋</h1>
        <p>Saisie des notes — Sélectionnez une filière puis une matière</p>
    </div>
</div>

<?php
$nb_filieres = count($filieres);
$nb_matieres_total = $db->prepare("SELECT COUNT(DISTINCT matiere_id) FROM enseignant_matiere WHERE enseignant_id=?");
$nb_matieres_total->execute([$enseignant_id]);
$nb_mat = $nb_matieres_total->fetchColumn();

$nb_notes_saisies = $db->prepare("
    SELECT COUNT(*) FROM evaluation e
    JOIN enseignant_matiere em ON em.matiere_id = e.matiere_id
    WHERE em.enseignant_id = ?
");
$nb_notes_saisies->execute([$enseignant_id]);
$nb_notes = $nb_notes_saisies->fetchColumn();
?>
<div class="stats-wrapper">
    <div class="stats">
        <div class="stat-card"><div class="stat-icon blue">🏫</div><div><div class="stat-val"><?= $nb_filieres ?></div><div class="stat-lbl">Mes filières</div></div></div>
        <div class="stat-card"><div class="stat-icon green">📚</div><div><div class="stat-val"><?= $nb_mat ?></div><div class="stat-lbl">Mes matières</div></div></div>
        <div class="stat-card"><div class="stat-icon orange">✏️</div><div><div class="stat-val"><?= $nb_notes ?></div><div class="stat-lbl">Notes saisies</div></div></div>
        <div class="stat-card"><div class="stat-icon purple">👨‍🎓</div><div><div class="stat-val"><?= count($etudiants) ?></div><div class="stat-lbl">Étudiants</div></div></div>
    </div>
</div>

<div class="container">
    <?php if ($message): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if (empty($filieres)): ?>
    <div class="no-classes">
        <div style="font-size:52px; margin-bottom:16px;">📚</div>
        <h3 style="color:#0a2540; margin-bottom:8px;">Aucune matière assignée</h3>
        <p style="color:#888; font-size:14px;">Utilisez le formulaire ci-dessous pour ajouter des matières à votre programme.</p>
    </div>
    <?php else: ?>

    <p style="font-size:13px; font-weight:700; color:#999; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:14px;">Mes filières</p>
    <div class="filieres-grid">
        <?php foreach ($filieres as $f): ?>
        <a href="?filiere_id=<?= $f['id'] ?>&semestre_id=<?= $semestre_sel ?>"
           class="filiere-card <?= $filiere_sel == $f['id'] ? 'active' : '' ?>">
            <div class="filiere-code"><?= htmlspecialchars($f['code']) ?></div>
            <div class="filiere-nom"><?= htmlspecialchars($f['nom']) ?></div>
            <span class="filiere-badge <?= $f['niveau']=='L3' ? 'badge-l3' : 'badge-dts' ?>"><?= $f['niveau'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($filiere_sel): ?>
    <div class="filter-card">
        <div class="filter-title">Sélection</div>
        <form method="GET">
            <input type="hidden" name="filiere_id" value="<?= $filiere_sel ?>">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>📅 Semestre</label>
                    <select name="semestre_id">
                        <?php foreach ($semestres as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $semestre_sel==$s['id']?'selected':'' ?>><?= $s['libelle'] ?> — <?= $s['annee_univ'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📚 Matière</label>
                    <select name="matiere_id">
                        <option value="">— Toutes mes matières —</option>
                        <?php foreach ($matieres_ens as $mid => $mat): ?>
                            <option value="<?= $mid ?>" <?= $matiere_sel==$mid?'selected':'' ?>>[<?= $mat['ue_code'] ?>] <?= htmlspecialchars($mat['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group" style="grid-column:span 2;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-filter">Afficher les étudiants →</button>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($etudiants)): ?>
        <div class="empty-state"><div class="icon">👨‍🎓</div><p>Aucun étudiant dans cette filière.</p></div>
    <?php elseif (!$matiere_sel): ?>
        <div class="empty-state"><div class="icon">📚</div><p>Sélectionnez une matière pour saisir les notes.</p></div>
    <?php else:
        $mat_courante = $matieres_ens[$matiere_sel] ?? null;
    ?>
    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>✏️ Saisie — <?= htmlspecialchars($mat_courante['libelle'] ?? '') ?></h2>
                <div style="margin-top:6px; display:flex; gap:8px; flex-wrap:wrap;">
                    <span class="mat-badge"><?= $mat_courante['ue_code'] ?? '' ?></span>
                    <span class="mat-badge" style="background:#EAF3DE; color:#27500A;">Coeff. ×<?= $mat_courante['coefficient'] ?? '' ?></span>
                    <span class="mat-badge" style="background:#EEEDFE; color:#3C3489;"><?= $mat_courante['credits'] ?? '' ?> crédits</span>
                    <span class="mat-badge" style="background:#FAEEDA; color:#633806;"><?= count($etudiants) ?> étudiants</span>
                </div>
            </div>
            <div style="font-size:12px; color:#999;">Cliquez 💾 pour enregistrer chaque note</div>
        </div>
        <table>
            <thead><tr><th>#</th><th>Étudiant</th><th class="tc">CC (40%)</th><th class="tc">Examen (60%)</th><th class="tc">Rattrapage</th><th class="tc">Moyenne</th></tr></thead>
            <tbody>
            <?php foreach ($etudiants as $i => $etu):
                $cc   = $notes_existantes[$etu['id']]['CC'] ?? null;
                $exam = $notes_existantes[$etu['id']]['Examen'] ?? null;
                $ratt = $notes_existantes[$etu['id']]['Rattrapage'] ?? null;
                if ($ratt !== null) $moy = $ratt;
                elseif ($cc !== null && $exam !== null) $moy = round($cc*0.4 + $exam*0.6, 2);
                elseif ($cc !== null) $moy = $cc;
                elseif ($exam !== null) $moy = $exam;
                else $moy = null;
                $moy_cls = $moy === null ? 'moy-nd' : ($moy >= 10 ? 'moy-ok' : 'moy-nok');
                $initiales = strtoupper(substr($etu['prenom'],0,1).substr($etu['nom'],0,1));
            ?>
                <tr>
                    <td style="color:#bbb;"><?= $i+1 ?></td>
                    <td><div style="display:flex; align-items:center;"><span class="avatar"><?= $initiales ?></span><span class="td-nom"><?= htmlspecialchars($etu['nom'].' '.$etu['prenom']) ?></span></div></td>
                    <?php foreach (['CC','Examen','Rattrapage'] as $type):
                        $val = $notes_existantes[$etu['id']][$type] ?? null;
                        $cls = ($val !== null) ? ($val >= 10 ? 'has-note' : 'has-note note-fail') : '';
                    ?>
                    <td class="tc">
                        <form method="POST" style="display:flex; align-items:center; justify-content:center; gap:6px;">
                            <input type="hidden" name="etudiant_id" value="<?= $etu['id'] ?>">
                            <input type="hidden" name="matiere_id"  value="<?= $matiere_sel ?>">
                            <input type="hidden" name="type_eval"   value="<?= $type ?>">
                            <input type="number" name="note" step="0.25" min="0" max="20" class="note-input <?= $cls ?>" value="<?= $val !== null ? $val : '' ?>" placeholder="–">
                            <button type="submit" name="enregistrer" class="btn-save">💾</button>
                        </form>
                    </td>
                    <?php endforeach; ?>
                    <td class="tc moy-cell <?= $moy_cls ?>"><?= $moy !== null ? number_format($moy, 2) : '–' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Section Gestion des matières -->
    <div class="gestion-matiere">
        <div class="gestion-header"><h3>📚 Gérer mes matières</h3></div>
        <div class="gestion-body">
            <h4 style="color: #0a2540; margin-bottom: 15px;">📖 Mes matières actuelles</h4>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 30px;">
                <?php
                $stmt = $db->prepare("
                    SELECT m.id, m.libelle, f.code as filiere_code, f.id as filiere_id
                    FROM enseignant_matiere em
                    JOIN matiere m ON m.id = em.matiere_id
                    JOIN filiere f ON f.id = em.filiere_id
                    WHERE em.enseignant_id = ?
                    ORDER BY f.code, m.libelle
                ");
                $stmt->execute([$enseignant_id]);
                $mes_matieres = $stmt->fetchAll();
                if (empty($mes_matieres)): ?>
                    <p style="color: #999;">Aucune matière assignée.</p>
                <?php else:
                    foreach ($mes_matieres as $mm): ?>
                        <div class="matiere-tag">
                            <span style="font-weight: 600;"><?= htmlspecialchars($mm['libelle']) ?></span>
                            <span style="color: #666; font-size: 12px;">(<?= $mm['filiere_code'] ?>)</span>
                            <a href="?supprimer_matiere=<?= $mm['id'] ?>&filiere_id_suppr=<?= $mm['filiere_id'] ?>" style="color: #e74c3c; text-decoration: none; font-size: 16px;" onclick="return confirm('Supprimer cette matière ?')">✖</a>
                        </div>
                    <?php endforeach;
                endif; ?>
            </div>
            
            <h4 style="color: #0a2540; margin-bottom: 15px;">➕ Ajouter une matière existante</h4>
            <form method="POST" class="form-group">
                <input type="hidden" name="action_ajouter_matiere" value="1">
                <div class="form-field">
                    <label>📚 Matière</label>
                    <select name="nouvelle_matiere_id" required style="min-width: 250px;">
                        <option value="">-- Choisir une matière --</option>
                        <?php
                        $stmt = $db->prepare("
                            SELECT m.id, m.libelle, ue.libelle as ue_nom 
                            FROM matiere m
                            JOIN ue ON ue.id = m.ue_id
                            WHERE m.id NOT IN (SELECT matiere_id FROM enseignant_matiere WHERE enseignant_id = ?)
                            ORDER BY ue.id, m.libelle
                        ");
                        $stmt->execute([$enseignant_id]);
                        $matieres_dispo = $stmt->fetchAll();
                        foreach ($matieres_dispo as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['libelle']) ?> (<?= $m['ue_nom'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>🏫 Filière</label>
                    <select name="filiere_id_ajout" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($filieres_all as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $filiere_sel == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-ajouter">➕ Ajouter</button>
            </form>
            
            <h4 style="color: #0a2540; margin-bottom: 15px;">✨ Créer une nouvelle matière</h4>
            <form method="POST" class="form-group">
                <input type="hidden" name="action_creer_matiere" value="1">
                <div class="form-field">
                    <label>📝 Nom</label>
                    <input type="text" name="nouvelle_matiere_nom" placeholder="Ex: Réseaux Avancés" required style="width: 200px;">
                </div>
                <div class="form-field">
                    <label>⚖️ Coeff.</label>
                    <input type="number" name="coefficient" step="0.5" value="1" required style="width: 70px;">
                </div>
                <div class="form-field">
                    <label>🎓 Crédits</label>
                    <input type="number" name="credits" value="2" required style="width: 70px;">
                </div>
                <div class="form-field">
                    <label>📚 UE</label>
                    <select name="ue_id" required style="width: 180px;">
                        <option value="1">UE5-1: Enseignement Général</option>
                        <option value="2">UE5-2: Réseaux d'Entreprise</option>
                        <option value="3">UE6-1: Sciences de Base</option>
                        <option value="4">UE6-2: Télécoms et Réseaux</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>🏫 Filière</label>
                    <select name="filiere_id_ajout" required style="width: 100px;">
                        <?php foreach ($filieres_all as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $filiere_sel == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-creer">✨ Créer</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>