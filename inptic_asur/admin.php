<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$message = "";
$erreur  = "";

// ── Ajouter utilisateur ──
if (isset($_POST['ajouter'])) {
    $nom      = trim($_POST['nom']);
    $prenom   = trim($_POST['prenom']);
    $login    = trim($_POST['login']);
    $password = $_POST['password'];
    $role     = $_POST['role'];

    if (empty($nom) || empty($prenom) || empty($login) || empty($password)) {
        $erreur = "Tous les champs sont obligatoires.";
    } else {
        $check = $db->prepare("SELECT id FROM utilisateur WHERE login = ?");
        $check->execute([$login]);
        if ($check->fetch()) {
            $erreur = "Ce login existe déjà.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO utilisateur (nom, prenom, login, password_hash, role) VALUES (?,?,?,?,?)")
               ->execute([$nom, $prenom, $login, $hash, $role]);
            $message = "Utilisateur créé avec succès !";
        }
    }
}

// ── Supprimer ──
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    if ($id !== $_SESSION['user_id']) {
        $db->prepare("DELETE FROM utilisateur WHERE id = ?")->execute([$id]);
        $message = "Utilisateur supprimé.";
    } else {
        $erreur = "Vous ne pouvez pas supprimer votre propre compte.";
    }
}

// ── Activer / Désactiver ──
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $db->prepare("UPDATE utilisateur SET actif = NOT actif WHERE id = ?")->execute([$id]);
    $message = "Statut modifié.";
}

