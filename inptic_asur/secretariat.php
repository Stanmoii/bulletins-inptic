<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretariat') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$erreur  = "";

$stmt = $db->prepare("SELECT nom, prenom, login FROM utilisateur WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

$columns = $db->query("DESCRIBE etudiant")->fetchAll(PDO::FETCH_COLUMN);
$has_matricule = in_array('matricule', $columns);

// Vérifier si la colonne annee existe dans etudiant
$has_annee = in_array('annee', $columns);
if (!$has_annee) {
    $db->exec("ALTER TABLE etudiant ADD COLUMN annee INT DEFAULT 3 AFTER filiere_id");
    $has_annee = true;
}

// Vérifier si la colonne filiere_id existe dans ue
$ueColumns = $db->query("DESCRIBE ue")->fetchAll(PDO::FETCH_COLUMN);
$hasUeFiliere = in_array('filiere_id', $ueColumns);
if (!$hasUeFiliere) {
    $db->exec("ALTER TABLE ue ADD COLUMN filiere_id INT DEFAULT NULL AFTER semestre_id");
    $hasUeFiliere = true;
}

// Table absence
$tableAbsenceExists = $db->query("SHOW TABLES LIKE 'absence'")->rowCount() > 0;
if (!$tableAbsenceExists) {
    $db->exec("
        CREATE TABLE absence (
            id INT PRIMARY KEY AUTO_INCREMENT,
            etudiant_id INT NOT NULL,
            matiere_id INT NOT NULL,
            heures DECIMAL(5,2) NOT NULL,
            date_absence DATE NOT NULL,
            motif VARCHAR(255) DEFAULT NULL,
            date_saisie TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (etudiant_id) REFERENCES etudiant(id) ON DELETE CASCADE,
            FOREIGN KEY (matiere_id) REFERENCES matiere(id) ON DELETE CASCADE
        )
    ");
    $tableAbsenceExists = true;
}

// ─── TRAITEMENT DES FORMULAIRES ─────────────────────────────────────

if (isset($_POST['ajouter_etudiant'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $filiere_id = intval($_POST['filiere_id']);
    $annee = intval($_POST['annee']);
    $login = strtolower($prenom . '.' . $nom);
    $password = md5('etudiant123');
    
    $base_login = $login;
    $counter = 1;
    while (true) {
        $check = $db->prepare("SELECT id FROM utilisateur WHERE login = ?");
        $check->execute([$login]);
        if (!$check->fetch()) break;
        $login = $base_login . $counter;
        $counter++;
    }
    
    if ($nom && $prenom && $filiere_id) {
        try {
            $stmt = $db->prepare("INSERT INTO utilisateur (nom, prenom, login, password_hash, role, actif) VALUES (?, ?, ?, ?, 'etudiant', 1)");
            $stmt->execute([$nom, $prenom, $login, $password]);
            $utilisateur_id = $db->lastInsertId();
            
            if ($has_matricule) {
                $matricule = strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 3) . rand(100, 999));
                $stmt = $db->prepare("INSERT INTO etudiant (id, nom, prenom, matricule, filiere_id, annee) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$utilisateur_id, $nom, $prenom, $matricule, $filiere_id, $annee]);
            } else {
                $stmt = $db->prepare("INSERT INTO etudiant (id, nom, prenom, filiere_id, annee) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$utilisateur_id, $nom, $prenom, $filiere_id, $annee]);
            }
            $message = "✅ Étudiant ajouté ! Login: $login | Mot de passe: etudiant123";
        } catch (PDOException $e) {
            $erreur = "Erreur : " . $e->getMessage();
        }
    }
}

if (isset($_POST['ajouter_absence']) && $tableAbsenceExists) {
    $etudiant_id = intval($_POST['etudiant_id']);
    $matiere_id = intval($_POST['matiere_id']);
    $heures = floatval($_POST['heures']);
    $date_absence = $_POST['date_absence'];
    $motif = $_POST['motif'] ?? null;
    
    try {
        $stmt = $db->prepare("INSERT INTO absence (etudiant_id, matiere_id, heures, date_absence, motif) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$etudiant_id, $matiere_id, $heures, $date_absence, $motif]);
        $message = "✅ Absence enregistrée !";
    } catch (PDOException $e) {
        $erreur = "Erreur : " . $e->getMessage();
    }
}

if (isset($_GET['supprimer_absence'])) {
    $id = intval($_GET['supprimer_absence']);
    try {
        $db->prepare("DELETE FROM absence WHERE id = ?")->execute([$id]);
        $message = "✅ Absence supprimée !";
        header("Location: secretariat.php?tab=absences");
        exit();
    } catch (PDOException $e) {
        $erreur = "Erreur lors de la suppression.";
    }
}

if (isset($_GET['supprimer_etudiant'])) {
    $id = intval($_GET['supprimer_etudiant']);
    try {
        $db->prepare("DELETE FROM evaluation WHERE etudiant_id = ?")->execute([$id]);
        if ($tableAbsenceExists) {
            $db->prepare("DELETE FROM absence WHERE etudiant_id = ?")->execute([$id]);
        }
        $db->prepare("DELETE FROM etudiant WHERE id = ?")->execute([$id]);
        $db->prepare("DELETE FROM utilisateur WHERE id = ?")->execute([$id]);
        $message = "✅ Étudiant supprimé !";
        header("Location: secretariat.php");
        exit();
    } catch (PDOException $e) {
        $erreur = "Erreur lors de la suppression.";
    }
}

// ─── RÉCUPÉRATION DES FILTRES ─────────────────────────────────────
$annee_sel = isset($_GET['annee']) ? intval($_GET['annee']) : 3;
$filiere_sel = isset($_GET['filiere_id']) ? intval($_GET['filiere_id']) : 0;
$semestre_sel = isset($_GET['semestre']) ? intval($_GET['semestre']) : ($annee_sel == 1 ? 1 : ($annee_sel == 2 ? 3 : 5));

$annees = [
    1 => ['nom' => '1ère année', 'semestres' => [1, 2]],
    2 => ['nom' => '2ème année', 'semestres' => [3, 4]],
    3 => ['nom' => '3ème année', 'semestres' => [5, 6]]
];

// Filières
$filieres = $db->query("SELECT id, code, nom, annee FROM filiere ORDER BY annee, code")->fetchAll();

// Filtrer les filières par année
$filieres_par_annee = [];
foreach ($filieres as $f) {
    $filieres_par_annee[$f['annee']][] = $f;
}

// UE par filière et semestre
$ues_par_filiere_semestre = [];
$stmt = $db->prepare("SELECT * FROM ue WHERE filiere_id = ? AND semestre_id = ? ORDER BY code");
foreach ($filieres as $f) {
    for ($s = 1; $s <= 6; $s++) {
        $stmt->execute([$f['id'], $s]);
        $ues_par_filiere_semestre[$f['id']][$s] = $stmt->fetchAll();
    }
}

// Matières par filière et semestre
$matieres_par_filiere_semestre = [];
$stmt = $db->prepare("
    SELECT m.*, ue.code as ue_code, ue.libelle as ue_libelle
    FROM matiere m
    JOIN ue ON ue.id = m.ue_id
    WHERE ue.semestre_id = ? AND ue.filiere_id = ?
    ORDER BY ue.code, m.libelle
");
foreach ($filieres as $f) {
    for ($s = 1; $s <= 6; $s++) {
        $stmt->execute([$s, $f['id']]);
        $matieres_par_filiere_semestre[$f['id']][$s] = $stmt->fetchAll();
    }
}

// Étudiants
$etudiants = [];
if ($filiere_sel > 0) {
    $stmt = $db->prepare("
        SELECT e.id, e.nom, e.prenom, e.matricule, u.login
        FROM etudiant e
        LEFT JOIN utilisateur u ON u.id = e.id
        WHERE e.filiere_id = ? AND e.annee = ?
        ORDER BY e.nom ASC
    ");
    $stmt->execute([$filiere_sel, $annee_sel]);
    $etudiants = $stmt->fetchAll();
}

// Absences
$absences = [];
if ($tableAbsenceExists) {
    $absences = $db->query("
        SELECT a.*, e.nom, e.prenom, e.filiere_id, e.annee, f.code as filiere_code, m.libelle as matiere
        FROM absence a
        JOIN etudiant e ON e.id = a.etudiant_id
        JOIN matiere m ON m.id = a.matiere_id
        LEFT JOIN filiere f ON f.id = e.filiere_id
        ORDER BY a.date_absence DESC
        LIMIT 100
    ")->fetchAll();
}

$enseignants = $db->query("SELECT id, nom, prenom, login FROM utilisateur WHERE role = 'enseignant' ORDER BY nom")->fetchAll();

$active_tab = $_GET['tab'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Secrétariat — INPTIC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }

        .navbar {
            background: linear-gradient(135deg, #0a2540, #1a5276);
            padding: 0 30px; height: 64px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
        }
        .nav-left { display: flex; align-items: center; gap: 14px; }
        .nav-logo { width: 40px; height: 40px; border-radius: 8px; background: white; padding: 4px; display: flex; align-items: center; justify-content: center; }
        .nav-logo img { width: 100%; height: 100%; object-fit: contain; }
        .nav-title { color: white; font-size: 16px; font-weight: 700; }
        .nav-title span { color: #1abc9c; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .badge-role { background: rgba(26,188,156,0.2); color: #1abc9c; border: 1px solid rgba(26,188,156,0.3); padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .nav-user { color: rgba(255,255,255,0.85); font-size: 14px; }
        .btn-logout { background: rgba(231,76,60,0.15); color: #e74c3c; border: 1px solid rgba(231,76,60,0.3); padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }

        .hero {
            background: linear-gradient(135deg, #0a2540, #1a5276, #0e6655);
            padding: 20px 0 40px;
        }
        .hero-content { max-width: 1400px; margin: 0 auto; padding: 0 24px; }
        .hero h1 { color: white; font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .hero p { color: rgba(255,255,255,0.6); font-size: 14px; }
        .hero-accent { color: #1abc9c; }

        .stats-wrapper { max-width: 1400px; margin: -20px auto 0; padding: 0 24px; }
        .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
        .stat-card { background: white; border-radius: 12px; padding: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 10px; }
        .stat-icon { width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .stat-icon.blue { background: #E6F1FB; }
        .stat-icon.green { background: #EAF3DE; }
        .stat-icon.orange { background: #FAEEDA; }
        .stat-icon.purple { background: #EEEDFE; }
        .stat-icon.teal { background: #E0F7FA; }
        .stat-val { font-size: 16px; font-weight: 800; color: #0a2540; }
        .stat-lbl { font-size: 9px; color: #999; }

        .container { max-width: 1400px; margin: 28px auto; padding: 0 24px; }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
            margin-bottom: 30px;
        }
        .card-header {
            background: #0a2540;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-header h2 { color: white; font-size: 18px; }
        .card-body { padding: 25px; }

        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px 15px; text-align: left; font-size: 11px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 10px 15px; border-bottom: 1px solid #eee; font-size: 13px; }
        tr:hover { background: #f7faff; }

        .btn-delete { color: #e74c3c; text-decoration: none; font-size: 16px; }
        .btn-delete:hover { color: #c0392b; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 2px solid #eef0f3; border-radius: 8px; font-size: 13px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #1a5276; }
        .btn-submit { background: #1a5276; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-submit:hover { background: #0a2540; }

        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; }
        .alert-success { background: #eafaf1; color: #1e8449; border-left: 4px solid #1abc9c; }
        .alert-error { background: #fdecea; color: #c0392b; border-left: 4px solid #e74c3c; }
        .alert-info { background: #E6F1FB; color: #1a5276; border-left: 4px solid #1a5276; }

        .tabs { display: flex; gap: 8px; margin-bottom: 25px; flex-wrap: wrap; }
        .tab-btn { background: #e0e0e0; border: none; padding: 10px 22px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 13px; }
        .tab-btn:hover { background: #c0c0c0; }
        .tab-btn.active { background: #1a5276; color: white; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        
        .annee-card {
            background: linear-gradient(135deg, #0a2540, #1a5276);
            color: white;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            width: 200px;
        }
        .annee-card:hover { transform: translateY(-3px); opacity: 0.9; }
        .annee-card.active { background: #1abc9c; }
        .annees-grid { display: flex; gap: 16px; margin-bottom: 25px; flex-wrap: wrap; }
        
        .filiere-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            border: 2px solid transparent;
            display: inline-block;
            width: 180px;
        }
        .filiere-card:hover { border-color: #1a5276; transform: translateY(-3px); }
        .filiere-card.active { border-color: #1a5276; background: #E6F1FB; }
        .filieres-grid { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        
        .login-info { font-family: monospace; color: #1a5276; font-weight: 600; }
        .search-input { padding: 6px 12px; border: 2px solid #eef0f3; border-radius: 8px; width: 250px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; }
        .badge-info { background: #E6F1FB; color: #1a5276; }
        .badge-primary { background: #0a2540; color: white; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>Secrétariat Pédagogique</span></div>
    </div>
    <div class="nav-right">
        <span class="badge-role">📋 Secrétariat</span>
        <span class="nav-user">👤 <?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?></span>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="hero">
    <div class="hero-content">
        <h1>Bonjour, <span class="hero-accent"><?= htmlspecialchars($user_info['prenom']) ?></span> 👋</h1>
        <p>Gestion complète par Année, Semestre et Filière</p>
    </div>
</div>

<div class="stats-wrapper">
    <div class="stats">
        <div class="stat-card"><div class="stat-icon blue">👨‍🎓</div><div><div class="stat-val"><?= count($etudiants) ?></div><div class="stat-lbl">Étudiants</div></div></div>
        <div class="stat-card"><div class="stat-icon green">👨‍🏫</div><div><div class="stat-val"><?= count($enseignants) ?></div><div class="stat-lbl">Enseignants</div></div></div>
        <div class="stat-card"><div class="stat-icon orange">📚</div><div><div class="stat-val"><?= count($matieres_par_filiere_semestre[$filiere_sel][$semestre_sel] ?? []) ?></div><div class="stat-lbl">Matières</div></div></div>
        <div class="stat-card"><div class="stat-icon purple">📖</div><div><div class="stat-val"><?= count($ues_par_filiere_semestre[$filiere_sel][$semestre_sel] ?? []) ?></div><div class="stat-lbl">UE</div></div></div>
        <div class="stat-card"><div class="stat-icon teal">📅</div><div><div class="stat-val"><?= count($absences) ?></div><div class="stat-lbl">Absences</div></div></div>
    </div>
</div>

<div class="container">
    <?php if ($message): ?>
        <div class="alert alert-success">✅ <?= $message ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab-btn <?= $active_tab == 'dashboard' ? 'active' : '' ?>" onclick="showTab('dashboard')">📊 Dashboard</button>
        <button class="tab-btn <?= $active_tab == 'etudiants' ? 'active' : '' ?>" onclick="showTab('etudiants')">👨‍🎓 Étudiants</button>
        <button class="tab-btn <?= $active_tab == 'enseignants' ? 'active' : '' ?>" onclick="showTab('enseignants')">👨‍🏫 Enseignants</button>
        <button class="tab-btn <?= $active_tab == 'matieres' ? 'active' : '' ?>" onclick="showTab('matieres')">📚 Matières</button>
        <button class="tab-btn <?= $active_tab == 'ue' ? 'active' : '' ?>" onclick="showTab('ue')">📖 UE</button>
        <button class="tab-btn <?= $active_tab == 'notes' ? 'active' : '' ?>" onclick="showTab('notes')">📊 Notes</button>
        <button class="tab-btn <?= $active_tab == 'absences' ? 'active' : '' ?>" onclick="showTab('absences')">📅 Absences</button>
        <button class="tab-btn <?= $active_tab == 'ajout' ? 'active' : '' ?>" onclick="showTab('ajout')">➕ Ajouter</button>
    </div>

    <!-- Dashboard -->
    <div id="dashboard" class="tab-pane <?= $active_tab == 'dashboard' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header"><h2>📊 Tableau de bord</h2></div>
            <div class="card-body">
                <p>Bienvenue. Sélectionnez une année, puis une filière :</p>
                
                <div class="annees-grid" style="margin: 20px 0;">
                    <?php foreach ($annees as $num => $info): ?>
                        <a href="?tab=dashboard&annee=<?= $num ?>" class="annee-card <?= ($annee_sel == $num) ? 'active' : '' ?>">
                            <div style="font-size: 20px; font-weight: 800;"><?= $info['nom'] ?></div>
                            <div style="font-size: 12px;">Semestres <?= $info['semestres'][0] ?> et <?= $info['semestres'][1] ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <div class="filieres-grid">
                    <?php foreach ($filieres_par_annee[$annee_sel] ?? [] as $f): ?>
                        <a href="?tab=etudiants&annee=<?= $annee_sel ?>&filiere_id=<?= $f['id'] ?>" class="filiere-card <?= ($filiere_sel == $f['id']) ? 'active' : '' ?>">
                            <div style="font-size: 20px; font-weight: 800; color: #0a2540;"><?= htmlspecialchars($f['code']) ?></div>
                            <div style="font-size: 11px; color: #666;"><?= htmlspecialchars($f['nom']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglet Étudiants -->
    <div id="etudiants" class="tab-pane <?= $active_tab == 'etudiants' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header">
                <h2>📋 Liste des étudiants</h2>
                <div>
                    <select id="annee_select" onchange="location.href='?tab=etudiants&annee='+this.value" style="padding: 6px 12px; border-radius: 8px;">
                        <?php foreach ($annees as $num => $info): ?>
                            <option value="<?= $num ?>" <?= ($annee_sel == $num) ? 'selected' : '' ?>><?= $info['nom'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="searchEtudiant" class="search-input" placeholder="🔍 Rechercher..." onkeyup="filterTable('tableEtudiants', this.value)" style="margin-left: 10px;">
                </div>
            </div>
            <div class="card-body">
                <div style="overflow-x: auto;">
                    <table id="tableEtudiants">
                        <thead><tr><th>#</th><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Filière</th><th>Login</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php 
                            $allEtudiants = $db->query("
                                SELECT e.id, e.nom, e.prenom, e.matricule, f.code as filiere_code, u.login, e.annee
                                FROM etudiant e
                                LEFT JOIN filiere f ON f.id = e.filiere_id
                                LEFT JOIN utilisateur u ON u.id = e.id
                                WHERE u.role = 'etudiant'
                                ORDER BY e.annee, e.nom ASC
                            ")->fetchAll();
                            foreach ($allEtudiants as $index => $e): 
                            ?>
                            <tr>
                                <td><?= $index+1 ?></td>
                                <td><?= htmlspecialchars($e['matricule'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($e['nom']) ?></td>
                                <td><?= htmlspecialchars($e['prenom']) ?></td>
                                <td><?= htmlspecialchars($e['filiere_code'] ?? '-') ?> (A<?= $e['annee'] ?>)</a>
                                <td class="login-info"><?= htmlspecialchars($e['login'] ?? '-') ?></td>
                                <td><a href="?supprimer_etudiant=<?= $e['id'] ?>&tab=etudiants" class="btn-delete" onclick="return confirm('Supprimer ?')">🗑️</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglet Enseignants -->
    <div id="enseignants" class="tab-pane <?= $active_tab == 'enseignants' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header"><h2>📋 Liste des enseignants</h2></div>
            <div class="card-body">
                <div style="overflow-x: auto;">
                    <table id="tableEnseignants">
                        <thead><tr><th>#</th><th>Nom</th><th>Prénom</th><th>Login</th></tr></thead>
                        <tbody>
                            <?php foreach ($enseignants as $index => $e): ?>
                            <tr>
                                <td><?= $index+1 ?></td>
                                <td><?= htmlspecialchars($e['nom']) ?></td>
                                <td><?= htmlspecialchars($e['prenom']) ?></td>
                                <td class="login-info"><?= htmlspecialchars($e['login']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglet Matières -->
    <div id="matieres" class="tab-pane <?= $active_tab == 'matieres' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header">
                <h2>📚 Liste des matières</h2>
                <div>
                    <select id="filiere_matiere" onchange="location.href='?tab=matieres&filiere_id='+this.value" style="padding: 6px 12px; border-radius: 8px;">
                        <option value="0">-- Choisir une filière --</option>
                        <?php foreach ($filieres as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= ($filiere_sel == $f['id']) ? 'selected' : '' ?>><?= htmlspecialchars($f['code']) ?> (A<?= $f['annee'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <select id="semestre_matiere" onchange="location.href='?tab=matieres&filiere_id=<?= $filiere_sel ?>&semestre='+this.value" style="padding: 6px 12px; border-radius: 8px; margin-left: 10px;">
                        <?php
                        // Afficher les semestres en fonction de l'année de la filière sélectionnée
                        $annee_filiere = 3;
                        foreach ($filieres as $f) {
                            if ($f['id'] == $filiere_sel) {
                                $annee_filiere = $f['annee'];
                                break;
                            }
                        }
                        $semestres_affiches = ($annee_filiere == 1) ? [1,2] : (($annee_filiere == 2) ? [3,4] : [5,6]);
                        ?>
                        <?php if (in_array(1, $semestres_affiches)): ?>
                            <option value="1" <?= $semestre_sel == 1 ? 'selected' : '' ?>>Semestre 1 (S1)</option>
                            <option value="2" <?= $semestre_sel == 2 ? 'selected' : '' ?>>Semestre 2 (S2)</option>
                        <?php endif; ?>
                        <?php if (in_array(3, $semestres_affiches)): ?>
                            <option value="3" <?= $semestre_sel == 3 ? 'selected' : '' ?>>Semestre 3 (S3)</option>
                            <option value="4" <?= $semestre_sel == 4 ? 'selected' : '' ?>>Semestre 4 (S4)</option>
                        <?php endif; ?>
                        <?php if (in_array(5, $semestres_affiches)): ?>
                            <option value="5" <?= $semestre_sel == 5 ? 'selected' : '' ?>>Semestre 5 (S5)</option>
                            <option value="6" <?= $semestre_sel == 6 ? 'selected' : '' ?>>Semestre 6 (S6)</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div style="overflow-x: auto;">
                    <table id="tableMatieres">
                        <thead><tr><th>ID</th><th>Libellé</th><th>UE</th><th>Coeff.</th><th>Crédits</th></tr></thead>
                        <tbody>
                            <?php foreach ($matieres_par_filiere_semestre[$filiere_sel][$semestre_sel] ?? [] as $m): ?>
                            <tr>
                                <td><?= $m['id'] ?></td>
                                <td><?= htmlspecialchars($m['libelle']) ?></td>
                                <td><?= $m['ue_code'] ?> - <?= htmlspecialchars($m['ue_libelle']) ?></td>
                                <td><?= $m['coefficient'] ?></td>
                                <td><?= $m['credits'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($filiere_sel == 0): ?>
                    <p class="alert alert-info" style="margin-top: 15px;">Veuillez sélectionner une filière pour voir ses matières.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Onglet UE -->
    <div id="ue" class="tab-pane <?= $active_tab == 'ue' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header">
                <h2>📖 Unités d'Enseignement</h2>
                <div>
                    <select id="filiere_ue" onchange="location.href='?tab=ue&filiere_id='+this.value" style="padding: 6px 12px; border-radius: 8px;">
                        <option value="0">-- Choisir une filière --</option>
                        <?php foreach ($filieres as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= ($filiere_sel == $f['id']) ? 'selected' : '' ?>><?= htmlspecialchars($f['code']) ?> (A<?= $f['annee'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <select id="semestre_ue" onchange="location.href='?tab=ue&filiere_id=<?= $filiere_sel ?>&semestre='+this.value" style="padding: 6px 12px; border-radius: 8px; margin-left: 10px;">
                        <option value="1" <?= $semestre_sel == 1 ? 'selected' : '' ?>>Semestre 1</option>
                        <option value="2" <?= $semestre_sel == 2 ? 'selected' : '' ?>>Semestre 2</option>
                        <option value="3" <?= $semestre_sel == 3 ? 'selected' : '' ?>>Semestre 3</option>
                        <option value="4" <?= $semestre_sel == 4 ? 'selected' : '' ?>>Semestre 4</option>
                        <option value="5" <?= $semestre_sel == 5 ? 'selected' : '' ?>>Semestre 5</option>
                        <option value="6" <?= $semestre_sel == 6 ? 'selected' : '' ?>>Semestre 6</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div style="overflow-x: auto;">
                    <table><thead><tr><th>Code</th><th>Libellé</th><th>Semestre</th></tr></thead>
                    <tbody>
                        <?php foreach ($ues_par_filiere_semestre[$filiere_sel][$semestre_sel] ?? [] as $u): ?>
                        <tr>
                            <td><?= $u['code'] ?></td>
                            <td><?= htmlspecialchars($u['libelle']) ?></td>
                            <td>S<?= $u['semestre_id'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php if ($filiere_sel == 0): ?>
                    <p class="alert alert-info" style="margin-top: 15px;">Veuillez sélectionner une filière pour voir ses UE.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Onglet Notes -->
    <div id="notes" class="tab-pane <?= $active_tab == 'notes' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header"><h2>📊 Consultation des notes</h2></div>
            <div class="card-body">
                <form method="GET" class="form-grid" style="margin-bottom: 20px;">
                    <input type="hidden" name="tab" value="notes">
                    <div class="form-group"><label>🏫 Filière</label>
                        <select name="filiere_id" required onchange="this.form.submit()">
                            <option value="0">-- Choisir une filière --</option>
                            <?php foreach ($filieres as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= ($filiere_sel == $f['id']) ? 'selected' : '' ?>><?= htmlspecialchars($f['code']) ?> (A<?= $f['annee'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>📚 Semestre</label>
                        <select name="semestre" required onchange="this.form.submit()">
                            <option value="1" <?= $semestre_sel == 1 ? 'selected' : '' ?>>Semestre 1</option>
                            <option value="2" <?= $semestre_sel == 2 ? 'selected' : '' ?>>Semestre 2</option>
                            <option value="3" <?= $semestre_sel == 3 ? 'selected' : '' ?>>Semestre 3</option>
                            <option value="4" <?= $semestre_sel == 4 ? 'selected' : '' ?>>Semestre 4</option>
                            <option value="5" <?= $semestre_sel == 5 ? 'selected' : '' ?>>Semestre 5</option>
                            <option value="6" <?= $semestre_sel == 6 ? 'selected' : '' ?>>Semestre 6</option>
                        </select>
                    </div>
                    <div class="form-group"><label>📚 Matière</label>
                        <select name="matiere_id" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($matieres_par_filiere_semestre[$filiere_sel][$semestre_sel] ?? [] as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= (isset($_GET['matiere_id']) && $_GET['matiere_id'] == $m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><button type="submit" class="btn-submit">Afficher</button></div>
                </form>
                <?php if (isset($_GET['matiere_id']) && $_GET['matiere_id'] && $filiere_sel > 0): 
                    $matiere_id = intval($_GET['matiere_id']);
                    $notes = $db->prepare("
                        SELECT e.nom, e.prenom, e.annee, 
                               MAX(CASE WHEN ev.type_eval='CC' THEN ev.note END) as CC,
                               MAX(CASE WHEN ev.type_eval='Examen' THEN ev.note END) as Examen,
                               MAX(CASE WHEN ev.type_eval='Rattrapage' THEN ev.note END) as Rattrapage
                        FROM etudiant e
                        LEFT JOIN evaluation ev ON ev.etudiant_id = e.id AND ev.matiere_id = ?
                        WHERE e.filiere_id = ?
                        GROUP BY e.id
                        ORDER BY e.nom
                    ");
                    $notes->execute([$matiere_id, $filiere_sel]);
                    $notes_data = $notes->fetchAll();
                    $matiere_nom = $db->query("SELECT libelle FROM matiere WHERE id=$matiere_id")->fetchColumn();
                ?>
                    <h3>Notes - <?= htmlspecialchars($matiere_nom) ?> (<?= htmlspecialchars($f['code'] ?? 'Filière') ?>)</h3>
                    <div style="overflow-x: auto;">
                        <table id="tableNotes">
                            <thead><tr><th>Étudiant</th><th>CC</th><th>Examen</th><th>Rattrapage</th><th>Moyenne</th></tr></thead>
                            <tbody><?php foreach ($notes_data as $n): $moy = $n['Rattrapage'] ?? ($n['CC'] && $n['Examen'] ? round($n['CC']*0.4+$n['Examen']*0.6,2) : ($n['CC'] ?? $n['Examen'] ?? null)); ?>
                            <tr>
                                <td><?= $n['nom'].' '.$n['prenom'] ?> (A<?= $n['annee'] ?>)</a>
                                <td><?= $n['CC'] ?? '-' ?></td>
                                <td><?= $n['Examen'] ?? '-' ?></td>
                                <td><?= $n['Rattrapage'] ?? '-' ?></td>
                                <td><strong><?= $moy ? number_format($moy,2) : '-' ?></strong></td>
                            </tr>
                            <?php endforeach; ?></tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Onglet Absences -->
    <div id="absences" class="tab-pane <?= $active_tab == 'absences' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header"><h2>📅 Gestion des absences</h2></div>
            <div class="card-body">
                <form method="POST" class="form-grid" style="margin-bottom: 25px;">
                    <div class="form-group">
                        <label>🏫 Filière</label>
                        <select name="filiere_filter" id="filiere_absence" required onchange="chargerEtudiants(this.value)">
                            <option value="">-- Choisir une filière --</option>
                            <?php foreach ($filieres as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['code']) ?> (A<?= $f['annee'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>👨‍🎓 Étudiant</label>
                        <select name="etudiant_id" id="etudiant_absence" required>
                            <option value="">-- Choisir d'abord une filière --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>📚 Matière</label>
                        <select name="matiere_id" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($matieres_par_filiere_semestre[$filiere_sel][$semestre_sel] ?? [] as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>⏱️ Heures</label><input type="number" name="heures" step="0.5" required></div>
                    <div class="form-group"><label>📅 Date</label><input type="date" name="date_absence" required></div>
                    <div class="form-group"><label>📝 Motif</label><input type="text" name="motif" placeholder="Optionnel"></div>
                    <div class="form-group"><button type="submit" name="ajouter_absence" class="btn-submit">➕ Ajouter l'absence</button></div>
                </form>

                <div style="overflow-x: auto;">
                    <table id="tableAbsences">
                        <thead><tr><th>Date</th><th>Filière</th><th>Étudiant</th><th>Matière</th><th>Heures</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($absences as $a): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($a['date_absence'])) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($a['filiere_code'] ?? '-') ?> (A<?= $a['annee'] ?>)</span></td>
                                <td><?= htmlspecialchars($a['nom'] . ' ' . $a['prenom']) ?></td>
                                <td><?= htmlspecialchars($a['matiere']) ?></td>
                                <td><?= $a['heures'] ?>h</a></td>
                                <td><a href="?supprimer_absence=<?= $a['id'] ?>&tab=absences" class="btn-delete" onclick="return confirm('Supprimer ?')">🗑️</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglet Ajouter -->
    <div id="ajout" class="tab-pane <?= $active_tab == 'ajout' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header"><h2>➕ Ajouter un étudiant</h2></div>
            <div class="card-body">
                <form method="POST" class="form-grid">
                    <div class="form-group"><label>Nom</label><input type="text" name="nom" required></div>
                    <div class="form-group"><label>Prénom</label><input type="text" name="prenom" required></div>
                    <div class="form-group"><label>Année</label>
                        <select name="annee" required>
                            <option value="1">1ère année</option>
                            <option value="2">2ème année</option>
                            <option value="3">3ème année</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Filière</label>
                        <select name="filiere_id" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($filieres_par_annee[$annee_sel] as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['code'] . ' - ' . $f['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><button type="submit" name="ajouter_etudiant" class="btn-submit">➕ Ajouter</button></div>
                </form>
                <p class="badge badge-info" style="margin-top:15px;">📌 Login: prenom.nom | Mot de passe: etudiant123</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>➕ Ajouter un enseignant</h2></div>
            <div class="card-body">
                <form method="POST" class="form-grid">
                    <div class="form-group"><label>Nom</label><input type="text" name="nom" required></div>
                    <div class="form-group"><label>Prénom</label><input type="text" name="prenom" required></div>
                    <div class="form-group"><label>Login</label><input type="text" name="login" placeholder="ex: prenom.nom" required></div>
                    <div class="form-group"><button type="submit" name="ajouter_enseignant" class="btn-submit">➕ Ajouter</button></div>
                </form>
                <p class="badge badge-info" style="margin-top:15px;">📌 Mot de passe: password123</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Données des étudiants par filière
    const etudiantsParFiliere = <?php 
        $data = [];
        $stmt = $db->query("SELECT id, nom, prenom, filiere_id FROM etudiant ORDER BY nom");
        foreach ($stmt->fetchAll() as $e) {
            $data[$e['filiere_id']][] = $e;
        }
        echo json_encode($data);
    ?>;
    
    function showTab(tabId) {
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        event.target.classList.add('active');
        window.history.pushState({}, '', '?tab=' + tabId);
    }
    
    function filterTable(tableId, searchText) {
        let table = document.getElementById(tableId);
        if (!table) return;
        let rows = table.getElementsByTagName('tr');
        for (let i = 1; i < rows.length; i++) {
            let text = rows[i].innerText.toLowerCase();
            rows[i].style.display = text.includes(searchText.toLowerCase()) ? '' : 'none';
        }
    }
    
    function chargerEtudiants(filiereId) {
        let selectEtudiant = document.getElementById('etudiant_absence');
        let etudiants = etudiantsParFiliere[filiereId] || [];
        selectEtudiant.innerHTML = '<option value="">-- Choisir un étudiant --</option>';
        etudiants.forEach(etu => {
            let option = document.createElement('option');
            option.value = etu.id;
            option.textContent = etu.nom + ' ' + etu.prenom;
            selectEtudiant.appendChild(option);
        });
    }
</script>
</body>
</html>