<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

function calculerMoyenneMatiere($cc, $exam, $ratt) {
    if ($ratt !== null) return round($ratt, 2);
    if ($cc !== null && $exam !== null) return round($cc * 0.4 + $exam * 0.6, 2);
    if ($cc !== null) return round($cc, 2);
    if ($exam !== null) return round($exam, 2);
    return null;
}

function getMention($moy) {
    if ($moy === null) return '-';
    if ($moy >= 16) return 'Très Bien';
    if ($moy >= 14) return 'Bien';
    if ($moy >= 12) return 'Assez Bien';
    if ($moy >= 10) return 'Passable';
    return 'Insuffisant';
}

// Récupérer tous les étudiants
$etudiants = $db->query("SELECT id, nom, prenom FROM etudiant ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$promotion = [];

foreach ($etudiants as $etu) {
    $eid = $etu['id'];
    
    $data = [
        'nom' => $etu['nom'] . ' ' . $etu['prenom'],
        'moy_s5' => null,
        'moy_s6' => null,
        'moy_ann' => null,
        'cr_s5' => 0,
        'cr_s6' => 0,
        'mention' => '-',
        'decision' => ['label' => '–', 'class' => 'dec-nd']
    ];

    foreach ([1, 2] as $sem_id) {
        $sql = "
            SELECT ue.id as ue_id, ue.code, ue.libelle,
                   m.id as mat_id, m.libelle as mat_libelle, 
                   m.coefficient, m.credits,
                   MAX(CASE WHEN e.type_eval='CC' THEN e.note END) as cc,
                   MAX(CASE WHEN e.type_eval='Examen' THEN e.note END) as exam,
                   MAX(CASE WHEN e.type_eval='Rattrapage' THEN e.note END) as ratt
            FROM ue
            JOIN matiere m ON m.ue_id = ue.id
            LEFT JOIN evaluation e ON e.matiere_id = m.id AND e.etudiant_id = ?
            WHERE ue.semestre_id = ?
            GROUP BY ue.id, ue.code, ue.libelle, m.id, m.libelle, m.coefficient, m.credits
            ORDER BY ue.id, m.libelle
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$eid, $sem_id]);
        $rows = $stmt->fetchAll();

        $ues = [];
        foreach ($rows as $row) {
            $uid = $row['ue_id'];
            if (!isset($ues[$uid])) {
                $ues[$uid] = [
                    'code' => $row['code'],
                    'matieres' => [],
                    'credits_total' => 0
                ];
            }
            $moy_matiere = calculerMoyenneMatiere($row['cc'], $row['exam'], $row['ratt']);
            $ues[$uid]['matieres'][] = ['moyenne' => $moy_matiere, 'coeff' => $row['coefficient']];
            $ues[$uid]['credits_total'] += $row['credits'];
        }

        // Calcul de la moyenne du semestre
        $points_sem = 0; $coeff_sem = 0;
        $credits_purement_acquis = 0;

        foreach ($ues as $ue) {
            $p_ue = 0; $c_ue = 0;
            foreach ($ue['matieres'] as $m) {
                if ($m['moyenne'] !== null) {
                    $p_ue += $m['moyenne'] * $m['coeff'];
                    $c_ue += $m['coeff'];
                }
            }
            $moy_ue = $c_ue > 0 ? $p_ue / $c_ue : null;
            if ($moy_ue >= 10) $credits_purement_acquis += $ue['credits_total'];
            
            if ($moy_ue !== null) {
                $points_sem += $moy_ue * $c_ue;
                $coeff_sem += $c_ue;
            }
        }
        $moy_sem = $coeff_sem > 0 ? round($points_sem / $coeff_sem, 2) : null;

        // AJUSTEMENT : Compensation intégrale du semestre
        if ($moy_sem >= 10) {
            $final_credits = 30; // On valide tout le semestre
        } else {
            $final_credits = $credits_purement_acquis;
        }

        if ($sem_id == 1) {
            $data['moy_s5'] = $moy_sem;
            $data['cr_s5'] = $final_credits;
        } else {
            $data['moy_s6'] = $moy_sem;
            $data['cr_s6'] = $final_credits;
        }
    }

    // Calcul Annuel et Décision Jury
    if ($data['moy_s5'] !== null && $data['moy_s6'] !== null) {
        $data['moy_ann'] = round(($data['moy_s5'] + $data['moy_s6']) / 2, 2);
        $data['mention'] = getMention($data['moy_ann']);
        
        // AJUSTEMENT LOGIQUE JURY
        if ($data['moy_ann'] >= 10) {
            $data['cr_s5'] = 30; // Sécurité visuelle
            $data['cr_s6'] = 30;
            $data['decision'] = ['label' => 'Diplômé(e)', 'class' => 'dec-diplome'];
        } else {
            $data['decision'] = ['label' => 'Redoublement L3', 'class' => 'dec-redouble'];
        }
    }
    $promotion[] = $data;
}

