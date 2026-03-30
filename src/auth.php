<?php
/**
 * Crée un nouvel utilisateur avec son mot de passe hashé et sa paire de clés RSA.
 */
function registerUser($pdo, $username, $password) {

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Configuration de la paire de clés RSA 2048 bits
    $config = [
        "digest_alg"       => "sha256",
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ];

    $res = openssl_pkey_new($config);
    if (!$res) {
        return ["success" => false, "message" => "Erreur de génération des clés RSA."];
    }

    $privateKeyEncrypted = "";
    openssl_pkey_export($res, $privateKeyEncrypted, $password);

    $keyDetails = openssl_pkey_get_details($res);
    $publicKey  = $keyDetails["key"];

    $sql  = "INSERT INTO UTILISATEUR (USERNAME, PASSWORD_HASH, CLE_PUBLIQUE, CLE_PRIVEE_CHIFFREE) 
             VALUES (:username, :hash, :pub, :priv)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':username' => $username,
            ':hash'     => $passwordHash,
            ':pub'      => $publicKey,
            ':priv'     => $privateKeyEncrypted
        ]);
        return ["success" => true, "message" => "Utilisateur créé avec succès."];
    } catch (\PDOException $e) {
        if ($e->getCode() == '23505') {
            return ["success" => false, "message" => "Ce nom d'utilisateur est déjà utilisé."];
        }
        return ["success" => false, "message" => "Erreur base de données : " . $e->getMessage()];
    }
}


/**
 * Connecte un utilisateur après vérification du mot de passe et de sa clé privée RSA.
 */
function loginUser($pdo, $username, $password) {

    $sql  = "SELECT ID_USER, PASSWORD_HASH, CLE_PRIVEE_CHIFFREE FROM UTILISATEUR WHERE USERNAME = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {

        // Vérifie que le mot de passe permet bien de déverrouiller la clé privée RSA
        $privateKeyTest = openssl_pkey_get_private($user['cle_privee_chiffree'], $password);

        if ($privateKeyTest !== false) {
            $_SESSION['user_id']  = $user['id_user'];
            $_SESSION['username'] = $username;

            // Conserve le mot de passe en session pour déchiffrer les fichiers côté serveur
            $_SESSION['crypto_pass'] = $password;

            return ["success" => true, "message" => "Connexion réussie."];
        } else {
            return ["success" => false, "message" => "Erreur critique : Impossible de déchiffrer le trousseau de clés."];
        }
    }

    return ["success" => false, "message" => "Identifiants incorrects."];
}


/**
 * Supprime le compte d'un utilisateur ainsi que tous ses fichiers physiques.
 * Utilise une transaction pour garantir la cohérence base/disque.
 */
function deleteUserAccount($pdo, $userId) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT CHEMIN_STOCKAGE FROM FICHIER WHERE ID_USER = ?");
        $stmt->execute([$userId]);
        $files = $stmt->fetchAll();

        $uploadDir = dirname(__DIR__) . '/uploads/';
        foreach ($files as $file) {
            $filePath = $uploadDir . $file['chemin_stockage'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM UTILISATEUR WHERE ID_USER = ?");
        $stmt->execute([$userId]);

        $pdo->commit();
        return ["success" => true, "message" => "Compte et données supprimés avec succès."];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ["success" => false, "message" => "Erreur lors de la suppression : " . $e->getMessage()];
    }
}