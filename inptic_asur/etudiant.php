<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'etudiant') {
    header('Location: login.php');
    exit();
}

$id_etudiant = $_SESSION['user_id'];

// infos étudiant (utilise $db au lieu de $pdo)
$stmt = $db->prepare("SELECT * FROM etudiant WHERE id = ?");
$stmt->execute([$id_etudiant]);
$etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

// notes avec les bonnes tables
$sql = "SELECT m.libelle as nom_matiere, ev.note, ev.type_eval
        FROM evaluation ev
        JOIN matiere m ON ev.matiere_id = m.id
        WHERE ev.etudiant_id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$id_etudiant]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des moyennes par matière (CC 40% + Examen 60%)
$matieres_notes = [];
foreach ($notes as $n) {
    $matiere = $n['nom_matiere'];
    $type = $n['type_eval'];
    $note = $n['note'];
    
    if (!isset($matieres_notes[$matiere])) {
        $matieres_notes[$matiere] = ['cc' => null, 'examen' => null, 'rattrapage' => null];
    }
    
    if ($type == 'CC') $matieres_notes[$matiere]['cc'] = $note;
    elseif ($type == 'Examen') $matieres_notes[$matiere]['examen'] = $note;
    elseif ($type == 'Rattrapage') $matieres_notes[$matiere]['rattrapage'] = $note;
}

// Calcul des moyennes finales par matière
$moyennes_matieres = [];
$total_notes = 0;
$nb_matieres = 0;

foreach ($matieres_notes as $matiere => $notes_mat) {
    $ratt = $notes_mat['rattrapage'];
    $cc = $notes_mat['cc'];
    $exam = $notes_mat['examen'];
    
    if ($ratt !== null) {
        $moyenne = $ratt;
    } elseif ($cc !== null && $exam !== null) {
        $moyenne = round($cc * 0.4 + $exam * 0.6, 2);
    } elseif ($cc !== null) {
        $moyenne = $cc;
    } elseif ($exam !== null) {
        $moyenne = $exam;
    } else {
        $moyenne = null;
    }
    
    if ($moyenne !== null) {
        $moyennes_matieres[] = ['matiere' => $matiere, 'moyenne' => $moyenne];
        $total_notes += $moyenne;
        $nb_matieres++;
    }
}

$moyenne_generale = ($nb_matieres > 0) ? $total_notes / $nb_matieres : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Espace Étudiant — INPTIC</title>
    <style>
        body{
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .navbar {
            background: linear-gradient(135deg, #0a2540, #1a5276);
            padding: 0 30px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            margin: -20px -20px 20px -20px;
        }
        .nav-title { color: white; font-size: 18px; font-weight: 700; }
        .nav-title span { color: #1abc9c; }
        .nav-user { color: rgba(255,255,255,0.85); font-size: 14px; }
        .btn-logout {
            background: rgba(231,76,60,0.15);
            color: #e74c3c;
            border: 1px solid rgba(231,76,60,0.3);
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }
        .container{
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        h2{
            text-align: center;
            color: #0a2540;
            margin-bottom: 25px;
        }
        .info{
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .info p{
            margin: 5px 0;
            color: #555;
        }
        .info strong{
            color: #0a2540;
        }
        table{
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td{
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        th{
            background: #0a2540;
            color: white;
        }
        tr:hover{
            background: #f5f5f5;
        }
        .moyenne{
            margin-top: 25px;
            padding: 15px;
            background: #eafaf1;
            border-radius: 10px;
            text-align: center;
            font-size: 18px;
        }
        .admis{
            color: #27ae60;
            font-weight: bold;
        }
        .non-admis{
            color: #e74c3c;
            font-weight: bold;
        }
        .footer{
            text-align: center;
            margin-top: 20px;
            color: #999;
            font-size: 12px;
        }
        .btn-back {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #1a5276;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
        }
        .btn-back:hover {
            background: #0a2540;
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="nav-title">INPTIC — <span>Espace Étudiant</span></div>
    <div>
        <span class="nav-user">👤 <?= htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']) ?></span>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</div>

<div class="container">

    <h2>📊 Mes notes et résultats</h2>

    <div class="info">
        <p><strong>👨‍🎓 Nom :</strong> <?= htmlspecialchars($etudiant['nom'] ?? $_SESSION['nom']) ?></p>
        <p><strong>📛 Prénom :</strong> <?= htmlspecialchars($etudiant['prenom'] ?? $_SESSION['prenom']) ?></p>
        <p><strong>🏫 Filière :</strong> LP ASUR — Administration et Sécurité des Réseaux</p>
    </div>

    <?php if (empty($moyennes_matieres)): ?>
        <p style="text-align: center; color: #999;">Aucune note disponible pour le moment.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Matière</th>
                    <th>Moyenne /20</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($moyennes_matieres as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['matiere']) ?></td>
                    <td><?= number_format($m['moyenne'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="moyenne">
        📈 Moyenne générale : <strong><?= number_format($moyenne_generale, 2) ?> / 20</strong><br><br>

        <?php if ($moyenne_generale >= 10): ?>
            <span class="admis">✅ Admis</span>
        <?php else: ?>
            <span class="non-admis">❌ Non admis</span>
        <?php endif; ?>
    </div>

    <a href="dashboard.php" class="btn-back">← Retour au tableau de bord</a>

</div>

<div class="footer">
    Institut National de la Poste, des Technologies de l'Information et de la Communication © 2026
</div>

</body>
</html>