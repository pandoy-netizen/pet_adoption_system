-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 24, 2026 at 10:27 AM
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
-- Database: `pet_adoption_rescue`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', 'admin123', '2026-02-24 04:17:55');

-- --------------------------------------------------------

--
-- Table structure for table `adoptions`
--

CREATE TABLE `adoptions` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Under Review'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `adoption_applications`
--

CREATE TABLE `adoption_applications` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `living_space` varchar(255) DEFAULT NULL,
  `experience_level` varchar(255) DEFAULT NULL,
  `activity_level` varchar(255) DEFAULT NULL,
  `with_children` varchar(50) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Pending','Under Review','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `application_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adoption_applications`
--

INSERT INTO `adoption_applications` (`id`, `pet_id`, `living_space`, `experience_level`, `activity_level`, `with_children`, `user_id`, `status`, `created_at`, `application_date`) VALUES
(1, 3, 'Apartment', 'Beginner', 'Low', 'No', 2, 'Pending', '2026-03-15 04:46:48', '2026-03-14 21:46:48'),
(2, 3, 'Apartment', 'Beginner', 'Low', 'No', 2, 'Pending', '2026-03-15 04:50:46', '2026-03-14 21:50:46'),
(3, 3, 'Apartment', 'Beginner', 'Low', 'No', 2, 'Pending', '2026-03-15 05:03:29', '2026-03-14 22:03:29');

-- --------------------------------------------------------

--
-- Table structure for table `adoption_history`
--

CREATE TABLE `adoption_history` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `adoption_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `adoption_requests`
--

CREATE TABLE `adoption_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_preferences`
--

CREATE TABLE `client_preferences` (
  `preference_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `preferred_species` varchar(50) DEFAULT NULL,
  `preferred_breed` varchar(100) DEFAULT NULL,
  `preferred_age` varchar(20) DEFAULT NULL,
  `preferred_size` varchar(20) DEFAULT NULL,
  `preferred_gender` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_posts`
--

CREATE TABLE `community_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_display_name` varchar(100) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `species` varchar(50) DEFAULT NULL,
  `breed` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'Available',
  `image` varchar(255) DEFAULT 'default.jpg',
  `description` text DEFAULT NULL,
  `size` varchar(50) DEFAULT 'Medium',
  `temperament` varchar(100) DEFAULT 'Friendly',
  `gender` varchar(50) DEFAULT 'Male',
  `activity_level` varchar(50) DEFAULT 'Moderate',
  `vaccinated` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`id`, `name`, `species`, `breed`, `age`, `status`, `image`, `description`, `size`, `temperament`, `gender`, `activity_level`, `vaccinated`) VALUES
(1, 'Bella', NULL, 'Golden Retriever', 2, 'Available', 'https://images.unsplash.com/photo-1552053831-71594a27632d?w=500', 'Bella is a sweet and energetic Golden Retriever who loves playing fetch and going for long walks.', 'Medium', 'Friendly', 'Male', 'Moderate', NULL),
(2, 'Luna', NULL, 'Siamese Cat', 1, 'Available', 'https://images.unsplash.com/photo-1513360371669-4adf3dd7dff8?w=500', 'Luna is a vocal and affectionate Siamese cat. She loves cuddling on the couch.', 'Medium', 'Friendly', 'Male', 'Moderate', NULL),
(3, 'Max', NULL, 'Beagle', 3, 'Available', 'https://images.unsplash.com/photo-1537151608804-ea2f1ea3f6a2?w=500', 'Max has a great nose and loves to explore. He is great with kids and other dogs.', 'Medium', 'Friendly', 'Male', 'Moderate', NULL),
(4, 'Milo', NULL, 'British Shorthair', 4, 'Available', 'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=500', 'Milo is a calm, independent cat who enjoys watching the world go by from a sunny windowsill.', 'Medium', 'Friendly', 'Male', 'Moderate', NULL),
(5, 'Charlie', NULL, 'French Bulldog', 2, 'Available', 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=500', 'Charlie is a hilarious little clown. He loves naps and belly rubs.', 'Medium', 'Friendly', 'Male', 'Moderate', NULL),
(6, 'Daisy', NULL, 'Mixed Breed', 1, 'Available', 'https://images.unsplash.com/photo-1544568100-847a948585b9?w=500', 'Daisy is a rescue pup with a heart of gold. Very trainable and eager to please.', 'Medium', 'Friendly', 'Male', 'Moderate', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pet_history`
--

CREATE TABLE `pet_history` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `action_desc` varchar(255) DEFAULT NULL,
  `date_logged` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pet_preferences`
--

CREATE TABLE `pet_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `preferred_breed` varchar(100) DEFAULT NULL,
  `preferred_age` int(11) DEFAULT NULL,
  `preferred_gender` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pet_traits`
--

CREATE TABLE `pet_traits` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `trait` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pet_traits`
--

