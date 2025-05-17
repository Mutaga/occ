-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 15, 2025 at 04:28 PM
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
-- Stand-in structure for view `attendance_summary`
-- (See below for the actual view)
--
CREATE TABLE `attendance_summary` (
`member_id` varchar(5)
,`formation_id` int(11)
,`formation_name` enum('Isoko Classe 1','Isoko Classe 2','Isoko Classe 3')
,`promotion` varchar(50)
,`days_attended` bigint(21)
,`total_days` bigint(21)
,`total_points` decimal(32,0)
,`attendance_score` decimal(38,4)
);

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
-- Table structure for table `exam_results`
--

CREATE TABLE `exam_results` (
  `id` int(11) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `member_id` varchar(5) NOT NULL,
  `exam_points` float NOT NULL CHECK (`exam_points` >= 0 and `exam_points` <= 50),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exam_results`
--

INSERT INTO `exam_results` (`id`, `formation_id`, `member_id`, `exam_points`, `created_at`, `updated_at`) VALUES
(1, 5, '00003', 40, '2025-05-15 13:30:52', '2025-05-15 13:32:30'),
(2, 5, '00006', 30, '2025-05-15 13:30:52', '2025-05-15 13:32:30');

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
(5, 'Isoko Classe 1', 'Ya mbere 2025', '2025-05-06', '2025-05-31', '2025-05-12 21:36:50', 'active', '00006'),
(6, 'Isoko Classe 2', 'gb', '2025-05-15', '2025-08-13', '2025-05-13 12:55:40', 'active', '00002');

-- --------------------------------------------------------

--
-- Table structure for table `formation_attendance`
--

CREATE TABLE `formation_attendance` (
  `id` int(11) NOT NULL,
  `member_id` varchar(5) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `date_presence` date NOT NULL,
  `present` tinyint(1) DEFAULT 1,
  `points` int(11) GENERATED ALWAYS AS (case when `present` = 1 then 1 else 0 end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `formation_attendance`
--

INSERT INTO `formation_attendance` (`id`, `member_id`, `formation_id`, `session_id`, `date_presence`, `present`) VALUES
(1, '00003', 5, 2, '2025-05-14', 1),
(2, '00006', 5, 2, '2025-05-14', 1);

-- --------------------------------------------------------

--
-- Table structure for table `formation_exams`
--

CREATE TABLE `formation_exams` (
  `id` int(11) NOT NULL,
  `member_id` varchar(5) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `exam_score` float NOT NULL CHECK (`exam_score` >= 0 and `exam_score` <= 50),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `formation_exams`
--

INSERT INTO `formation_exams` (`id`, `member_id`, `formation_id`, `exam_score`, `created_at`) VALUES
(1, '00003', 5, 30, '2025-05-15 13:42:15'),
(2, '00006', 5, 20, '2025-05-15 13:42:15'),
(3, '00005', 3, 50, '2025-05-15 13:42:47');

-- --------------------------------------------------------

--
-- Table structure for table `formation_results`
--

CREATE TABLE `formation_results` (
  `id` int(11) NOT NULL,
  `member_id` varchar(5) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `attendance_score` float NOT NULL CHECK (`attendance_score` >= 0 and `attendance_score` <= 50),
  `exam_score` float NOT NULL CHECK (`exam_score` >= 0 and `exam_score` <= 50),
  `total_score` float NOT NULL CHECK (`total_score` >= 0 and `total_score` <= 100),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `formation_results`
--

INSERT INTO `formation_results` (`id`, `member_id`, `formation_id`, `attendance_score`, `exam_score`, `total_score`, `created_at`) VALUES
(1, '00003', 5, 25, 30, 55, '2025-05-15 13:42:15'),
(2, '00006', 5, 25, 20, 45, '2025-05-15 13:42:15'),
(3, '00005', 3, 0, 50, 50, '2025-05-15 13:42:47');

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
(1, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:25:05'),
(2, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:25:07'),
(3, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:28:15'),
(4, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:28:19'),
(5, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:28:25'),
(6, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:28:28'),
(7, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:30:08'),
(8, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:30:12'),
(9, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:30:52'),
(10, 1, 'Mise à jour de 2 points d\'examen pour la formation: 5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:30:52'),
(11, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:30:52'),
(12, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:31:20'),
(13, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:31:27'),
(14, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:32:26'),
(15, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:32:30'),
(16, 1, 'Mise à jour de 2 points d\'examen pour la formation: 5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:32:30'),
(17, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:32:30'),
(18, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:41:47'),
(19, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:41:54'),
(20, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:41:58'),
(21, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:02'),
(22, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:08'),
(23, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:15'),
(24, 1, 'Enregistrement de 2 points d\'examen pour la formation: 5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:15'),
(25, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:15'),
(26, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:20'),
(27, 1, 'Enregistrement de 0 points d\'examen pour la formation: 5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:20'),
(28, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:20'),
(29, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:22'),
(30, 1, 'Enregistrement de 0 points d\'examen pour la formation: 5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:22'),
(31, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:22'),
(32, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:29'),
(33, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:34'),
(34, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:47'),
(35, 1, 'Enregistrement de 1 points d\'examen pour la formation: 3', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:47'),
(36, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:47'),
(37, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:49'),
(38, 1, 'Enregistrement de 0 points d\'examen pour la formation: 3', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:49'),
(39, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:49'),
(40, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:51'),
(41, 1, 'Enregistrement de 0 points d\'examen pour la formation: 3', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:51'),
(42, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:51'),
(43, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:42:57'),
(44, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:43:00'),
(45, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:43:07'),
(46, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:46:05'),
(47, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:47:58'),
(48, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:48:00'),
(49, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:48:01'),
(50, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:48:57'),
(51, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:03'),
(52, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:03'),
(53, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:09'),
(54, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:15'),
(55, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:15'),
(56, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:27'),
(57, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:28'),
(58, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:37'),
(59, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:37'),
(60, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:38'),
(61, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:43'),
(62, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:47'),
(63, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:49'),
(64, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:49:58'),
(65, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:50:08'),
(66, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:50:08'),
(67, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:50:09'),
(68, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:50:09'),
(69, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:50:11'),
(70, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:50:12'),
(71, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:50:52'),
(72, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:51:01'),
(73, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:51:13'),
(74, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:51:19'),
(75, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:51:24'),
(76, 1, 'Téléchargement CSV des totaux pour la formation: 5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:51:24'),
(77, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:52:12'),
(78, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:52:13'),
(79, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:52:13'),
(80, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:52:13'),
(81, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:52:14'),
(82, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 13:52:16'),
(83, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:59:47'),
(84, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 13:59:47'),
(85, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:00:32'),
(86, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:00:43'),
(87, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:00:43'),
(88, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:01:34'),
(89, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:01:34'),
(90, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:01:35'),
(91, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:01:35'),
(92, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:01:42'),
(93, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:01:42'),
(94, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:02:53'),
(95, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:02:54'),
(96, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:03:06'),
(97, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:03:07'),
(98, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:03:14'),
(99, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:03:14'),
(100, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:03:27'),
(101, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:03:27'),
(102, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:03:54'),
(103, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:03:54'),
(104, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:44'),
(105, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:45'),
(106, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:47'),
(107, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:48'),
(108, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:50'),
(109, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:53'),
(110, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:53'),
(111, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:53'),
(112, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:54'),
(113, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:55'),
(114, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:57'),
(115, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:58'),
(116, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:04:59'),
(117, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:05:01'),
(118, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:05:02'),
(119, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:05:03'),
(120, 1, 'Accès à sessions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:05:04'),
(121, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:05:05'),
(122, 1, 'Accès à promotions.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:05:05'),
(123, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:10:42'),
(124, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:10:42'),
(125, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:10:58'),
(126, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:11:02'),
(127, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:11:08'),
(128, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:11:13'),
(129, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:11:14'),
(130, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:11:32'),
(131, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:11:32'),
(132, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:11:32'),
(133, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:11:36'),
(134, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:14:41'),
(135, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:14:41'),
(136, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:14:47'),
(137, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:14:57'),
(138, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:14:57'),
(139, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:14:59'),
(140, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:14:59'),
(141, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:15:05'),
(142, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:15:05'),
(143, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:18:29'),
(144, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:18:32'),
(145, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:18:41'),
(146, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:18:42'),
(147, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:24:52'),
(148, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:24:54'),
(149, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:24:54'),
(150, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:25:07'),
(151, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:25:10'),
(152, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:25:26'),
(153, 1, 'Déconnexion', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'Windows NT', '2025-05-15 14:25:32'),
(154, 1, 'Connexion réussie', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:25:35'),
(155, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:25:37'),
(156, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:25:38'),
(157, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:25:38'),
(158, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:25:44'),
(159, 1, 'Accès à formations.php', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'Windows NT', '2025-05-15 14:25:52');

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
  `oikos_id` int(11) DEFAULT NULL,
  `departement` enum('Media','Comptabilité','Sécurité','Chorale','SundaySchool','Protocole','Pastorat','Diaconat') DEFAULT NULL,
  `fiche_membre` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sexe` enum('Masculin','Féminin') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `nom`, `prenom`, `date_naissance`, `province_naissance`, `pays_naissance`, `telephone`, `email`, `residence`, `profession`, `etat_civil`, `conjoint_nom_prenom`, `date_nouvelle_naissance`, `eglise_nouvelle_naissance`, `lieu_nouvelle_naissance`, `oikos_id`, `departement`, `fiche_membre`, `created_at`, `sexe`) VALUES
