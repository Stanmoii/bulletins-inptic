<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

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
        // Vérifier si la note existe déjà
        $check = $db->prepare("SELECT id FROM evaluation WHERE etudiant_id=? AND matiere_id=? AND type_eval=?");
        $check->execute([$etudiant_id, $matiere_id, $type]);

        if ($check->fetch()) {
            $db->prepare("UPDATE evaluation SET note=? WHERE etudiant_id=? AND matiere_id=? AND type_eval=?")
               ->execute([$note, $etudiant_id, $matiere_id, $type]);
            $message = "Note mise à jour avec succès !";
        } else {
            $db->prepare("INSERT INTO evaluation (etudiant_id, matiere_id, type_eval, note) VALUES (?,?,?,?)")
               ->execute([$etudiant_id, $matiere_id, $type, $note]);
            $message = "Note enregistrée avec succès !";
        }
    }
}

// ── Supprimer une note ──
if (isset($_GET['supprimer'])) {
    $db->prepare("DELETE FROM evaluation WHERE id=?")->execute([intval($_GET['supprimer'])]);
    $message = "Note supprimée.";
}

// ── Données pour les listes ──
$etudiants = $db->query("SELECT id, nom, prenom FROM etudiant ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$semestres = $db->query("SELECT * FROM semestre ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Étudiant sélectionné
$etudiant_sel = intval($_GET['etudiant_id'] ?? $_POST['etudiant_id'] ?? 0);
$semestre_sel = intval($_GET['semestre_id'] ?? 1);

// UE et matières du semestre sélectionné
$ues = [];
if ($semestre_sel) {
    $stmt = $db->prepare("
        SELECT ue.id as ue_id, ue.code, ue.libelle as ue_libelle,
               m.id as matiere_id, m.libelle as matiere_libelle, m.coefficient, m.credits
        FROM ue
        JOIN matiere m ON m.ue_id = ue.id
        WHERE ue.semestre_id = ?
        ORDER BY ue.id, m.libelle
    ");
    $stmt->execute([$semestre_sel]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $ues[$row['ue_id']]['libelle'] = $row['ue_libelle'];
        $ues[$row['ue_id']]['code']    = $row['code'];
        $ues[$row['ue_id']]['matieres'][] = $row;
    }
}

// Notes existantes de l'étudiant sélectionné
$notes_existantes = [];
if ($etudiant_sel) {
    $stmt = $db->prepare("
        SELECT e.id, e.matiere_id, e.type_eval, e.note
        FROM evaluation e
        JOIN matiere m ON m.id = e.matiere_id
        JOIN ue ON ue.id = m.ue_id
        WHERE e.etudiant_id = ? AND ue.semestre_id = ?
    ");
    $stmt->execute([$etudiant_sel, $semestre_sel]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $notes_existantes[$n['matiere_id']][$n['type_eval']] = ['note' => $n['note'], 'id' => $n['id']];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Saisie des notes — INPTIC</title>
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
                    border-radius: 8px; font-size: 13px; font-weight: 600;
                    text-decoration: none; }
        .btn-logout { background: rgba(231,76,60,0.15); color: #e74c3c;
                      border: 1px solid rgba(231,76,60,0.3); padding: 7px 16px;
                      border-radius: 8px; font-size: 13px; font-weight: 600;
                      text-decoration: none; }

        .container { max-width: 1150px; margin: 32px auto; padding: 0 24px; }

        /* Filtre */
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
                               background: #fafbfc; transition: border-color 0.3s; }
        .filter-group select:focus { border-color: #1a5276; }
        .btn-filter { padding: 11px 24px; background: linear-gradient(135deg, #0a2540, #1a5276);
                      color: white; border: none; border-radius: 10px; font-size: 14px;
                      font-weight: 700; cursor: pointer; white-space: nowrap; }

        /* Alerte */
        .alert { padding: 13px 18px; border-radius: 10px; margin-bottom: 20px;
                 font-size: 14px; font-weight: 500; }
        .alert-success { background: #eafaf1; color: #1e8449; border-left: 4px solid #1abc9c; }
        .alert-error   { background: #fdecea; color: #c0392b; border-left: 4px solid #e74c3c; }

        /* UE block */
        .ue-block { background: white; border-radius: 16px; margin-bottom: 20px;
                    box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
        .ue-header { background: linear-gradient(135deg, #0a2540, #1a5276);
                     padding: 14px 24px; display: flex; align-items: center; gap: 12px; }
        .ue-code { background: rgba(26,188,156,0.25); color: #1abc9c;
                   padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .ue-titre { color: white; font-size: 15px; font-weight: 700; }

        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 12px 16px; font-size: 11px; font-weight: 700;
                   color: #888; text-transform: uppercase; letter-spacing: 1px;
                   text-align: left; background: #f8f9fb; }
        tbody tr { border-bottom: 1px solid #f5f5f5; }
        tbody tr:hover { background: #f7faff; }
        tbody td { padding: 12px 16px; font-size: 14px; }

        .td-matiere { font-weight: 600; color: #0a2540; }
        .badge-coeff { background: #e8f4fd; color: #1a5276; padding: 3px 10px;
                       border-radius: 20px; font-size: 11px; font-weight: 700; }

        /* Input note inline */
        .note-form { display: flex; align-items: center; gap: 8px; }
        .note-input { width: 75px; padding: 7px 10px; border: 2px solid #eef0f3;
                      border-radius: 8px; font-size: 14px; text-align: center;
                      outline: none; transition: border-color 0.2s; }
        .note-input:focus { border-color: #1a5276; }
        .note-input.has-note { border-color: #1abc9c; background: #f0fff8; color: #1e8449; font-weight: 700; }
        .btn-save { padding: 7px 14px; background: #1a5276; color: white;
                    border: none; border-radius: 8px; cursor: pointer;
                    font-size: 12px; font-weight: 700; }
        .btn-save:hover { background: #0a2540; }
        .btn-del-note { padding: 6px 10px; background: #fdecea; color: #e74c3c;
                        border: none; border-radius: 8px; cursor: pointer; font-size: 12px; }

        .moyenne-cell { font-weight: 800; font-size: 15px; }
        .moy-ok  { color: #1e8449; }
        .moy-nok { color: #c0392b; }

        .empty-state { text-align: center; padding: 60px; color: #bbb; }
        .empty-state .icon { font-size: 52px; margin-bottom: 16px; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>Saisie des notes</span></div>
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

    <!-- Filtre étudiant / semestre -->
    <form method="GET" class="filter-card">
        <div class="filter-group">
            <label>👨‍🎓 Étudiant</label>
            <select name="etudiant_id" required>
                <option value="">-- Sélectionner un étudiant --</option>
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
                        <?= htmlspecialchars($s['libelle']) ?> — <?= $s['annee_univ'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-filter">Afficher →</button>
    </form>

    <?php if (!$etudiant_sel): ?>
        <div class="empty-state">
            <div class="icon">✏️</div>
            <p>Sélectionnez un étudiant et un semestre<br>pour saisir ses notes.</p>
        </div>

    <?php elseif (empty($ues)): ?>
        <div class="empty-state">
            <div class="icon">📚</div>
            <p>Aucune matière trouvée pour ce semestre.</p>
        </div>

    <?php else: ?>

        <?php
        // Nom de l'étudiant sélectionné
        $stmt = $db->prepare("SELECT nom, prenom FROM etudiant WHERE id=?");
        $stmt->execute([$etudiant_sel]);
        $etu = $stmt->fetch(PDO::FETCH_ASSOC);
        ?>

        <div style="margin-bottom:20px;">
            <h2 style="font-size:18px; color:#0a2540; font-weight:800;">
                📋 Notes de <?= htmlspecialchars($etu['nom'] . ' ' . $etu['prenom']) ?>
                — Semestre <?= $semestre_sel == 1 ? '5 (S5)' : '6 (S6)' ?>
            </h2>
            <p style="color:#999; font-size:13px; margin-top:4px;">
                Cliquez sur 💾 après chaque note pour l'enregistrer
            </p>
        </div>

        <?php foreach ($ues as $ue_id => $ue): ?>
        <div class="ue-block">
            <div class="ue-header">
                <span class="ue-code"><?= htmlspecialchars($ue['code']) ?></span>
                <span class="ue-titre"><?= htmlspecialchars($ue['libelle']) ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th>Coeff.</th>
                        <th>CC (40%)</th>
                        <th>Examen (60%)</th>
                        <th>Rattrapage</th>
                        <th>Moyenne</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ue['matieres'] as $mat):
                    $cc   = $notes_existantes[$mat['matiere_id']]['CC']         ?? null;
                    $exam = $notes_existantes[$mat['matiere_id']]['Examen']     ?? null;
                    $ratt = $notes_existantes[$mat['matiere_id']]['Rattrapage'] ?? null;

                    // Calculer la moyenne
                    if ($ratt) {
                        $moy = $ratt['note'];
                    } elseif ($cc && $exam) {
                        $moy = round($cc['note'] * 0.4 + $exam['note'] * 0.6, 2);
                    } elseif ($cc) {
                        $moy = $cc['note'];
                    } elseif ($exam) {
                        $moy = $exam['note'];
                    } else {
                        $moy = null;
                    }
                ?>
                <tr>
                    <td class="td-matiere"><?= htmlspecialchars($mat['matiere_libelle']) ?></td>
                    <td><span class="badge-coeff">×<?= $mat['coefficient'] ?></span></td>

                    <?php foreach (['CC', 'Examen', 'Rattrapage'] as $type):
                        $n = $notes_existantes[$mat['matiere_id']][$type] ?? null;
                    ?>
                    <td>
                        <form method="POST" class="note-form">
                            <input type="hidden" name="etudiant_id" value="<?= $etudiant_sel ?>">
                            <input type="hidden" name="matiere_id"  value="<?= $mat['matiere_id'] ?>">
                            <input type="hidden" name="type_eval"   value="<?= $type ?>">
                            <input type="number" name="note" step="0.25" min="0" max="20"
                                   class="note-input <?= $n ? 'has-note' : '' ?>"
                                   value="<?= $n ? $n['note'] : '' ?>"
                                   placeholder="–">
                            <button type="submit" name="enregistrer" class="btn-save" title="Enregistrer">💾</button>
                            <?php if ($n): ?>
                                <a href="?etudiant_id=<?= $etudiant_sel ?>&semestre_id=<?= $semestre_sel ?>&supprimer=<?= $n['id'] ?>"
                                   class="btn-del-note"
                                   onclick="return confirm('Supprimer cette note ?')"
                                   title="Supprimer">✕</a>
                            <?php endif; ?>
                        </form>
                    </td>
                    <?php endforeach; ?>

                    <td class="moyenne-cell <?= $moy !== null ? ($moy >= 10 ? 'moy-ok' : 'moy-nok') : '' ?>">
                        <?= $moy !== null ? number_format($moy, 2) : '–' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>
</body>
</html>