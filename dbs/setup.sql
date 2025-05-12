

-- working initial database --
-- Supprimer la base de données existante si elle existe
DROP DATABASE IF EXISTS occ_db;

-- Créer une nouvelle base de données
CREATE DATABASE occ_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE occ_db;

-- Tableaux des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Stocké en clair
    role ENUM('admin', 'diacre', 'pasteur') NOT NULL,
    last_login DATETIME,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tableaux des formations
CREATE TABLE formations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom ENUM('Isoko Classe 1', 'Isoko Classe 2', 'Isoko Classe 3') NOT NULL,
    promotion VARCHAR(50) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tableaux des membres
CREATE TABLE members (
    id VARCHAR(5) PRIMARY KEY, -- Sequential 5-digit ID (e.g., 00001)
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE NOT NULL,
    province_naissance VARCHAR(100),
    pays_naissance VARCHAR(100),
    telephone VARCHAR(20),
    email VARCHAR(100),
    residence VARCHAR(255),
    profession VARCHAR(100),
    etat_civil ENUM('Célibataire', 'Marié(e)', 'Veuf(ve)', 'Divorcé(e)') NOT NULL,
    conjoint_nom_prenom VARCHAR(200),
    date_nouvelle_naissance DATE,
    eglise_nouvelle_naissance VARCHAR(100),
    lieu_nouvelle_naissance VARCHAR(100),
    formation_id INT,
    oikos_id INT,
    departement ENUM('Media', 'Comptabilité', 'Sécurité', 'Chorale', 'SundaySchool', 'Protocole'),
    fiche_membre VARCHAR(255), -- Path to PDF (e.g., uploads/00001_Nom.pdf)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (formation_id) REFERENCES formations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tableaux des Oikos
CREATE TABLE oikos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    quartier VARCHAR(100) NOT NULL,
    lieu VARCHAR(255) NOT NULL,
    president_id VARCHAR(5),
    president_telephone VARCHAR(20),
    vice_president_id VARCHAR(5),
    vice_president_telephone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (president_id) REFERENCES members(id),
    FOREIGN KEY (vice_president_id) REFERENCES members(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tableaux des enfants (Sunday School)
CREATE TABLE children (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    parent_nom VARCHAR(100) NOT NULL,
    parent_prenom VARCHAR(100) NOT NULL,
    parent_telephone1 VARCHAR(20) NOT NULL,
    parent_telephone2 VARCHAR(20),
    parent_email VARCHAR(100),
    parent_id VARCHAR(5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES members(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tableaux des présences aux formations
CREATE TABLE formation_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(5) NOT NULL,
    formation_id INT NOT NULL,
    date_presence DATE NOT NULL,
    present BOOLEAN DEFAULT TRUE,
    points INT DEFAULT 0,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (formation_id) REFERENCES formations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tableaux des événements
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(5) NOT NULL,
    type ENUM('Baptême', 'Mariage') NOT NULL,
    date_evenement DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tableaux des logs
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    browser VARCHAR(255),
    os VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for oikos_id in members after oikos table is created
ALTER TABLE members
ADD FOREIGN KEY (oikos_id) REFERENCES oikos(id);

-- Données de test
INSERT INTO users (username, password, role) 
VALUES ('admin', 'admin123', 'admin');

INSERT INTO formations (nom, promotion, date_debut, date_fin) 
VALUES ('Isoko Classe 1', '2025-1', '2025-01-01', '2025-03-01');

INSERT INTO members (id, nom, prenom, date_naissance, etat_civil) 
VALUES ('00001', 'Dupont', 'Jean', '1980-05-15', 'Célibataire');

INSERT INTO oikos (nom, quartier, lieu, president_id, president_telephone) 
VALUES ('Oikos Central', 'Centre-Ville', 'Église OCC', '00001', '0123456789');

UPDATE members SET oikos_id = 1 WHERE id = '00001';