// Statistiques
$nb_diplomes = count(array_filter($promotion, fn($e) => $e['decision']['label'] === 'Diplômé(e)'));
$nb_redouble = count(array_filter($promotion, fn($e) => $e['decision']['label'] === 'Redoublement L3'));
$moyennes = array_filter(array_column($promotion, 'moy_ann'));
$moy_promo = count($moyennes) > 0 ? round(array_sum($moyennes) / count($moyennes), 2) : null;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Décisions du jury — INPTIC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
        .navbar { background: linear-gradient(135deg, #0a2540, #1a5276); padding: 0 30px; height: 64px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 15px rgba(0,0,0,0.2); }
        .nav-left { display: flex; align-items: center; gap: 14px; }
        .nav-logo { width: 40px; height: 40px; border-radius: 8px; background: white; padding: 4px; display: flex; align-items: center; justify-content: center; }
        .nav-logo img { width: 100%; height: 100%; object-fit: contain; }
        .nav-title { color: white; font-size: 16px; font-weight: 700; }
        .nav-title span { color: #1abc9c; }
        .container { max-width: 1150px; margin: 32px auto; padding: 0 24px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat { background: white; border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border-top: 4px solid #1a5276; }
        .stat.green { border-top-color: #1abc9c; }
        .stat.red { border-top-color: #e74c3c; }
        .stat.purple { border-top-color: #8e44ad; }
        .stat .val { font-size: 28px; font-weight: 800; color: #0a2540; }
        .stat .lbl { font-size: 11px; color: #999; margin-top: 4px; }
        .table-card { background: white; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
        .table-header { padding: 20px 28px; border-bottom: 2px solid #f0f2f5; }
        .table-header h2 { font-size: 17px; color: #0a2540; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #0a2540; }
        thead th { padding: 13px 16px; font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.7); text-transform: uppercase; text-align: left; }
        .text-center { text-align: center; }
        tbody td { padding: 13px 16px; font-size: 14px; border-bottom: 1px solid #f5f5f5; }
        .td-nom { font-weight: 700; color: #0a2540; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #1a5276, #1abc9c); display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 12px; margin-right: 10px; }
        .moy { font-weight: 700; }
        .moy-ok { color: #1e8449; }
        .moy-nok { color: #c0392b; }
        .dec-badge { padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .dec-diplome { background: #eafaf1; color: #1e8449; }
        .dec-redouble { background: #fdecea; color: #c0392b; }
        .mention-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .m-ab { background: #eafaf1; color: #1e8449; }
        .m-p { background: #fef9e7; color: #d68910; }
        .m-i { background: #fdecea; color: #c0392b; }
        .rang { background: #f0f2f5; color: #555; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>Décisions du jury</span></div>
    </div>
</nav>

<div class="container">
    <div class="stats">
        <div class="stat"><div class="val"><?= count($promotion) ?></div><div class="lbl">👨‍🎓 Étudiants</div></div>
        <div class="stat green"><div class="val"><?= $nb_diplomes ?></div><div class="lbl">✅ Diplômés</div></div>
        <div class="stat red"><div class="val"><?= $nb_redouble ?></div><div class="lbl">❌ Redoublants</div></div>
        <div class="stat purple"><div class="val"><?= $moy_promo ?? '–' ?></div><div class="lbl">📊 Moyenne promotion</div></div>
    </div>

    <div class="table-card">
        <div class="table-header"><h2>⚖️ Récapitulatif de la promotion — LP ASUR 2025-2026</h2></div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Étudiant</th>
                    <th class="text-center">Moy. S5</th>
                    <th class="text-center">Moy. S6</th>
                    <th class="text-center">Moy. Annuelle</th>
                    <th class="text-center">Crédits</th>
                    <th class="text-center">Mention</th>
                    <th class="text-center">Décision jury</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            usort($promotion, fn($a, $b) => ($b['moy_ann'] ?? -1) <=> ($a['moy_ann'] ?? -1));
            foreach ($promotion as $i => $e): 
                $parts = explode(' ', $e['nom']);
                $initiales = strtoupper(substr($parts[0], 0, 1) . substr($parts[1] ?? '', 0, 1));
            ?>
                <tr>
                    <td><span class="rang"><?= $i + 1 ?></span></td>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <span class="avatar"><?= $initiales ?></span>
                            <span class="td-nom"><?= htmlspecialchars($e['nom']) ?></span>
                        </div>
                    </td>
                    <td class="text-center moy <?= $e['moy_s5'] >= 10 ? 'moy-ok' : 'moy-nok' ?>"><?= number_format($e['moy_s5'], 2) ?></td>
                    <td class="text-center moy <?= $e['moy_s6'] >= 10 ? 'moy-ok' : 'moy-nok' ?>"><?= number_format($e['moy_s6'], 2) ?></td>
                    <td class="text-center moy <?= $e['moy_ann'] >= 10 ? 'moy-ok' : 'moy-nok' ?>" style="font-size:16px;"><?= number_format($e['moy_ann'], 2) ?></td>
                    <td class="text-center" style="font-weight:700; color:#1a5276;"><?= $e['cr_s5'] + $e['cr_s6'] ?>/60</td>
                    <td class="text-center"><span class="mention-badge m-<?= $e['moy_ann'] >= 12 ? 'ab' : ($e['moy_ann'] >= 10 ? 'p' : 'i') ?>"><?= $e['mention'] ?></span></td>
                    <td class="text-center"><span class="dec-badge <?= $e['decision']['class'] ?>"><?= $e['decision']['label'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>