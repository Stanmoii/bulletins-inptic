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

// Calcul moyenne de classe pour une matière
function getMoyenneClasse($db, $matiere_id, $semestre_id) {
    $stmt = $db->prepare("
        SELECT AVG(
            CASE
                WHEN r.note IS NOT NULL THEN r.note
                WHEN cc.note IS NOT NULL AND ex.note IS NOT NULL THEN cc.note * 0.4 + ex.note * 0.6
                WHEN cc.note IS NOT NULL THEN cc.note
                WHEN ex.note IS NOT NULL THEN ex.note
                ELSE NULL
            END
        ) as moy_classe
        FROM etudiant et
        LEFT JOIN evaluation r  ON r.etudiant_id  = et.id AND r.matiere_id  = ? AND r.type_eval  = 'Rattrapage'
        LEFT JOIN evaluation cc ON cc.etudiant_id = et.id AND cc.matiere_id = ? AND cc.type_eval = 'CC'
        LEFT JOIN evaluation ex ON ex.etudiant_id = et.id AND ex.matiere_id = ? AND ex.type_eval = 'Examen'
    ");
    $stmt->execute([$matiere_id, $matiere_id, $matiere_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['moy_classe'] !== null ? round($row['moy_classe'], 2) : null;
}

$etudiants = $db->query("SELECT id, nom, prenom FROM etudiant ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$semestres = $db->query("SELECT * FROM semestre ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$etudiant_sel = intval($_GET['etudiant_id'] ?? 0);
$semestre_sel = intval($_GET['semestre_id'] ?? 1);

$etudiant  = null;
$resultats = [];
$moy_semestre   = null;
$credits_acquis = 0;
$credits_total  = 0;
$total_absences = 0;

if ($etudiant_sel) {
    $stmt = $db->prepare("SELECT * FROM etudiant WHERE id = ?");
    $stmt->execute([$etudiant_sel]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "
        SELECT ue.id as ue_id, ue.code, ue.libelle as ue_libelle,
               m.id as mat_id, m.libelle as mat_libelle,
               m.coefficient, m.credits,
               MAX(CASE WHEN e.type_eval='CC'         THEN e.note END) as cc,
               MAX(CASE WHEN e.type_eval='Examen'     THEN e.note END) as exam,
               MAX(CASE WHEN e.type_eval='Rattrapage' THEN e.note END) as ratt,
               COALESCE(a.heures, 0) as absences
        FROM ue
        JOIN matiere m ON m.ue_id = ue.id
        LEFT JOIN evaluation e ON e.matiere_id = m.id AND e.etudiant_id = ?
        LEFT JOIN absence a    ON a.matiere_id = m.id AND a.etudiant_id = ?
        WHERE ue.semestre_id = ?
        GROUP BY ue.id, ue.code, ue.libelle, m.id, m.libelle, m.coefficient, m.credits, a.heures
        ORDER BY ue.id, m.libelle
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$etudiant_sel, $etudiant_sel, $semestre_sel]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $uid = $row['ue_id'];
        if (!isset($resultats[$uid])) {
            $resultats[$uid] = [
                'code' => $row['code'], 'libelle' => $row['ue_libelle'],
                'matieres' => [], 'moyenne' => null, 'moy_classe' => null,
                'acquise' => false, 'compensee' => false,
                'credits' => 0, 'coeff_total' => 0
            ];
        }
        $moy = calculerMoyenneMatiere($row['cc'], $row['exam'], $row['ratt']);
        $moy_classe = getMoyenneClasse($db, $row['mat_id'], $semestre_sel);
        $total_absences += $row['absences'];
        $resultats[$uid]['matieres'][] = [
            'libelle' => $row['mat_libelle'], 'coefficient' => $row['coefficient'],
            'credits' => $row['credits'], 'cc' => $row['cc'],
            'exam' => $row['exam'], 'ratt' => $row['ratt'],
            'moyenne' => $moy, 'moy_classe' => $moy_classe,
            'absences' => $row['absences']
        ];
        $resultats[$uid]['credits']     += $row['credits'];
        $resultats[$uid]['coeff_total'] += $row['coefficient'];
        $credits_total += $row['credits'];
    }

    // Moyennes UE
    $items_sem = [];
    foreach ($resultats as $uid => &$ue) {
        $items = array_map(fn($m) => ['moyenne' => $m['moyenne'], 'coeff' => $m['coefficient']], $ue['matieres']);
        $ue['moyenne'] = calculerMoyennePonderee($items);
        $items_classe  = array_map(fn($m) => ['moyenne' => $m['moy_classe'], 'coeff' => $m['coefficient']], $ue['matieres']);
        $ue['moy_classe'] = calculerMoyennePonderee($items_classe);
        $items_sem[] = ['moyenne' => $ue['moyenne'], 'coeff' => $ue['coeff_total']];
    }
    unset($ue);

    $moy_semestre = calculerMoyennePonderee($items_sem);

    // Rang dans la promotion
    $tous = [];
    foreach ($etudiants as $e) {
        $stmt2 = $db->prepare("
            SELECT AVG(CASE WHEN ev.type_eval='Rattrapage' THEN ev.note
                            WHEN ev.type_eval='CC' THEN ev.note * 0.4
                            ELSE ev.note * 0.6 END) as moy
            FROM evaluation ev
            JOIN matiere m ON m.id = ev.matiere_id
            JOIN ue ON ue.id = m.ue_id
            WHERE ev.etudiant_id = ? AND ue.semestre_id = ?
        ");
        $stmt2->execute([$e['id'], $semestre_sel]);
        $r = $stmt2->fetch(PDO::FETCH_ASSOC);
        $tous[] = ['id' => $e['id'], 'moy' => $r['moy']];
    }
    usort($tous, fn($a, $b) => ($b['moy'] ?? -1) <=> ($a['moy'] ?? -1));
    $rang = 'N/C';
    foreach ($tous as $i => $t) {
        if ($t['id'] == $etudiant_sel) { $rang = ($i + 1) . '/' . count($tous); break; }
    }

    // Acquis / compensation
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
}

$sem_label = $semestre_sel == 1 ? '5' : '6';
$valide    = $credits_acquis >= $credits_total && $credits_total > 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin S<?= $sem_label ?> — INPTIC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: #e0e0e0;
            font-size: 11px;
        }

        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .bulletin { box-shadow: none; margin: 0; width: 100%; }
            @page { margin: 1cm; }
        }

        /* ── Barre de contrôle ── */
        .topbar {
            background: linear-gradient(135deg, #0a2540, #1a5276);
            padding: 14px 30px; display: flex;
            justify-content: space-between; align-items: center;
        }
        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .topbar-logo { width: 38px; height: 38px; background: white; border-radius: 6px;
                       padding: 3px; display: flex; align-items: center; justify-content: center; }
        .topbar-logo img { width: 100%; object-fit: contain; }
        .topbar-title { color: white; font-size: 15px; font-weight: bold; }
        .topbar-title span { color: #1abc9c; }
        .topbar-right { display: flex; gap: 10px; align-items: center; }
        .btn { padding: 8px 18px; border-radius: 8px; font-size: 13px;
               font-weight: bold; cursor: pointer; border: none; text-decoration: none; }
        .btn-back  { background: rgba(255,255,255,0.15); color: white; }
        .btn-print { background: #1abc9c; color: white; }

        /* ── Formulaire de sélection ── */
        .selector {
            background: white; margin: 20px auto; padding: 18px 24px;
            max-width: 800px; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;
        }
        .sel-group { flex: 1; min-width: 180px; }
        .sel-group label { display: block; font-size: 10px; font-weight: bold;
                           color: #888; text-transform: uppercase; letter-spacing: 1px;
                           margin-bottom: 6px; }
        .sel-group select { width: 100%; padding: 9px 12px; border: 2px solid #e0e0e0;
                            border-radius: 8px; font-size: 13px; outline: none; }
        .btn-show { padding: 9px 20px; background: #0a2540; color: white;
                    border: none; border-radius: 8px; font-size: 13px;
                    font-weight: bold; cursor: pointer; }

        /* ── BULLETIN OFFICIEL ── */
        .bulletin {
            width: 800px; margin: 0 auto 40px;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 24px 32px;
        }

        /* En-tête */
        .bul-entete {
            display: flex; justify-content: space-between;
            align-items: flex-start; margin-bottom: 6px;
        }
        .bul-gauche { text-align: center; font-size: 10px; line-height: 1.6; }
        .bul-gauche strong { font-size: 10.5px; }
        .bul-gauche img { width: 80px; margin: 4px 0; }
        .bul-droite { text-align: center; font-size: 10px; line-height: 1.8; }
        .bul-droite strong { font-size: 11px; }

        .sep-pointille { border: none; border-top: 1px dashed #999; margin: 6px 0; }

        .bul-titre {
            text-align: center; margin: 10px 0 4px;
        }
        .bul-titre h2 { font-size: 15px; font-weight: bold; }
        .bul-titre p  { font-size: 11px; color: #444; }

        .bul-classe {
            border: 1px solid #333; padding: 5px 12px;
            font-size: 11px; margin: 8px 0;
        }
        .bul-classe strong { font-size: 11.5px; }

        /* Infos étudiant */
        .bul-infos { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .bul-infos td { border: 1px solid #555; padding: 4px 10px; font-size: 11px; }
        .bul-infos td:first-child { width: 40%; background: #f5f5f5; }
        .bul-infos td:last-child { font-weight: bold; }

        /* Tableau des notes */
        .bul-notes { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 10.5px; }
        .bul-notes th, .bul-notes td { border: 1px solid #888; padding: 4px 6px; text-align: center; }
        .bul-notes th { background: #d0d0d0; font-size: 10px; }
        .bul-notes td.td-left { text-align: left; padding-left: 8px; }
        .bul-notes tr.ue-row td { background: #e8e8e8; font-weight: bold; text-align: left;
                                   padding-left: 6px; font-size: 11px; }
        .bul-notes tr.moy-row td { background: #f0f0f0; font-weight: bold; font-size: 11px; }
        .bul-notes tr.moy-row td:nth-child(3) { background: #ffeb99; }
        .bul-notes .note-etudiant { font-weight: bold; }

        /* Ligne absences */
        .bul-abs { width: 100%; border-collapse: collapse; margin-top: 0; font-size: 10.5px; }
        .bul-abs td { border: 1px solid #888; padding: 3px 6px; }

        /* Résumé semestre */
        .bul-resume { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 10.5px; }
        .bul-resume td { border: 1px solid #888; padding: 4px 8px; text-align: center; }
        .bul-resume .highlight { background: #ffeb99; font-weight: bold; }
        .bul-resume .rang-cell { background: #f0f0f0; }

        /* État validation crédits */
        .bul-credits { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10.5px; }
        .bul-credits th { border: 1px solid #888; padding: 4px 8px; background: #e8e8e8;
                          font-size: 10px; text-align: center; }
        .bul-credits td { border: 1px solid #888; padding: 4px 8px; text-align: center; }
        .bul-credits td.ok   { color: #1a7a3c; font-weight: bold; }
        .bul-credits td.comp { color: #d68910; font-weight: bold; }
        .bul-credits td.nok  { color: #c0392b; font-weight: bold; }

        /* Décision */
        .bul-decision { margin-top: 12px; font-size: 12px; }
        .bul-decision strong { font-size: 13px; }

        /* Signatures */
        .bul-signatures {
            display: flex; justify-content: space-between;
            margin-top: 30px; font-size: 10.5px;
        }
        .sig { text-align: center; }
        .sig p { margin-bottom: 30px; }

        .empty-state {
            text-align: center; padding: 60px; color: #bbb;
            background: white; max-width: 800px; margin: 20px auto;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<!-- Barre de contrôle -->
<div class="topbar no-print">
    <div class="topbar-left">
        <div class="topbar-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="topbar-title">INPTIC — <span>Bulletins de Notes</span></div>
    </div>
    <div class="topbar-right">
        <a href="dashboard.php" class="btn btn-back">← Retour</a>
        <?php if ($etudiant_sel): ?>
            <button onclick="window.print()" class="btn btn-print">🖨️ Imprimer</button>
        <?php endif; ?>
    </div>
</div>

<!-- Sélecteur -->
<form method="GET" class="selector no-print">
    <div class="sel-group">
        <label>Étudiant</label>
        <select name="etudiant_id">
            <option value="">-- Sélectionner --</option>
            <?php foreach ($etudiants as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $etudiant_sel == $e['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sel-group">
        <label>Semestre</label>
        <select name="semestre_id">
            <?php foreach ($semestres as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $semestre_sel == $s['id'] ? 'selected' : '' ?>>
                    <?= $s['libelle'] ?> — <?= $s['annee_univ'] ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn-show">Générer →</button>
</form>

<?php if (!$etudiant_sel): ?>
    <div class="empty-state">
        <div style="font-size:48px; margin-bottom:12px;">📄</div>
        <p>Sélectionnez un étudiant et un semestre pour générer le bulletin officiel.</p>
    </div>

<?php elseif ($etudiant): ?>

<!-- ══════════════════════════════════════════
     BULLETIN OFFICIEL
══════════════════════════════════════════ -->
<div class="bulletin">

    <!-- En-tête -->
    <div class="bul-entete">
        <div class="bul-gauche">
            <strong>INSTITUT NATIONAL DE LA POSTE, DES TECHNOLOGIES<br>
            DE L'INFORMATION ET DE LA COMMUNICATION</strong><br>
            <img src="logo_inptic.png" alt="INPTIC"><br>
            <strong>DIRECTION DES ÉTUDES ET DE LA PÉDAGOGIE</strong>
        </div>
        <div class="bul-droite">
            <strong>RÉPUBLIQUE GABONAISE</strong><br>
            - - - - - - - - - - - - - -<br>
            Union - Travail - Justice<br>
            - - - - - - - - - - - - - -
        </div>
    </div>

    <hr class="sep-pointille">

    <!-- Titre -->
    <div class="bul-titre">
        <h2>Bulletin de notes du Semestre <?= $sem_label ?></h2>
        <p>Année universitaire : <?= htmlspecialchars($semestres[$semestre_sel-1]['annee_univ'] ?? '2025-2026') ?></p>
    </div>

    <!-- Classe -->
    <div class="bul-classe">
        <strong>Classe :</strong> Licence Professionnelle Réseaux et Télécommunications
        <strong>Option Administration et Sécurité des Réseaux (ASUR)</strong>
    </div>

    <!-- Infos étudiant -->
    <table class="bul-infos">
        <tr>
            <td>Nom(s) et Prénom(s)</td>
            <td><?= htmlspecialchars(strtoupper($etudiant['nom']) . ' ' . $etudiant['prenom']) ?></td>
        </tr>
        <tr>
            <td>Date et lieu de naissance</td>
            <td>
                Né(e) le <?= $etudiant['date_naissance'] ? date('d/m/Y', strtotime($etudiant['date_naissance'])) : '–' ?>
                <?= $etudiant['lieu_naissance'] ? ' à ' . htmlspecialchars($etudiant['lieu_naissance']) : '' ?>
            </td>
        </tr>
    </table>

    <!-- Tableau des notes -->
    <table class="bul-notes">
        <thead>
            <tr>
                <th style="width:38%; text-align:left; padding-left:6px;"></th>
                <th style="width:7%;">Crédits</th>
                <th style="width:9%;">Coefficients</th>
                <th style="width:14%;">Notes de l'étudiant</th>
                <th style="width:14%;">Moyenne de classe</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $coeff_sem_total = 0;
        foreach ($resultats as $uid => $ue):
            $coeff_ue = array_sum(array_column($ue['matieres'], 'coefficient'));
            $coeff_sem_total += $coeff_ue;
        ?>
            <!-- Ligne UE -->
            <tr class="ue-row">
                <td colspan="5">
                    <?= htmlspecialchars($ue['code']) ?> : <?= strtoupper(htmlspecialchars($ue['libelle'])) ?>
                </td>
            </tr>

            <!-- Matières -->
            <?php foreach ($ue['matieres'] as $mat):
                $moy = $mat['moyenne'];
                $cls = $moy === null ? '' : ($moy >= 10 ? '' : 'color:#c0392b;');
            ?>
            <tr>
                <td class="td-left"><?= htmlspecialchars($mat['libelle']) ?></td>
                <td><?= $mat['credits'] ?></td>
                <td><?= number_format($mat['coefficient'], 2) ?></td>
                <td class="note-etudiant" style="<?= $cls ?>">
                    <?= $moy !== null ? number_format($moy, 2) : '' ?>
                </td>
                <td><?= $mat['moy_classe'] !== null ? number_format($mat['moy_classe'], 2) : '' ?></td>
            </tr>
            <?php endforeach; ?>

            <!-- Moyenne UE -->
            <tr class="moy-row">
                <td class="td-left" style="padding-left:20px;">Moyenne <?= $ue['code'] ?></td>
                <td><?= $ue['credits'] ?></td>
                <td><?= number_format($coeff_ue, 2) ?></td>
                <td><?= $ue['moyenne'] !== null ? number_format($ue['moyenne'], 2) : '' ?></td>
                <td><?= $ue['moy_classe'] !== null ? number_format($ue['moy_classe'], 2) : '' ?></td>
            </tr>

        <?php endforeach; ?>

            <!-- Total crédits -->
            <tr>
                <td class="td-left"></td>
                <td><?= $credits_total ?>,00</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- Absences -->
    <table class="bul-abs">
        <tr>
            <td style="width:40%;">Pénalités d'absences</td>
            <td style="width:20%; color:#c0392b;">0,01/heure</td>
            <td><?= $total_absences ?> heure(s)</td>
        </tr>
    </table>

    <!-- Résumé semestre -->
    <?php
    $moy_classe_sem = null;
    $moys = [];
    foreach ($etudiants as $e) {
        $stmt3 = $db->prepare("
            SELECT ue.id as uid, m.coefficient,
                   MAX(CASE WHEN ev.type_eval='CC' THEN ev.note END) as cc,
                   MAX(CASE WHEN ev.type_eval='Examen' THEN ev.note END) as exam,
                   MAX(CASE WHEN ev.type_eval='Rattrapage' THEN ev.note END) as ratt
            FROM ue JOIN matiere m ON m.ue_id=ue.id
            LEFT JOIN evaluation ev ON ev.matiere_id=m.id AND ev.etudiant_id=?
            WHERE ue.semestre_id=?
            GROUP BY ue.id, m.id, m.coefficient
        ");
        $stmt3->execute([$e['id'], $semestre_sel]);
        $rr = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        $items3 = [];
        foreach ($rr as $r3) {
            $m3 = calculerMoyenneMatiere($r3['cc'], $r3['exam'], $r3['ratt']);
            $items3[] = ['moyenne' => $m3, 'coeff' => $r3['coefficient']];
        }
        $m3 = calculerMoyennePonderee($items3);
        if ($m3 !== null) $moys[] = $m3;
    }
    $moy_classe_sem = count($moys) > 0 ? round(array_sum($moys) / count($moys), 2) : null;
    ?>
    <table class="bul-resume">
        <tr>
            <td colspan="2" style="text-align:center; font-weight:bold;">
                Moyenne Semestre <?= $sem_label ?>
            </td>
            <td class="highlight"><?= $moy_semestre !== null ? number_format($moy_semestre, 2) : '–' ?></td>
            <td><?= $moy_classe_sem !== null ? number_format($moy_classe_sem, 2) : '–' ?></td>
        </tr>
        <tr>
            <td class="rang-cell">Rang de l'étudiant au Semestre</td>
            <td><?= $rang ?></td>
            <td>Mention</td>
            <td><?= getMention($moy_semestre) ?></td>
        </tr>
    </table>

    <!-- État validation crédits -->
    <br>
    <table class="bul-credits">
        <thead>
            <tr>
                <th colspan="<?= count($resultats) + 2 ?>">
                    État de la Validation des Crédits au Semestre <?= $sem_label ?>
                </th>
            </tr>
            <tr>
                <?php foreach ($resultats as $uid => $ue): ?>
                    <th><?= $ue['code'] ?></th>
                <?php endforeach; ?>
                <th>Crédits validés au Semestre <?= $sem_label ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php foreach ($resultats as $uid => $ue): ?>
                    <td class="<?= $ue['acquise'] ? 'ok' : 'nok' ?>">
                        <?= $ue['acquise'] ? $ue['credits'] : 0 ?> Crédits / <?= $ue['credits'] ?>
                    </td>
                <?php endforeach; ?>
                <td class="<?= $credits_acquis >= $credits_total ? 'ok' : 'nok' ?>" style="font-weight:bold;">
                    <?= $credits_acquis ?> Crédits /<?= $credits_total ?>
                </td>
            </tr>
            <tr>
                <?php foreach ($resultats as $uid => $ue): ?>
                    <td class="<?= $ue['compensee'] ? 'comp' : ($ue['acquise'] ? 'ok' : 'nok') ?>">
                        <?php if ($ue['compensee']): ?>
                            UE Acquise par Compensation
                        <?php elseif ($ue['acquise']): ?>
                            UE Acquise
                        <?php else: ?>
                            UE non Acquise
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
                <td class="<?= $valide ? 'ok' : 'nok' ?>">
                    <?= $valide ? 'Semestre Acquis' : 'Semestre non Acquis' ?>
                    <?php if (!$valide && $moy_semestre >= 10): ?>
                        par Compensation
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Décision -->
    <div class="bul-decision">
        <strong>Décision du Jury :</strong>
        <strong style="color:<?= $valide ? '#1a7a3c' : '#c0392b' ?>; margin-left:10px;">
            <?= $valide ? 'Semestre ' . $sem_label . ' validé' : 'Semestre ' . $sem_label . ' non validé' ?>
        </strong>
    </div>

    <hr class="sep-pointille" style="margin-top:16px;">

    <!-- Date et signatures -->
    <div style="text-align:center; margin-top:10px; font-size:11px;">
        Fait à Libreville, le <?= date('j F Y') ?><br><br>
        <strong>Le Directeur des Études et de la Pédagogie</strong>
        <br><br><br><br>
        <strong>Davy Edgard MOUSSAVOU</strong>
    </div>

</div>

<?php endif; ?>
</body>
</html>