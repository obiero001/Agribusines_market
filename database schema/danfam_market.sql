-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2026 at 11:12 AM
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
-- Database: `danfam_market`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `produce_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Cereals & Grains', 'Maize, beans, rice, wheat, sorghum, millet', '2026-10-29 09:40:45'),
(2, 'Vegetables', 'Sukuma wiki, spinach, tomatoes, onions, cabbages', '2026-10-29 09:40:45'),
(3, 'Fruits', 'Bananas, mangoes, avocados, pineapples, pawpaws', '2026-10-29 09:40:45'),
(4, 'Tubers & Roots', 'Potatoes, sweet potatoes, arrowroots, cassava', '2026-10-29 09:40:45'),
(5, 'Legumes & Pulses', 'Green grams, cowpeas, pigeon peas, dolichos', '2026-10-29 09:40:45');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_status` enum('Placed','Confirmed','Dispatched','Delivered','Cancelled') DEFAULT 'Placed',
  `shipping_address` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `produce_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `produce`
--

CREATE TABLE `produce` (
  `produce_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `produce_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `quantity_available` decimal(10,2) NOT NULL DEFAULT 0.00,
  `photo_url` varchar(255) DEFAULT NULL,
  `location` varchar(150) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produce`
--

INSERT INTO `produce` (`produce_id`, `farmer_id`, `category_id`, `produce_name`, `description`, `price_per_unit`, `unit`, `quantity_available`, `photo_url`, `location`, `status`, `created_at`) VALUES
(1, 2, 4, 'Sweet potatoes', '', 3200.00, 'bags', 100.00, 'produce_1793267327_7542701f.jpg', 'Migori', 'Pending', '2026-10-29 09:48:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('Farmer','Buyer','Administrator') NOT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role`, `is_approved`, `created_at`) VALUES
(1, 'obieromikwa640', 'obieromikwa@gmail.com', '$2y$10$dFDsUUM4yE1CpPxURmjRWuaK.GiopMCSxbbdPKqw2.OKpKkezzQw.', 'Steve obiero', NULL, 'Administrator', 1, '2026-10-29 09:32:28'),
(2, 'annnafula866', 'annnafula@gmail.com', '$2y$10$9VHwFdne1tt/w8uSlKvjle0g4bFhEII5BgwEadXiuR6mrFcO9.1Fa', 'Ann nafula', NULL, 'Farmer', 1, '2026-10-29 09:33:39'),
(3, 'phalinek778', 'phalinek@gmail.com', '$2y$10$.GjOnD0867eYc942FyJOk.TZrRuH1apncaOzf83azxsDZd3Pn3sYe', 'Phaline kemunto', NULL, 'Buyer', 1, '2026-10-29 09:35:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `produce_id` (`produce_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `produce_id` (`produce_id`);

--
-- Indexes for table `produce`
--
ALTER TABLE `produce`
  ADD PRIMARY KEY (`produce_id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `produce`
--
ALTER TABLE `produce`
  MODIFY `produce_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`produce_id`) REFERENCES `produce` (`produce_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`produce_id`) REFERENCES `produce` (`produce_id`);

--
-- Constraints for table `produce`
--
ALTER TABLE `produce`
  ADD CONSTRAINT `produce_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `produce_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
