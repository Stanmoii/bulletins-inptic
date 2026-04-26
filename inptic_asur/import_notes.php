<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretariat'])) {
    die("Accès refusé");
}

$message = "";

if (isset($_POST['import'])) {

    if (!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
        $message = "Erreur lors de l'upload du fichier.";
    } else {

        $file = $_FILES['file']['tmp_name'];
        $handle = fopen($file, "r");

        if ($handle) {

            $row = 0;

            // STRUCTURE TEMPORAIRE POUR REGROUPER LES NOTES
            $notes = [];

            while (($data = fgetcsv($handle, 1000, ",")) !== false) {

                if ($row == 0) { $row++; continue; }

                $matricule = trim($data[0]);
                $matiere   = trim($data[1]);
                $type      = strtoupper(trim($data[2])); // CC / EXAM / RATTRAPAGE
                $note      = floatval($data[3]);

                if ($note < 0 || $note > 20) continue;

                // récupérer étudiant
                $stmt = $db->prepare("SELECT id FROM etudiant WHERE matricule=?");
                $stmt->execute([$matricule]);
                $etudiant = $stmt->fetch();

                // récupérer matière
                $stmt = $db->prepare("SELECT id FROM matiere WHERE code=?");
                $stmt->execute([$matiere]);
                $matiereData = $stmt->fetch();

                if ($etudiant && $matiereData) {

                    $eid = $etudiant['id'];
                    $mid = $matiereData['id'];

                    // on stocke temporairement
                    $notes[$eid][$mid][$type] = $note;
                }
            }

            fclose($handle);

            // =========================
            // CALCUL AUTOMATIQUE
            // =========================
            foreach ($notes as $etudiant_id => $matieres) {

                foreach ($matieres as $matiere_id => $types) {

                    $cc = $types['CC'] ?? null;
                    $exam = $types['EXAM'] ?? null;
                    $rat = $types['RATTRAPAGE'] ?? null;

                    // LOGIQUE CAHIER DES CHARGES
                    if ($rat !== null) {
                        $moyenne = $rat;
                    } else if ($cc !== null && $exam !== null) {
                        $moyenne = ($cc * 0.4) + ($exam * 0.6);
                    } else {
                        $moyenne = $cc ?? $exam;
                    }

                    if ($moyenne < 0 || $moyenne > 20) continue;

                    // ENREGISTREMENT OU MISE À JOUR MOYENNE MATIERE
                    $stmt = $db->prepare("
                        INSERT INTO moyenne_matiere (etudiant_id, matiere_id, moyenne)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE moyenne = VALUES(moyenne)
                    ");

                    $stmt->execute([
                        $etudiant_id,
                        $matiere_id,
                        $moyenne
                    ]);
                }
            }

            $message = "Import + calcul des moyennes terminé avec succès ✔️";

        } else {
            $message = "Impossible de lire le fichier.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Import des notes — INPTIC</title>

<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family:'Segoe UI', sans-serif;
    background:#f0f2f5;
}

.navbar {
    background: linear-gradient(135deg,#0a2540,#1a5276);
    height:64px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:0 30px;
}

.nav-title { color:white; font-weight:700; }
.nav-title span { color:#1abc9c; }

.nav-btn {
    color:white;
    text-decoration:none;
    padding:7px 14px;
    border-radius:8px;
    background:rgba(255,255,255,0.1);
}

.container { max-width:900px; margin:40px auto; padding:0 20px; }

.card {
    background:white;
    border-radius:16px;
    padding:35px;
}

h2 { color:#0a2540; margin-bottom:20px; }

input[type=file] {
    width:100%;
    padding:12px;
    margin-bottom:15px;
}

button {
    width:100%;
    padding:12px;
    background:#0a2540;
    color:white;
    border:none;
    border-radius:10px;
    cursor:pointer;
}

.msg {
    margin-top:15px;
    padding:12px;
    background:#eafaf1;
    color:#1e8449;
    text-align:center;
}
</style>
</head>

<body>

<div class="navbar">
    <div class="nav-title">
        INPTIC — <span>Import des notes</span>
    </div>
    <a class="nav-btn" href="dashboard.php">← Retour</a>
</div>

<div class="container">
    <div class="card">

        <h2>📥 Import des notes</h2>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <button type="submit" name="import">Importer + Calculer</button>
        </form>

        <?php if ($message): ?>
            <div class="msg"><?= $message ?></div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>