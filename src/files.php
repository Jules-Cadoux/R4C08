<?php
function uploadAndEncryptFile($pdo, $userId, $fileInfo) {
    if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
        return ["success" => false, "message" => "Erreur de transmission du fichier."];
    }

    $originalName = basename($fileInfo['name']);
    $tmpPath = $fileInfo['tmp_name'];
    $fileContent = file_get_contents($tmpPath);

    $aesKey = openssl_random_pseudo_bytes(32);
    $iv = openssl_random_pseudo_bytes(16);

    $encryptedContent = openssl_encrypt($fileContent, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
    if ($encryptedContent === false) {
        return ["success" => false, "message" => "Échec critique : Impossible de chiffrer le fichier."];
    }

    $stmt = $pdo->prepare("SELECT CLE_PUBLIQUE FROM UTILISATEUR WHERE ID_USER = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ["success" => false, "message" => "Propriétaire introuvable."];
    }

    $keyPayload = base64_encode($aesKey) . '::' . base64_encode($iv);
    $encryptedAesKey = '';
    $rsaSuccess = openssl_public_encrypt($keyPayload, $encryptedAesKey, $user['cle_publique']);
    
    if (!$rsaSuccess) {
        return ["success" => false, "message" => "Échec critique : Impossible de verrouiller la clé AES."];
    }
    $encryptedAesKeyB64 = base64_encode($encryptedAesKey);

    $storageFilename = bin2hex(random_bytes(16)) . '.enc';
    $storagePath = __DIR__ . '/../uploads/' . $storageFilename;
    
    if (file_put_contents($storagePath, $encryptedContent) === false) {
         return ["success" => false, "message" => "Impossible d'écrire le fichier chiffré sur le disque."];
    }

    $sql = "INSERT INTO FICHIER (ID_USER, NOM_FICHIER, CHEMIN_STOCKAGE, CLE_AES_CHIFFREE) 
            VALUES (:user_id, :nom, :chemin, :cle_aes)";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([
            ':user_id' => $userId,
            ':nom' => $originalName,
            ':chemin' => $storageFilename,
            ':cle_aes' => $encryptedAesKeyB64
        ]);
        @unlink($tmpPath); 
        return ["success" => true, "message" => "Fichier '$originalName' chiffré et stocké avec succès."];
    } catch (\PDOException $e) {
        @unlink($storagePath);
        return ["success" => false, "message" => "Erreur base de données : " . $e->getMessage()];
    }
}

function getUserFiles($pdo, $userId) {
    $sql = "SELECT ID_FICHIER, NOM_FICHIER, DATE_UPLOAD FROM FICHIER WHERE ID_USER = :id ORDER BY DATE_UPLOAD DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Recherche dans les fichiers personnels de l'utilisateur par nom (insensible à la casse).
 *
 * @param PDO    $pdo    Connexion PDO.
 * @param int    $userId Identifiant de l'utilisateur.
 * @param string $query  Terme de recherche (peut être vide pour tout retourner).
 * @return array         Liste des fichiers correspondants.
 */
function searchUserFiles($pdo, $userId, $query) {
    $query = trim($query);

    // Si la requête est vide, on délègue à getUserFiles pour rester cohérent.
    if ($query === '') {
        return getUserFiles($pdo, $userId);
    }

    $sql = "SELECT ID_FICHIER, NOM_FICHIER, DATE_UPLOAD
            FROM FICHIER
            WHERE ID_USER = :id
              AND LOWER(NOM_FICHIER) LIKE LOWER(:q)
            ORDER BY DATE_UPLOAD DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $userId,
        ':q'  => '%' . $query . '%',
    ]);
    return $stmt->fetchAll();
}

/**
 * Recherche dans les fichiers partagés avec l'utilisateur par nom (insensible à la casse).
 *
 * @param PDO    $pdo    Connexion PDO.
 * @param int    $userId Identifiant de l'utilisateur.
 * @param string $query  Terme de recherche (peut être vide pour tout retourner).
 * @return array         Liste des fichiers partagés correspondants.
 */
