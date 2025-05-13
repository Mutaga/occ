-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2025 at 02:04 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `occ_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `sexe` enum('Masculin','Féminin') DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `categorie` enum('Enfant','Teenager') DEFAULT NULL,
  `parent_nom` varchar(100) NOT NULL,
  `parent_prenom` varchar(100) NOT NULL,
  `parent_telephone1` varchar(20) NOT NULL,
  `parent_telephone2` varchar(20) DEFAULT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `parent_id` varchar(5) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `children`
--

INSERT INTO `children` (`id`, `nom`, `prenom`, `sexe`, `date_naissance`, `categorie`, `parent_nom`, `parent_prenom`, `parent_telephone1`, `parent_telephone2`, `parent_email`, `parent_id`, `created_at`) VALUES
(1, 'Ntwari', 'Chris Azael Anael Ael Rael', 'Masculin', '2012-02-09', 'Teenager', 'Kwizera', 'Anaclet', '+25771557480', '+25771557480', 'kg@mail.com', '00004', '2025-04-25 11:46:13'),
(2, 'Arakaza', 'Chris', 'Masculin', '2020-06-09', 'Enfant', 'Kaburungu', 'Paul', '+25771557480', '+25771557480', 'kg@mail.com', '00003', '2025-04-25 12:09:42'),
(3, 'Irakoze', 'Benit', 'Masculin', '2018-06-09', 'Enfant', 'Kamariza', 'Claudette', '796253625', '68525325', 'kgdddd@mail.com', '00004', '2025-05-12 16:10:18');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `member_id` varchar(5) NOT NULL,
  `type` enum('Baptême','Mariage') NOT NULL,
  `date_evenement` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `member_id`, `type`, `date_evenement`, `created_at`) VALUES
(2, '00002', 'Mariage', '2025-01-18', '2025-04-25 09:28:31'),
(3, '00005', 'Baptême', '2025-05-15', '2025-05-12 18:20:57'),
(4, '00006', 'Baptême', '2025-05-15', '2025-05-12 18:26:27');

-- --------------------------------------------------------

--
-- Table structure for table `formations`
--

CREATE TABLE `formations` (
  `id` int(11) NOT NULL,
  `nom` enum('Isoko Classe 1','Isoko Classe 2','Isoko Classe 3') NOT NULL,
  `promotion` varchar(50) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','pending','completed') NOT NULL DEFAULT 'pending',
  `responsible_id` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `formations`
--

INSERT INTO `formations` (`id`, `nom`, `promotion`, `date_debut`, `date_fin`, `created_at`, `status`, `responsible_id`) VALUES
(1, 'Isoko Classe 1', '2025-1', '2025-01-01', '2025-03-01', '2025-04-24 14:27:34', 'completed', '00001'),
(2, 'Isoko Classe 2', 'Mai 2025 f', '2025-05-18', '2025-08-14', '2025-05-12 09:20:34', 'pending', '00002'),
(3, 'Isoko Classe 3', '2025', '2025-05-12', '2025-11-10', '2025-05-12 09:21:19', 'active', '00003'),
(4, 'Isoko Classe 2', 'Mai 2025', '2025-05-19', '2025-06-19', '2025-05-12 17:56:35', 'pending', '00002'),
(5, 'Isoko Classe 1', 'Ya mbere 2025', '2025-05-06', '2025-05-31', '2025-05-12 21:36:50', 'active', '00006');

-- --------------------------------------------------------

--
-- Table structure for table `formation_attendance`
--

CREATE TABLE `formation_attendance` (
  `id` int(11) NOT NULL,
  `member_id` varchar(5) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `date_presence` date NOT NULL,
  `present` tinyint(1) DEFAULT 1,
  `points` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `browser` varchar(255) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `ip_address`, `browser`, `os`, `created_at`) VALUES
(1, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:28:53'),
(2, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:29:07'),
(3, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:34:11'),
(4, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:34:11'),
(5, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:34:35'),
(6, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:34:44'),
(7, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:35:39'),
(8, 1, 'Erreur création formation: Une formation avec ce nom et cette promotion existe déjà.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:35:39'),
(9, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:35:54'),
(10, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:35:54'),
(11, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:36:50'),
(12, 1, 'Création formation: 5 (Nom: Isoko Classe 1, Promotion: Ya mbere 2025, Statut: active, Responsable: 00006)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:36:50'),
(13, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:36:59'),
(14, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:36:59'),
(15, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:37:12'),
(16, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:37:15'),
(17, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:37:19'),
(18, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:37:23'),
(19, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:37:24'),
(20, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:37:24'),
(21, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:38:20'),
(22, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:38:20'),
(23, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:48:45'),
(24, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:48:45'),
(25, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:49:19'),
(26, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:49:19'),
(27, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:50:56'),
(28, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:50:56'),
(29, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:51:06'),
(30, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:51:06'),
(31, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:51:47'),
(32, 1, 'Création session: 2 (Formation: 5, Nom: Chapitre 1er, Statut: planned, Enseignant: 00002)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:51:47'),
(33, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:52:16'),
(34, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:52:16'),
(35, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:53:05'),
(36, 1, 'Création session: 3 (Formation: 5, Nom: Chapitre 2, Statut: planned, Enseignant: 00002)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 21:53:05'),
(37, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-13 07:19:35'),
(38, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-13 07:19:36'),
(39, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-13 12:02:49'),
(40, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-13 12:02:51'),
(41, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-13 12:02:57'),
(42, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-13 12:02:59'),
(43, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-13 12:03:43'),
(44, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-13 12:03:43');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` varchar(5) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date NOT NULL,
  `province_naissance` varchar(100) DEFAULT NULL,
  `pays_naissance` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `residence` varchar(255) DEFAULT NULL,
  `profession` varchar(100) DEFAULT NULL,
  `etat_civil` enum('Célibataire','Marié(e)','Veuf(ve)','Divorcé(e)') NOT NULL,
  `conjoint_nom_prenom` varchar(200) DEFAULT NULL,
  `date_nouvelle_naissance` date DEFAULT NULL,
  `eglise_nouvelle_naissance` varchar(100) DEFAULT NULL,
  `lieu_nouvelle_naissance` varchar(100) DEFAULT NULL,
  `formation_id` int(11) DEFAULT NULL,
  `oikos_id` int(11) DEFAULT NULL,
  `departement` enum('Media','Comptabilité','Sécurité','Chorale','SundaySchool','Protocole','Pastorat','Diaconat') DEFAULT NULL,
  `fiche_membre` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sexe` enum('Masculin','Féminin') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `nom`, `prenom`, `date_naissance`, `province_naissance`, `pays_naissance`, `telephone`, `email`, `residence`, `profession`, `etat_civil`, `conjoint_nom_prenom`, `date_nouvelle_naissance`, `eglise_nouvelle_naissance`, `lieu_nouvelle_naissance`, `formation_id`, `oikos_id`, `departement`, `fiche_membre`, `created_at`, `sexe`) VALUES
('00001', 'Dupont', 'Jean', '1980-05-15', NULL, NULL, NULL, NULL, NULL, NULL, 'Divorcé(e)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, '2025-04-24 14:27:34', 'Masculin'),
('00002', 'NZOKUNDA', 'Guillaume', '2024-04-06', 'Bujumbura Mairie', 'Burundi', '+25771557480', 'nzokundaguillaume@gmail.com', 'Gasenyi', 'IT', 'Marié(e)', 'Irakoze Chanceline', '2012-07-27', 'Eglise du Bon Berger', 'Socarti', 1, 1, NULL, 'Uploads/00002_NZOKUNDA.pdf', '2025-04-24 15:45:32', 'Masculin'),
('00003', 'Kaburungu', 'Paul', '2025-04-30', 'Bururi', 'Burundi', '+25771557480', 'k@gmail.com', 'Bujumbura, Muha', 'Comptable', 'Célibataire', NULL, '2025-04-16', 'El Shaddai', 'Bujumbura', 1, 1, 'Protocole', 'Uploads/00003_Kaburungu.pdf', '2025-04-25 08:50:30', NULL),
('00004', 'Kwizera', 'Anaclet', '2025-04-15', 'Mwaro', 'Burundi', '+25768541443', 'muhe@gmail.com', 'Bujumbura, Muha', 'Agent Immobilier', 'Célibataire', NULL, '2025-04-26', 'El Shaddai', 'Kabondo', 1, 1, 'Comptabilité', 'Uploads/00004_Kwizera.pdf', '2025-04-25 09:05:31', 'Masculin'),
('00005', 'Manirambona', 'Guillaume', '2000-06-14', 'Gitega', 'Burundi', '68542265', 'gn@gmail.com', 'Kanyosha', 'Informaticien', 'Célibataire', NULL, '2025-04-02', 'Rocher', 'Muyinga', 3, 1, 'Diaconat', 'Uploads/00005_Manirambona.pdf', '2025-05-12 18:20:57', 'Masculin'),
('00006', 'Manirambona', 'Lambert', '2000-06-14', 'Muramvya', 'Burundi', '68542265', 'gn@gmail.com', 'Kibega', 'Informaticien', 'Marié(e)', 'Erica Warner', '2025-04-02', 'Rocher', 'Muyinga', NULL, NULL, 'Pastorat', 'Uploads/00006_Manirambona.pdf', '2025-05-12 18:26:27', 'Masculin');

-- --------------------------------------------------------

--
-- Table structure for table `oikos`
--

CREATE TABLE `oikos` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `quartier` varchar(100) NOT NULL,
  `lieu` varchar(255) NOT NULL,
  `president_id` varchar(5) DEFAULT NULL,
  `president_telephone` varchar(20) DEFAULT NULL,
  `vice_president_id` varchar(5) DEFAULT NULL,
  `vice_president_telephone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `oikos`
