<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = "";
$erreur  = "";

// ── Suppression ──
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    try {
        $db->prepare("DELETE FROM utilisateur WHERE etudiant_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM etudiant WHERE id = ?")->execute([$id]);
        $message = "Étudiant supprimé avec succès.";
    } catch (Exception $e) {
        $erreur = "Impossible de supprimer (notes existantes).";
    }
}

// ── Ajout ──
if (isset($_POST['ajouter'])) {
    $nom    = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $date_n = $_POST['date_naissance'];
    $lieu   = trim($_POST['lieu_naissance']);
    $bac    = trim($_POST['type_bac']);
    $etab   = trim($_POST['etablissement']);

    if (empty($nom) || empty($prenom)) {
        $erreur = "Le nom et le prénom sont obligatoires.";
    } else {
        // Créer l'étudiant
        $sql = "INSERT INTO etudiant (nom, prenom, date_naissance, lieu_naissance, type_bac, etablissement) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $db->prepare($sql)->execute([$nom, $prenom, $date_n, $lieu, $bac, $etab]);
        $etudiant_id = $db->lastInsertId();

        // Créer automatiquement le compte utilisateur
        // Login = 1ère lettre prénom + nom en minuscule (ex: dabessolo)
        $login_base = strtolower(substr($prenom, 0, 1) . $nom);
        $login_base = preg_replace('/[^a-z0-9]/', '', $login_base);
        $login = $login_base;

        // Si le login existe déjà, ajouter un numéro
        $i = 1;
        while (true) {
            $check = $db->prepare("SELECT id FROM utilisateur WHERE login = ?");
            $check->execute([$login]);
            if (!$check->fetch()) break;
            $login = $login_base . $i;
            $i++;
        }

        $hash = password_hash('etudiant123', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO utilisateur (nom, prenom, login, password_hash, role, etudiant_id) VALUES (?,?,?,?,?,?)")
           ->execute([$nom, $prenom, $login, $hash, 'etudiant', $etudiant_id]);

        $message = "Étudiant ajouté ! Login : <strong>$login</strong> — Mot de passe : <strong>etudiant123</strong>";
    }
}

// ── Modification ──
if (isset($_POST['modifier'])) {
    $id     = intval($_POST['id']);
    $nom    = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $date_n = $_POST['date_naissance'];
    $lieu   = trim($_POST['lieu_naissance']);
    $bac    = trim($_POST['type_bac']);
    $etab   = trim($_POST['etablissement']);

    $sql = "UPDATE etudiant SET nom=?, prenom=?, date_naissance=?, lieu_naissance=?, type_bac=?, etablissement=? WHERE id=?";
    $db->prepare($sql)->execute([$nom, $prenom, $date_n, $lieu, $bac, $etab, $id]);
    $db->prepare("UPDATE utilisateur SET nom=?, prenom=? WHERE etudiant_id=?")->execute([$nom, $prenom, $id]);
    $message = "Étudiant modifié avec succès !";
}

// ── Récupérer étudiant à modifier ──
$etudiant_edit = null;
if (isset($_GET['modifier'])) {
    $stmt = $db->prepare("SELECT * FROM etudiant WHERE id = ?");
    $stmt->execute([intval($_GET['modifier'])]);
    $etudiant_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Liste des étudiants avec leur login ──
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $db->prepare("
        SELECT e.*, u.login 
        FROM etudiant e 
        LEFT JOIN utilisateur u ON u.etudiant_id = e.id
        WHERE e.nom LIKE ? OR e.prenom LIKE ? 
        ORDER BY e.nom
    ");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $db->query("
        SELECT e.*, u.login 
        FROM etudiant e 
        LEFT JOIN utilisateur u ON u.etudiant_id = e.id
        ORDER BY e.nom
    ");
}
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Étudiants — INPTIC</title>
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

        .container { max-width: 1300px; margin: 32px auto; padding: 0 24px;
                     display: grid; grid-template-columns: 340px 1fr; gap: 28px; }

        .form-card { background: white; border-radius: 16px; padding: 28px;
                     box-shadow: 0 2px 12px rgba(0,0,0,0.06); height: fit-content; }
        .form-card h2 { font-size: 17px; color: #0a2540; font-weight: 700;
                        margin-bottom: 22px; padding-bottom: 12px; border-bottom: 2px solid #f0f2f5; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700;
                            color: #666; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 7px; }
        .form-group input { width: 100%; padding: 11px 14px; border: 2px solid #eef0f3;
                            border-radius: 10px; font-size: 14px; outline: none; background: #fafbfc; }
        .form-group input:focus { border-color: #1a5276; background: white; }
        .btn-submit { width: 100%; padding: 13px; background: linear-gradient(135deg, #0a2540, #1a5276);
                      color: white; border: none; border-radius: 10px; font-size: 14px;
                      font-weight: 700; cursor: pointer; margin-top: 6px; }
        .btn-cancel { width: 100%; padding: 11px; background: #f0f2f5; color: #666;
                      border: none; border-radius: 10px; font-size: 14px; font-weight: 600;
                      cursor: pointer; margin-top: 8px; text-decoration: none;
                      display: block; text-align: center; }

        .info-compte { background: #e8f4fd; border-left: 4px solid #1a5276;
                       padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;
                       font-size: 12px; color: #1a5276; }

        .table-card { background: white; border-radius: 16px;
                      box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow-x: auto; }
        .table-header { padding: 22px 28px; display: flex;
                        justify-content: space-between; align-items: center;
                        border-bottom: 2px solid #f0f2f5; flex-wrap: wrap; gap: 16px; }
        .table-header h2 { font-size: 17px; color: #0a2540; font-weight: 700; }
        .search-box { display: flex; gap: 8px; }
        .search-box input { padding: 9px 14px; border: 2px solid #eef0f3;
                            border-radius: 10px; font-size: 14px; outline: none; width: 200px; }
        .search-box button { padding: 9px 16px; background: #1a5276; color: white;
                             border: none; border-radius: 10px; cursor: pointer; font-size: 13px; }

        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        thead { background: #f8f9fb; }
        thead th { padding: 12px 16px; font-size: 11px; font-weight: 700;
                   color: #888; text-transform: uppercase; letter-spacing: 1px; text-align: left; }
        tbody tr { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
        tbody tr:hover { background: #f7faff; }
        tbody td { padding: 12px 16px; font-size: 14px; }

        .avatar { width: 36px; height: 36px; border-radius: 50%;
                  background: linear-gradient(135deg, #1a5276, #1abc9c);
                  display: inline-flex; align-items: center; justify-content: center;
                  color: white; font-weight: 700; font-size: 13px; margin-right: 10px; }
        .td-nom { font-weight: 700; color: #0a2540; }
        .td-login { font-family: monospace; background: #f0f2f5; padding: 3px 8px;
                    border-radius: 6px; font-size: 12px; color: #555; display: inline-block; }

        .btn-edit { background: #e8f4fd; color: #1a5276; border: none;
                    padding: 6px 12px; border-radius: 7px; cursor: pointer;
                    font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-del  { background: #fdecea; color: #e74c3c; border: none;
                    padding: 6px 12px; border-radius: 7px; cursor: pointer;
                    font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; }

        .alert { padding: 13px 18px; border-radius: 10px; margin: 16px 28px;
                 font-size: 14px; font-weight: 500; }
        .alert-success { background: #eafaf1; color: #1e8449; border-left: 4px solid #1abc9c; }
        .alert-error   { background: #fdecea; color: #c0392b; border-left: 4px solid #e74c3c; }

        .empty { text-align: center; padding: 50px; color: #bbb; }
        .edit-banner { background: #fff8e1; border-left: 4px solid #f39c12;
                       padding: 10px 16px; border-radius: 8px; margin-bottom: 16px;
                       font-size: 13px; color: #856404; font-weight: 600; }

        @media (max-width: 850px) {
            .container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo"><img src="logo_inptic.png" alt="INPTIC"></div>
        <div class="nav-title">INPTIC — <span>Étudiants</span></div>
    </div>
    <div class="nav-right">
        <a href="dashboard.php" class="btn-back">← Retour</a>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container">

    <div class="form-card">
        <?php if ($etudiant_edit): ?>
            <div class="edit-banner">✏️ Modification en cours</div>
            <h2>Modifier l'étudiant</h2>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $etudiant_edit['id'] ?>">
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($etudiant_edit['nom']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Prénom *</label>
                    <input type="text" name="prenom" value="<?= htmlspecialchars($etudiant_edit['prenom']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Date de naissance</label>
                    <input type="date" name="date_naissance" value="<?= $etudiant_edit['date_naissance'] ?>">
                </div>
                <div class="form-group">
                    <label>Lieu de naissance</label>
                    <input type="text" name="lieu_naissance" value="<?= htmlspecialchars($etudiant_edit['lieu_naissance'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Type de Bac</label>
                    <input type="text" name="type_bac" value="<?= htmlspecialchars($etudiant_edit['type_bac'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Établissement d'origine</label>
                    <input type="text" name="etablissement" value="<?= htmlspecialchars($etudiant_edit['etablissement'] ?? '') ?>">
                </div>
                <button type="submit" name="modifier" class="btn-submit">💾 Enregistrer</button>
                <a href="etudiants.php" class="btn-cancel">Annuler</a>
            </form>
        <?php else: ?>
            <h2>➕ Ajouter un étudiant</h2>
            <div class="info-compte">
                ℹ️ Un compte de connexion sera créé automatiquement.<br>
                Mot de passe par défaut : <strong>etudiant123</strong>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="nom" placeholder="Nom de l'étudiant" required>
                </div>
                <div class="form-group">
                    <label>Prénom *</label>
                    <input type="text" name="prenom" placeholder="Prénom de l'étudiant" required>
                </div>
                <div class="form-group">
                    <label>Date de naissance</label>
                    <input type="date" name="date_naissance">
                </div>
                <div class="form-group">
                    <label>Lieu de naissance</label>
                    <input type="text" name="lieu_naissance" placeholder="Ville, Pays">
                </div>
                <div class="form-group">
                    <label>Type de Bac</label>
                    <input type="text" name="type_bac" placeholder="ex: Bac S, Bac G...">
                </div>
                <div class="form-group">
                    <label>Établissement d'origine</label>
                    <input type="text" name="etablissement" placeholder="Lycée, école...">
                </div>
                <button type="submit" name="ajouter" class="btn-submit">➕ Ajouter</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>👨‍🎓 Liste des étudiants (<?= count($etudiants) ?>)</h2>
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Rechercher..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit">🔍</button>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?= $message ?></div>
        <?php endif; ?>
        <?php if ($erreur): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if (empty($etudiants)): ?>
            <div class="empty">
                <div style="font-size:48px; margin-bottom:12px;">👨‍🎓</div>
                <p>Aucun étudiant. Utilisez le formulaire pour en ajouter.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Étudiant</th>
                            <th>Login</th>
                            <th>Date naissance</th>
                            <th>Bac</th>
                            <th>Établissement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($etudiants as $i => $e): ?>
                        <tr>
                            <td style="color:#bbb; font-size:12px;"><?= $i+1 ?></td>
                            <td>
                                <div style="display:flex; align-items:center;">
                                    <span class="avatar">
                                        <?= strtoupper(substr($e['prenom'],0,1).substr($e['nom'],0,1)) ?>
                                    </span>
                                    <div>
                                        <div class="td-nom"><?= htmlspecialchars($e['nom'].' '.$e['prenom']) ?></div>
                                        <div style="font-size:12px; color:#bbb;"><?= htmlspecialchars($e['lieu_naissance'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="td-login"><?= htmlspecialchars($e['login'] ?? '–') ?></span>
                            </td>
                            <td><?= $e['date_naissance'] ? date('d/m/Y', strtotime($e['date_naissance'])) : '–' ?></td>
                            <td><?= htmlspecialchars($e['type_bac'] ?? '–') ?></td>
                            <td><?= htmlspecialchars($e['etablissement'] ?? '–') ?></td>
                            <td style="white-space: nowrap;">
                                <a href="?modifier=<?= $e['id'] ?>" class="btn-edit">✏️ Modifier</a>
                                <a href="?supprimer=<?= $e['id'] ?>" class="btn-del"
                                   onclick="return confirm('Supprimer cet étudiant et son compte ?')">🗑️ Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>