<?php
require 'connexion.php';

$login = 'dabessolo1'; // change ici
$password = 'etudiant123';

$stmt = $db->prepare("SELECT * FROM utilisateur WHERE login = ? AND actif = 1");
$stmt->execute([$login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Test de connexion pour : $login</h2>";

if ($user) {
    echo "<p>✅ Utilisateur trouvé !</p>";
    
    if (password_verify($password, $user['password_hash'])) {
        echo "<p style='color:green'>✅ MOT DE PASSE CORRECT ! La connexion devrait fonctionner.</p>";
    } else {
        echo "<p style='color:red'>❌ MOT DE PASSE INCORRECT !</p>";
        echo "<p>Hash stocké : " . $user['password_hash'] . "</p>";
        echo "<p>Mot de passe testé : " . $password . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ Utilisateur non trouvé ou compte inactif</p>";
}
?>