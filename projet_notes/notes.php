<?php
$host = "localhost"; $user = "root"; $pass = ""; $dbname = "gestion_notes_inptic";
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) { die("Erreur : " . $e->getMessage()); }

$msg = "";
if (isset($_POST['valider'])) {
    $sql = "INSERT INTO evaluation (etudiant_id, matiere_id, type_eval, note) VALUES (?, ?, ?, ?)";
    $db->prepare($sql)->execute([$_POST['etudiant'], $_POST['matiere'], $_POST['type'], $_POST['note']]);
    $msg = "<p style='color:green; text-align:center;'>Note enregistrée !</p>";
}

// On récupère les étudiants et les matières pour les listes déroulantes
$etudiants = $db->query("SELECT id, nom, prenom FROM etudiant")->fetchAll();
$matieres = $db->query("SELECT id, libelle FROM matiere")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Saisie des Notes</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f0f2f5; }
        .box { background: white; padding: 30px; border-radius: 10px; shadow: 0 4px 10px rgba(0,0,0,0.1); width: 400px; }
        select, input, button { width: 100%; padding: 10px; margin: 10px 0; border-radius: 5px; border: 1px solid #ccc; }
        button { background: #007bff; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Saisie des Notes</h2>
        <?php echo $msg; ?>
        <form method="POST">
            <label>Étudiant :</label>
            <select name="etudiant">
                <?php foreach($etudiants as $e) echo "<option value='".$e['id']."'>".$e['nom']." ".$e['prenom']."</option>"; ?>
            </select>

            <label>Matière :</label>
            <select name="matiere">
                <?php foreach($matieres as $m) echo "<option value='".$m['id']."'>".$m['libelle']."</option>"; ?>
            </select>

            <label>Type :</label>
            <select name="type">
                <option value="CC">Contrôle Continu (CC)</option>
                <option value="Examen">Examen</option>
            </select>

            <label>Note :</label>
            <input type="number" step="0.01" name="note" min="0" max="20" required>

            <button type="submit" name="valider">Enregistrer la note</button>
        </form>
        <br>
        <a href="index.php">Retour à l'inscription</a>
    </div>
</body>
</html>