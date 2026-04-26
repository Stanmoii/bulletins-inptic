<?php
// --- CONFIGURATION DE LA CONNEXION ---
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "gestion_notes_inptic";

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// --- LOGIQUE D'ENREGISTREMENT ---
$message = "";
if (isset($_POST['enregistrer'])) {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $date_n = $_POST['date_n'];

    $sql = "INSERT INTO etudiant (nom, prenom, date_naissance) VALUES (:nom, :prenom, :date_n)";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute([':nom' => $nom, ':prenom' => $prenom, ':date_n' => $date_n])) {
        $message = "<p style='color:green; text-align:center;'>✅ Étudiant ajouté avec succès !</p>";
    } else {
        $message = "<p style='color:red; text-align:center;'>❌ Erreur lors de l'ajout.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription Étudiant</title>
    <style>
        /* Centrage complet de la page */
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 0.9em;
        }
        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box; /* Important pour que le padding ne dépasse pas */
        }
        button {
            background-color: #28a745;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
        }
        button:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Inscription Étudiant</h2>
    
    <?php echo $message; ?>
    
    <form method="POST">
        <label>Nom</label>
        <input type="text" name="nom" placeholder="Nom de l'élève" required>

        <label>Prénom</label>
        <input type="text" name="prenom" placeholder="Prénom de l'élève" required>

        <label>Date de Naissance</label>
        <input type="date" name="date_n" required>

        <button type="submit" name="enregistrer">Enregistrer</button>
    </form>
</div>

</body>
</html>