('00001', 'Dupont', 'Jean', '1980-05-15', NULL, NULL, NULL, NULL, NULL, NULL, 'Divorcé(e)', NULL, NULL, NULL, NULL, 1, NULL, NULL, '2025-04-24 14:27:34', 'Masculin'),
('00002', 'NZOKUNDA', 'Guillaume', '2024-04-06', 'Bujumbura Mairie', 'Burundi', '+25771557480', 'nzokundaguillaume@gmail.com', 'Gasenyi', 'IT', 'Marié(e)', 'Irakoze Chanceline', '2012-07-27', 'Eglise du Bon Berger', 'Socarti', 1, NULL, 'Uploads/00002_NZOKUNDA.pdf', '2025-04-24 15:45:32', 'Masculin'),
('00003', 'Kaburungu', 'Paul', '2025-04-30', 'Bururi', 'Burundi', '+25771557480', 'k@gmail.com', 'Bujumbura, Muha', 'Comptable', 'Célibataire', NULL, '2025-04-16', 'El Shaddai', 'Bujumbura', 1, 'Protocole', 'Uploads/00003_Kaburungu.pdf', '2025-04-25 08:50:30', NULL),
('00004', 'Kwizera', 'Anaclet', '2025-04-15', 'Mwaro', 'Burundi', '+25768541443', 'muhe@gmail.com', 'Bujumbura, Muha', 'Agent Immobilier', 'Célibataire', NULL, '2025-04-26', 'El Shaddai', 'Kabondo', 1, 'Comptabilité', 'Uploads/00004_Kwizera.pdf', '2025-04-25 09:05:31', 'Masculin'),
('00005', 'Manirambona', 'Guillaume', '2000-06-14', 'Gitega', 'Burundi', '68542265', 'gn@gmail.com', 'Kanyosha', 'Informaticien', 'Célibataire', NULL, '2025-04-02', 'Rocher', 'Muyinga', 1, 'Diaconat', 'Uploads/00005_Manirambona.pdf', '2025-05-12 18:20:57', 'Masculin'),
('00006', 'Manirambona', 'Lambert', '2000-06-14', 'Muramvya', 'Burundi', '68542265', 'gn@gmail.com', 'Kibega', 'Informaticien', 'Marié(e)', 'Erica Warner', '2025-04-02', 'Rocher', 'Muyinga', NULL, 'Pastorat', 'Uploads/00006_Manirambona.pdf', '2025-05-12 18:26:27', 'Masculin');

