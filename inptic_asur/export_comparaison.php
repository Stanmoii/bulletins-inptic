<?php
// export_comparaison.php
session_start();
require 'connexion.php';

// Optionnel : vérifier que l'utilisateur est admin ou secrétariat
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretariat'])) {
    header('Location: login.php');
    exit();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="tableau_comparaison_notes_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Entêtes CSV
fputcsv($output, ['ID', 'Matricule', 'Nom', 'Prénom', 'Moyenne S5', 'Moyenne S6', 'Moyenne Annuelle', 'Décision']);

// Récupérer les données
$stmt = $db->query("
    SELECT e.id, e.matricule, e.nom, e.prenom
    FROM etudiant e
    ORDER BY e.nom
");

while ($etudiant = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $id = $etudiant['id'];
    
    // Calcul moyenne S5 (UE5-1 et UE5-2)
    $stmt_s5 = $db->prepare("
        SELECT AVG(moyenne_finale) as moy_s5
        FROM (
            SELECT 
                m.id,
                CASE 
                    WHEN ratt.note IS NOT NULL THEN ratt.note
                    WHEN cc.note IS NOT NULL AND exam.note IS NOT NULL THEN (cc.note * 0.4 + exam.note * 0.6)
                    WHEN cc.note IS NOT NULL THEN cc.note
                    WHEN exam.note IS NOT NULL THEN exam.note
                    ELSE NULL
                END as moyenne_finale
            FROM matiere m
            JOIN ue ON ue.id = m.ue_id
            LEFT JOIN evaluation cc ON cc.matiere_id = m.id AND cc.etudiant_id = ? AND cc.type_eval = 'CC'
            LEFT JOIN evaluation exam ON exam.matiere_id = m.id AND exam.etudiant_id = ? AND exam.type_eval = 'Examen'
            LEFT JOIN evaluation ratt ON ratt.matiere_id = m.id AND ratt.etudiant_id = ? AND ratt.type_eval = 'Rattrapage'
            WHERE ue.semestre_id = 1
        ) as matieres_s5
    ");
    $stmt_s5->execute([$id, $id, $id]);
    $moy_s5 = round($stmt_s5->fetchColumn() ?: 0, 2);
    
    // Calcul moyenne S6 (UE6-1 et UE6-2)
    $stmt_s6 = $db->prepare("
        SELECT AVG(moyenne_finale) as moy_s6
        FROM (
            SELECT 
                m.id,
                CASE 
                    WHEN ratt.note IS NOT NULL THEN ratt.note
                    WHEN cc.note IS NOT NULL AND exam.note IS NOT NULL THEN (cc.note * 0.4 + exam.note * 0.6)
                    WHEN cc.note IS NOT NULL THEN cc.note
                    WHEN exam.note IS NOT NULL THEN exam.note
                    ELSE NULL
                END as moyenne_finale
            FROM matiere m
            JOIN ue ON ue.id = m.ue_id
            LEFT JOIN evaluation cc ON cc.matiere_id = m.id AND cc.etudiant_id = ? AND cc.type_eval = 'CC'
            LEFT JOIN evaluation exam ON exam.matiere_id = m.id AND exam.etudiant_id = ? AND exam.type_eval = 'Examen'
            LEFT JOIN evaluation ratt ON ratt.matiere_id = m.id AND ratt.etudiant_id = ? AND ratt.type_eval = 'Rattrapage'
            WHERE ue.semestre_id = 2
        ) as matieres_s6
    ");
    $stmt_s6->execute([$id, $id, $id]);
    $moy_s6 = round($stmt_s6->fetchColumn() ?: 0, 2);
    
    // Moyenne annuelle
    $moy_annuelle = round(($moy_s5 + $moy_s6) / 2, 2);
    
    // Décision
    $decision = $moy_annuelle >= 10 ? 'Admis' : 'Non admis';
    
    fputcsv($output, [
        $etudiant['id'],
        $etudiant['matricule'] ?? '-',
        $etudiant['nom'],
        $etudiant['prenom'],
        $moy_s5,
        $moy_s6,
        $moy_annuelle,
        $decision
    ]);
}

fclose($output);
?>