<?php
require 'connexion.php';

$message = "";

if (isset($_POST['inscription'])) {

    $nom     = trim($_POST['nom']);
    $prenom  = trim($_POST['prenom']);
    $login   = trim($_POST['login']);
    $password = $_POST['password'];
    $role    = "etudiant"; // par défaut

    // Vérifier si login existe déjà
    $check = $db->prepare("SELECT id FROM utilisateur WHERE login = ?");
    $check->execute([$login]);

    if ($check->rowCount() > 0) {
        $message = "Ce login existe déjà.";
    } else {

        // hash du mot de passe
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO utilisateur (nom, prenom, login, password_hash, role, actif)
            VALUES (?, ?, ?, ?, ?, 1)
        ");

        if ($stmt->execute([$nom, $prenom, $login, $hash, $role])) {
            $message = "Compte créé avec succès. Vous pouvez vous connecter.";
        } else {
            $message = "Erreur lors de la création du compte.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Inscription</title>

<style>
body{
    font-family: Arial;
    background: linear-gradient(135deg,#0a2540,#1a5276);
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.container{
    background:white;
    padding:40px;
    border-radius:12px;
    width:400px;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
}

h2{
    text-align:center;
    margin-bottom:20px;
}

input{
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    background:#0a2540;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

button:hover{
    background:#1a5276;
}

.msg{
    text-align:center;
    margin-bottom:10px;
    color:red;
}
</style>

</head>
<body>

<div class="container">

<h2>Créer un compte</h2>

<?php if ($message): ?>
    <div class="msg"><?= $message ?></div>
<?php endif; ?>

<form method="POST">

<input type="text" name="nom" placeholder="Nom" required>
<input type="text" name="prenom" placeholder="Prénom" required>
<input type="text" name="login" placeholder="Login" required>
<input type="password" name="password" placeholder="Mot de passe" required>

<button type="submit" name="inscription">S'inscrire</button>

</form>

</div>

</body>
</html>