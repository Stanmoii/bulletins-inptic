<?php
// export_excel.php
session_start();
require 'connexion.php';

// Vérifier connexion
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretariat'])) {
    header('Location: login.php');
    exit();
}

// Récupérer tous les étudiants avec leurs moyennes
$etudiants = [];
$stmt = $db->query("SELECT id, nom, prenom, matricule FROM etudiant ORDER BY nom");
$etudiants_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($etudiants_data as $etudiant) {
    $id = $etudiant['id'];
    
    // Calcul moyenne S5
    $stmt_s5 = $db->prepare("
        SELECT AVG(
            CASE 
                WHEN ratt.note IS NOT NULL THEN ratt.note
                WHEN cc.note IS NOT NULL AND exam.note IS NOT NULL THEN (cc.note * 0.4 + exam.note * 0.6)
                WHEN cc.note IS NOT NULL THEN cc.note
                WHEN exam.note IS NOT NULL THEN exam.note
                ELSE NULL
            END
        ) as moyenne
        FROM matiere m
        JOIN ue ON ue.id = m.ue_id
        LEFT JOIN evaluation cc ON cc.matiere_id = m.id AND cc.etudiant_id = ? AND cc.type_eval = 'CC'
        LEFT JOIN evaluation exam ON exam.matiere_id = m.id AND exam.etudiant_id = ? AND exam.type_eval = 'Examen'
        LEFT JOIN evaluation ratt ON ratt.matiere_id = m.id AND ratt.etudiant_id = ? AND ratt.type_eval = 'Rattrapage'
        WHERE ue.semestre_id = 1
    ");
    $stmt_s5->execute([$id, $id, $id]);
    $moy_s5 = round($stmt_s5->fetchColumn() ?: 0, 2);
    
    // Calcul moyenne S6
    $stmt_s6 = $db->prepare("
        SELECT AVG(
            CASE 
                WHEN ratt.note IS NOT NULL THEN ratt.note
                WHEN cc.note IS NOT NULL AND exam.note IS NOT NULL THEN (cc.note * 0.4 + exam.note * 0.6)
                WHEN cc.note IS NOT NULL THEN cc.note
                WHEN exam.note IS NOT NULL THEN exam.note
                ELSE NULL
            END
        ) as moyenne
        FROM matiere m
        JOIN ue ON ue.id = m.ue_id
        LEFT JOIN evaluation cc ON cc.matiere_id = m.id AND cc.etudiant_id = ? AND cc.type_eval = 'CC'
        LEFT JOIN evaluation exam ON exam.matiere_id = m.id AND exam.etudiant_id = ? AND exam.type_eval = 'Examen'
        LEFT JOIN evaluation ratt ON ratt.matiere_id = m.id AND ratt.etudiant_id = ? AND ratt.type_eval = 'Rattrapage'
        WHERE ue.semestre_id = 2
    ");
    $stmt_s6->execute([$id, $id, $id]);
    $moy_s6 = round($stmt_s6->fetchColumn() ?: 0, 2);
    
    $moy_annuelle = round(($moy_s5 + $moy_s6) / 2, 2);
    $decision = $moy_annuelle >= 10 ? 'Admis' : 'Non admis';
    
    $etudiants[] = [
        'id' => $etudiant['id'],
        'matricule' => $etudiant['matricule'] ?? '-',
        'nom' => $etudiant['nom'],
        'prenom' => $etudiant['prenom'],
        'moy_s5' => $moy_s5,
        'moy_s6' => $moy_s6,
        'moy_annuelle' => $moy_annuelle,
        'decision' => $decision
    ];
}

// Statistiques
$nb_admis = count(array_filter($etudiants, fn($e) => $e['decision'] == 'Admis'));
$nb_non_admis = count($etudiants) - $nb_admis;
$moyenne_promo = round(array_sum(array_column($etudiants, 'moy_annuelle')) / count($etudiants), 2);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export Excel - Tableau Comparaison</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 16px; padding: 25px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        h1 { color: #0a2540; margin-bottom: 10px; }
        h2 { color: #1a5276; margin: 20px 0 15px; font-size: 18px; }
        .btn-excel { background: #1e8449; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 700; margin-bottom: 20px; }
        .btn-excel:hover { background: #145a32; }
        .stats { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-card { background: #f8f9fa; border-radius: 12px; padding: 15px 25px; text-align: center; }
        .stat-card .val { font-size: 28px; font-weight: 800; color: #0a2540; }
        .stat-card .lbl { font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #0a2540; color: white; padding: 12px; text-align: left; }
        td { padding: 10px 12px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
        .admis { color: #1e8449; font-weight: bold; }
        .non-admis { color: #c0392b; font-weight: bold; }
        .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; text-align: center; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <h1>📊 Tableau Comparatif des Résultats</h1>
    <p>LP ASUR — Année universitaire 2025-2026</p>
    
    <div class="stats">
        <div class="stat-card"><div class="val"><?= count($etudiants) ?></div><div class="lbl">Total étudiants</div></div>
        <div class="stat-card"><div class="val" style="color:#1e8449;"><?= $nb_admis ?></div><div class="lbl">Admis</div></div>
        <div class="stat-card"><div class="val" style="color:#c0392b;"><?= $nb_non_admis ?></div><div class="lbl">Non admis</div></div>
        <div class="stat-card"><div class="val"><?= $moyenne_promo ?></div><div class="lbl">Moyenne promo</div></div>
    </div>
    
    <button class="btn-excel" onclick="exportToExcel()">📎 Exporter vers Excel</button>
    
    <h2>📋 Liste des étudiants</h2>
    <div style="overflow-x: auto;">
        <table id="tableau_notes">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Matricule</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Moyenne S5</th>
                    <th>Moyenne S6</th>
                    <th>Moyenne Annuelle</th>
                    <th>Décision</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($etudiants as $e): ?>
                <tr>
                    <td><?= $e['id'] ?></td>
                    <td><?= htmlspecialchars($e['matricule']) ?></td>
                    <td><?= htmlspecialchars($e['nom']) ?></td>
                    <td><?= htmlspecialchars($e['prenom']) ?></td>
                    <td><?= number_format($e['moy_s5'], 2) ?></td>
                    <td><?= number_format($e['moy_s6'], 2) ?></td>
                    <td><strong><?= number_format($e['moy_annuelle'], 2) ?></strong></td>
                    <td class="<?= $e['decision'] == 'Admis' ? 'admis' : 'non-admis' ?>"><?= $e['decision'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="footer">
        INPTIC — LP ASUR — Gestion des bulletins de notes
    </div>
</div>

<script>
function exportToExcel() {
    let table = document.getElementById('tableau_notes');
    let html = table.outerHTML;
    let blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    let link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'tableau_comparaison_notes.xls';
    link.click();
    URL.revokeObjectURL(link.href);
}
</script>
</body>
</html>