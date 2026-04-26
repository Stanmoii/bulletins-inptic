<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ── Fonctions de calcul ──
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
    if ($moy === null) return '-';
    if ($moy >= 16) return 'Très Bien';
    if ($moy >= 14) return 'Bien';
    if ($moy >= 12) return 'Assez Bien';
    if ($moy >= 10) return 'Passable';
    return 'Insuffisant';
}

function getCouleurMention($moy) {
    if ($moy === null) return '#999';
    if ($moy >= 16) return '#8e44ad';
    if ($moy >= 14) return '#1a5276';
    if ($moy >= 12) return '#1e8449';
    if ($moy >= 10) return '#d68910';
    return '#c0392b';
}

// ── Données ──
$etudiants  = $db->query("SELECT id, nom, prenom FROM etudiant ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$semestres  = $db->query("SELECT * FROM semestre ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$etudiant_sel = intval($_GET['etudiant_id'] ?? 0);
$semestre_sel = intval($_GET['semestre_id'] ?? 1);

$resultats = [];
$etudiant  = null;
$moy_semestre = null;
$credits_acquis = 0;
$credits_total  = 0;

if ($etudiant_sel) {
    $stmt = $db->prepare("SELECT * FROM etudiant WHERE id = ?");
    $stmt->execute([$etudiant_sel]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer toutes les matières avec notes
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
    $stmt->execute([$etudiant_sel, $semestre_sel]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser par UE
    foreach ($rows as $row) {
        $uid = $row['ue_id'];
        if (!isset($resultats[$uid])) {
            $resultats[$uid] = [
                'code'     => $row['code'],
                'libelle'  => $row['ue_libelle'],
                'matieres' => [],
                'moyenne'  => null,
                'acquise'  => false,
                'compensee'=> false,
                'credits'  => 0,
            ];
        }
        $moy = calculerMoyenneMatiere($row['cc'], $row['exam'], $row['ratt']);
        $resultats[$uid]['matieres'][] = [
            'libelle'     => $row['mat_libelle'],
            'coefficient' => $row['coefficient'],
            'credits'     => $row['credits'],
            'cc'          => $row['cc'],
            'exam'        => $row['exam'],
            'ratt'        => $row['ratt'],
            'moyenne'     => $moy,
        ];
        $resultats[$uid]['credits'] += $row['credits'];
        $credits_total += $row['credits'];
    }

    // Calculer moyenne UE
    $items_sem = [];
    foreach ($resultats as $uid => &$ue) {
        $items = array_map(fn($m) => ['moyenne' => $m['moyenne'], 'coeff' => $m['coefficient']], $ue['matieres']);
        $ue['moyenne'] = calculerMoyennePonderee($items);
        $coeff_ue = array_sum(array_column($ue['matieres'], 'coefficient'));
        $items_sem[] = ['moyenne' => $ue['moyenne'], 'coeff' => $coeff_ue];
    }
    unset($ue);

    // Moyenne semestre
    $moy_semestre = calculerMoyennePonderee($items_sem);

    // Acquis / compensation / crédits
    foreach ($resultats as $uid => &$ue) {
        if ($ue['moyenne'] !== null) {
            if ($ue['moyenne'] >= 10) {
                $ue['acquise'] = true;
            } elseif ($moy_semestre !== null && $moy_semestre >= 10) {
                $ue['acquise']  = true;
                $ue['compensee']= true;
            }
        }
        if ($ue['acquise']) $credits_acquis += $ue['credits'];
    }
    unset($ue);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Résultats — INPTIC</title>
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

        .container { max-width: 1100px; margin: 32px auto; padding: 0 24px; }

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
                               background: #fafbfc; }
        .btn-filter { padding: 11px 24px; background: linear-gradient(135deg, #0a2540, #1a5276);
                      color: white; border: none; border-radius: 10px; font-size: 14px;
                      font-weight: 700; cursor: pointer; }

        /* Stats */
        .stats { display: grid; grid-template-columns: repeat(4, 1fr);
                 gap: 16px; margin-bottom: 24px; }
        .stat { background: white; border-radius: 14px; padding: 20px 22px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.06); border-top: 4px solid #1a5276; }
        .stat.green  { border-top-color: #1abc9c; }
        .stat.orange { border-top-color: #f39c12; }
        .stat.purple { border-top-color: #8e44ad; }
        .stat .val { font-size: 28px; font-weight: 800; color: #0a2540; }
        .stat .lbl { font-size: 12px; color: #999; margin-top: 4px; }

        /* UE block */
        .ue-block { background: white; border-radius: 16px; margin-bottom: 20px;
                    box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
        .ue-header { background: linear-gradient(135deg, #0a2540, #1a5276);
                     padding: 14px 24px; display: flex; justify-content: space-between;
                     align-items: center; }
        .ue-left { display: flex; align-items: center; gap: 12px; }
        .ue-code { background: rgba(26,188,156,0.25); color: #1abc9c;
                   padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .ue-titre { color: white; font-size: 15px; font-weight: 700; }
        .ue-moy { display: flex; align-items: center; gap: 10px; }
        .badge-moy { padding: 6px 16px; border-radius: 20px; font-size: 14px;
                     font-weight: 800; }
        .badge-ok   { background: #eafaf1; color: #1e8449; }
        .badge-comp { background: #fef9e7; color: #d68910; }
        .badge-nok  { background: #fdecea; color: #c0392b; }
        .badge-nd   { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); }

        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 12px 16px; font-size: 11px; font-weight: 700;
                   color: #888; text-transform: uppercase; letter-spacing: 1px;
                   text-align: left; background: #f8f9fb; }
        .text-center { text-align: center; }
        tbody tr { border-bottom: 1px solid #f5f5f5; }
        tbody tr:hover { background: #f7faff; }
        tbody td { padding: 12px 16px; font-size: 14px; color: #333; }
        .td-matiere { font-weight: 600; color: #0a2540; }
        .note { font-weight: 700; }
        .note-ok  { color: #1e8449; }
        .note-nok { color: #c0392b; }
        .note-nd  { color: #ccc; }
        .moy-cell { font-weight: 800; font-size: 15px; }

        .empty-state { text-align: center; padding: 60px; color: #bbb; }
        .empty-state .icon { font-size: 52px; margin-bottom: 16px; }

        /* Décision */
        .decision-card { background: white; border-radius: 16px; padding: 28px;
                         box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-top: 24px;
                         display: flex; justify-content: space-between; align-items: center; }
        .decision-label { font-size: 14px; color: #999; margin-bottom: 6px; }
        .decision-value { font-size: 22px; font-weight: 800; }
        .dec-valide  { color: #1e8449; }
        .dec-invalid { color: #c0392b; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>Résultats</span></div>
    </div>
    <div class="nav-right">
        <a href="dashboard.php" class="btn-back">← Retour</a>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container">

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
            <div class="icon">📊</div>
            <p>Sélectionnez un étudiant pour voir ses résultats.</p>
        </div>

    <?php else: ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat">
                <div class="val"><?= $moy_semestre !== null ? number_format($moy_semestre, 2) : '–' ?></div>
                <div class="lbl">📊 Moyenne du semestre</div>
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
                <div class="val"><?= $credits_acquis >= $credits_total ? '✅' : '❌' ?></div>
                <div class="lbl">🎓 Semestre <?= $credits_acquis >= $credits_total ? 'validé' : 'non validé' ?></div>
            </div>
        </div>

        <!-- Résultats par UE -->
        <?php foreach ($resultats as $uid => $ue): ?>
        <div class="ue-block">
            <div class="ue-header">
                <div class="ue-left">
                    <span class="ue-code"><?= $ue['code'] ?></span>
                    <span class="ue-titre"><?= htmlspecialchars($ue['libelle']) ?></span>
                </div>
                <div class="ue-moy">
                    <?php if ($ue['moyenne'] !== null): ?>
                        <?php if ($ue['acquise'] && !$ue['compensee']): ?>
                            <span class="badge-moy badge-ok">✅ <?= number_format($ue['moyenne'], 2) ?>/20</span>
                            <span style="color:rgba(255,255,255,0.6); font-size:12px;">Acquise</span>
                        <?php elseif ($ue['compensee']): ?>
                            <span class="badge-moy badge-comp">⚡ <?= number_format($ue['moyenne'], 2) ?>/20</span>
                            <span style="color:rgba(255,255,255,0.6); font-size:12px;">Compensée</span>
                        <?php else: ?>
                            <span class="badge-moy badge-nok">❌ <?= number_format($ue['moyenne'], 2) ?>/20</span>
                            <span style="color:rgba(255,255,255,0.6); font-size:12px;">Non acquise</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge-moy badge-nd">– Non évaluée</span>
                    <?php endif; ?>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th class="text-center">Coeff.</th>
                        <th class="text-center">CC (40%)</th>
                        <th class="text-center">Examen (60%)</th>
                        <th class="text-center">Rattrapage</th>
                        <th class="text-center">Moyenne</th>
                        <th class="text-center">Crédits</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ue['matieres'] as $mat):
                    $moy = $mat['moyenne'];
                    $cls = $moy === null ? 'note-nd' : ($moy >= 10 ? 'note-ok' : 'note-nok');
                ?>
                <tr>
                    <td class="td-matiere"><?= htmlspecialchars($mat['libelle']) ?></td>
                    <td class="text-center">
                        <span style="background:#e8f4fd; color:#1a5276; padding:3px 10px;
                                     border-radius:20px; font-size:11px; font-weight:700;">
                            ×<?= $mat['coefficient'] ?>
                        </span>
                    </td>
                    <td class="text-center note <?= $mat['cc'] !== null ? ($mat['cc'] >= 10 ? 'note-ok' : 'note-nok') : 'note-nd' ?>">
                        <?= $mat['cc'] !== null ? number_format($mat['cc'], 2) : '–' ?>
                    </td>
                    <td class="text-center note <?= $mat['exam'] !== null ? ($mat['exam'] >= 10 ? 'note-ok' : 'note-nok') : 'note-nd' ?>">
                        <?= $mat['exam'] !== null ? number_format($mat['exam'], 2) : '–' ?>
                    </td>
                    <td class="text-center note <?= $mat['ratt'] !== null ? ($mat['ratt'] >= 10 ? 'note-ok' : 'note-nok') : 'note-nd' ?>">
                        <?= $mat['ratt'] !== null ? number_format($mat['ratt'], 2) : '–' ?>
                    </td>
                    <td class="text-center moy-cell <?= $cls ?>">
                        <?= $moy !== null ? number_format($moy, 2) : '–' ?>
                    </td>
                    <td class="text-center">
                        <span style="background:<?= $ue['acquise'] ? '#eafaf1' : '#fdecea' ?>;
                                     color:<?= $ue['acquise'] ? '#1e8449' : '#c0392b' ?>;
                                     padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700;">
                            <?= $ue['acquise'] ? $mat['credits'] : 0 ?>/<?= $mat['credits'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <!-- Décision finale -->
        <div class="decision-card">
            <div>
                <div class="decision-label">Étudiant</div>
                <div class="decision-value" style="color:#0a2540;">
                    <?= htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) ?>
                </div>
            </div>
            <div style="text-align:center;">
                <div class="decision-label">Moyenne semestre</div>
                <div class="decision-value" style="color:#1a5276;">
                    <?= $moy_semestre !== null ? number_format($moy_semestre, 2) . '/20' : '–' ?>
                </div>
            </div>
            <div style="text-align:center;">
                <div class="decision-label">Crédits acquis</div>
                <div class="decision-value" style="color:#d68910;">
                    <?= $credits_acquis ?>/<?= $credits_total ?>
                </div>
            </div>
            <div style="text-align:center;">
                <div class="decision-label">Mention</div>
                <div class="decision-value" style="color:<?= getCouleurMention($moy_semestre) ?>;">
                    <?= getMention($moy_semestre) ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div class="decision-label">Décision</div>
                <div class="decision-value <?= $credits_acquis >= $credits_total ? 'dec-valide' : 'dec-invalid' ?>">
                    <?= $credits_acquis >= $credits_total ? '✅ Semestre validé' : '❌ Non validé' ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>
</body>
</html>