function searchSharedFiles($pdo, $userId, $query) {
    $query = trim($query);

    // Si la requête est vide, on délègue à getSharedWithMe pour rester cohérent.
    if ($query === '') {
        return getSharedWithMe($pdo, $userId);
    }

    $sql = "SELECT f.ID_FICHIER, f.NOM_FICHIER, u.USERNAME AS OWNER_NAME, p.CLE_AES_PARTAGE_CHIFFREE
            FROM EST_PARTAGE_AVEC p
            JOIN FICHIER f     ON p.ID_FICHIER = f.ID_FICHIER
            JOIN UTILISATEUR u ON f.ID_USER    = u.ID_USER
            WHERE p.ID_USER = :uid
              AND LOWER(f.NOM_FICHIER) LIKE LOWER(:q)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid' => $userId,
        ':q'   => '%' . $query . '%',
    ]);
    return $stmt->fetchAll();
}

function downloadFile($pdo, $userId, $fileId, $userPassword) {
    $sql = "SELECT f.NOM_FICHIER, f.CHEMIN_STOCKAGE, u.CLE_PRIVEE_CHIFFREE,
            COALESCE(p.CLE_AES_PARTAGE_CHIFFREE, f.CLE_AES_CHIFFREE) as CLE_A_UTILISER
            FROM FICHIER f
            JOIN UTILISATEUR u ON u.ID_USER = :uid
            LEFT JOIN EST_PARTAGE_AVEC p ON f.ID_FICHIER = p.ID_FICHIER AND p.ID_USER = :uid
            WHERE f.ID_FICHIER = :fid 
            AND (f.ID_USER = :uid OR p.ID_USER = :uid)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId, ':fid' => $fileId]);
    $data = $stmt->fetch();

    if (!$data) return ["success" => false, "message" => "Accès refusé ou fichier inexistant."];

    $privKey = openssl_pkey_get_private($data['cle_privee_chiffree'], $userPassword);
    
    $decryptedAesPayload = '';
    openssl_private_decrypt(base64_decode($data['cle_a_utiliser']), $decryptedAesPayload, $privKey);

    if (empty($decryptedAesPayload)) return ["success" => false, "message" => "Échec du déchiffrement."];

    list($aesKeyB64, $ivB64) = explode('::', $decryptedAesPayload);
    
    $content = file_get_contents(dirname(__DIR__) . '/uploads/' . $data['chemin_stockage']);
    $decrypted = openssl_decrypt($content, 'aes-256-cbc', base64_decode($aesKeyB64), OPENSSL_RAW_DATA, base64_decode($ivB64));

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $data['nom_fichier'] . '"');
    echo $decrypted;
    exit;
}

