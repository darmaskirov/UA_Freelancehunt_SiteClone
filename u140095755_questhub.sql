-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Час створення: Вер 06 2025 р., 23:40
-- Версія сервера: 10.11.10-MariaDB-log
-- Версія PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База даних: `u140095755_questhub`
--

-- --------------------------------------------------------

--
-- Структура таблиці `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблиці `balances`
--

CREATE TABLE `balances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `currency` enum('CNY','HKD','USD','JPY','KRW','VND','THB','BRL','PLN','UAH') NOT NULL DEFAULT 'USD',
  `amount` decimal(12,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `balances`
--

INSERT INTO `balances` (`id`, `user_id`, `currency`, `amount`, `updated_at`) VALUES
(5, 22, 'CNY', 0.00, '2025-09-04 08:59:59'),
(6, 23, 'CNY', 0.00, '2025-09-05 08:31:22'),
(7, 24, '', 0.00, '2025-09-06 16:19:46'),
(8, 25, '', 0.00, '2025-09-06 18:45:15'),
(9, 26, '', 0.00, '2025-09-06 18:47:08'),
(10, 27, '', 0.00, '2025-09-06 18:47:44'),
(11, 28, '', 0.00, '2025-09-06 18:49:59'),
(12, 29, 'CNY', 0.00, '2025-09-06 19:13:54'),
(13, 30, 'BRL', 0.00, '2025-09-06 19:23:26'),
(14, 31, 'USD', 111111.00, '2025-09-06 19:57:04'),
(15, 32, 'USD', 0.00, '2025-09-06 20:17:36');

-- --------------------------------------------------------

--
-- Структура таблиці `profiles`
--

CREATE TABLE `profiles` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблиці `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_key` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблиці `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','withdraw','transfer','bonus') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблиці `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('active','banned','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `currency`, `password_hash`, `role`, `status`, `created_at`, `updated_at`) VALUES
(22, 'test', 'test@gmail.com', 'CNY', '12345', 'user', 'active', '2025-09-04 08:59:59', '2025-09-05 08:21:33'),
(23, 'darmaskirov', 'test2@gmail.com', 'CNY', '$2y$10$QkoXrYnY8DYoOoaYrKcuxuic.cbqttN3oDVDKwXcqbpskgQqgHL0O', 'user', 'active', '2025-09-05 08:31:21', '2025-09-05 09:04:21'),
(24, 'sds13', 'sds13@gmail.com', 'GBP', '$2y$10$lDdAcGaxaC/kONHhZ39nyuPNgVONUkhWANvCWuqnkX3XhJLHkGgoW', 'user', 'active', '2025-09-06 16:19:46', '2025-09-06 16:19:46'),
(25, 'sfdfds', 'sfdfds@gmail.com', 'TWD', '$2y$10$EejtMqbz3r7rpMBssRUCluowTrHUetAz0cSltc0I.jH4q4zYzqFHW', 'user', 'active', '2025-09-06 18:45:15', '2025-09-06 18:45:15'),
(26, 'sfdfdse', 'sfdfdse@gmail.com', 'INR', '$2y$10$JX6jOttw.dtPz0Wqi6zUAenv/hfMJcwCemt0RU9k89eL6veyuJt52', 'user', 'active', '2025-09-06 18:47:08', '2025-09-06 18:47:08'),
(27, 'sfdfdse3', 'sfdfdse3@gmail.com', 'INR', '$2y$10$d1HwRzdN29ro9NtV8LpvsO2gDepsKL1t0Ay7neu6MknjAdCgCT0Za', 'user', 'active', '2025-09-06 18:47:44', '2025-09-06 18:47:44'),
(28, 'darmasoff', 'darmasoff@gmail.com', 'PHP', '$2y$10$GQnJuCnsnYgiwkZB8VocKeddi0m.NQ8YykAg8oDxz9dMV.ZqN3bae', 'admin', 'active', '2025-09-06 18:49:59', '2025-09-06 19:52:59'),
(29, 'sfdsf', 'sfdsf@gmail.com', 'CNY', '$2y$10$PKXgu2Rn8W.qeLNyqJjaUe41QXm7J1pDsrnP6Xm0EYNFkferkULza', 'user', 'active', '2025-09-06 19:13:54', '2025-09-06 19:13:54'),
(30, 'fdsfdsdsaf', 'fdsfdsdsaf@gmail.com', 'BRL', '$2y$10$3.4OeaqdDLDa.AFClgSSn.WDWTnRxaBAvQzHVz5pNc616XCX6qx3q', 'user', 'active', '2025-09-06 19:23:26', '2025-09-06 19:23:26'),
(31, 'newuser', 'newuser@gmail.com', 'USD', '$2y$10$weAt1XQaT9QBaROwOud4wO5YZ5zv881DdlGKHB.OuAVpoTbl5CEIe', 'user', 'active', '2025-09-06 19:57:04', '2025-09-06 19:57:04'),
(32, 'hello', 'hello@228.com', 'USD', '$2y$10$AkVW.E/k8p21.MYdJrOh2.9pSygV81vgbMmQZZTP7Y2RwRsQ.2hru', 'user', 'active', '2025-09-06 20:17:36', '2025-09-06 20:17:36');

--
-- Індекси збережених таблиць
--

--
-- Індекси таблиці `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Індекси таблиці `balances`
--
ALTER TABLE `balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Індекси таблиці `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Індекси таблиці `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_key` (`session_key`),
  ADD KEY `user_id` (`user_id`);

--
-- Індекси таблиці `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Індекси таблиці `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для збережених таблиць
--

--
-- AUTO_INCREMENT для таблиці `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблиці `balances`
--
ALTER TABLE `balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблиці `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблиці `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблиці `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Обмеження зовнішнього ключа збережених таблиць
--

--
-- Обмеження зовнішнього ключа таблиці `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `balances`
--
ALTER TABLE `balances`
  ADD CONSTRAINT `balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `profiles`
--
ALTER TABLE `profiles`
  ADD CONSTRAINT `profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