--

INSERT INTO `oikos` (`id`, `nom`, `quartier`, `lieu`, `president_id`, `president_telephone`, `vice_president_id`, `vice_president_telephone`, `created_at`) VALUES
(1, 'Oikos Central', 'Centre-Ville', 'Église OCC', '00001', '0123456789', NULL, NULL, '2025-04-24 14:27:34');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `teacher_id` varchar(5) DEFAULT NULL,
  `nom` varchar(255) NOT NULL,
  `date_session` date DEFAULT NULL,
  `status` enum('planned','completed','cancelled') DEFAULT 'planned'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `formation_id`, `teacher_id`, `nom`, `date_session`, `status`) VALUES
(1, 2, NULL, 'Chapitre 1er', '2025-05-19', 'planned'),
(2, 5, '00002', 'Chapitre 1er', '2025-06-12', 'planned'),
(3, 5, '00002', 'Chapitre 2', '2025-08-12', 'planned');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` varchar(5) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `first_name`, `last_name`, `email`, `created_at`) VALUES
('00002', 'Guillaume', 'NZOKUNDA', 'nzokundaguillaume@gmail.com', '2025-05-12 21:28:27'),
('00003', 'Paul', 'Kaburungu', 'k@gmail.com', '2025-05-12 21:28:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','diacre','pasteur') NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `last_login`, `ip_address`, `created_at`) VALUES
(1, 'admin', 'admin123', 'admin', '2025-05-13 14:02:51', '127.0.0.1', '2025-04-24 14:27:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `formations`
--
ALTER TABLE `formations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `responsible_id` (`responsible_id`);

