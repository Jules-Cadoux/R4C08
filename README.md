# 🛡️ SecureCloud 

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.1+-777bb4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/PostgreSQL-latest-336791?style=for-the-badge&logo=postgresql&logoColor=white" alt="Postgres">
  <img src="https://img.shields.io/badge/Security-AES--256--RSA-success?style=for-the-badge" alt="Security">
</p>

**SecureCloud** est une solution de stockage cloud hautement sécurisée basée sur un modèle de **chiffrement hybride**. L'application garantit une confidentialité totale : seul le propriétaire (ou les destinataires autorisés) peut accéder au contenu des fichiers.

---

## 🔒 Architecture de Sécurité

Le projet implémente les standards de l'industrie pour assurer une confidentialité **"Zero-Knowledge"** :

* **Authentification** : Hachage des mots de passe avec **Bcrypt** pour protéger les accès.
* **Chiffrement des données** : Utilisation de **AES-256-CBC** pour le contenu des fichiers.
* **Gestion des identités** : Paire de clés **RSA-2048** par utilisateur.
* **Protection des clés** : La clé privée RSA est chiffrée par le secret (mot de passe) de l'utilisateur.

### 🔄 Flux de Chiffrement Hybride
Le système utilise le principe de l'enveloppe numérique :
1. Le fichier $F$ est chiffré par une **Clé AES** unique $K$ : $$C = E_{AES}(F, K)$$
2. La Clé AES est "scellée" par la **Clé Publique RSA** de l'utilisateur ($Pub_{u}$) : $$K_{sealed} = E_{RSA}(K, Pub_{u})$$

---

## 🛠️ Installation

### 1. Cloner le projet
```bash
git clone [https://github.com/Jules-Cadoux/R4C08.git](https://github.com/Jules-Cadoux/R4C08.git)
cd R4C08
```

### 2. Configurer la base de données
Importez le fichier `database.sql` dans votre instance PostgreSQL pour créer les tables `UTILISATEUR`, `FICHIER` et `EST_PARTAGE_AVEC`.

### 3. Variables d'environnement
Créez le fichier `config/database.php` (ce fichier est ignoré par Git pour la sécurité) :

```php
<?php
$host = 'votre_host';
$db   = 'votre_db';
$user = 'votre_user';
$pass = 'votre_pass';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass);
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>
```

---

## 🚀 Fonctionnalités Utilisateur

- [x] **Explorateur de fichiers** : Navigation par arborescence dans la barre latérale.
- [x] **Partage sécurisé** : Envoi de fichiers par pseudo utilisateur.
- [x] **Menu Contextuel** : Actions rapides (Télécharger, Partager, Supprimer) via menu "trois points".
- [x] **Zone de danger** : Suppression définitive du compte et des données physiques associées.

---

<p align="center">
  Projet réalisé dans le cadre de la ressource <b>R4C08</b>
</p>
