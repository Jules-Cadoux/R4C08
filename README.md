# 🛡️ SecureCloud - Déploiement Alwaysdata

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.1+-777bb4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/PostgreSQL-Alwaysdata-336791?style=for-the-badge&logo=postgresql&logoColor=white" alt="Postgres">
  <img src="https://img.shields.io/badge/Security-AES--256--CBC-blue?style=for-the-badge" alt="AES">
  <img src="https://img.shields.io/badge/Security-RSA--2048-success?style=for-the-badge" alt="RSA">
</p>

**SecureCloud** est une solution de stockage cloud sécurisée déployée sur Alwaysdata, basée sur un modèle de **chiffrement hybride**. L'application garantit une confidentialité totale : seul le propriétaire peut accéder au contenu de ses fichiers.

---

## 🔒 Architecture de Sécurité

Le projet assure une confidentialité **"Zero-Knowledge"** :

* **Authentification** : Hachage des mots de passe avec **Bcrypt**.
* **Chiffrement Symétrique** : **AES-256-CBC** pour les fichiers.
* **Chiffrement Asymétrique** : **RSA-2048** pour le scellage des clés de session.
* **Protection** : La clé privée RSA est stockée chiffrée par le mot de passe de l'utilisateur.

### 🔄 Flux Cryptographique
1. Le fichier $F$ est chiffré par une **Clé AES** unique $K$ : $$C = E_{AES}(F, K)$$
2. La Clé AES est scellée par la **Clé Publique RSA** de l'utilisateur ($Pub_{u}$) : $$K_{sealed} = E_{RSA}(K, Pub_{u})$$

---

## 🛠️ Configuration du Projet

### 1. Structure de la Base de Données
Le système repose sur trois tables principales configurées sur l'instance PostgreSQL d'Alwaysdata :
* `UTILISATEURS` : Stockage des identifiants et des paires de clés RSA.
* `FICHIER` : Métadonnées des fichiers et clés AES scellées.
* `EST_PARTAGE_AVEC` : Gestion des accès tiers par re-chiffrement RSA.

*Référence : Voir le diagramme de classe pour le détail des champs.*

### 2. Variables d'environnement
Le fichier `config/database.php` (exclu du dépôt pour la sécurité) contient les accès distants à Alwaysdata :

```php
<?php
$host = 'postgresql-votre-compte.alwaysdata.net';
$db   = 'votre_base';
$user = 'votre_user';
$pass = 'votre_password';
?>
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    die("Erreur critique : Impossible de se connecter à la base Alwaysdata. Détail : " . $e->getMessage());
}
?>
```

---

## 🚀 Fonctionnalités Opérationnelles

- [x] **Explorateur** : Navigation par arborescence (Sidebar).
- [x] **Partage** : Envoi sécurisé par scellage RSA du destinataire.
- [x] **Menu Contextuel** : Actions rapides sur les fichiers.
- [x] **Droit à l'oubli** : Suppression intégrale du compte et des fichiers physiques associés.

---

<p align="center">
  Projet réalisé dans le cadre de la ressource <b>R4C08</b>
</p>