$utilisateurs = $db->query("SELECT * FROM utilisateur ORDER BY role, nom")->fetchAll(PDO::FETCH_ASSOC);
$role_labels  = ['admin' => '⚙️ Administrateur', 'enseignant' => '👨‍🏫 Enseignant',
                 'secretariat' => '📋 Secrétariat', 'etudiant' => '👨‍🎓 Étudiant'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration — INPTIC</title>
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

        .container { max-width: 1100px; margin: 32px auto; padding: 0 24px;
                     display: grid; grid-template-columns: 320px 1fr; gap: 28px; }

        .form-card { background: white; border-radius: 16px; padding: 28px;
                     box-shadow: 0 2px 12px rgba(0,0,0,0.06); height: fit-content; }
        .form-card h2 { font-size: 17px; color: #0a2540; font-weight: 700;
                        margin-bottom: 22px; padding-bottom: 12px; border-bottom: 2px solid #f0f2f5; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700;
                            color: #666; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 7px; }
        .form-group input, .form-group select {
            width: 100%; padding: 11px 14px; border: 2px solid #eef0f3;
            border-radius: 10px; font-size: 14px; color: #333; outline: none; background: #fafbfc; }
        .form-group input:focus, .form-group select:focus { border-color: #1a5276; }
        .btn-submit { width: 100%; padding: 13px; background: linear-gradient(135deg, #0a2540, #1a5276);
                      color: white; border: none; border-radius: 10px; font-size: 14px;
                      font-weight: 700; cursor: pointer; margin-top: 6px; }

        .table-card { background: white; border-radius: 16px;
                      box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
        .table-header { padding: 22px 28px; border-bottom: 2px solid #f0f2f5; }
        .table-header h2 { font-size: 17px; color: #0a2540; font-weight: 700; }

        .alert { padding: 13px 18px; border-radius: 10px; margin: 16px 28px;
                 font-size: 14px; font-weight: 500; }
        .alert-success { background: #eafaf1; color: #1e8449; border-left: 4px solid #1abc9c; }
        .alert-error   { background: #fdecea; color: #c0392b; border-left: 4px solid #e74c3c; }

        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fb; }
        thead th { padding: 12px 16px; font-size: 11px; font-weight: 700;
                   color: #888; text-transform: uppercase; letter-spacing: 1px; text-align: left; }
        tbody tr { border-bottom: 1px solid #f5f5f5; }
        tbody tr:hover { background: #f7faff; }
        tbody td { padding: 12px 16px; font-size: 14px; }

        .avatar { width: 34px; height: 34px; border-radius: 50%;
                  background: linear-gradient(135deg, #1a5276, #1abc9c);
                  display: inline-flex; align-items: center; justify-content: center;
                  color: white; font-weight: 700; font-size: 12px; margin-right: 10px; }
        .td-nom { font-weight: 700; color: #0a2540; }

        .role-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .role-admin       { background: #fdecea; color: #c0392b; }
        .role-enseignant  { background: #e8f4fd; color: #1a5276; }
        .role-secretariat { background: #fef9e7; color: #d68910; }
        .role-etudiant    { background: #eafaf1; color: #1e8449; }

        .status-on  { background: #eafaf1; color: #1e8449; padding: 3px 10px;
                      border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-off { background: #fdecea; color: #c0392b; padding: 3px 10px;
                      border-radius: 20px; font-size: 11px; font-weight: 700; }

        .btn-sm { padding: 5px 12px; border: none; border-radius: 7px; cursor: pointer;
                  font-size: 12px; font-weight: 600; text-decoration: none; }
        .btn-toggle { background: #f0f2f5; color: #555; }
        .btn-del    { background: #fdecea; color: #e74c3c; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>Administration</span></div>
    </div>
    <div class="nav-right">
        <a href="dashboard.php" class="btn-back">← Retour</a>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container">

    <!-- Formulaire -->
    <div class="form-card">
        <h2>➕ Nouvel utilisateur</h2>
        <form method="POST">
            <div class="form-group">
                <label>Nom *</label>
                <input type="text" name="nom" placeholder="Nom" required>
            </div>
            <div class="form-group">
                <label>Prénom *</label>
                <input type="text" name="prenom" placeholder="Prénom" required>
            </div>
            <div class="form-group">
                <label>Login *</label>
                <input type="text" name="login" placeholder="identifiant unique" required>
            </div>
            <div class="form-group">
                <label>Mot de passe *</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <label>Rôle *</label>
                <select name="role">
                    <option value="enseignant">👨‍🏫 Enseignant</option>
                    <option value="secretariat">📋 Secrétariat</option>
                    <option value="etudiant">👨‍🎓 Étudiant</option>
                    <option value="admin">⚙️ Administrateur</option>
                </select>
            </div>
            <button type="submit" name="ajouter" class="btn-submit">➕ Créer le compte</button>
        </form>
    </div>

    <!-- Liste -->
    <div class="table-card">
        <div class="table-header">
            <h2>👥 Utilisateurs (<?= count($utilisateurs) ?>)</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($erreur): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Login</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($utilisateurs as $u):
                $initiales = strtoupper(substr($u['prenom'], 0, 1) . substr($u['nom'], 0, 1));
            ?>
            <tr>
                <td>
                    <div style="display:flex; align-items:center;">
                        <span class="avatar"><?= $initiales ?></span>
                        <span class="td-nom"><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></span>
                    </div>
                </td>
                <td style="color:#888; font-family:monospace;"><?= htmlspecialchars($u['login']) ?></td>
                <td>
                    <span class="role-badge role-<?= $u['role'] ?>">
                        <?= $role_labels[$u['role']] ?? $u['role'] ?>
                    </span>
                </td>
                <td>
                    <span class="<?= $u['actif'] ? 'status-on' : 'status-off' ?>">
                        <?= $u['actif'] ? '● Actif' : '● Inactif' ?>
                    </span>
                </td>
                <td style="display:flex; gap:6px;">
                    <a href="?toggle=<?= $u['id'] ?>" class="btn-sm btn-toggle">
                        <?= $u['actif'] ? '⏸ Désactiver' : '▶ Activer' ?>
                    </a>
                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                        <a href="?supprimer=<?= $u['id'] ?>" class="btn-sm btn-del"
                           onclick="return confirm('Supprimer cet utilisateur ?')">🗑️</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>