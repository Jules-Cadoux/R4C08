# 🛡️ SecureCloud 

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.1+-777bb4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/PostgreSQL-latest-336791?style=for-the-badge&logo=postgresql&logoColor=white" alt="Postgres">
  <img src="https://img.shields.io/badge/Security-AES--256--RSA-success?style=for-the-badge" alt="Security">
</p>

**SecureCloud** est une solution de stockage cloud hautement sécurisée basée sur un modèle de **chiffrement hybride**. L'application garantit que seul le propriétaire (ou les destinataires autorisés) peut accéder au contenu des fichiers.

---

## ✨ Aperçu du projet

| Connexion Sécurisée | Dashboard Moderne |
| :---: | :---: |
| ![Login](https://via.placeholder.com/400x250?text=Screen+Connexion) | ![Dashboard](https://via.placeholder.com/400x250?text=Screen+Dashboard) |
*(Note : Remplace ces liens par les screenshots de ton dossier public une fois pushés)*

---

## 🔒 Architecture de Sécurité

Le projet implémente les standards de l'industrie pour assurer une confidentialité **"Zero-Knowledge"** :

* **Authentification** : Hachage des mots de passe avec **Bcrypt**.
* **Chiffrement des données** : Utilisation de **AES-256-CBC** pour le contenu des fichiers.
* **Gestion des identités** : Paire de clés **RSA-2048** par utilisateur.
* **Protection des clés** : La clé privée RSA est elle-même chiffrée par le secret de l'utilisateur.

### 🔄 Flux de Chiffrement Hybride
1. Le fichier est chiffré par une **Clé AES** unique.
2. La Clé AES est "scellée" (chiffrée) par la **Clé Publique RSA** de l'utilisateur.
3. Pour le partage, la Clé AES est re-chiffrée avec la **Clé Publique du destinataire**.

---

## 🛠️ Installation Rapide

### 1. Cloner le projet
```bash
git clone [https://github.com/Jules-Cadoux/R4C08.git](https://github.com/Jules-Cadoux/R4C08.git)
cd R4C08
2. Configurer la base de données
Importez le fichier database.sql dans votre instance PostgreSQL.
Structure conforme au diagramme de classe.

3. Variables d'environnement
Créez le fichier config/database.php (ignoré par Git pour la sécurité) :
<?php
$host = 'votre_host';
$db   = 'votre_db';
$user = 'votre_user';
$pass = 'votre_pass';
🚀 Fonctionnalités Utilisateur
[x] Explorateur de fichiers : Navigation par arborescence dans la barre latérale.

[x] Partage sécurisé : Envoi de fichiers par pseudo utilisateur.

[x] Menu Contextuel : Actions rapides (Télécharger, Partager, Supprimer) via menu "trois points".

[x] Zone de danger : Suppression définitive du compte et des données physiques.

<p align="center">
Projet réalisé dans le cadre de la ressource <b>R4C08</b>
</p>