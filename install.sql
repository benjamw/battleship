-- phpMyAdmin SQL Dump
-- version 4.0.0-dev
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Aug 18, 2012 at 11:41 PM
-- Server version: 5.5.22
-- PHP Version: 5.4.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `games`
--

-- --------------------------------------------------------

--
-- Table structure for table `bs2_bs_player`
--

CREATE TABLE IF NOT EXISTS `bs2_bs_player` (
  `player_id` int(11) unsigned NOT NULL DEFAULT '0',
  `is_admin` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `allow_email` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `pre_hide_board` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `max_games` tinyint(3) NOT NULL DEFAULT '0',
  `color` varchar(25) COLLATE latin1_general_ci NOT NULL DEFAULT 'blue_white',
  `wins` smallint(5) unsigned NOT NULL DEFAULT '0',
  `losses` smallint(5) unsigned NOT NULL DEFAULT '0',
  `last_online` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `id` (`player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bs2_chat`
--

CREATE TABLE IF NOT EXISTS `bs2_chat` (
  `chat_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message` text COLLATE latin1_general_ci NOT NULL,
  `from_id` int(10) unsigned NOT NULL DEFAULT '0',
  `game_id` int(10) unsigned NOT NULL DEFAULT '0',
  `private` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`chat_id`),
  KEY `game_id` (`game_id`),
  KEY `private` (`private`),
  KEY `from_id` (`from_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `bs2_game`
--

CREATE TABLE IF NOT EXISTS `bs2_game` (
  `game_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `white_id` int(10) unsigned DEFAULT NULL,
  `black_id` int(10) unsigned DEFAULT NULL,
  `state` enum('Waiting','Placing','Playing','Finished') COLLATE latin1_general_ci NOT NULL DEFAULT 'Waiting',
  `white_ready` tinyint(1) NOT NULL DEFAULT '0',
  `black_ready` tinyint(1) NOT NULL DEFAULT '0',
  `method` enum('Single','Five','Salvo') COLLATE latin1_general_ci NOT NULL DEFAULT 'Single',
  `paused` tinyint(1) NOT NULL DEFAULT '0',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modify_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`game_id`),
  KEY `state` (`state`),
  KEY `white_id` (`white_id`),
  KEY `black_id` (`black_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `bs2_game_board`
--

CREATE TABLE IF NOT EXISTS `bs2_game_board` (
  `game_id` int(10) unsigned NOT NULL DEFAULT '0',
  `white_board` varchar(100) COLLATE latin1_general_ci DEFAULT NULL,
  `black_board` varchar(100) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `move_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `game_id` (`game_id`),
  KEY `move_date` (`move_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bs2_game_nudge`
--

CREATE TABLE IF NOT EXISTS `bs2_game_nudge` (
  `game_id` int(10) unsigned NOT NULL DEFAULT '0',
  `player_id` int(10) unsigned NOT NULL DEFAULT '0',
  `nudged` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `game_player` (`game_id`,`player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `bs2_message`
--

CREATE TABLE IF NOT EXISTS `bs2_message` (
  `message_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `message` text COLLATE latin1_general_ci NOT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `bs2_message_glue`
--

CREATE TABLE IF NOT EXISTS `bs2_message_glue` (
  `message_glue_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(10) unsigned NOT NULL DEFAULT '0',
  `from_id` int(10) unsigned NOT NULL DEFAULT '0',
  `to_id` int(10) unsigned NOT NULL DEFAULT '0',
  `send_date` datetime DEFAULT NULL,
  `expire_date` datetime DEFAULT NULL,
  `view_date` datetime DEFAULT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`message_glue_id`),
  KEY `outbox` (`from_id`,`message_id`),
  KEY `inbox` (`to_id`,`message_id`),
  KEY `created` (`create_date`),
  KEY `expire_date` (`expire_date`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `bs2_settings`
--

CREATE TABLE IF NOT EXISTS `bs2_settings` (
  `setting` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `value` text CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `notes` text CHARACTER SET latin1 COLLATE latin1_general_ci,
  `sort` smallint(5) unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `setting` (`setting`),
  KEY `sort` (`sort`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `bs2_settings`
--

INSERT INTO `bs2_settings` (`setting`, `value`, `notes`, `sort`) VALUES
('site_name', 'Your Site Name', 'The name of your site', 10),
('default_color', 'c_blue_black.css', 'The default theme color for the script pages', 20),
('nav_links', '<a href="/">Home</a>', 'HTML code for your site''s navigation links to display on the script pages', 30),
('from_email', 'your.mail@yoursite.com', 'The email address used to send game emails', 40),
('to_email', 'you@yoursite.com', 'The email address to send admin notices to (comma separated)', 50),
('new_users', '1', '(1/0) Allow new users to register (0 = off)', 60),
('approve_users', '0', '(1/0) Require admin approval for new users (0 = off)', 70),
('confirm_email', '0', '(1/0) Require email confirmation for new users (0 = off)', 80),
('max_users', '0', 'Max users allowed to register (0 = off)', 90),
('default_pass', 'change!me', 'The password to use when resetting a user''s password', 100),
('expire_users', '45', 'Number of days until untouched games are deleted (0 = off)', 110),
('save_games', '1', '(1/0) Save games in the ''games'' directory on the server (0 = off)', 120),
('expire_finished_games', '7', 'Number of days until finished games are deleted (0 = off)', 128),
('expire_games', '30', 'Number of days until untouched user accounts are deleted (0 = off)', 130),
('nudge_flood_control', '24', 'Number of hours between nudges. (-1 = no nudging, 0 = no flood control)', 135),
('timezone', 'UTC', 'The timezone to use for dates (<a href="http://www.php.net/manual/en/timezones.php">List of Timezones</a>)', 140),
('long_date', 'M j, Y g:i a', 'The long format for dates (<a href="http://www.php.net/manual/en/function.date.php">Date Format Codes</a>)', 150),
('short_date', 'Y.m.d H:i', 'The short format for dates (<a href="http://www.php.net/manual/en/function.date.php">Date Format Codes</a>)', 160),
('debug_pass', '', 'The DEBUG password to use to set temporary DEBUG status for the script', 170),
('DB_error_log', '1', '(1/0) Log database errors to the ''logs'' directory on the server (0 = off)', 180),
('DB_error_email', '1', '(1/0) Email database errors to the admin email addresses given (0 = off)', 190);

-- --------------------------------------------------------

--
-- Table structure for table `player`
--

CREATE TABLE IF NOT EXISTS `player` (
  `player_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(20) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `first_name` varchar(20) COLLATE latin1_general_ci DEFAULT NULL,
  `last_name` varchar(20) COLLATE latin1_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `timezone` varchar(255) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `is_admin` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `password` varchar(32) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `alt_pass` varchar(32) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `ident` varchar(32) COLLATE latin1_general_ci DEFAULT NULL,
  `token` varchar(32) COLLATE latin1_general_ci DEFAULT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_approved` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`player_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
