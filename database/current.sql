-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql200.infinityfree.com
-- Generation Time: Sep 10, 2026 at 11:00 PM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42218927_kapebilidad`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `full_name`, `created_at`) VALUES
(1, 'admin', '$2y$10$GRB3vVyUaGt4zknPg9W0TeOYiv//JEehFIax2uBT4vSUGlXL8Wmly', 'Cafe Admin', '2026-09-11 04:01:49');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `display_order`, `created_at`) VALUES
(1, 'Kape Muna', 'kape-muna', 1, '2026-09-10 12:56:52'),
(2, 'Walang Kape', 'walang-kape', 2, '2026-09-10 12:56:52'),
(3, 'Refresher', 'refresher', 3, '2026-09-10 12:56:52'),
(4, 'Frappes', 'frappes', 4, '2026-09-10 12:56:52'),
(5, 'Snacks', 'snacks', 5, '2026-09-10 12:56:52');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_code` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `order_note` varchar(255) DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','preparing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_code`, `customer_name`, `order_note`, `total_price`, `status`, `created_at`) VALUES
(4, 'KB-000004', 'liyam', 'Sweetened', '120.00', 'completed', '2026-09-11 08:45:07'),
(5, 'KB-000005', 'elle', 'less ice', '470.00', 'pending', '2026-09-11 10:52:52');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `item_note` varchar(255) DEFAULT NULL,
  `addons_summary` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `price`, `subtotal`, `item_note`, `addons_summary`) VALUES
(7, 4, 1, 'Cafe Kapebilidad', 1, '120.00', '120.00', NULL, NULL),
(8, 5, 10, 'Java Chip Frappe', 1, '150.00', '150.00', NULL, NULL),
(9, 5, 10, 'Java Chip Frappe', 2, '160.00', '320.00', NULL, 'Choco Drizzle (+?10.00)');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `addons` text DEFAULT NULL,
  `is_bestseller` tinyint(1) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `image`, `addons`, `is_bestseller`, `is_available`, `created_at`) VALUES
(1, 1, 'Cafe Kapebilidad', 'Our signature house blend espresso with steamed milk and a hint of caramel.', '120.00', 'kape-muna-1.jpg', '[{\"name\":\"Extra Shot\",\"price\":20},{\"name\":\"Oat Milk\",\"price\":15},{\"name\":\"Extra Foam\",\"price\":10}]', 1, 1, '2026-09-10 12:56:52'),
(2, 1, 'Spanish Latte', 'Rich espresso balanced with condensed milk for a smooth, sweet finish.', '130.00', 'kape-muna-2.jpg', '[{\"name\":\"Extra Shot\",\"price\":20},{\"name\":\"Oat Milk\",\"price\":15},{\"name\":\"Extra Foam\",\"price\":10}]', 0, 1, '2026-09-10 12:56:52'),
(3, 1, 'Iced Caramel Macchiato', 'Espresso layered with vanilla milk and caramel drizzle over ice.', '140.00', 'kape-muna-3.jpg', '[{\"name\":\"Extra Shot\",\"price\":20},{\"name\":\"Oat Milk\",\"price\":15},{\"name\":\"Extra Foam\",\"price\":10}]', 1, 1, '2026-09-10 12:56:52'),
(4, 2, 'Matcha Latte', 'Premium Japanese matcha whisked with fresh steamed milk.', '135.00', 'walang-kape-1.jpg', NULL, 1, 1, '2026-09-10 12:56:52'),
(5, 2, 'Chocolate Supreme', 'Velvety dark chocolate drink topped with whipped cream.', '125.00', 'walang-kape-2.jpg', NULL, 0, 1, '2026-09-10 12:56:52'),
(6, 2, 'Taro Milk Tea', 'Creamy taro flavored milk tea with chewy tapioca pearls.', '130.00', 'walang-kape-3.jpg', NULL, 0, 1, '2026-09-10 12:56:52'),
(7, 3, 'Blue Lemonade Refresher', 'Sparkling blue lemonade with fresh calamansi and mint.', '110.00', 'refresher-1.jpg', NULL, 0, 1, '2026-09-10 12:56:52'),
(8, 3, 'Strawberry Fizz', 'Fresh strawberry puree with soda water and lime.', '115.00', 'refresher-2.jpg', NULL, 1, 1, '2026-09-10 12:56:52'),
(9, 3, 'Passionfruit Cooler', 'Tangy passionfruit juice blended with lychee and soda.', '115.00', 'refresher-3.jpg', NULL, 0, 1, '2026-09-10 12:56:52'),
(10, 4, 'Java Chip Frappe', 'Blended coffee frappe loaded with chocolate chips and whipped cream.', '150.00', 'frappe-1.jpg', '[{\"name\":\"Extra Whipped Cream\",\"price\":10},{\"name\":\"Extra Shot\",\"price\":20},{\"name\":\"Choco Drizzle\",\"price\":10}]', 1, 1, '2026-09-10 12:56:52'),
(11, 4, 'Caramel Frappe', 'Buttery caramel blended with espresso, milk, and ice, topped with drizzle.', '145.00', 'frappe-2.jpg', '[{\"name\":\"Extra Whipped Cream\",\"price\":10},{\"name\":\"Extra Shot\",\"price\":20},{\"name\":\"Choco Drizzle\",\"price\":10}]', 0, 1, '2026-09-10 12:56:52'),
(12, 4, 'Cookies and Cream Frappe', 'Crushed chocolate cookies blended with creamy milk and ice.', '145.00', 'frappe-3.jpg', '[{\"name\":\"Extra Whipped Cream\",\"price\":10},{\"name\":\"Extra Cookie Crumbs\",\"price\":15}]', 0, 1, '2026-09-10 12:56:52'),
(13, 5, 'Ensaymada Supreme', 'Soft buttery ensaymada topped with cheese and sugar.', '65.00', 'snacks-1.jpg', NULL, 1, 1, '2026-09-10 12:56:52'),
(14, 5, 'Cheese Pandesal Sandwich', 'Warm pandesal filled with melted cheese and ham.', '75.00', 'snacks-2.jpg', '[{\"name\":\"Extra Cheese\",\"price\":15},{\"name\":\"Extra Ham\",\"price\":20}]', 0, 1, '2026-09-10 12:56:52'),
(15, 5, 'Chocolate Chip Cookie', 'Freshly baked cookie loaded with chocolate chips.', '55.00', 'snacks-3.jpg', NULL, 0, 1, '2026-09-10 12:56:52');

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
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