--
-- Indexes for table `formation_attendance`
--
ALTER TABLE `formation_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `formation_id` (`formation_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `formation_id` (`formation_id`),
  ADD KEY `oikos_id` (`oikos_id`);

--
-- Indexes for table `oikos`
--
ALTER TABLE `oikos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `president_id` (`president_id`),
  ADD KEY `vice_president_id` (`vice_president_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `formation_id` (`formation_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `formation_attendance`
--
ALTER TABLE `formation_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `oikos`
--
ALTER TABLE `oikos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `children_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `members` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`);

--
-- Constraints for table `formations`
--
ALTER TABLE `formations`
  ADD CONSTRAINT `formations_ibfk_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `members` (`id`);

--
-- Constraints for table `formation_attendance`
--
ALTER TABLE `formation_attendance`
  ADD CONSTRAINT `formation_attendance_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `formation_attendance_ibfk_2` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`);

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`),
  ADD CONSTRAINT `members_ibfk_2` FOREIGN KEY (`oikos_id`) REFERENCES `oikos` (`id`);

--
-- Constraints for table `oikos`
--
ALTER TABLE `oikos`
  ADD CONSTRAINT `oikos_ibfk_1` FOREIGN KEY (`president_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `oikos_ibfk_2` FOREIGN KEY (`vice_president_id`) REFERENCES `members` (`id`);

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`),
  ADD CONSTRAINT `sessions_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
