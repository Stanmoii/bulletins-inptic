<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'etudiant') {
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

function calculerMoyennePonderee($items) {
    $points = 0; $coeff = 0;
    foreach ($items as $item) {
        if ($item['moyenne'] !== null) {
            $points += $item['moyenne'] * $item['coeff'];
            $coeff  += $item['coeff'];
        }
    }
    return $coeff > 0 ? round($points / $coeff, 2) : null;
}

function getMention($moy) {
    if ($moy === null) return '–';
    if ($moy >= 16) return 'Très Bien';
    if ($moy >= 14) return 'Bien';
    if ($moy >= 12) return 'Assez Bien';
    if ($moy >= 10) return 'Passable';
    return 'Insuffisant';
}

// Récupérer l'étudiant lié au compte connecté
$stmt = $db->prepare("SELECT e.* FROM etudiant e JOIN utilisateur u ON u.etudiant_id = e.id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    echo "Aucun dossier étudiant lié à ce compte."; exit();
}

$semestre_sel = intval($_GET['semestre_id'] ?? 1);
$semestres    = $db->query("SELECT * FROM semestre ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Résultats
$resultats      = [];
$moy_semestre   = null;
$credits_acquis = 0;
$credits_total  = 0;

$sql = "
    SELECT ue.id as ue_id, ue.code, ue.libelle as ue_libelle,
           m.id as mat_id, m.libelle as mat_libelle,
           m.coefficient, m.credits,
           MAX(CASE WHEN e.type_eval='CC'         THEN e.note END) as cc,
           MAX(CASE WHEN e.type_eval='Examen'     THEN e.note END) as exam,
           MAX(CASE WHEN e.type_eval='Rattrapage' THEN e.note END) as ratt
    FROM ue
    JOIN matiere m ON m.ue_id = ue.id
    LEFT JOIN evaluation e ON e.matiere_id = m.id AND e.etudiant_id = ?
    WHERE ue.semestre_id = ?
    GROUP BY ue.id, ue.code, ue.libelle, m.id, m.libelle, m.coefficient, m.credits
    ORDER BY ue.id, m.libelle
";
$stmt = $db->prepare($sql);
$stmt->execute([$etudiant['id'], $semestre_sel]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $uid = $row['ue_id'];
    if (!isset($resultats[$uid])) {
        $resultats[$uid] = ['code' => $row['code'], 'libelle' => $row['ue_libelle'],
                            'matieres' => [], 'moyenne' => null,
                            'acquise' => false, 'compensee' => false, 'credits' => 0];
    }
    $moy = calculerMoyenneMatiere($row['cc'], $row['exam'], $row['ratt']);
    $resultats[$uid]['matieres'][] = [
        'libelle' => $row['mat_libelle'], 'coefficient' => $row['coefficient'],
        'credits' => $row['credits'], 'cc' => $row['cc'],
        'exam' => $row['exam'], 'ratt' => $row['ratt'], 'moyenne' => $moy,
    ];
    $resultats[$uid]['credits'] += $row['credits'];
    $credits_total += $row['credits'];
}

$items_sem = [];
foreach ($resultats as $uid => &$ue) {
    $items = array_map(fn($m) => ['moyenne' => $m['moyenne'], 'coeff' => $m['coefficient']], $ue['matieres']);
    $ue['moyenne'] = calculerMoyennePonderee($items);
    $coeff_ue = array_sum(array_column($ue['matieres'], 'coefficient'));
    $items_sem[] = ['moyenne' => $ue['moyenne'], 'coeff' => $coeff_ue];
}
unset($ue);

$moy_semestre = calculerMoyennePonderee($items_sem);

foreach ($resultats as $uid => &$ue) {
    if ($ue['moyenne'] !== null) {
        if ($ue['moyenne'] >= 10) { $ue['acquise'] = true; }
        elseif ($moy_semestre !== null && $moy_semestre >= 10) {
            $ue['acquise'] = true; $ue['compensee'] = true;
        }
    }
    if ($ue['acquise']) $credits_acquis += $ue['credits'];
}
unset($ue);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes notes — INPTIC</title>
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
        .nav-user { color: rgba(255,255,255,0.85); font-size: 14px; }
        .badge-role { background: rgba(26,188,156,0.2); color: #1abc9c;
                      border: 1px solid rgba(26,188,156,0.3); padding: 4px 12px;
                      border-radius: 20px; font-size: 12px; font-weight: 600; }
        .btn-logout { background: rgba(231,76,60,0.15); color: #e74c3c;
                      border: 1px solid rgba(231,76,60,0.3); padding: 7px 16px;
                      border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }

        .container { max-width: 1000px; margin: 32px auto; padding: 0 24px; }

        /* Profil étudiant */
        .profil-card {
            background: linear-gradient(135deg, #0a2540, #1a5276);
            border-radius: 16px; padding: 24px 28px; margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 20px rgba(10,37,64,0.3);
        }
        .profil-left { display: flex; align-items: center; gap: 18px; }
        .profil-avatar {
            width: 60px; height: 60px; border-radius: 50%;
            background: linear-gradient(135deg, #1abc9c, #0e6655);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 22px;
            border: 3px solid rgba(255,255,255,0.2);
        }
        .profil-nom { color: white; font-size: 20px; font-weight: 800; }
        .profil-info { color: rgba(255,255,255,0.6); font-size: 13px; margin-top: 4px; }
        .profil-right { text-align: right; }
        .profil-right .label { color: rgba(255,255,255,0.5); font-size: 11px;
                               text-transform: uppercase; letter-spacing: 1px; }
        .profil-right .value { color: white; font-size: 15px; font-weight: 700; }

        /* Onglets semestres */
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .tab { padding: 10px 24px; border-radius: 10px; font-size: 14px; font-weight: 600;
               cursor: pointer; text-decoration: none; border: 2px solid transparent; }
        .tab-active { background: #0a2540; color: white; }
        .tab-inactive { background: white; color: #888; border-color: #e0e0e0; }
        .tab-inactive:hover { border-color: #1a5276; color: #1a5276; }

        /* Stats */
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat { background: white; border-radius: 14px; padding: 18px 20px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.06); border-top: 4px solid #1a5276; }
        .stat.green  { border-top-color: #1abc9c; }
        .stat.orange { border-top-color: #f39c12; }
        .stat.purple { border-top-color: #8e44ad; }
        .stat .val { font-size: 26px; font-weight: 800; color: #0a2540; }
        .stat .lbl { font-size: 11px; color: #999; margin-top: 4px; }

        /* UE blocks */
        .ue-block { background: white; border-radius: 16px; margin-bottom: 20px;
                    box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
        .ue-header { background: linear-gradient(135deg, #0a2540, #1a5276);
                     padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; }
        .ue-left { display: flex; align-items: center; gap: 12px; }
        .ue-code { background: rgba(26,188,156,0.25); color: #1abc9c;
                   padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .ue-titre { color: white; font-size: 15px; font-weight: 700; }
        .ue-badge { padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 800; }
        .badge-ok   { background: #eafaf1; color: #1e8449; }
        .badge-comp { background: #fef9e7; color: #d68910; }
        .badge-nok  { background: #fdecea; color: #c0392b; }
        .badge-nd   { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); }

        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 12px 16px; font-size: 11px; font-weight: 700;
                   color: #888; text-transform: uppercase; letter-spacing: 1px;
                   text-align: left; background: #f8f9fb; }
        .tc { text-align: center; }
        tbody tr { border-bottom: 1px solid #f5f5f5; }
        tbody tr:hover { background: #f7faff; }
        tbody td { padding: 12px 16px; font-size: 14px; }
        .td-mat { font-weight: 600; color: #0a2540; }
        .note-ok  { color: #1e8449; font-weight: 700; }
        .note-nok { color: #c0392b; font-weight: 700; }
        .note-nd  { color: #ccc; }

        /* Bouton bulletin */
        .btn-bulletin {
            display: block; text-align: center; margin-top: 20px;
            background: linear-gradient(135deg, #1abc9c, #0e6655);
            color: white; padding: 14px; border-radius: 12px;
            font-size: 15px; font-weight: 700; text-decoration: none;
            box-shadow: 0 4px 15px rgba(26,188,156,0.3);
        }
        .btn-bulletin:hover { opacity: 0.9; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>Mes Notes</span></div>
    </div>
    <div class="nav-right">
        <span class="badge-role">👨‍🎓 Étudiant</span>
        <span class="nav-user"><?= htmlspecialchars($_SESSION['user_nom']) ?></span>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container">

    <!-- Profil -->
    <div class="profil-card">
        <div class="profil-left">
            <div class="profil-avatar">
                <?= strtoupper(substr($etudiant['prenom'],0,1).substr($etudiant['nom'],0,1)) ?>
            </div>
            <div>
                <div class="profil-nom"><?= htmlspecialchars(strtoupper($etudiant['nom']).' '.$etudiant['prenom']) ?></div>
                <div class="profil-info">
                    LP ASUR — INPTIC
                    <?= $etudiant['date_naissance'] ? ' | Né(e) le '.date('d/m/Y', strtotime($etudiant['date_naissance'])) : '' ?>
                </div>
            </div>
        </div>
        <div class="profil-right">
            <div class="label">Formation</div>
            <div class="value">Licence Professionnelle ASUR</div>
            <div class="label" style="margin-top:8px;">Année</div>
            <div class="value">2025-2026</div>
        </div>
    </div>

    <!-- Onglets semestres -->
    <div class="tabs">
        <?php foreach ($semestres as $s): ?>
            <a href="?semestre_id=<?= $s['id'] ?>"
               class="tab <?= $semestre_sel == $s['id'] ? 'tab-active' : 'tab-inactive' ?>">
                Semestre <?= $s['libelle'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat">
            <div class="val"><?= $moy_semestre !== null ? number_format($moy_semestre, 2) : '–' ?></div>
            <div class="lbl">📊 Moyenne semestre</div>
        </div>
        <div class="stat green">
            <div class="val"><?= $credits_acquis ?>/<?= $credits_total ?></div>
            <div class="lbl">⭐ Crédits acquis</div>
        </div>
        <div class="stat orange">
            <div class="val"><?= getMention($moy_semestre) ?></div>
            <div class="lbl">🏅 Mention</div>
        </div>
        <div class="stat purple">
            <div class="val"><?= $credits_acquis >= $credits_total && $credits_total > 0 ? '✅' : '❌' ?></div>
            <div class="lbl">🎓 <?= $credits_acquis >= $credits_total && $credits_total > 0 ? 'Semestre validé' : 'Non validé' ?></div>
        </div>
    </div>

    <!-- Notes par UE -->
    <?php foreach ($resultats as $uid => $ue): ?>
    <div class="ue-block">
        <div class="ue-header">
            <div class="ue-left">
                <span class="ue-code"><?= $ue['code'] ?></span>
                <span class="ue-titre"><?= htmlspecialchars($ue['libelle']) ?></span>
            </div>
            <?php if ($ue['moyenne'] !== null): ?>
                <?php if ($ue['acquise'] && !$ue['compensee']): ?>
                    <span class="ue-badge badge-ok">✅ <?= number_format($ue['moyenne'],2) ?>/20 — Acquise</span>
                <?php elseif ($ue['compensee']): ?>
                    <span class="ue-badge badge-comp">⚡ <?= number_format($ue['moyenne'],2) ?>/20 — Compensée</span>
                <?php else: ?>
                    <span class="ue-badge badge-nok">❌ <?= number_format($ue['moyenne'],2) ?>/20 — Non acquise</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="ue-badge badge-nd">– Non évaluée</span>
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Matière</th>
                    <th class="tc">Coeff.</th>
                    <th class="tc">CC (40%)</th>
                    <th class="tc">Examen (60%)</th>
                    <th class="tc">Rattrapage</th>
                    <th class="tc">Moyenne</th>
                    <th class="tc">Crédits</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ue['matieres'] as $mat):
                $moy = $mat['moyenne'];
                $cls = $moy === null ? 'note-nd' : ($moy >= 10 ? 'note-ok' : 'note-nok');
            ?>
            <tr>
                <td class="td-mat"><?= htmlspecialchars($mat['libelle']) ?></td>
                <td class="tc"><span style="background:#e8f4fd;color:#1a5276;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">×<?= $mat['coefficient'] ?></span></td>
                <td class="tc <?= $mat['cc'] !== null ? ($mat['cc']>=10?'note-ok':'note-nok') : 'note-nd' ?>">
                    <?= $mat['cc'] !== null ? number_format($mat['cc'],2) : '–' ?>
                </td>
                <td class="tc <?= $mat['exam'] !== null ? ($mat['exam']>=10?'note-ok':'note-nok') : 'note-nd' ?>">
                    <?= $mat['exam'] !== null ? number_format($mat['exam'],2) : '–' ?>
                </td>
                <td class="tc <?= $mat['ratt'] !== null ? ($mat['ratt']>=10?'note-ok':'note-nok') : 'note-nd' ?>">
                    <?= $mat['ratt'] !== null ? number_format($mat['ratt'],2) : '–' ?>
                </td>
                <td class="tc <?= $cls ?>" style="font-weight:800; font-size:15px;">
                    <?= $moy !== null ? number_format($moy,2) : '–' ?>
                </td>
                <td class="tc">
                    <span style="background:<?= $ue['acquise']?'#eafaf1':'#fdecea' ?>;color:<?= $ue['acquise']?'#1e8449':'#c0392b' ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                        <?= $ue['acquise'] ? $mat['credits'] : 0 ?>/<?= $mat['credits'] ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <!-- Lien bulletin -->
    <a href="bulletin.php?etudiant_id=<?= $etudiant['id'] ?>&semestre_id=<?= $semestre_sel ?>"
       class="btn-bulletin">
        📄 Voir et imprimer mon bulletin officiel
    </a>

</div>
</body>
</html>