<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/files.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['crypto_pass'])) {
    header("Location: index.php"); exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset(); session_destroy();
    header("Location: index.php"); exit;
}

$userId   = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);
$message  = '';

// ── ACTIONS POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (isset($_FILES['file_to_upload']) && $_FILES['file_to_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
            $result   = uploadAndEncryptFile($pdo, $userId, $_FILES['file_to_upload']);
            if ($result['success'] && $folderId) {
                // Récupère l'ID du fichier qu'on vient d'insérer
                $lastId = $pdo->lastInsertId();
                moveFileToFolder($pdo, $userId, $lastId, $folderId);
            }
            $message = $result['message'];
        } else {
            $message = "Aucun fichier sélectionné.";
        }
    }

    if ($action === 'create_folder') {
        $name     = trim($_POST['folder_name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $result   = createFolder($pdo, $userId, $name, $parentId);
        $message  = $result['message'];
    }

    if ($action === 'rename_folder') {
        $result  = renameFolder($pdo, $userId, (int)$_POST['folder_id'], $_POST['new_name'] ?? '');
        $message = $result['message'];
    }

    if ($action === 'move_file') {
        $fid    = (int)$_POST['file_id'];
        $did    = !empty($_POST['dest_folder_id']) ? (int)$_POST['dest_folder_id'] : null;
        $result = moveFileToFolder($pdo, $userId, $fid, $did);
        $message = $result['message'];
    }

    if ($action === 'share') {
        $target = trim($_POST['target']);
        $fileId = $_POST['file_id'] ?? null;
        if ($fileId) {
            $result  = shareFile($pdo, $userId, (int)$fileId, $target, $_SESSION['crypto_pass']);
            $message = $result['message'];
        }
    }

    if ($action === 'share_folder') {
        $result  = shareFolder($pdo, $userId, (int)$_POST['folder_id'], trim($_POST['target']));
        $message = $result['message'];
    }
}

// ── ACTIONS GET ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'], $_GET['id'])) {
    $fileId = (int)$_GET['id'];
    if ($_GET['action'] === 'download') {
        $result  = downloadFile($pdo, $userId, $fileId, $_SESSION['crypto_pass']);
        $message = $result['message'] ?? '';
    }
    if ($_GET['action'] === 'delete') {
        $result  = deleteFile($pdo, $userId, $fileId);
        $message = $result['message'];
    }
    if ($_GET['action'] === 'delete_folder') {
        $result  = deleteFolder($pdo, $userId, (int)$_GET['id']);
        $message = $result['message'];
        header("Location: ?"); exit;
    }
    if ($_GET['action'] === 'delete_account') {
        $result = deleteUserAccount($pdo, $userId);
        if ($result['success']) { session_destroy(); header("Location: index.php"); exit; }
        $message = $result['message'];
    }
}

// ── DONNÉES ─────────────────────────────────────────────────────────────
$query      = trim($_GET['search'] ?? '');
$view       = $_GET['view']      ?? 'my_files';
$folderId   = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

$allFolders   = getUserFolders($pdo, $userId);
$folderTree   = buildFolderTree($allFolders);
$sharedFolders = getSharedFoldersWithMe($pdo, $userId);