function deleteFile($pdo, $userId, $fileId) {
    $stmt = $pdo->prepare("SELECT CHEMIN_STOCKAGE FROM FICHIER WHERE ID_FICHIER = :file_id AND ID_USER = :user_id");
    $stmt->execute([':file_id' => $fileId, ':user_id' => $userId]);
    $fileData = $stmt->fetch();

    if (!$fileData) {
        return ["success" => false, "message" => "Fichier introuvable ou accès refusé."];
    }

    $filePath = dirname(__DIR__) . '/uploads/' . $fileData['chemin_stockage'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    $stmt = $pdo->prepare("DELETE FROM FICHIER WHERE ID_FICHIER = :file_id AND ID_USER = :user_id");
    $stmt->execute([':file_id' => $fileId, ':user_id' => $userId]);

    return ["success" => true, "message" => "Fichier supprimé avec succès."];
}

function shareFile($pdo, $ownerId, $fileId, $targetUsername, $ownerPassword) {
    $stmt = $pdo->prepare("SELECT ID_USER, CLE_PUBLIQUE FROM UTILISATEUR WHERE USERNAME = :u");
    $stmt->execute([':u' => $targetUsername]);
    $target = $stmt->fetch();

    if (!$target) return ["success" => false, "message" => "Utilisateur destinataire introuvable."];
    if ($target['id_user'] == $ownerId) return ["success" => false, "message" => "Vous ne pouvez pas partager avec vous-même."];

    $stmt = $pdo->prepare("SELECT f.CLE_AES_CHIFFREE, u.CLE_PRIVEE_CHIFFREE 
                           FROM FICHIER f JOIN UTILISATEUR u ON f.ID_USER = u.ID_USER 
                           WHERE f.ID_FICHIER = :fid AND f.ID_USER = :oid");
    $stmt->execute([':fid' => $fileId, ':oid' => $ownerId]);
    $data = $stmt->fetch();

    if (!$data) return ["success" => false, "message" => "Fichier introuvable."];

    $privKey = openssl_pkey_get_private($data['cle_privee_chiffree'], $ownerPassword);
    $decryptedPayload = '';
    openssl_private_decrypt(base64_decode($data['cle_aes_chiffree']), $decryptedPayload, $privKey);

    $encryptedForTarget = '';
    openssl_public_encrypt($decryptedPayload, $encryptedForTarget, $target['cle_publique']);

    $sql = "INSERT INTO EST_PARTAGE_AVEC (ID_FICHIER, ID_USER, CLE_AES_PARTAGE_CHIFFREE) 
            VALUES (:fid, :uid, :cle) 
            ON CONFLICT (ID_FICHIER, ID_USER) DO NOTHING";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':fid' => $fileId, ':uid' => $target['id_user'], ':cle' => base64_encode($encryptedForTarget)]);

    return ["success" => true, "message" => "Fichier partagé avec succès avec $targetUsername."];
}

function getSharedWithMe($pdo, $userId) {
    $sql = "SELECT f.ID_FICHIER, f.NOM_FICHIER, u.USERNAME as OWNER_NAME, p.CLE_AES_PARTAGE_CHIFFREE
            FROM EST_PARTAGE_AVEC p
            JOIN FICHIER f ON p.ID_FICHIER = f.ID_FICHIER
            JOIN UTILISATEUR u ON f.ID_USER = u.ID_USER
            WHERE p.ID_USER = :uid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}


// ── DOSSIERS ────────────────────────────────────────────────────────────

function createFolder($pdo, $userId, $name, $parentId = null) {
    $name = trim($name);
    if ($name === '') return ["success" => false, "message" => "Le nom ne peut pas être vide."];

    $stmt = $pdo->prepare("INSERT INTO DOSSIER (ID_USER, ID_PARENT, NOM_DOSSIER) VALUES (:uid, :pid, :nom)");
    $stmt->execute([':uid' => $userId, ':pid' => $parentId ?: null, ':nom' => $name]);
    return ["success" => true, "message" => "Dossier '$name' créé."];
}

function deleteFolder($pdo, $userId, $folderId) {
    // Récupère récursivement tous les IDs de dossiers enfants
    function collectFolderIds($pdo, $folderId): array {
        $ids = [(int)$folderId];
        $stmt = $pdo->prepare("SELECT ID_DOSSIER FROM DOSSIER WHERE ID_PARENT = :pid");
        $stmt->execute([':pid' => $folderId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
            $ids = array_merge($ids, collectFolderIds($pdo, $childId));
        }
        return $ids;
    }

    // Vérifie que le dossier appartient bien à l'utilisateur
    $stmt = $pdo->prepare("SELECT 1 FROM DOSSIER WHERE ID_DOSSIER = :fid AND ID_USER = :uid");
    $stmt->execute([':fid' => $folderId, ':uid' => $userId]);
    if (!$stmt->fetch()) return ["success" => false, "message" => "Dossier introuvable."];

    $allIds = collectFolderIds($pdo, $folderId);
    $in     = implode(',', $allIds);

    // Récupère les chemins des fichiers physiques à supprimer
    $stmt = $pdo->query("SELECT CHEMIN_STOCKAGE FROM FICHIER WHERE ID_DOSSIER IN ($in)");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $chemin) {
        $path = dirname(__DIR__) . '/uploads/' . $chemin;
        if (file_exists($path)) @unlink($path);
    }

    // Supprime les fichiers en base puis les dossiers (cascade SQL pour les sous-dossiers)
    $pdo->exec("DELETE FROM FICHIER  WHERE ID_DOSSIER  IN ($in)");
    $pdo->exec("DELETE FROM DOSSIER  WHERE ID_DOSSIER  IN ($in)");

    return ["success" => true, "message" => "Dossier et fichiers supprimés."];
}

function renameFolder($pdo, $userId, $folderId, $newName) {
    $newName = trim($newName);
    if ($newName === '') return ["success" => false, "message" => "Nom invalide."];
    $stmt = $pdo->prepare("UPDATE DOSSIER SET NOM_DOSSIER = :nom WHERE ID_DOSSIER = :fid AND ID_USER = :uid");
    $stmt->execute([':nom' => $newName, ':fid' => $folderId, ':uid' => $userId]);
    return ["success" => true, "message" => "Dossier renommé."];
}

function moveFileToFolder($pdo, $userId, $fileId, $folderId = null) {
    $stmt = $pdo->prepare("UPDATE FICHIER SET ID_DOSSIER = :did WHERE ID_FICHIER = :fid AND ID_USER = :uid");
    $stmt->execute([':did' => $folderId ?: null, ':fid' => $fileId, ':uid' => $userId]);
    return ["success" => true, "message" => "Fichier déplacé."];
}

function getUserFolders($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM DOSSIER WHERE ID_USER = :uid ORDER BY ID_PARENT NULLS FIRST, NOM_DOSSIER");
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFolderFiles($pdo, $userId, $folderId) {
    $stmt = $pdo->prepare(
        "SELECT ID_FICHIER, NOM_FICHIER, DATE_UPLOAD FROM FICHIER 
         WHERE ID_USER = :uid AND ID_DOSSIER = :did ORDER BY DATE_UPLOAD DESC"
    );
    $stmt->execute([':uid' => $userId, ':did' => $folderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function shareFolder($pdo, $ownerId, $folderId, $targetUsername) {
    $stmt = $pdo->prepare("SELECT ID_USER FROM UTILISATEUR WHERE USERNAME = :u");
    $stmt->execute([':u' => $targetUsername]);
    $target = $stmt->fetch();
    if (!$target) return ["success" => false, "message" => "Utilisateur introuvable."];
    if ($target['id_user'] == $ownerId) return ["success" => false, "message" => "Vous ne pouvez pas partager avec vous-même."];

    $stmt = $pdo->prepare("SELECT 1 FROM DOSSIER WHERE ID_DOSSIER = :fid AND ID_USER = :uid");
    $stmt->execute([':fid' => $folderId, ':uid' => $ownerId]);
    if (!$stmt->fetch()) return ["success" => false, "message" => "Dossier introuvable."];

    $stmt = $pdo->prepare(
        "INSERT INTO DOSSIER_PARTAGE (ID_DOSSIER, ID_USER) VALUES (:did, :uid) 
         ON CONFLICT DO NOTHING"
    );
    $stmt->execute([':did' => $folderId, ':uid' => $target['id_user']]);
    return ["success" => true, "message" => "Dossier partagé avec $targetUsername."];
}

function getSharedFoldersWithMe($pdo, $userId) {
    $sql = "SELECT d.ID_DOSSIER, d.NOM_DOSSIER, u.USERNAME AS OWNER_NAME
            FROM DOSSIER_PARTAGE dp
            JOIN DOSSIER d      ON dp.ID_DOSSIER = d.ID_DOSSIER
            JOIN UTILISATEUR u  ON d.ID_USER     = u.ID_USER
            WHERE dp.ID_USER = :uid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Construit l'arbre récursif à partir d'une liste plate
function buildFolderTree(array $folders, $parentId = null): array {
    $tree = [];
    foreach ($folders as $f) {
        $pid = $f['id_parent'] ?? $f['ID_PARENT'] ?? null;
        if ((int)$pid === (int)$parentId || ($parentId === null && $pid === null)) {
            $fid = $f['id_dossier'] ?? $f['ID_DOSSIER'];
            $f['children'] = buildFolderTree($folders, $fid);
            $tree[] = $f;
        }
    }
    return $tree;
}