-- --------------------------------------------------------

--
-- Table structure for table `member_formations`
--

CREATE TABLE `member_formations` (
  `member_id` varchar(5) NOT NULL,
  `formation_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `member_formations`
--

INSERT INTO `member_formations` (`member_id`, `formation_id`) VALUES
('00001', 1),
('00002', 1),
('00003', 1),
('00003', 5),
('00004', 1),
('00005', 3),
('00006', 5);

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
(1, 'admin', 'admin123', 'admin', '2025-05-15 16:25:35', '127.0.0.1', '2025-04-24 14:27:34');

-- --------------------------------------------------------

--
-- Structure for view `attendance_summary`
--
DROP TABLE IF EXISTS `attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `attendance_summary`  AS SELECT `fa`.`member_id` AS `member_id`, `fa`.`formation_id` AS `formation_id`, `f`.`nom` AS `formation_name`, `f`.`promotion` AS `promotion`, count(distinct `fa`.`date_presence`) AS `days_attended`, (select count(distinct `s`.`date_session`) from `sessions` `s` where `s`.`formation_id` = `fa`.`formation_id`) AS `total_days`, sum(`fa`.`points`) AS `total_points`, sum(`fa`.`points`) / (select count(distinct `s`.`date_session`) from `sessions` `s` where `s`.`formation_id` = `fa`.`formation_id`) * 50 AS `attendance_score` FROM (`formation_attendance` `fa` join `formations` `f` on(`fa`.`formation_id` = `f`.`id`)) WHERE `fa`.`present` = 1 GROUP BY `fa`.`member_id`, `fa`.`formation_id` ;

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
-- Indexes for table `exam_results`
--
ALTER TABLE `exam_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `formation_id` (`formation_id`,`member_id`),
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
  ADD UNIQUE KEY `unique_attendance` (`member_id`,`session_id`,`date_presence`),
  ADD KEY `formation_id` (`formation_id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `formation_exams`
--
ALTER TABLE `formation_exams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_exam` (`member_id`,`formation_id`),
  ADD KEY `formation_id` (`formation_id`);

--
-- Indexes for table `formation_results`
--
ALTER TABLE `formation_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_result` (`member_id`,`formation_id`),
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
  ADD KEY `oikos_id` (`oikos_id`);

--
-- Indexes for table `member_formations`
--
ALTER TABLE `member_formations`
  ADD PRIMARY KEY (`member_id`,`formation_id`),
  ADD KEY `formation_id` (`formation_id`);

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
-- AUTO_INCREMENT for table `exam_results`
--
ALTER TABLE `exam_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `formation_attendance`
--
ALTER TABLE `formation_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `formation_exams`
--
ALTER TABLE `formation_exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `formation_results`
--
ALTER TABLE `formation_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

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
-- Constraints for table `exam_results`
--
ALTER TABLE `exam_results`
  ADD CONSTRAINT `exam_results_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_results_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `formation_attendance_ibfk_2` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`),
  ADD CONSTRAINT `formation_attendance_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `formation_exams`
--
ALTER TABLE `formation_exams`
  ADD CONSTRAINT `formation_exams_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `formation_exams_ibfk_2` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `formation_results`
--
ALTER TABLE `formation_results`
  ADD CONSTRAINT `formation_results_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `formation_results_ibfk_2` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_2` FOREIGN KEY (`oikos_id`) REFERENCES `oikos` (`id`);

--
-- Constraints for table `member_formations`
--
ALTER TABLE `member_formations`
  ADD CONSTRAINT `member_formations_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `member_formations_ibfk_2` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE;

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