if ($view === 'shared') {
    $displayFiles = searchSharedFiles($pdo, $userId, $query);
    $viewTitle    = "Partagés avec moi";
    $isSharedView = true;
} elseif ($folderId) {
    $displayFiles = $query ? searchUserFiles($pdo, $userId, $query, $folderId) : getFolderFiles($pdo, $userId, $folderId);
    $folderInfo   = array_values(array_filter($allFolders, fn($f) => (int)($f['id_dossier'] ?? $f['ID_DOSSIER']) === $folderId))[0] ?? null;
    $viewTitle    = "📁 " . htmlspecialchars($folderInfo['nom_dossier'] ?? $folderInfo['NOM_DOSSIER'] ?? 'Dossier');
    $isSharedView = false;
} else {
    $displayFiles = searchUserFiles($pdo, $userId, $query);
    $viewTitle    = "Mon Espace Personnel";
    $isSharedView = false;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureCloud — <?= $viewTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb; --danger: #dc2626;
            --bg: #f8fafc;      --surface: #ffffff;
            --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text-main); margin: 0; display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar { width: 270px; background: var(--surface); border-right: 1px solid var(--border); padding: 20px; display: flex; flex-direction: column; }
        .sidebar h2 { font-size: 1.2rem; margin: 0 0 24px; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; text-decoration: none; color: var(--text-main); border-radius: 8px; margin-bottom: 4px; font-size: .9rem; }
        .nav-link.active { background: #eff6ff; color: var(--primary); font-weight: 600; }
        .nav-link:hover:not(.active) { background: #f1f5f9; }

        /* Arbre de dossiers */
        .folder-tree { list-style: none; padding: 0; margin: 0; }
        .folder-tree li { margin: 0; }
        .folder-row { display: flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: .875rem; color: var(--text-main); text-decoration: none; }
        .folder-row:hover { background: #f1f5f9; }
        .folder-row.active { background: #eff6ff; color: var(--primary); font-weight: 600; }
        .folder-row .chevron { font-size: .65rem; transition: transform .2s; flex-shrink: 0; color: var(--text-muted); }
        .folder-row .chevron.open { transform: rotate(90deg); }
        .subfolder-list { list-style: none; padding-left: 16px; margin: 0; overflow: hidden; }
        .subfolder-list.collapsed { display: none; }
        .folder-actions { margin-left: auto; display: none; gap: 2px; }
        .folder-row:hover .folder-actions { display: flex; }
        .folder-action-btn { background: none; border: none; cursor: pointer; font-size: .8rem; padding: 2px 4px; border-radius: 4px; color: var(--text-muted); }
        .folder-action-btn:hover { background: #e2e8f0; color: var(--text-main); }

        .section-label { font-size: .75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; margin: 20px 0 8px 8px; }

        /* Bouton nouveau dossier */
        .btn-new-folder { display: flex; align-items: center; gap: 8px; width: 100%; background: none; border: 1.5px dashed var(--border); border-radius: 8px; padding: 8px 12px; font-size: .85rem; color: var(--text-muted); cursor: pointer; margin-top: 8px; transition: .2s; }
        .btn-new-folder:hover { border-color: var(--primary); color: var(--primary); background: #eff6ff; }

        /* ── MAIN ── */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .logout-btn { color: var(--danger); text-decoration: none; font-weight: 500; font-size: .9rem; }

        .upload-zone { background: var(--surface); padding: 24px 30px; border-radius: 12px; border: 2px dashed var(--border); margin-bottom: 32px; }
        .btn-primary { background: var(--primary); color: white; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: .9rem; }

        /* Search */
        .search-row { display: flex; align-items: center; gap: .5rem; margin-bottom: 1.25rem; }
        .search-form { display: flex; align-items: center; flex: 1; max-width: 480px; background: #f8fafc; border: 1.5px solid var(--border); border-radius: 8px; padding: .4rem .75rem; transition: .2s; }
        .search-form:focus-within { border-color: var(--primary); background: #fff; }
        .search-form input { flex: 1; border: none; background: transparent; font-size: .95rem; outline: none; font-family: inherit; }

        /* Table */
        .file-card { background: var(--surface); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 16px; border-bottom: 1px solid var(--border); color: var(--text-muted); font-weight: 500; font-size: .82rem; }
        td { padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: .9rem; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        /* Dropdown */
        .menu-container { position: relative; display: inline-block; }
        .dots-btn { cursor: pointer; padding: 6px 10px; border-radius: 6px; color: var(--text-muted); font-size: 18px; user-select: none; }
        .dots-btn:hover { background: #e2e8f0; }
        .dropdown { display: none; position: absolute; right: 0; top: 34px; background: var(--surface); min-width: 210px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,.12); z-index: 50; border: 1px solid var(--border); overflow: hidden; }
        .dropdown.show { display: block; }
        .dropdown a, .dropdown button.dd-item { display: flex; align-items: center; gap: 8px; padding: 11px 16px; text-decoration: none; color: var(--text-main); font-size: .88rem; background: none; border: none; width: 100%; cursor: pointer; font-family: inherit; }
        .dropdown a:hover, .dropdown button.dd-item:hover { background: #f8fafc; }
        .dropdown .delete-item { color: var(--danger); }
        .share-section { border-top: 1px solid var(--border); padding: 10px 14px; }
        .share-section label { font-size: .75rem; color: var(--text-muted); display: block; margin-bottom: 6px; }
        .share-section .share-row { display: flex; gap: 5px; }
        .share-section input { flex: 1; padding: 5px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: .85rem; }
        .share-section button { padding: 5px 10px; border-radius: 4px; border: none; background: var(--primary); color: #fff; cursor: pointer; font-size: .85rem; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-content { background: #fff; padding: 30px; border-radius: 16px; max-width: 420px; width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,.15); }
        .modal-content h3 { margin-top: 0; }
        .modal-input { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: .95rem; margin: 12px 0; font-family: inherit; }
        .modal-input:focus { outline: none; border-color: var(--primary); }
        .modal-buttons { display: flex; gap: 10px; margin-top: 8px; }
        .modal-btn { flex: 1; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: .9rem; }
        .btn-cancel { background: #f1f5f9; color: var(--text-main); }
        .btn-confirm { background: var(--primary); color: #fff; }
        .btn-confirm-delete { background: var(--danger); color: #fff; }

        /* Move file select */
        .move-select { width: 100%; padding: 5px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: .85rem; margin-bottom: 5px; }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════════════════ -->
<aside class="sidebar">
    <div style="flex:1; overflow-y:auto;">
        <h2>🛡️ SecureCloud</h2>

        <p class="section-label">Navigation</p>
        <a href="?" class="nav-link <?= (!$folderId && $view === 'my_files') ? 'active' : '' ?>">🏠 Mon espace</a>
        <a href="?view=shared" class="nav-link <?= ($view === 'shared') ? 'active' : '' ?>">🤝 Partagés avec moi</a>

        <p class="section-label">Mes dossiers</p>
        <ul class="folder-tree" id="folderTree">
            <?php renderFolderTree($folderTree, $folderId); ?>
        </ul>

        <button class="btn-new-folder" onclick="openCreateFolder(null)">
            ➕ Nouveau dossier
        </button>

        <?php if (!empty($sharedFolders)): ?>
        <p class="section-label">Dossiers partagés</p>
        <?php foreach ($sharedFolders as $sf): ?>
            <a href="?view=shared_folder&folder=<?= $sf['id_dossier'] ?? $sf['ID_DOSSIER'] ?>"
               class="nav-link">
                📂 <?= htmlspecialchars($sf['nom_dossier'] ?? $sf['NOM_DOSSIER']) ?>
                <small style="color:var(--text-muted); margin-left:auto;"><?= htmlspecialchars($sf['owner_name'] ?? $sf['OWNER_NAME']) ?></small>
            </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="border-top:1px solid var(--border); padding-top:16px; margin-top:16px;">
        <a href="javascript:void(0)" onclick="document.getElementById('deleteAccountModal').classList.add('open')"
           style="color:var(--danger); text-decoration:none; font-size:.85rem; font-weight:500; display:flex; align-items:center; gap:8px;">
            ⚠️ Supprimer mon compte
        </a>
    </div>
</aside>

<?php
// Fonction de rendu récursif de l'arbre
function renderFolderTree(array $tree, $activeFolderId, $depth = 0) {
    foreach ($tree as $folder) {
        $fid      = $folder['id_dossier'] ?? $folder['ID_DOSSIER'];
        $name     = htmlspecialchars($folder['nom_dossier'] ?? $folder['NOM_DOSSIER']);
        $hasKids  = !empty($folder['children']);
        $isActive = (int)$activeFolderId === (int)$fid;
        $isOpen   = $isActive || isAncestor($folder, $activeFolderId);
        ?>
        <li>
            <div class="folder-row <?= $isActive ? 'active' : '' ?>" onclick="navFolder(event, <?= $fid ?>)">
                <span class="chevron <?= ($hasKids && $isOpen) ? 'open' : '' ?>"
                      id="chv-<?= $fid ?>"
                      onclick="event.stopPropagation(); toggleSubfolder(<?= $fid ?>)">
                    <?= $hasKids ? '▶' : '' ?>
                </span>
                <span>📁 <?= $name ?></span>
                <span class="folder-actions">
                    <button class="folder-action-btn" title="Sous-dossier"
                            onclick="event.stopPropagation(); openCreateFolder(<?= $fid ?>)">➕</button>
                    <button class="folder-action-btn" title="Renommer"
                            onclick="event.stopPropagation(); openRenameFolder(<?= $fid ?>, '<?= addslashes($name) ?>')">✏️</button>
                            <button class="folder-action-btn" title="Supprimer"
                            onclick="event.stopPropagation(); openDeleteFolder(<?= $fid ?>, '<?= addslashes($name) ?>')">🗑️</button>
                </span>
            </div>
            <?php if ($hasKids): ?>
            <ul class="subfolder-list <?= $isOpen ? '' : 'collapsed' ?>" id="sub-<?= $fid ?>">
                <?php renderFolderTree($folder['children'], $activeFolderId, $depth + 1); ?>
            </ul>
            <?php endif; ?>
        </li>
        <?php
    }
}

function isAncestor(array $folder, $targetId): bool {
    foreach ($folder['children'] ?? [] as $child) {
        $cid = $child['id_dossier'] ?? $child['ID_DOSSIER'];
        if ((int)$cid === (int)$targetId) return true;
        if (isAncestor($child, $targetId)) return true;
    }
    return false;
}
?>

<!-- ══════════════════════════════════════════════════════
     MAIN
══════════════════════════════════════════════════════ -->
<main class="main-content">
    <header>
        <h1><?= $viewTitle ?></h1>
        <div class="user-info">
            <span>Bonjour, <strong><?= $username ?></strong></span>
            <a href="?action=logout" class="logout-btn">Se déconnecter</a>
        </div>
    </header>

    <?php if ($message): ?>
    <div style="background:#dcfce7; color:#166534; padding:14px 18px; border-radius:8px; margin-bottom:24px; border:1px solid #bbf7d0;">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Upload -->
    <?php if (!$isSharedView): ?>
    <section class="upload-zone">
        <p style="margin:0 0 16px; color:var(--text-muted); font-size:.9rem;">
            Ajoutez un fichier<?= $folderId ? ' dans ce dossier' : '' ?> — il sera stocké chiffré.
        </p>
        <form method="POST" enctype="multipart/form-data" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="folder_id" value="<?= $folderId ?? '' ?>">
            <input type="file" name="file_to_upload" required>
            <button type="submit" class="btn-primary">Chiffrer et envoyer</button>
        </form>
    </section>
    <?php endif; ?>

    <!-- Barre de recherche -->
    <div class="search-row">
        <form method="GET" action="" id="searchForm" class="search-form">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <?php if ($folderId): ?><input type="hidden" name="folder" value="<?= $folderId ?>"> <?php endif; ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-right:.5rem;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" name="search" placeholder="Rechercher un fichier…"
                   value="<?= htmlspecialchars($query) ?>" autocomplete="off">
            <?php if ($query): ?>
            <button type="button" onclick="document.querySelector('.search-form input[name=search]').value=''; searchForm.submit();"
                    style="background:none;border:none;cursor:pointer;color:#64748b;font-size:1.1rem;">×</button>
            <?php endif; ?>
        </form>
        <button type="submit" form="searchForm" class="btn-primary">Rechercher</button>
    </div>

    <?php if ($query): ?>
    <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;">
        <?= count($displayFiles) ?> résultat(s) pour <strong>"<?= htmlspecialchars($query) ?>"</strong> —
        <a href="?view=<?= htmlspecialchars($view) ?><?= $folderId ? '&folder='.$folderId : '' ?>" style="color:var(--primary);">Réinitialiser</a>
    </p>
    <?php endif; ?>

    <!-- Table fichiers -->
    <div class="file-card">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th><?= $isSharedView ? 'Propriétaire' : 'Date' ?></th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($displayFiles)): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:40px;">
                    <?= $query ? 'Aucun résultat.' : 'Aucun fichier ici.' ?>
                </td></tr>
            <?php else: foreach ($displayFiles as $file):
                $fid = $file['id_fichier'] ?? $file['ID_FICHIER'];
                $nom = $file['nom_fichier'] ?? $file['NOM_FICHIER'];
                $date = $file['date_upload'] ?? $file['DATE_UPLOAD'] ?? null;
                $owner = $file['owner_name'] ?? $file['OWNER_NAME'] ?? '';
            ?>
                <tr>
                    <td><strong>📄 <?= htmlspecialchars($nom) ?></strong></td>
                    <td style="color:var(--text-muted);">
                        <?= $isSharedView ? htmlspecialchars($owner) : ($date ? date('d M Y, H:i', strtotime($date)) : '—') ?>
                    </td>
                    <td style="text-align:right">
                        <div class="menu-container">
                            <div class="dots-btn" onclick="toggleMenu(event,'m-<?= $fid ?>')">⋮</div>
                            <div id="m-<?= $fid ?>" class="dropdown">
                                <a href="?action=download&id=<?= $fid ?>">📥 Télécharger</a>
                                <?php if (!$isSharedView): ?>
                                <button class="dd-item" onclick="openMoveFile(<?= $fid ?>)">📂 Déplacer vers…</button>
                                <a href="javascript:void(0)" onclick="openDeleteFile(<?= $fid ?>)" class="delete-item">🗑️ Supprimer</a>                                <div class="share-section">
                                    <label>Partager le fichier avec :</label>
                                    <form method="POST" class="share-row">
                                        <input type="hidden" name="action" value="share">
                                        <input type="hidden" name="file_id" value="<?= $fid ?>">
                                        <input type="text" name="target" placeholder="Pseudo" required>
                                        <button type="submit">OK</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Partage du dossier courant -->
    <?php if ($folderId && !$isSharedView): ?>
    <div style="margin-top:20px; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px;">
        <strong style="font-size:.9rem;">Partager ce dossier</strong>
        <form method="POST" style="display:flex; gap:8px; margin-top:10px;">
            <input type="hidden" name="action" value="share_folder">
            <input type="hidden" name="folder_id" value="<?= $folderId ?>">
            <input type="text" name="target" placeholder="Pseudo de l'utilisateur" required
                   style="flex:1; padding:8px 12px; border:1.5px solid var(--border); border-radius:6px; font-size:.9rem;">
            <button type="submit" class="btn-primary">Partager</button>
        </form>
    </div>
    <?php endif; ?>
</main>

<!-- ══════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════ -->

<!-- Créer dossier -->
<div id="modalCreateFolder" class="modal-overlay">
    <div class="modal-content">
        <h3 style="margin-top:0;">📁 Nouveau dossier</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_folder">
            <input type="hidden" name="parent_id" id="createParentId" value="">
            <input type="text" name="folder_name" class="modal-input" placeholder="Nom du dossier" required autofocus>
            <div class="modal-buttons">
                <button type="button" class="modal-btn btn-cancel" onclick="document.getElementById('modalCreateFolder').classList.remove('open')">Annuler</button>
                <button type="submit" class="modal-btn btn-confirm">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Renommer dossier -->
<div id="modalRenameFolder" class="modal-overlay">
    <div class="modal-content">
        <h3 style="margin-top:0;">✏️ Renommer le dossier</h3>
        <form method="POST">
            <input type="hidden" name="action" value="rename_folder">
            <input type="hidden" name="folder_id" id="renameFolderId" value="">
            <input type="text" name="new_name" id="renameFolderInput" class="modal-input" required>
            <div class="modal-buttons">
                <button type="button" class="modal-btn btn-cancel" onclick="document.getElementById('modalRenameFolder').classList.remove('open')">Annuler</button>
                <button type="submit" class="modal-btn btn-confirm">Renommer</button>
            </div>
        </form>
    </div>
</div>

<!-- Déplacer fichier -->
<div id="modalMoveFile" class="modal-overlay">
    <div class="modal-content">
        <h3 style="margin-top:0;">📂 Déplacer le fichier</h3>
        <form method="POST">
            <input type="hidden" name="action" value="move_file">
            <input type="hidden" name="file_id" id="moveFileId" value="">
            <select name="dest_folder_id" class="move-select">
                <option value="">— Racine (aucun dossier) —</option>
                <?php
                function renderFolderOptions(array $tree, $depth = 0) {
                    foreach ($tree as $f) {
                        $fid  = $f['id_dossier'] ?? $f['ID_DOSSIER'];
                        $name = $f['nom_dossier'] ?? $f['NOM_DOSSIER'];
                        echo '<option value="' . $fid . '">' . str_repeat('　', $depth) . '📁 ' . htmlspecialchars($name) . '</option>';
                        if (!empty($f['children'])) renderFolderOptions($f['children'], $depth + 1);
                    }
                }
                renderFolderOptions($folderTree);
                ?>
            </select>
            <div class="modal-buttons">
                <button type="button" class="modal-btn btn-cancel" onclick="document.getElementById('modalMoveFile').classList.remove('open')">Annuler</button>
                <button type="submit" class="modal-btn btn-confirm">Déplacer</button>
            </div>
        </form>
    </div>
</div>

<!-- Supprimer compte -->
<div id="deleteAccountModal" class="modal-overlay">
    <div class="modal-content" style="text-align:center;">
        <h3 style="color:var(--danger);margin-top:0;">Supprimer mon compte ?</h3>
        <p style="color:var(--text-muted);">Action irréversible. Tous vos fichiers et partages seront supprimés.</p>
        <div class="modal-buttons">
            <button class="modal-btn btn-cancel" onclick="document.getElementById('deleteAccountModal').classList.remove('open')">Annuler</button>
            <a href="?action=delete_account" class="modal-btn btn-confirm-delete" style="text-decoration:none;display:flex;align-items:center;justify-content:center;">Confirmer</a>
        </div>
    </div>
</div>

<!-- Supprimer fichier -->
<div id="deleteFileModal" class="modal-overlay">
    <div class="modal-content" style="text-align:center;">
        <div style="font-size:2.5rem; margin-bottom:12px;">🗑️</div>
        <h3 style="color:var(--danger); margin-top:0;">Supprimer ce fichier ?</h3>
        <p style="color:var(--text-muted); font-size:.9rem; line-height:1.6;">
            Cette action est irréversible.<br>Le fichier chiffré sera définitivement effacé du serveur.
        </p>
        <div class="modal-buttons">
            <button class="modal-btn btn-cancel"
                    onclick="document.getElementById('deleteFileModal').classList.remove('open')">
                Annuler
            </button>
            <a id="confirmDeleteFileBtn" href="#" class="modal-btn btn-confirm-delete"
               style="text-decoration:none; display:flex; align-items:center; justify-content:center;">
                Supprimer
            </a>
        </div>
    </div>
</div>
<!-- Supprimer dossier -->
<div id="deleteFolderModal" class="modal-overlay">
    <div class="modal-content" style="text-align:center;">
        <div style="font-size:2.5rem; margin-bottom:12px;">📁</div>
        <h3 style="color:var(--danger); margin-top:0;">Supprimer ce dossier ?</h3>
        <p style="color:var(--text-muted); font-size:.9rem; line-height:1.6;">
            Le dossier <strong id="deleteFolderName"></strong> et <strong>tous les fichiers qu'il contient</strong>
            seront définitivement supprimés.<br>Cette action est irréversible.
        </p>
        <div class="modal-buttons">
            <button class="modal-btn btn-cancel"
                    onclick="document.getElementById('deleteFolderModal').classList.remove('open')">
                Annuler
            </button>
            <a id="confirmDeleteFolderBtn" href="#" class="modal-btn btn-confirm-delete"
               style="text-decoration:none; display:flex; align-items:center; justify-content:center;">
                Supprimer
            </a>
        </div>
    </div>
</div>

<script>
function openDeleteFolder(fid, name) {
    document.getElementById('deleteFolderName').textContent = name;
    document.getElementById('confirmDeleteFolderBtn').href = '?action=delete_folder&id=' + fid;
    document.getElementById('deleteFolderModal').classList.add('open');
}
function openDeleteFile(fid) {
    document.getElementById('confirmDeleteFileBtn').href = '?action=delete&id=' + fid;
    document.getElementById('deleteFileModal').classList.add('open');
    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
}
// Menus dropdown
function toggleMenu(e, id) {
    e.stopPropagation();
    document.querySelectorAll('.dropdown').forEach(d => { if (d.id !== id) d.classList.remove('show'); });
    document.getElementById(id).classList.toggle('show');
}
window.addEventListener('click', e => {
    if (!e.target.closest('.menu-container')) document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
});

// Fermer modals en cliquant l'overlay
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
});

// Arbre de dossiers
function toggleSubfolder(fid) {
    const sub = document.getElementById('sub-' + fid);
    const chv = document.getElementById('chv-' + fid);
    if (!sub) return;
    sub.classList.toggle('collapsed');
    chv.classList.toggle('open');
}
function navFolder(e, fid) {
    if (e.target.closest('.folder-actions') || e.target.closest('.chevron')) return;
    location.href = '?folder=' + fid;
}

// Modals dossiers
function openCreateFolder(parentId) {
    document.getElementById('createParentId').value = parentId ?? '';
    document.getElementById('modalCreateFolder').classList.add('open');
}
function openRenameFolder(fid, currentName) {
    document.getElementById('renameFolderId').value = fid;
    document.getElementById('renameFolderInput').value = currentName;
    document.getElementById('modalRenameFolder').classList.add('open');
}
function openMoveFile(fid) {
    document.getElementById('moveFileId').value = fid;
    document.getElementById('modalMoveFile').classList.add('open');
    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
}
</script>
</body>
</html>