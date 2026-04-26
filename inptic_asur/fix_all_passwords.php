<?php
session_start();
require 'connexion.php';

// Sécurité : seul un admin peut faire ça
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("⛔ Accès refusé. Vous devez être administrateur.");
}

$message = "";
$erreur = "";

// Réinitialiser TOUS les mots de passe des étudiants
if (isset($_POST['fix_all'])) {
    $new_password = 'etudiant123';
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE utilisateur SET password_hash = ? WHERE role = 'etudiant'");
    if ($stmt->execute([$hash])) {
        $count = $stmt->rowCount();
        $message = "✅ $count mots de passe étudiants réinitialisés à 'etudiant123' !";
    } else {
        $erreur = "❌ Erreur lors de la réinitialisation";
    }
}

// Réinitialiser un seul étudiant
if (isset($_POST['reset_one'])) {
    $etudiant_id = intval($_POST['etudiant_id']);
    $new_password = 'etudiant123';
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE utilisateur SET password_hash = ? WHERE etudiant_id = ?");
    if ($stmt->execute([$hash, $etudiant_id])) {
        $message = "✅ Mot de passe réinitialisé à 'etudiant123' pour l'étudiant !";
    } else {
        $erreur = "❌ Erreur lors de la réinitialisation";
    }
}

// Récupérer tous les étudiants
$etudiants = $db->query("
    SELECT e.id, e.nom, e.prenom, u.login, u.password_hash, u.actif 
    FROM etudiant e 
    LEFT JOIN utilisateur u ON u.etudiant_id = e.id
    ORDER BY e.nom
")->fetchAll();

// Tester un mot de passe spécifique
$test_result = "";
if (isset($_POST['test_password'])) {
    $test_login = $_POST['test_login'];
    $test_password = $_POST['test_password'];
    
    $stmt = $db->prepare("SELECT * FROM utilisateur WHERE login = ? AND actif = 1");
    $stmt->execute([$test_login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if (password_verify($test_password, $user['password_hash'])) {
            $test_result = "<span style='color:green'>✅ MOT DE PASSE CORRECT !</span>";
        } else {
            $test_result = "<span style='color:red'>❌ MOT DE PASSE INCORRECT</span>";
        }
    } else {
        $test_result = "<span style='color:red'>❌ Utilisateur non trouvé</span>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fixer les mots de passe</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            padding: 40px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        h1 { color: #0a2540; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #f0f2f5; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0a2540; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #eef0f3; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .login { font-family: monospace; font-size: 14px; background: #f8f9fa; padding: 4px 8px; border-radius: 5px; }
        .btn-reset {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-reset:hover { background: #c0392b; }
        .btn-fix-all {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .btn-fix-all:hover { background: #1e7e34; }
        .badge { background: #28a745; color: white; padding: 2px 8px; border-radius: 20px; font-size: 11px; }
        .test-box {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 4px solid #1a5276;
        }
        .test-box input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-right: 10px;
        }
        .hash { font-family: monospace; font-size: 11px; color: #999; max-width: 200px; overflow: hidden; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Correction des mots de passe étudiants</h1>
    <div class="subtitle">Les mots de passe actuels sont corrompus. Utilisez ce formulaire pour les corriger.</div>

    <?php if ($message): ?>
        <div class="success"><?= $message ?></div>
    <?php endif; ?>
    
    <?php if ($erreur): ?>
        <div class="error"><?= $erreur ?></div>
    <?php endif; ?>

    <form method="POST">
        <button type="submit" name="fix_all" class="btn-fix-all" onclick="return confirm('⚠️ Réinitialiser TOUS les mots de passe étudiants à \"etudiant123\" ?')">
            🔄 RÉINITIALISER TOUS LES MOTS DE PASSE (etudiant123)
        </button>
    </form>

    <h3 style="margin: 20px 0 15px;">📋 Liste des étudiants</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom complet</th>
                <th>Login</th>
                <th>Statut</th>
                <th>Hash actuel</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($etudiants as $e): ?>
            <tr>
                <td><?= $e['id'] ?></td>
                <td><strong><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></strong></td>
                <td><span class="login"><?= htmlspecialchars($e['login'] ?? '❌ PAS DE LOGIN') ?></span></td>
                <td><?= $e['actif'] == 1 ? '<span class="badge">✅ Actif</span>' : '❌ Inactif' ?></td>
                <td class="hash"><?= htmlspecialchars(substr($e['password_hash'] ?? '', 0, 30)) ?>...</td>
                <td>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="etudiant_id" value="<?= $e['id'] ?>">
                        <button type="submit" name="reset_one" class="btn-reset">🔄 Réinitialiser</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="test-box">
        <h3>🔐 Tester un mot de passe</h3>
        <form method="POST">
            <input type="text" name="test_login" placeholder="Login (ex: dabessolo1)" required>
            <input type="text" name="test_password" placeholder="Mot de passe à tester" required>
            <button type="submit" name="test_password" style="background: #1a5276; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Tester</button>
        </form>
        <?php if ($test_result): ?>
            <p style="margin-top: 15px; font-weight: bold;"><?= $test_result ?></p>
        <?php endif; ?>
        <p style="margin-top: 20px;">
            <a href="login.php" target="_blank" style="background: #1a5276; color: white; padding: 8px 16px; text-decoration: none; border-radius: 8px; display: inline-block;">🔑 Aller à la page de connexion</a>
        </p>
    </div>
</div>
</body>
</html>