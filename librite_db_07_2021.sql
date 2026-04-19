-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2026 at 08:42 PM
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
-- Database: `librite_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_creds`
--

CREATE TABLE `admin_creds` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `security_answer` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(150) NOT NULL,
  `isbn` varchar(30) DEFAULT '',
  `publisher` varchar(150) DEFAULT '',
  `year` varchar(10) DEFAULT '',
  `category` varchar(100) DEFAULT 'Fiction',
  `genre` varchar(100) DEFAULT '',
  `status` enum('Available','Borrowed','Lost','Maintenance','Archived') DEFAULT 'Available',
  `copies` int(11) DEFAULT 1,
  `shelf` varchar(50) DEFAULT '',
  `description` text DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `available_copies` int(11) DEFAULT 1,
  `total_copies` int(11) DEFAULT 1,
  `cover_image` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `publisher`, `year`, `category`, `genre`, `status`, `copies`, `shelf`, `description`, `added_at`, `available_copies`, `total_copies`, `cover_image`) VALUES
(1, '', '', '', '', '', 'Academic', 'Programming', 'Available', 1, 'A-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(2, 'Atomic Habits', 'James Clear', '9.78074E+13', '', '', 'Self-help', 'Productivity', 'Available', 2, 'B-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(3, 'The Great Gatsby', 'F. Scott Fitzgerald', '9.78074E+13', '', '', 'Fiction', 'Classic', 'Available', 3, 'C-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(4, '1984', 'George Orwell', '9.78045E+13', '', '', 'Fiction', 'Dystopian', 'Available', 3, 'A-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(5, 'To Kill a Mockingbird', 'Harper Lee', '9.78006E+13', '', '', 'Fiction', 'Classic', 'Available', 5, 'B-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(6, 'The Alchemist', 'Paulo Coelho', '9.78006E+13', '', '', 'Fiction', 'Philosophy', 'Available', 6, 'C-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(7, 'Sapiens', 'Yuval Noah Harari', '9.78006E+13', '', '', 'Non-Fiction', 'History', 'Available', 7, 'A-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(8, 'Homo Deus', 'Yuval Noah Harari', '9.78006E+13', '', '', 'Non-Fiction', 'Future', 'Available', 8, 'B-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(9, 'Rich Dad Poor Dad', 'Robert Kiyosaki', '9.78161E+13', '', '', 'Finance', 'Money', 'Available', 9, 'C-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(10, 'Think and Grow Rich', 'Napoleon Hill', '9.78159E+13', '', '', 'Self-help', 'Success', 'Available', 10, 'A-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(11, 'Deep Work', 'Cal Newport', '9.78146E+14', '', '', 'Self-help', 'Productivity', 'Available', 1, 'B-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(12, 'Zero to One', 'Peter Thiel', '9.7808E+14', '', '', 'Business', 'Startup', 'Available', 2, 'C-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(13, 'The Lean Startup', 'Eric Ries', '9.78031E+14', '', '', 'Business', 'Startup', 'Available', 3, 'A-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(14, 'Start With Why', 'Simon Sinek', '9.78159E+14', '', '', 'Business', 'Leadership', 'Available', 4, 'B-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(15, 'The Power of Habit', 'Charles Duhigg', '9.78081E+14', '', '', 'Self-help', 'Habit', 'Available', 5, 'C-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(16, 'Ikigai', 'Hector Garcia', '9.78014E+14', '', '', 'Self-help', 'Lifestyle', 'Available', 6, 'A-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(17, 'Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', '9.78059E+14', '', '', 'Fiction', 'Fantasy', 'Available', 7, 'B-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(18, 'Harry Potter and the Chamber of Secrets', 'J.K. Rowling', '9.78044E+14', '', '', 'Fiction', 'Fantasy', 'Available', 8, 'C-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(19, 'Harry Potter and the Prisoner of Azkaban', 'J.K. Rowling', '9.78044E+14', '', '', 'Fiction', 'Fantasy', 'Available', 9, 'A-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(20, 'The Hobbit', 'J.R.R. Tolkien', '9.78055E+14', '', '', 'Fiction', 'Fantasy', 'Available', 10, 'B-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(21, 'The Lord of the Rings', 'J.R.R. Tolkien', '9.78062E+14', '', '', 'Fiction', 'Fantasy', 'Available', 1, 'C-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(22, 'A Game of Thrones', 'George R.R. Martin', '9.78055E+14', '', '', 'Fiction', 'Fantasy', 'Available', 2, 'A-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(23, 'The Catcher in the Rye', 'J.D. Salinger', '9.78032E+14', '', '', 'Fiction', 'Classic', 'Available', 3, 'B-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(24, 'The Da Vinci Code', 'Dan Brown', '9.78031E+14', '', '', 'Fiction', 'Thriller', 'Available', 4, 'C-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(25, 'Angels and Demons', 'Dan Brown', '9.78074E+14', '', '', 'Fiction', 'Thriller', 'Available', 5, 'A-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(26, 'Digital Fortress', 'Dan Brown', '9.78031E+14', '', '', 'Fiction', 'Tech Thriller', 'Available', 6, 'B-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(27, 'The Kite Runner', 'Khaled Hosseini', '9.78159E+14', '', '', 'Fiction', 'Drama', 'Available', 7, 'C-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(28, 'A Thousand Splendid Suns', 'Khaled Hosseini', '9.78159E+14', '', '', 'Fiction', 'Drama', 'Available', 7, 'A-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(29, 'The Book Thief', 'Markus Zusak', '9.78038E+14', '', '', 'Fiction', 'Historical', 'Available', 9, 'B-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(30, 'The Fault in Our Stars', 'John Green', '9.78014E+14', '', '', 'Fiction', 'Romance', 'Available', 10, 'C-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(31, 'Looking for Alaska', 'John Green', '9.78014E+14', '', '', 'Fiction', 'Young Adult', 'Available', 1, 'A-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(32, 'The Subtle Art of Not Giving a F*ck', 'Mark Manson', '9.78006E+14', '', '', 'Self-help', 'Lifestyle', 'Available', 2, 'B-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(33, 'You Can Win', 'Shiv Khera', '9.78936E+14', '', '', 'Self-help', 'Motivation', 'Available', 3, 'C-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(34, 'Wings of Fire', 'A.P.J. Abdul Kalam', '9.78817E+14', '', '', 'Biography', 'Inspiration', 'Available', 4, 'A-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(35, 'Ignited Minds', 'A.P.J. Abdul Kalam', '9.78014E+14', '', '', 'Non-Fiction', 'Inspiration', 'Available', 5, 'B-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(36, 'The Psychology of Money', 'Morgan Housel', '9.78086E+14', '', '', 'Finance', 'Money', 'Available', 6, 'C-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(37, 'Think Like a Monk', 'Jay Shetty', '9.78198E+14', '', '', 'Self-help', 'Mindfulness', 'Available', 7, 'A-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(38, 'The 7 Habits of Highly Effective People', 'Stephen Covey', '9.78074E+14', '', '', 'Self-help', 'Leadership', 'Available', 8, 'B-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(39, 'Can\'t Hurt Me', 'David Goggins', '9.78154E+14', '', '', 'Self-help', 'Motivation', 'Available', 9, 'C-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(40, 'Steve Jobs', 'Walter Isaacson', '9.78145E+14', '', '', 'Biography', 'Tech', 'Available', 10, 'A-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(41, 'Elon Musk', 'Ashlee Vance', '9.78006E+14', '', '', 'Biography', 'Tech', 'Available', 1, 'B-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(42, 'The Code Book', 'Simon Singh', '9.78039E+14', '', '', 'Science', 'Cryptography', 'Available', 2, 'C-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(43, 'A Brief History of Time', 'Stephen Hawking', '9.78055E+14', '', '', 'Science', 'Physics', 'Available', 3, 'A-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(44, 'The Selfish Gene', 'Richard Dawkins', '9.7802E+14', '', '', 'Science', 'Biology', 'Available', 4, 'B-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(45, 'The Gene', 'Siddhartha Mukherjee', '9.78148E+14', '', '', 'Science', 'Biology', 'Available', 5, 'C-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(46, 'The Immortal Life of Henrietta Lacks', 'Rebecca Skloot', '9.7814E+14', '', '', 'Science', 'Biography', 'Available', 6, 'A-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(47, 'The Design of Everyday Things', 'Don Norman', '9.78047E+14', '', '', 'Design', 'UX', 'Available', 7, 'B-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(48, 'Don\'t Make Me Think', 'Steve Krug', '9.78032E+14', '', '', 'Design', 'UX', 'Available', 8, 'C-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(49, 'Refactoring', 'Martin Fowler', '9.7802E+14', '', '', 'Programming', 'Software', 'Available', 9, 'A-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(50, 'Code Complete', 'Steve McConnell', '9.78074E+14', '', '', 'Programming', 'Software', 'Available', 10, 'B-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(51, 'Clean Code', 'Robert C. Martin', '9.78013E+14', '', '', 'Academic', 'Programming', 'Available', 1, 'C-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(52, 'Atomic Habits', 'James Clear', '9.78074E+14', '', '', 'Self-help', 'Productivity', 'Available', 2, 'A-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(53, 'The Great Gatsby', 'F. Scott Fitzgerald', '9.78074E+14', '', '', 'Fiction', 'Classic', 'Available', 3, 'B-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(54, '1984', 'George Orwell', '9.78045E+14', '', '', 'Fiction', 'Dystopian', 'Available', 4, 'C-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(55, 'To Kill a Mockingbird', 'Harper Lee', '9.78006E+14', '', '', 'Fiction', 'Classic', 'Available', 5, 'A-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(56, 'The Alchemist', 'Paulo Coelho', '9.78006E+14', '', '', 'Fiction', 'Philosophy', 'Available', 6, 'B-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(57, 'Sapiens', 'Yuval Noah Harari', '9.78006E+14', '', '', 'Non-Fiction', 'History', 'Available', 7, 'C-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(58, 'Homo Deus', 'Yuval Noah Harari', '9.78006E+14', '', '', 'Non-Fiction', 'Future', 'Available', 8, 'A-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(59, 'Rich Dad Poor Dad', 'Robert Kiyosaki', '9.78161E+14', '', '', 'Finance', 'Money', 'Available', 9, 'B-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(60, 'Think and Grow Rich', 'Napoleon Hill', '9.78159E+14', '', '', 'Self-help', 'Success', 'Available', 10, 'C-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(61, 'Deep Work', 'Cal Newport', '9.78146E+14', '', '', 'Self-help', 'Productivity', 'Available', 1, 'A-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(62, 'Zero to One', 'Peter Thiel', '9.7808E+14', '', '', 'Business', 'Startup', 'Available', 2, 'B-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(63, 'The Lean Startup', 'Eric Ries', '9.78031E+14', '', '', 'Business', 'Startup', 'Available', 3, 'C-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(64, 'Start With Why', 'Simon Sinek', '9.78159E+14', '', '', 'Business', 'Leadership', 'Available', 4, 'A-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(65, 'The Power of Habit', 'Charles Duhigg', '9.78081E+14', '', '', 'Self-help', 'Habit', 'Available', 5, 'B-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(66, 'Ikigai', 'Hector Garcia', '9.78014E+14', '', '', 'Self-help', 'Lifestyle', 'Available', 6, 'C-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(67, 'Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', '9.78059E+14', '', '', 'Fiction', 'Fantasy', 'Available', 7, 'A-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(68, 'Harry Potter and the Chamber of Secrets', 'J.K. Rowling', '9.78044E+14', '', '', 'Fiction', 'Fantasy', 'Available', 8, 'B-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(69, 'Harry Potter and the Prisoner of Azkaban', 'J.K. Rowling', '9.78044E+14', '', '', 'Fiction', 'Fantasy', 'Available', 9, 'C-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(70, 'The Hobbit', 'J.R.R. Tolkien', '9.78055E+14', '', '', 'Fiction', 'Fantasy', 'Available', 10, 'A-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(71, 'The Lord of the Rings', 'J.R.R. Tolkien', '9.78062E+14', '', '', 'Fiction', 'Fantasy', 'Available', 1, 'B-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(72, 'A Game of Thrones', 'George R.R. Martin', '9.78055E+14', '', '', 'Fiction', 'Fantasy', 'Available', 2, 'C-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(73, 'The Catcher in the Rye', 'J.D. Salinger', '9.78032E+14', '', '', 'Fiction', 'Classic', 'Available', 3, 'A-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(74, 'The Da Vinci Code', 'Dan Brown', '9.78031E+14', '', '', 'Fiction', 'Thriller', 'Available', 4, 'B-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(75, 'Angels and Demons', 'Dan Brown', '9.78074E+14', '', '', 'Fiction', 'Thriller', 'Available', 5, 'C-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(76, 'Digital Fortress', 'Dan Brown', '9.78031E+14', '', '', 'Fiction', 'Tech Thriller', 'Available', 6, 'A-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(77, 'The Kite Runner', 'Khaled Hosseini', '9.78159E+14', '', '', 'Fiction', 'Drama', 'Available', 7, 'B-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(78, 'A Thousand Splendid Suns', 'Khaled Hosseini', '9.78159E+14', '', '', 'Fiction', 'Drama', 'Available', 8, 'C-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(79, 'The Book Thief', 'Markus Zusak', '9.78038E+14', '', '', 'Fiction', 'Historical', 'Available', 9, 'A-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(80, 'The Fault in Our Stars', 'John Green', '9.78014E+14', '', '', 'Fiction', 'Romance', 'Available', 10, 'B-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(81, 'Looking for Alaska', 'John Green', '9.78014E+14', '', '', 'Fiction', 'Young Adult', 'Available', 1, 'C-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(82, 'The Subtle Art of Not Giving a F*ck', 'Mark Manson', '9.78006E+14', '', '', 'Self-help', 'Lifestyle', 'Available', 2, 'A-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(83, 'You Can Win', 'Shiv Khera', '9.78936E+14', '', '', 'Self-help', 'Motivation', 'Available', 3, 'B-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(84, 'Wings of Fire', 'A.P.J. Abdul Kalam', '9.78817E+14', '', '', 'Biography', 'Inspiration', 'Available', 4, 'C-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(85, 'Ignited Minds', 'A.P.J. Abdul Kalam', '9.78014E+14', '', '', 'Non-Fiction', 'Inspiration', 'Available', 5, 'A-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(86, 'The Psychology of Money', 'Morgan Housel', '9.78086E+14', '', '', 'Finance', 'Money', 'Available', 6, 'B-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(87, 'Think Like a Monk', 'Jay Shetty', '9.78198E+14', '', '', 'Self-help', 'Mindfulness', 'Available', 7, 'C-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(88, 'The 7 Habits of Highly Effective People', 'Stephen Covey', '9.78074E+14', '', '', 'Self-help', 'Leadership', 'Available', 8, 'A-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(89, 'Can\'t Hurt Me', 'David Goggins', '9.78154E+14', '', '', 'Self-help', 'Motivation', 'Available', 9, 'B-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(90, 'Steve Jobs', 'Walter Isaacson', '9.78145E+14', '', '', 'Biography', 'Tech', 'Available', 10, 'C-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(91, 'Elon Musk', 'Ashlee Vance', '9.78006E+14', '', '', 'Biography', 'Tech', 'Available', 1, 'A-01', NULL, '2026-04-05 15:53:43', 1, 1, ''),
(92, 'The Code Book', 'Simon Singh', '9.78039E+14', '', '', 'Science', 'Cryptography', 'Available', 2, 'B-02', NULL, '2026-04-05 15:53:43', 2, 2, ''),
(93, 'A Brief History of Time', 'Stephen Hawking', '9.78055E+14', '', '', 'Science', 'Physics', 'Available', 3, 'C-03', NULL, '2026-04-05 15:53:43', 3, 3, ''),
(94, 'The Selfish Gene', 'Richard Dawkins', '9.7802E+14', '', '', 'Science', 'Biology', 'Available', 4, 'A-04', NULL, '2026-04-05 15:53:43', 4, 4, ''),
(95, 'The Gene', 'Siddhartha Mukherjee', '9.78148E+14', '', '', 'Science', 'Biology', 'Available', 5, 'B-05', NULL, '2026-04-05 15:53:43', 5, 5, ''),
(96, 'The Immortal Life of Henrietta Lacks', 'Rebecca Skloot', '9.7814E+14', '', '', 'Science', 'Biography', 'Available', 6, 'C-06', NULL, '2026-04-05 15:53:43', 6, 6, ''),
(97, 'The Design of Everyday Things', 'Don Norman', '9.78047E+14', '', '', 'Design', 'UX', 'Available', 7, 'A-07', NULL, '2026-04-05 15:53:43', 7, 7, ''),
(98, 'Don\'t Make Me Think', 'Steve Krug', '9.78032E+14', '', '', 'Design', 'UX', 'Available', 8, 'B-08', NULL, '2026-04-05 15:53:43', 8, 8, ''),
(99, 'Refactoring', 'Martin Fowler', '9.7802E+14', '', '', 'Programming', 'Software', 'Available', 9, 'C-09', NULL, '2026-04-05 15:53:43', 9, 9, ''),
(100, 'Code Complete', 'Steve McConnell', '9.78074E+14', '', '', 'Programming', 'Software', 'Available', 10, 'A-10', NULL, '2026-04-05 15:53:43', 10, 10, ''),
(101, 'rabiya', 'rrfsd', '9.35E+09', '', '', 'horror', 'horror', 'Available', 10, 'A-01', NULL, '2026-04-05 15:53:54', 10, 10, ''),
(102, 'sdfsdf', 'dfgdf', '64569569', '', '', 'magic', 'magic', 'Available', 25, 'A-01', NULL, '2026-04-05 15:53:54', 25, 25, ''),
(103, 'rather than', 'rajesha', '533664646', '', '', 'real', 'real', 'Available', 5, 'A-01', NULL, '2026-04-05 15:53:54', 5, 5, ''),
(104, 'rabiya', 'rrfsd', '9.35E+09', '', '', 'horror', 'horror', 'Available', 10, 'A-01', NULL, '2026-04-06 09:45:00', 1, 1, ''),
(105, 'sdfsdf', 'dfgdf', '64569569', '', '', 'magic', 'magic', 'Available', 25, 'A-01', NULL, '2026-04-06 09:45:00', 1, 1, ''),
(106, 'rather than', 'rajesha', '533664646', '', '', 'real', 'real', 'Available', 5, 'A-01', NULL, '2026-04-06 09:45:00', 1, 1, ''),
(107, 'Programming in C', 'Dennis Ritchie', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(108, 'Data Structures using C', 'Reema Thareja', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(109, 'Computer Fundamentals', 'P.K. Sinha', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(110, 'Operating System Concepts', 'Silberschatz', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(111, 'Database Management System', 'Korth', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(112, 'Java Programming', 'Herbert Schildt', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(113, 'Python Programming', 'Guido Van Rossum', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(114, 'Web Technologies', 'Jeffrey Jackson', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(115, 'Software Engineering', 'Pressman', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(116, 'Computer Networks', 'Andrew Tanenbaum', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(117, 'Artificial Intelligence', 'Stuart Russell', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(118, 'Machine Learning Basics', 'Tom Mitchell', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(119, 'Cloud Computing', 'Rajkumar Buyya', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(120, 'Cyber Security', 'William Stallings', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(121, 'Big Data Analytics', 'Viktor Mayer', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(122, 'Mobile App Development', 'Google Dev Team', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(123, 'PHP & MySQL', 'Luke Welling', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(124, 'HTML & CSS', 'Jon Duckett', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(125, 'JavaScript Essentials', 'Douglas Crockford', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(126, 'Data Science Intro', 'Joel Grus', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(127, 'Principles of Management', 'Harold Koontz', '', '', '', 'BBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(128, 'Business Communication', 'C.S. Rayudu', '', '', '', 'BBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(129, 'Marketing Management', 'Philip Kotler', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(130, 'Human Resource Management', 'Gary Dessler', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(131, 'Financial Management', 'I.M. Pandey', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(132, 'Organizational Behavior', 'Stephen Robbins', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(133, 'Strategic Management', 'Fred David', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(134, 'Business Ethics', 'Velasquez', '', '', '', 'BBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(135, 'Entrepreneurship Development', 'Khanka', '', '', '', 'BBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(136, 'Operations Management', 'Heizer', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(137, 'Supply Chain Management', 'Sunil Chopra', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(138, 'Retail Management', 'Levy & Weitz', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(139, 'International Business', 'Hill', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(140, 'Business Analytics', 'James Evans', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(141, 'Leadership Theory', 'Peter Northouse', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(142, 'Project Management', 'Kerzner', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(143, 'Digital Marketing', 'Ryan Deiss', '', '', '', 'BBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(144, 'E-Commerce', 'Kenneth Laudon', '', '', '', 'BBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(145, 'Startup Management', 'Eric Ries', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(146, 'Business Law', 'N.D. Kapoor', '', '', '', 'BBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(147, 'Financial Accounting', 'T.S. Grewal', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(148, 'Corporate Accounting', 'S.N. Maheshwari', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(149, 'Cost Accounting', 'Jain & Narang', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(150, 'Business Economics', 'H.L. Ahuja', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(151, 'Income Tax Law', 'Girish Ahuja', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(152, 'Auditing', 'Arun Jha', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(153, 'Banking Theory', 'Sundaram', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(154, 'Business Statistics', 'Gupta', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(155, 'Advanced Accounting', 'M.C. Shukla', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(156, 'Financial Reporting', 'ICAI', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(157, 'GST Law', 'V.S. Datey', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(158, 'Investment Analysis', 'Reilly & Brown', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(159, 'Corporate Finance', 'Ross', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(160, 'Business Finance', 'Khan & Jain', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(161, 'Principles of Economics', 'Samuelson', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(162, 'Micro Economics', 'Varian', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(163, 'Macro Economics', 'Blanchard', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(164, 'Accounting Standards', 'ICAI', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(165, 'Risk Management', 'Hull', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(166, 'Insurance Principles', 'Black', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(167, 'C++ Programming', 'Bjarne Stroustrup', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(168, 'Advanced Java', 'Herbert Schildt', '', '', '', 'MCA', '', 'Borrowed', 0, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(169, 'Data Mining', 'Han & Kamber', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(170, 'Unix Programming', 'Kernighan', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(171, 'Compiler Design', 'Aho', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(172, 'Discrete Mathematics', 'Rosen', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(173, 'Numerical Methods', 'Jain', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(174, 'Android Development', 'Google', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(175, 'iOS Development', 'Apple', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(176, 'Game Development', 'Unity Docs', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(177, 'Brand Management', 'Keller', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(178, 'Advertising Management', 'Belch', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(179, 'Sales Management', 'Spiro', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(180, 'Customer Relationship Mgmt', 'Buttle', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(181, 'Business Research Methods', 'Cooper', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(182, 'HR Analytics', 'Fitz-enz', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(183, 'Negotiation Skills', 'Fisher', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(184, 'Corporate Strategy', 'Porter', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(185, 'Innovation Management', 'Tidd', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(186, 'Change Management', 'Kotter', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(187, 'Business Environment', 'Francis Cherunilam', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(188, 'Company Law', 'Kapoor', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(189, 'Financial Markets', 'Fabozzi', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(190, 'Portfolio Management', 'Sharpe', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(191, 'Behavioral Finance', 'Shiller', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(192, 'Public Finance', 'Musgrave', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(193, 'Development Economics', 'Todaro', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(194, 'Managerial Economics', 'Dominick', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(195, 'Industrial Economics', 'Barthwal', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(196, 'International Finance', 'Madura', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(197, 'Advanced Python', 'Mark Lutz', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(198, 'React JS', 'Meta Docs', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(199, 'Node JS', 'Ryan Dahl', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(200, 'MongoDB Guide', 'Chodorow', '', '', '', 'BCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(201, 'DevOps Basics', 'Gene Kim', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(202, 'Blockchain Basics', 'Nakamoto', '', '', '', 'MCA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(203, 'AI for Business', 'McKinsey', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(204, 'FinTech Basics', 'Arner', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(205, 'Startup Finance', 'Brad Feld', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(206, 'Business Forecasting', 'Makridakis', '', '', '', 'MBA', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(207, 'Tax Planning', 'Singhania', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(208, 'Corporate Tax', 'ICAI', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(209, 'Accounting Theory', 'Hendriksen', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(210, 'E-Banking', 'Indian Institute', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(211, 'Financial Derivatives', 'Hull', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(212, 'Trade Finance', 'Finance Experts', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(213, 'Retail Banking', 'Indian Banking', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(214, 'Microfinance', 'Yunus', '', '', '', 'BCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(215, 'Public Policy', 'Anderson', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, ''),
(216, 'Global Economics', 'Krugman', '', '', '', 'MCom', '', 'Available', 1, '', NULL, '2026-04-17 19:37:33', 1, 1, '');

-- --------------------------------------------------------

--
-- Table structure for table `book_purchase_requests`
--

CREATE TABLE `book_purchase_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `author` varchar(150) DEFAULT '',
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Reviewed','Ordered','Rejected') DEFAULT 'Pending',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_purchase_requests`
--

INSERT INTO `book_purchase_requests` (`id`, `user_id`, `book_title`, `author`, `reason`, `status`, `admin_note`, `created_at`) VALUES
(1, 1, 'Dear Debbie', 'Freida McFadden', 'i love reading thrilling books', 'Reviewed', 'it will be get u soon', '2026-04-06 08:59:35'),
(2, 1, 'Dear Debbie', 'Freida McFadden', 'i love reading thrilling books', 'Ordered', 'i will reach u soon', '2026-04-06 10:07:45'),
(3, 3, 'stranger be again', 'ds', 'df', 'Reviewed', '', '2026-04-18 08:45:30');

-- --------------------------------------------------------

--
-- Table structure for table `book_requests`
--

CREATE TABLE `book_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Returned') DEFAULT 'Pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `notified` tinyint(1) DEFAULT 0,
  `fine_paid` tinyint(1) DEFAULT 0,
  `payment_ref` varchar(100) DEFAULT '',
  `payment_screenshot` varchar(255) DEFAULT '',
  `extension_count` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_requests`
--

INSERT INTO `book_requests` (`id`, `user_id`, `book_id`, `status`, `requested_at`, `approved_at`, `due_date`, `returned_at`, `fine_amount`, `notified`, `fine_paid`, `payment_ref`, `payment_screenshot`, `extension_count`) VALUES
(1, 1, 4, 'Approved', '2026-04-05 16:30:40', '2026-04-06 14:55:11', '2026-04-10', NULL, 45.00, 1, 0, '', '', 0),
(2, 1, 28, 'Approved', '2026-04-05 16:32:33', '2026-04-06 14:55:09', '2026-04-20', NULL, 0.00, 1, 0, '', '', 1),
(3, 2, 4, 'Returned', '2026-04-02 09:42:17', '2026-04-02 15:13:54', '2026-04-05', '2026-04-02 15:14:04', 0.00, 0, 0, '', '', 0),
(4, 3, 168, 'Approved', '2026-04-18 08:44:28', '2026-04-18 14:18:01', '2026-04-22', NULL, 0.00, 0, 0, '', '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `book_reviews`
--

CREATE TABLE `book_reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT 0,
  `comment` text DEFAULT NULL,
  `type` varchar(20) DEFAULT 'review',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_reviews`
--

INSERT INTO `book_reviews` (`id`, `user_id`, `book_id`, `rating`, `comment`, `type`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 4, '', 'review', '2026-04-18 00:41:01', '2026-04-18 01:08:04');

-- --------------------------------------------------------

--
-- Table structure for table `fine_payments`
--

CREATE TABLE `fine_payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `upi_ref` varchar(100) DEFAULT '',
  `screenshot` varchar(255) DEFAULT '',
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT '',
  `username` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT '',
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `dept` varchar(100) DEFAULT '',
  `member_type` enum('Student','Faculty','Staff','Teacher') DEFAULT 'Student',
  `year` varchar(20) DEFAULT 'N/A',
  `register_no` varchar(20) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT '',
  `profile_photo` varchar(255) DEFAULT '',
  `full_name` varchar(150) DEFAULT '',
  `reg_no` varchar(20) DEFAULT '',
  `department` varchar(100) DEFAULT '',
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(15) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `role`, `status`, `dept`, `member_type`, `year`, `register_no`, `security_answer`, `profile_photo`, `full_name`, `reg_no`, `department`, `dob`, `address`, `phone`, `created_at`) VALUES
(1, 'suprith', 'suprith', '9663468615', '$2y$10$RbsV4mWcvWzDhrCb1sCMcOomLtwJDb.l0daDKfb3mY860htZ.WSgu', 'user', 'Active', '', 'Student', 'N/A', 'U06ED23S0020', 'stranger things', 'uploads/profiles/user_1_1775403923.jpg', 'suprith d', '', '', NULL, '', '', '2026-04-05 15:44:31'),
(2, 'darshan', 'darshan', '6360052336', '$2y$10$Kdk0mctxRVMEv4XHdsI2luLa6bR6M.aNk1wb.QrLD7rwqW0.RXOUa', 'user', 'Active', '', 'Student', 'N/A', 'U06ED23S0021', 'tiger', '', '', '', '', NULL, '', '', '2026-04-06 09:41:27'),
(3, 'thrupti gowda', 'thrupti gowda', '8296863012', '$2y$10$jrqLSWpiNVDer8Z431JL0eguaMrNXar1uMMeEUcm8nNOLjToM1pVO', 'user', 'Active', '', 'Student', 'N/A', 'U06ED24S0061', 'can we be strangers again', 'uploads/profiles/user_3_1776501729.jpg', '', '', '', NULL, NULL, '', '2026-04-18 08:41:14');

-- --------------------------------------------------------

--
-- Table structure for table `website_reports`
--

CREATE TABLE `website_reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `issue_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `website_reports`
--

INSERT INTO `website_reports` (`id`, `user_id`, `issue_type`, `description`, `status`, `created_at`) VALUES
(1, 3, 'bug', 'clear this eror', 'Pending', '2026-04-18 14:12:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_creds`
--
ALTER TABLE `admin_creds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `book_purchase_requests`
--
ALTER TABLE `book_purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `book_requests`
--
ALTER TABLE `book_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `book_reviews`
--
ALTER TABLE `book_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `fine_payments`
--
ALTER TABLE `fine_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `website_reports`
--
ALTER TABLE `website_reports`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_creds`
--
ALTER TABLE `admin_creds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=217;

--
-- AUTO_INCREMENT for table `book_purchase_requests`
--
ALTER TABLE `book_purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `book_requests`
--
ALTER TABLE `book_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `book_reviews`
--
ALTER TABLE `book_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fine_payments`
--
ALTER TABLE `fine_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `website_reports`
--
ALTER TABLE `website_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `book_purchase_requests`
--
ALTER TABLE `book_purchase_requests`
  ADD CONSTRAINT `book_purchase_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `book_requests`
--
ALTER TABLE `book_requests`
  ADD CONSTRAINT `book_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_requests_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fine_payments`
--
ALTER TABLE `fine_payments`
  ADD CONSTRAINT `fine_payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fine_payments_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `book_requests` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