INSERT INTO `pet_traits` (`id`, `pet_id`, `trait`) VALUES
(1, 1, 'friendly'),
(2, 1, 'playful'),
(3, 2, 'loyal'),
(4, 2, 'smart'),
(5, 3, 'calm'),
(6, 3, 'indoor');

-- --------------------------------------------------------

--
-- Table structure for table `rescue_reports`
--

CREATE TABLE `rescue_reports` (
  `id` int(11) NOT NULL,
  `reporter_name` varchar(100) DEFAULT NULL,
  `contact_info` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Action Needed',
  `after_id` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `latitude` varchar(50) NOT NULL,
  `longitude` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rescue_reports`
--

INSERT INTO `rescue_reports` (`id`, `reporter_name`, `contact_info`, `location`, `description`, `image`, `status`, `after_id`, `user_id`, `latitude`, `longitude`) VALUES
(1, 'client', 'client1@gmail.com', '', 'pa rescue po', '1782722541_Screenshot 2026-05-30 185830.png', 'Action Needed', '2026-06-29 08:42:21', 2, '', '');

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Offline',
  `last_login` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_attendance`
--

INSERT INTO `staff_attendance` (`id`, `name`, `role`, `status`, `last_login`) VALUES
(1, 'Dr. Santos', 'Veterinarian', 'Present', '2026-06-29 08:18:29'),
(2, 'Mark Reyes', 'Shelter Staff', 'Present', '2026-06-29 08:18:29'),
(3, 'Anna Cruz', 'Admin', 'Offline', '2026-06-29 08:18:29');

-- --------------------------------------------------------

--
-- Table structure for table `stray_reports`
--

CREATE TABLE `stray_reports` (
  `id` int(11) NOT NULL,
  `photo` varchar(255) NOT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending Review',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stray_reports`
--

INSERT INTO `stray_reports` (`id`, `photo`, `latitude`, `longitude`, `address`, `description`, `status`, `created_at`) VALUES
(2, '1782719949_Screenshot 2026-05-30 185830.png', '14.34689116470226', '121.05136912802358', 'San Lorenzo Ruiz, Pacita II, San Pedro, Laguna, Calabarzon, 4023, Philippines', 'pa rescue po ng aso dito ', 'Pending Review', '2026-06-29 07:59:09'),
(3, '1782719999_Screenshot 2026-05-30 185830.png', '14.34689116470226', '121.05136912802358', 'San Lorenzo Ruiz, Pacita II, San Pedro, Laguna, Calabarzon, 4023, Philippines', 'pa rescue po ng aso dito ', 'Pending Review', '2026-06-29 07:59:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'adopter',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'owa', 'owa@gmail.com', '$2y$10$c.bvelBn0HwdQ00jn9EztOctPI9vtAgfWuv77s0JPpWT6CSMNIbwS', 'admin', '2026-02-26 09:49:15'),
(2, 'client', 'client1@gmail.com', '$2y$10$ONtS7UB9tOYK4y0ySQAoLummYc31/NLY9BcXTGS8M21d4ZowtbAbO', 'adopter', '2026-02-28 07:02:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `adoptions`
--
ALTER TABLE `adoptions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `adoption_applications`
--
ALTER TABLE `adoption_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `adoption_history`
--
ALTER TABLE `adoption_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `adoption_requests`
--
ALTER TABLE `adoption_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_preferences`
--
ALTER TABLE `client_preferences`
  ADD PRIMARY KEY (`preference_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `community_posts`
--
ALTER TABLE `community_posts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pet_history`
--
ALTER TABLE `pet_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pet_preferences`
--
ALTER TABLE `pet_preferences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pet_traits`
--
ALTER TABLE `pet_traits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `rescue_reports`
--
ALTER TABLE `rescue_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stray_reports`
--
ALTER TABLE `stray_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `adoptions`
--
ALTER TABLE `adoptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `adoption_applications`
--
ALTER TABLE `adoption_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `adoption_history`
--
ALTER TABLE `adoption_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `adoption_requests`
--
ALTER TABLE `adoption_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_preferences`
--
ALTER TABLE `client_preferences`
  MODIFY `preference_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_posts`
--
ALTER TABLE `community_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pet_history`
--
ALTER TABLE `pet_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pet_preferences`
--
ALTER TABLE `pet_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pet_traits`
--
ALTER TABLE `pet_traits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rescue_reports`
--
ALTER TABLE `rescue_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stray_reports`
--
ALTER TABLE `stray_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `adoption_applications`
--
ALTER TABLE `adoption_applications`
  ADD CONSTRAINT `adoption_applications_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`);

--
-- Constraints for table `adoption_history`
--
ALTER TABLE `adoption_history`
  ADD CONSTRAINT `adoption_history_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`),
  ADD CONSTRAINT `adoption_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pet_preferences`
--
ALTER TABLE `pet_preferences`
  ADD CONSTRAINT `pet_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_traits`
--
ALTER TABLE `pet_traits`
  ADD CONSTRAINT `pet_traits_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
