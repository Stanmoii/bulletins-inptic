<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = "";
$erreur  = "";

// ── Enregistrer une absence ──
if (isset($_POST['enregistrer'])) {
    $etudiant_id = intval($_POST['etudiant_id']);
    $matiere_id  = intval($_POST['matiere_id']);
    $heures      = intval($_POST['heures']);

    if ($heures < 0) {
        $erreur = "Le nombre d'heures ne peut pas être négatif.";
    } else {
        $check = $db->prepare("SELECT id FROM absence WHERE etudiant_id=? AND matiere_id=?");
        $check->execute([$etudiant_id, $matiere_id]);
        if ($check->fetch()) {
            $db->prepare("UPDATE absence SET heures=? WHERE etudiant_id=? AND matiere_id=?")
               ->execute([$heures, $etudiant_id, $matiere_id]);
            $message = "Absence mise à jour !";
        } else {
            $db->prepare("INSERT INTO absence (etudiant_id, matiere_id, heures) VALUES (?,?,?)")
               ->execute([$etudiant_id, $matiere_id, $heures]);
            $message = "Absence enregistrée !";
        }
    }
}

// ── Données ──
$etudiants  = $db->query("SELECT id, nom, prenom FROM etudiant ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$semestres  = $db->query("SELECT * FROM semestre ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$etudiant_sel = intval($_GET['etudiant_id'] ?? $_POST['etudiant_id'] ?? 0);
$semestre_sel = intval($_GET['semestre_id'] ?? 1);

// Matières du semestre
$matieres = [];
if ($semestre_sel) {
    $stmt = $db->prepare("
        SELECT m.id, m.libelle, ue.code, ue.libelle as ue_libelle
        FROM matiere m
        JOIN ue ON ue.id = m.ue_id
        WHERE ue.semestre_id = ?
        ORDER BY ue.id, m.libelle
    ");
    $stmt->execute([$semestre_sel]);
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Absences existantes
$absences = [];
if ($etudiant_sel) {
    $stmt = $db->prepare("SELECT matiere_id, heures FROM absence WHERE etudiant_id=?");
    $stmt->execute([$etudiant_sel]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $absences[$a['matiere_id']] = $a['heures'];
    }
}
$total_heures = array_sum($absences);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Absences — INPTIC</title>
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
        .nav-logo { width: 40px; height: 40px; border-radius: 8px; background: white;
                    padding: 4px; display: flex; align-items: center; justify-content: center; }
        .nav-logo img { width: 100%; height: 100%; object-fit: contain; }
        .nav-title { color: white; font-size: 16px; font-weight: 700; }
        .nav-title span { color: #1abc9c; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .btn-back { background: rgba(255,255,255,0.1); color: white;
                    border: 1px solid rgba(255,255,255,0.2); padding: 7px 16px;
                    border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }
        .btn-logout { background: rgba(231,76,60,0.15); color: #e74c3c;
                      border: 1px solid rgba(231,76,60,0.3); padding: 7px 16px;
                      border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }

        .container { max-width: 900px; margin: 32px auto; padding: 0 24px; }

        .filter-card {
            background: white; border-radius: 16px; padding: 24px 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 24px;
            display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;
        }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; font-size: 11px; font-weight: 700;
                              color: #888; text-transform: uppercase; letter-spacing: 1px;
                              margin-bottom: 8px; }
        .filter-group select { width: 100%; padding: 11px 14px; border: 2px solid #eef0f3;
                               border-radius: 10px; font-size: 14px; outline: none;
                               background: #fafbfc; }
        .btn-filter { padding: 11px 24px; background: linear-gradient(135deg, #0a2540, #1a5276);
                      color: white; border: none; border-radius: 10px; font-size: 14px;
                      font-weight: 700; cursor: pointer; }

        .alert { padding: 13px 18px; border-radius: 10px; margin-bottom: 20px;
                 font-size: 14px; font-weight: 500; }
        .alert-success { background: #eafaf1; color: #1e8449; border-left: 4px solid #1abc9c; }
        .alert-error   { background: #fdecea; color: #c0392b; border-left: 4px solid #e74c3c; }

        .stat-bar { display: flex; gap: 16px; margin-bottom: 24px; }
        .stat { background: white; border-radius: 14px; padding: 18px 22px; flex: 1;
                box-shadow: 0 2px 12px rgba(0,0,0,0.06); border-top: 4px solid #e74c3c; }
        .stat .val { font-size: 26px; font-weight: 800; color: #0a2540; }
        .stat .lbl { font-size: 12px; color: #999; margin-top: 4px; }

        .table-card { background: white; border-radius: 16px;
                      box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
        .table-header { padding: 20px 24px; border-bottom: 2px solid #f0f2f5; }
        .table-header h2 { font-size: 17px; color: #0a2540; font-weight: 700; }

        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fb; }
        thead th { padding: 12px 16px; font-size: 11px; font-weight: 700;
                   color: #888; text-transform: uppercase; letter-spacing: 1px; text-align: left; }
        tbody tr { border-bottom: 1px solid #f5f5f5; }
        tbody tr:hover { background: #f7faff; }
        tbody td { padding: 11px 16px; font-size: 14px; }
        .td-mat { font-weight: 600; color: #0a2540; }
        .ue-badge { background: #e8f4fd; color: #1a5276; padding: 3px 10px;
                    border-radius: 20px; font-size: 11px; font-weight: 700; }

        .abs-form { display: flex; align-items: center; gap: 8px; }
        .abs-input { width: 70px; padding: 7px 10px; border: 2px solid #eef0f3;
                     border-radius: 8px; font-size: 14px; text-align: center; outline: none; }
        .abs-input:focus { border-color: #e74c3c; }
        .abs-input.has-abs { border-color: #e74c3c; background: #fff5f5;
                             color: #c0392b; font-weight: 700; }
        .btn-save { padding: 7px 14px; background: #1a5276; color: white;
                    border: none; border-radius: 8px; cursor: pointer;
                    font-size: 12px; font-weight: 700; }

        .empty-state { text-align: center; padding: 60px; color: #bbb; }
        .empty-state .icon { font-size: 52px; margin-bottom: 16px; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>Absences</span></div>
    </div>
    <div class="nav-right">
        <a href="dashboard.php" class="btn-back">← Retour</a>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container">

    <?php if ($message): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <!-- Filtre -->
    <form method="GET" class="filter-card">
        <div class="filter-group">
            <label>👨‍🎓 Étudiant</label>
            <select name="etudiant_id">
                <option value="">-- Sélectionner --</option>
                <?php foreach ($etudiants as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $etudiant_sel == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>📅 Semestre</label>
            <select name="semestre_id">
                <?php foreach ($semestres as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $semestre_sel == $s['id'] ? 'selected' : '' ?>>
                        <?= $s['libelle'] ?> — <?= $s['annee_univ'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-filter">Afficher →</button>
    </form>

    <?php if (!$etudiant_sel): ?>
        <div class="empty-state">
            <div class="icon">📅</div>
            <p>Sélectionnez un étudiant pour gérer ses absences.</p>
        </div>
    <?php else: ?>

        <!-- Stats -->
        <div class="stat-bar">
            <div class="stat">
                <div class="val"><?= $total_heures ?>h</div>
                <div class="lbl">⏱️ Total heures d'absence</div>
            </div>
            <div class="stat" style="border-top-color:#f39c12;">
                <div class="val"><?= count(array_filter($absences)) ?></div>
                <div class="lbl">📚 Matières concernées</div>
            </div>
        </div>

        <!-- Tableau -->
        <div class="table-card">
            <div class="table-header">
                <h2>📅 Absences par matière — Semestre <?= $semestre_sel == 1 ? '5' : '6' ?></h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>UE</th>
                        <th>Matière</th>
                        <th style="text-align:center;">Heures d'absence</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($matieres as $mat):
                    $h = $absences[$mat['id']] ?? 0;
                ?>
                <tr>
                    <td><span class="ue-badge"><?= htmlspecialchars($mat['code']) ?></span></td>
                    <td class="td-mat"><?= htmlspecialchars($mat['libelle']) ?></td>
                    <td style="text-align:center;">
                        <form method="POST" class="abs-form" style="justify-content:center;">
                            <input type="hidden" name="etudiant_id" value="<?= $etudiant_sel ?>">
                            <input type="hidden" name="matiere_id"  value="<?= $mat['id'] ?>">
                            <input type="number" name="heures" min="0" max="200"
                                   class="abs-input <?= $h > 0 ? 'has-abs' : '' ?>"
                                   value="<?= $h ?>">
                            <button type="submit" name="enregistrer" class="btn-save">💾</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>
</body>
</html>