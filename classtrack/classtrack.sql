-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 13, 2025 at 03:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `classtrack`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_important` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `start_date` date DEFAULT curdate(),
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT '00:00:00',
  `end_time` time DEFAULT '23:59:59'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `teacher_id`, `class_id`, `title`, `content`, `is_important`, `created_at`, `start_date`, `end_date`, `start_time`, `end_time`) VALUES
(1, 2, 2, 'HELLO', 'Quiz Mamayang linggo hahahaha', 1, '2025-10-29 17:43:19', '2025-10-30', '2025-11-07', '00:00:00', '00:00:00'),
(4, 2, 2, 'we wetr', 'rte wtr', 1, '2025-11-06 14:01:48', '2025-11-06', '2025-11-07', '22:01:00', '22:01:00');

-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--

CREATE TABLE `class_schedules` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section` varchar(50) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `day` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `type` varchar(20) NOT NULL,
  `campus` varchar(100) DEFAULT NULL,
  `building` varchar(100) DEFAULT NULL,
  `room` varchar(50) NOT NULL,
  `color` varchar(20) DEFAULT '#e3e9ff',
  `unique_code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_schedules`
--

INSERT INTO `class_schedules` (`id`, `teacher_id`, `section`, `subject_code`, `day`, `start_time`, `end_time`, `type`, `campus`, `building`, `room`, `color`, `unique_code`, `created_at`) VALUES
(1, 1, 'IT3A', 'WEBSYS', 'Monday', '07:30:00', '11:00:00', 'Lecture', 'Main Campus', 'E', '201', '#ffe6e6', 'IT3A-WEBSYS', '2025-10-23 17:12:43'),
(2, 2, 'IT3A', 'WEBSYS', 'Monday', '07:30:00', '11:00:00', 'Lecture', 'Main Campus', 'Building E', 'E14', '#e3e9ff', 'IT3A-WEBSYS-490', '2025-10-29 17:42:48');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `session_id` varchar(128) NOT NULL,
  `data` text DEFAULT NULL,
  `expires` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_classes`
--

CREATE TABLE `student_classes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_classes`
--

INSERT INTO `student_classes` (`id`, `student_id`, `class_id`, `joined_at`) VALUES
(1, 3, 2, '2025-11-06 22:27:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','teacher') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'teacher1', '$2y$10$4N3QkytHg9EIJ6G0me/HEO54RNMD8iKgjjJ1mYd4VDx.pgGlUZtp6', 'teacher', '2025-10-23 16:17:15'),
(2, 'teacher2', '$2y$10$kAkoXrewWqkPfrZ9fsfT7ed5JCOD0kvvV2vTdBFHy3nPyGiNxkdOi', 'teacher', '2025-10-29 16:33:19'),
(3, 'student1', '$2y$10$QrdBL7jL.2kiap8TbGlqJ.ZrwUEvCohQDPTX8rpRnvznaDZEOge3u', 'student', '2025-11-06 14:13:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`unique_code`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`session_id`);

--
-- Indexes for table `student_classes`
--
ALTER TABLE `student_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_class` (`student_id`,`class_id`),
  ADD KEY `class_id` (`class_id`);

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
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_classes`
--
ALTER TABLE `student_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `class_schedules_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_classes`
--
ALTER TABLE `student_classes`
  ADD CONSTRAINT `student_classes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_classes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `class_schedules` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
