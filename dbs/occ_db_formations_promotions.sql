-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2025 at 01:22 PM
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
(2, 'Arakaza', 'Chris', 'Masculin', '2020-06-09', 'Enfant', 'Kaburungu', 'Paul', '+25771557480', '+25771557480', 'kg@mail.com', '00003', '2025-04-25 12:09:42');

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
(2, '00002', 'Mariage', '2025-01-18', '2025-04-25 09:28:31');

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
  `status` enum('active','pending','completed') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `formations`
--

INSERT INTO `formations` (`id`, `nom`, `promotion`, `date_debut`, `date_fin`, `created_at`, `status`) VALUES
(1, 'Isoko Classe 1', '2025-1', '2025-01-01', '2025-03-01', '2025-04-24 14:27:34', 'completed'),
(2, 'Isoko Classe 2', 'Mai 2025 f', '2025-05-18', '2025-08-14', '2025-05-12 09:20:34', 'pending'),
(3, 'Isoko Classe 3', '2025', '2025-05-12', '2025-11-10', '2025-05-12 09:21:19', 'active');

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
(1, 1, 'Connexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 14:27:39'),
(2, 1, 'Connexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 14:28:45'),
(3, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 15:04:35'),
(4, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 15:15:44'),
(5, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 15:15:46'),
(6, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-24 15:17:04'),
(7, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-24 15:17:16'),
(8, 1, 'Erreur ajout membre: Le dossier uploads/ n\'est pas accessible en écriture.', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 15:35:58'),
(9, 1, 'Erreur ajout membre: SQLSTATE[01000]: Warning: 1265 Data truncated for column \'departement\' at row 1', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 15:38:40'),
(10, 1, 'Ajout membre: 00002', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 15:45:32'),
(11, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 16:07:25'),
(12, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-24 16:07:27'),
(13, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-25 08:01:06'),
(14, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-25 08:26:16'),
(15, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-25 08:26:18'),
(16, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 08:26:51'),
(17, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-25 08:33:22'),
(18, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-25 08:41:22'),
(19, 1, 'Ajout membre: 00003', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-25 08:50:30'),
(20, 1, 'Ajout membre: 00004', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-25 09:05:31'),
(21, 1, 'Mise à jour membre: 00002', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-25 09:28:31'),
(22, 1, 'Erreur suppression membre: SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`occ_db`.`oikos`, CONSTRAINT `oikos_ibfk_1` FOREIGN KEY (`president_id`) REFERENCES `members` (`id`))', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0', 'Linux', '2025-04-25 09:28:40'),
(23, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 10:04:39'),
(24, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 10:44:10'),
(25, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 10:44:12'),
(26, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 10:51:39'),
(27, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 10:51:41'),
(28, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 10:59:45'),
(29, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 10:59:46'),
(30, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 11:16:32'),
(31, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 11:16:34'),
(32, 1, 'Mise à jour membre: 00001', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 11:17:21'),
(33, 1, 'Erreur ajout enfant: SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect integer value: \'CHLD001\' for column `occ_db`.`children`.`id` at row 1', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 11:32:32'),
(34, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 11:39:16'),
(35, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 11:39:18'),
(36, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 11:44:40'),
(37, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 11:44:42'),
(38, 1, 'Ajout enfant: 1', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 11:46:13'),
(39, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 12:08:45'),
(40, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 12:08:47'),
(41, 1, 'Ajout enfant: 2', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 12:09:42'),
(42, 1, 'Mise à jour enfant: 2', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 12:10:18'),
(43, 1, 'Mise à jour enfant: 1', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 12:11:02'),
(44, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 12:16:21'),
(45, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'Linux', '2025-04-25 12:16:23'),
(46, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:18:36'),
(47, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:18:36'),
(48, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:19:42'),
(49, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:19:42'),
(50, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:20:18'),
(51, 1, 'Erreur création formation: La date de fin doit être postérieure à la date de début.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:20:18'),
(52, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:20:34'),
(53, 1, 'Création formation: 2 (Nom: Isoko Classe 2, Promotion: Mai 2025, Statut: pending)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:20:34'),
(54, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:20:35'),
(55, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:20:36'),
(56, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:19'),
(57, 1, 'Création formation: 3 (Nom: Isoko Classe 3, Promotion: 2025, Statut: active)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:19'),
(58, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:21'),
(59, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:21'),
(60, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:35'),
(61, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:42'),
(62, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:42'),
(63, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:45'),
(64, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:45'),
(65, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:48'),
(66, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:48'),
(67, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:51'),
(68, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:51'),
(69, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:52'),
(70, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:52'),
(71, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:21:57'),
(72, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:22:01'),
(73, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:22:01'),
(74, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:22:03'),
(75, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:22:37'),
(76, 1, 'Erreur suppression formation: SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`occ_db`.`members`, CONSTRAINT `members_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `formations` ', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:22:37'),
(77, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:37:05'),
(78, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:37:08'),
(79, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:37:17'),
(80, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:37:22'),
(81, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:37:24'),
(82, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:37:33'),
(83, 1, 'Mise à jour formation: 2 (Statut: pending)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:37:33'),
(84, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:37:47'),
(85, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:37:47'),
(86, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:48:14'),
(87, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 09:48:14'),
(88, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 10:16:58'),
(89, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 10:17:00'),
(90, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 10:17:06'),
(91, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 10:17:09'),
(92, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 10:17:11'),
(93, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 10:17:20'),
(94, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 11:21:41'),
(95, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 11:21:43'),
(96, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 11:22:09'),
(97, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-12 11:22:09');

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
('00002', 'NZOKUNDA', 'Guillaume', '2024-04-06', 'Bujumbura Mairie', 'Burundi', '+25771557480', 'nzokundaguillaume@gmail.com', 'Gasenyi', 'IT', 'Marié(e)', 'Irakoze Chanceline', '2012-07-27', 'Eglise du Bon Berger', 'Socarti', 1, 1, NULL, 'uploads/00002_NZOKUNDA.pdf', '2025-04-24 15:45:32', 'Masculin'),
('00003', 'Kaburungu', 'Paul', '2025-04-30', 'Bururi', 'Burundi', '+25771557480', 'k@gmail.com', 'Bujumbura, Muha', 'Comptable', 'Célibataire', NULL, '2025-04-16', 'El Shaddai', 'Bujumbura', 1, 1, 'Protocole', 'uploads/00003_Kaburungu.pdf', '2025-04-25 08:50:30', NULL),
('00004', 'Kwizera', 'Anaclet', '2025-04-15', 'Mwaro', 'Burundi', '+25768541443', 'muhe@gmail.com', 'Bujumbura, Muha', 'Agent Immobilier', 'Célibataire', NULL, '2025-04-26', 'El Shaddai', 'Kabondo', 1, 1, 'Comptabilité', 'uploads/00004_Kwizera.pdf', '2025-04-25 09:05:31', 'Masculin');

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
(1, 'admin', 'admin123', 'admin', '2025-04-25 14:16:23', '127.0.0.1', '2025-04-24 14:27:34');

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
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `formation_attendance`
--
ALTER TABLE `formation_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `oikos`
--
ALTER TABLE `oikos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
