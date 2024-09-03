-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2024 at 10:33 AM
-- Server version: 10.4.25-MariaDB
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `telegram_bot`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_ballances`
--

CREATE TABLE `account_ballances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `ballance` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `account_ballance_in_dollar` double(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_permissons`
--

CREATE TABLE `agent_permissons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `minus_ballance` tinyint(1) DEFAULT 0,
  `create_products` tinyint(1) DEFAULT 0,
  `delete_products` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `traffic_limitation_tb` double(15,2) DEFAULT 10.00,
  `product_limitation` int(10) UNSIGNED DEFAULT 1000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_products`
--

CREATE TABLE `agent_products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_categories_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `price` int(10) UNSIGNED DEFAULT 0,
  `price_in_dollar` decimal(8,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `download_link` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_src` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'android',
  `how_to_use` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `youtube_link` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `name`, `download_link`, `file_src`, `os`, `how_to_use`, `description`, `youtube_link`, `is_active`, `created_at`, `updated_at`) VALUES
(5, 'Hiddify Next', 'https://github.com/hiddify/hiddify-next/releases/latest/download/Hiddify-Android-universal.apk', NULL, 'Android', 'https://hiddify.com/fa/app/How-to-use-Hiddify-app/', 'یک کلاینت خودکار مالتی‌پلتفرم مبتنی بر سینگ‌باکس که به عنوان یک ابزار عمومی برای پروکسی عمل می‌کند. این برنامه طیف گسترده‌ای از قابلیت‌ها را ارائه می‌دهد مثل انتخاب خودکار نود، مود تونل، پروفایل‌های ریموت و غیره. این برنامه رایگان، بدون آگهی و منبع باز است. با پشتیبانی از طیف وسیعی از پروتکل‌ها، این اپلیکیشن یک ابزار امن و مطمئن برای دسترسی به اینترنت رایگان فراهم می‌کند.', 'https://www.youtube.com/watch?v=NtQ0bQlIRrY', 1, '2024-04-09 08:18:27', '2024-04-09 08:21:06'),
(7, 'Hiddify', 'https://github.com/hiddify/hiddify-next/releases/latest/download/Hiddify-Windows-Setup-x64.Msix', NULL, 'Windows 64bit', 'https://hiddify.com/fa/app/How-to-use-Hiddify-app/', 'هیدیفای‌اپ یک کلاینت مالتی‌پلتفرم مبتنی بر سینگ‌باکس که به عنوان یک ابزار عمومی برای پروکسی عمل می‌کند و با دارا بودن قابلیت‌های فراوان از پروتکل‌های زیادی برای دور زدن محدودیت‌های اینترنتی پشتیبانی می‌کند.', 'https://www.youtube.com/watch?v=vUaA1AEUy1s', 1, '2024-04-09 08:20:30', '2024-04-09 08:20:30');

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) NOT NULL,
  `bill_id` bigint(20) NOT NULL,
  `amount` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `amount_dollar` double(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bot_users`
--

CREATE TABLE `bot_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) NOT NULL DEFAULT 0,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `channel_locks`
--

CREATE TABLE `channel_locks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `channel_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `channel_lock_menu_items`
--

CREATE TABLE `channel_lock_menu_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main',
  `alias_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'برای استفاده از ربات می بایست در کانال های زیر عضو باشید.',
  `level` int(10) UNSIGNED DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `channel_lock_menu_items`
--

INSERT INTO `channel_lock_menu_items` (`id`, `name`, `alias_name`, `level`, `created_at`, `updated_at`) VALUES
(1, 'main', '😉برای شروی روی متن زیر کلیک کنید و عضو کانال شوید، سپس دوباره بر روی /start ضربه بزنید.', 1, '2023-09-18 04:07:05', '2023-09-21 04:58:47');

-- --------------------------------------------------------

--
-- Table structure for table `crypto_payments`
--

CREATE TABLE `crypto_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'nowpayments',
  `api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `env` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'live',
  `callback_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipn_callback_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `success_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partially_paid_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_fixed_rate` tinyint(1) DEFAULT 1,
  `is_fee_paid_by_user` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `question` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `question`, `answer`, `created_at`, `updated_at`) VALUES
(2, 'چند نفر می توانند به اکانت متصل بشوند؟', 'تعداد کاربران برای اکانت ها محدود شده نمی باشد، بنابراین کاربران می توانند بصورت نامحدود متصل بشوند.', '2023-09-17 11:52:40', '2023-09-18 02:16:48');

-- --------------------------------------------------------

--
-- Table structure for table `frist_menus`
--

CREATE TABLE `frist_menus` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gift_cards`
--

CREATE TABLE `gift_cards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `discount` int(10) UNSIGNED NOT NULL DEFAULT 1000,
  `count_of_use` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `count_of_use_per_user` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gift_card_menu_items`
--

CREATE TABLE `gift_card_menu_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main',
  `alias_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'کد تخفیف راوارد کنید.',
  `level` int(10) UNSIGNED DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gift_card_menu_items`
--

INSERT INTO `gift_card_menu_items` (`id`, `name`, `alias_name`, `level`, `created_at`, `updated_at`) VALUES
(4, 'main', 'کد گیفت کارت را وارد کنید.', 1, '2023-09-16 09:25:35', '2023-09-16 14:25:06'),
(5, 'accepted', 'کد گیفت کارت با موفقیت ثبت شد.', 2, '2023-09-16 09:25:35', '2023-09-16 14:33:10'),
(6, 'expired', 'این کد منقضی شده است.', 3, '2023-09-16 09:25:35', '2023-09-16 14:33:10');

-- --------------------------------------------------------

--
-- Table structure for table `inbounds`
--

CREATE TABLE `inbounds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `proxy_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` bigint(20) DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `main_menu_items`
--

CREATE TABLE `main_menu_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `position` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `main_menu_items`
--

INSERT INTO `main_menu_items` (`id`, `name`, `alias_name`, `is_active`, `position`) VALUES
(1, 'خرید اشتراک', 'خرید اشتراک', 1, 1),
(2, 'سابقه خرید', 'سابقه خرید', 1, 3),
(3, 'پشتیبانی', 'پشتیبانی', 1, 4),
(4, 'آموزش استفاده و سوالات متداول', 'آموزش استفاده و سوالات متداول', 1, 5),
(5, 'اطلاعات حساب', 'اطلاعات حساب', 1, 6),
(6, 'اکانت آزمایشی', 'اکانت آزمایشی', 1, 7),
(7, 'دانلود برنامه', 'دانلود برنامه', 1, 8),
(9, 'گیف کارت', 'گیف کارت', 1, 9),
(10, 'webapp', 'استفاده در وب اپلیکیشن', 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `menu_levels`
--

CREATE TABLE `menu_levels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) NOT NULL DEFAULT 0,
  `level` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(5, '2023_07_19_120044_create_pannels_table', 1),
(6, '2023_09_03_000003_create_product_categories_table', 1),
(7, '2023_09_03_000004_create_products_table', 1),
(8, '2023_09_03_000006_create_payment_types_table', 1),
(9, '2023_09_03_000007_create_transactions_table', 1),
(10, '2023_09_03_000011_create_supports_table', 1),
(11, '2023_09_03_053856_create_settings_table', 1),
(12, '2023_09_03_061126_create_account_ballances_table', 1),
(13, '2023_09_03_061211_create_logs_table', 1),
(14, '2023_09_03_061221_create_transaction_images_table', 1),
(15, '2023_09_03_061647_create_telegrams_table', 1),
(16, '2023_09_06_073339_create_orders_table', 1),
(17, '2023_09_09_073311_create_frist_menus_table', 1),
(18, '2023_09_09_073436_create_menu_levels_table', 1),
(19, '2023_09_09_073517_create_bot_users_table', 1),
(20, '2023_09_12_145801_create_main_menu_items_table', 1),
(21, '2023_09_15_083024_create_bills_table', 1),
(22, '2023_09_16_072708_create_payment_menu_items_table', 1),
(23, '2023_09_16_122940_create_gift_card_menu_items_table', 1),
(24, '2023_09_16_122950_create_gift_cards_table', 1),
(25, '2023_09_16_123619_create_used_gift_cards_table', 1),
(26, '2023_09_17_112919_create_faqs_table', 1),
(27, '2023_09_18_070848_create_channel_lock_menu_items_table', 1),
(28, '2023_09_18_070858_create_channel_locks_table', 1),
(29, '2023_09_19_120059_create_proxies_table', 1),
(30, '2023_09_19_120123_create_inbounds_table', 1),
(31, '2023_10_10_120932_add_volume_to_product_category', 1),
(32, '2023_11_01_081724_add_is_chargable_to_product_categori', 1),
(33, '2023_11_01_110241_add_is_active_to_product_categories', 1),
(34, '2023_11_12_072351_add_remarke_to_products_table', 1),
(37, '2024_01_17_075235_create_applications_table', 2),
(38, '2024_03_25_144650_add_secrets_code', 3),
(39, '2024_04_01_075313_add_hiddify_user_link', 4),
(40, '2024_04_15_062639_create_test_accounts_table', 5),
(41, '2024_04_15_062731_create_used_test_accounts_table', 5),
(42, '2024_04_21_071551_create_test_account_menus_table', 6),
(43, '2022_12_02_032758_create_nowpayments_logger_table', 7),
(44, '2024_04_27_084809_add_doller_price_to_product_category', 8),
(45, '2024_04_27_120647_add_ballance_in_dollar_to_account_ballances', 9),
(46, '2014_10_12_000000_create_users_table', 10),
(47, '2014_10_12_100000_create_password_reset_tokens_table', 10),
(48, '2019_08_19_000000_create_failed_jobs_table', 10),
(49, '2019_12_14_000001_create_personal_access_tokens_table', 10),
(50, '2024_05_05_065920_add_amount_in_dollar_to_bills_table', 11),
(51, '2024_05_05_084706_add_amount_in_dollar_to_transactions_table', 12),
(53, '2024_05_06_064823_create_crypto_payments_table', 13),
(55, '2024_05_06_133952_create_transaction_cryptos_table', 14),
(56, '2024_05_11_132700_add_order_id_to_transaction_cryptos', 15),
(57, '2024_05_12_124227_add_is_active_to_crypto_payments', 16),
(59, '2024_06_20_075204_create_agent_permissons_table', 18),
(61, '2024_05_18_074028_create_agent_products_table', 19),
(62, '2024_08_07_103840_create_transaction_settings_table', 20),
(64, '2024_08_11_115912_add_bandweigth_limitaion_and_pr_limitaion', 21),
(65, '2024_08_21_115639_add_deactive_by_admin_to_products_table', 22);

-- --------------------------------------------------------

--
-- Table structure for table `nowpayments_api_call_logger`
--

CREATE TABLE `nowpayments_api_call_logger` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `endpoint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `product_categories_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pannels`
--

CREATE TABLE `pannels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `password` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '123456',
  `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Bearer ',
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_port` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` int(10) UNSIGNED DEFAULT 1000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `secret_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cookie_session` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_link` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_menu_items`
--

CREATE TABLE `payment_menu_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main',
  `alias_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'گزینه پرداخت را انتخاب کنید.',
  `level` int(10) UNSIGNED DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_menu_items`
--

INSERT INTO `payment_menu_items` (`id`, `name`, `alias_name`, `level`, `created_at`, `updated_at`) VALUES
(3, 'main', '👈یک گزینه را انتخاب کنید👉', 1, '2023-09-16 04:38:23', '2023-09-16 05:56:02'),
(4, 'reponse', '💳 لطفا مبلغ را به این شماره واریز کنید و تصویر رسید را ارسال نمایید.', 2, '2023-09-16 04:38:23', '2023-09-16 05:56:02');

-- --------------------------------------------------------

--
-- Table structure for table `payment_types`
--

CREATE TABLE `payment_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `merchant_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'offline',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_types`
--

INSERT INTO `payment_types` (`id`, `name`, `merchant_id`, `type`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'کارت به کارت', '000000000000000000', 'offline', 1, NULL, '2023-09-14 09:43:05'),
(4, 'تتر (usdt-trc20)', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'offline', 0, NULL, '2023-10-21 08:51:12'),
(8, 'زرین پال', 'xxxxxxxxxxxxxxxxxxxxxxxx', 'online', 0, '2023-09-14 04:28:15', '2024-05-13 05:43:28');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_categories_id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `configs` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_link` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panel_link` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `remark` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deactive_by_admin` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pannel_id` bigint(20) UNSIGNED NOT NULL,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` bigint(20) UNSIGNED DEFAULT 0,
  `expire_day` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `volume` double(15,2) UNSIGNED NOT NULL DEFAULT 1.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rechargable` tinyint(1) DEFAULT 1,
  `show_subscription_link` tinyint(1) DEFAULT 1,
  `show_pannel_link` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `price_in_dollar` double(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proxies`
--

CREATE TABLE `proxies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pannel_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'vless',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bot_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '@v2ray_vip_fast',
  `admin_id` bigint(20) NOT NULL DEFAULT 0,
  `bot_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yukkbihb275Ui1LKeGpXSVw',
  `panel_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '127.0.0.1:8000/admin',
  `welcome_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'سلامممممممم به ربات ما خوش آمدید.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `bot_name`, `admin_id`, `bot_token`, `panel_address`, `welcome_message`, `created_at`, `updated_at`) VALUES
(1, '@powerpsbot', 0, 'botxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'https:powerps.ir', '👋سلام، به ربات فرو شv2ray خوش آمدید.', NULL, '2024-07-29 11:16:48');

-- --------------------------------------------------------

--
-- Table structure for table `supports`
--

CREATE TABLE `supports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `question` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `response_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supports`
--

INSERT INTO `supports` (`id`, `question`, `answer`, `response_type`, `created_at`, `updated_at`) VALUES
(1, 'ارتباط با اکانت پیشتیبانی', 'برای ارتباز با پشتیبان لطفا به @adminid پیام بدهید.', 'text', '2024-04-07 09:14:58', '2024-04-07 09:14:58');

-- --------------------------------------------------------

--
-- Table structure for table `telegrams`
--

CREATE TABLE `telegrams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_accounts`
--

CREATE TABLE `test_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pannel_id` bigint(20) UNSIGNED NOT NULL,
  `volume` double(15,2) DEFAULT 1.00,
  `expire_day` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_account_menus`
--

CREATE TABLE `test_account_menus` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `approved` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `denied` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint(20) NOT NULL,
  `payment_type_id` bigint(20) UNSIGNED NOT NULL,
  `confirmed` tinyint(1) DEFAULT 0,
  `recipe_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `amount_dollar` double(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_cryptos`
--

CREATE TABLE `transaction_cryptos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_dollar` double(15,2) DEFAULT 0.00,
  `crypto_payment_id` bigint(20) UNSIGNED NOT NULL,
  `confirmed` tinyint(1) DEFAULT 0,
  `recipe_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `order_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_images`
--

CREATE TABLE `transaction_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` bigint(20) UNSIGNED NOT NULL,
  `img_src` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_text` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_settings`
--

CREATE TABLE `transaction_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dollar_transaction` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaction_settings`
--

INSERT INTO `transaction_settings` (`id`, `dollar_transaction`, `created_at`, `updated_at`) VALUES
(1, 0, '2024-08-07 08:05:09', '2024-08-07 09:19:56');

-- --------------------------------------------------------

--
-- Table structure for table `used_gift_cards`
--

CREATE TABLE `used_gift_cards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `gift_cards_id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `used_test_accounts`
--

CREATE TABLE `used_test_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `test_account_id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('admin','agent','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_ballances`
--
ALTER TABLE `account_ballances`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `agent_permissons`
--
ALTER TABLE `agent_permissons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_permissons_user_id_foreign` (`user_id`);

--
-- Indexes for table `agent_products`
--
ALTER TABLE `agent_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_products_user_id_foreign` (`user_id`),
  ADD KEY `agent_products_product_categories_id_foreign` (`product_categories_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bills_bill_id_unique` (`bill_id`);

--
-- Indexes for table `bot_users`
--
ALTER TABLE `bot_users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `channel_locks`
--
ALTER TABLE `channel_locks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `channel_lock_menu_items`
--
ALTER TABLE `channel_lock_menu_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crypto_payments`
--
ALTER TABLE `crypto_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `frist_menus`
--
ALTER TABLE `frist_menus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gift_cards`
--
ALTER TABLE `gift_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gift_cards_code_unique` (`code`);

--
-- Indexes for table `gift_card_menu_items`
--
ALTER TABLE `gift_card_menu_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inbounds`
--
ALTER TABLE `inbounds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inbounds_proxy_id_foreign` (`proxy_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `main_menu_items`
--
ALTER TABLE `main_menu_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `main_menu_items_position_unique` (`position`);

--
-- Indexes for table `menu_levels`
--
ALTER TABLE `menu_levels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `nowpayments_api_call_logger`
--
ALTER TABLE `nowpayments_api_call_logger`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_order_number_unique` (`order_number`),
  ADD KEY `orders_product_categories_id_foreign` (`product_categories_id`),
  ADD KEY `orders_product_id_foreign` (`product_id`);

--
-- Indexes for table `pannels`
--
ALTER TABLE `pannels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payment_menu_items`
--
ALTER TABLE `payment_menu_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_types`
--
ALTER TABLE `payment_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_types_name_unique` (`name`),
  ADD UNIQUE KEY `payment_types_merchant_id_unique` (`merchant_id`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `products_product_categories_id_foreign` (`product_categories_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_categories_pannel_id_foreign` (`pannel_id`);

--
-- Indexes for table `proxies`
--
ALTER TABLE `proxies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proxies_pannel_id_foreign` (`pannel_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supports`
--
ALTER TABLE `supports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `telegrams`
--
ALTER TABLE `telegrams`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `test_accounts`
--
ALTER TABLE `test_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_accounts_pannel_id_foreign` (`pannel_id`);

--
-- Indexes for table `test_account_menus`
--
ALTER TABLE `test_account_menus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transactions_payment_type_id_foreign` (`payment_type_id`);

--
-- Indexes for table `transaction_cryptos`
--
ALTER TABLE `transaction_cryptos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_cryptos_crypto_payment_id_foreign` (`crypto_payment_id`);

--
-- Indexes for table `transaction_images`
--
ALTER TABLE `transaction_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_images_transaction_id_foreign` (`transaction_id`);

--
-- Indexes for table `transaction_settings`
--
ALTER TABLE `transaction_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `used_gift_cards`
--
ALTER TABLE `used_gift_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `used_gift_cards_gift_cards_id_foreign` (`gift_cards_id`);

--
-- Indexes for table `used_test_accounts`
--
ALTER TABLE `used_test_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `used_test_accounts_test_account_id_foreign` (`test_account_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_account_id_unique` (`account_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_ballances`
--
ALTER TABLE `account_ballances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `agent_permissons`
--
ALTER TABLE `agent_permissons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `agent_products`
--
ALTER TABLE `agent_products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `bot_users`
--
ALTER TABLE `bot_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `channel_locks`
--
ALTER TABLE `channel_locks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `channel_lock_menu_items`
--
ALTER TABLE `channel_lock_menu_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `crypto_payments`
--
ALTER TABLE `crypto_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `frist_menus`
--
ALTER TABLE `frist_menus`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gift_cards`
--
ALTER TABLE `gift_cards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `gift_card_menu_items`
--
ALTER TABLE `gift_card_menu_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inbounds`
--
ALTER TABLE `inbounds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2803;

--
-- AUTO_INCREMENT for table `main_menu_items`
--
ALTER TABLE `main_menu_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `menu_levels`
--
ALTER TABLE `menu_levels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `nowpayments_api_call_logger`
--
ALTER TABLE `nowpayments_api_call_logger`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pannels`
--
ALTER TABLE `pannels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `payment_menu_items`
--
ALTER TABLE `payment_menu_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payment_types`
--
ALTER TABLE `payment_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=143;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `proxies`
--
ALTER TABLE `proxies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supports`
--
ALTER TABLE `supports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `telegrams`
--
ALTER TABLE `telegrams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_accounts`
--
ALTER TABLE `test_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `test_account_menus`
--
ALTER TABLE `test_account_menus`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `transaction_cryptos`
--
ALTER TABLE `transaction_cryptos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `transaction_images`
--
ALTER TABLE `transaction_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `transaction_settings`
--
ALTER TABLE `transaction_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `used_gift_cards`
--
ALTER TABLE `used_gift_cards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `used_test_accounts`
--
ALTER TABLE `used_test_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agent_permissons`
--
ALTER TABLE `agent_permissons`
  ADD CONSTRAINT `agent_permissons_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_products`
--
ALTER TABLE `agent_products`
  ADD CONSTRAINT `agent_products_product_categories_id_foreign` FOREIGN KEY (`product_categories_id`) REFERENCES `product_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_products_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inbounds`
--
ALTER TABLE `inbounds`
  ADD CONSTRAINT `inbounds_proxy_id_foreign` FOREIGN KEY (`proxy_id`) REFERENCES `proxies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_product_categories_id_foreign` FOREIGN KEY (`product_categories_id`) REFERENCES `product_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_product_categories_id_foreign` FOREIGN KEY (`product_categories_id`) REFERENCES `product_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `product_categories_pannel_id_foreign` FOREIGN KEY (`pannel_id`) REFERENCES `pannels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proxies`
--
ALTER TABLE `proxies`
  ADD CONSTRAINT `proxies_pannel_id_foreign` FOREIGN KEY (`pannel_id`) REFERENCES `pannels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_accounts`
--
ALTER TABLE `test_accounts`
  ADD CONSTRAINT `test_accounts_pannel_id_foreign` FOREIGN KEY (`pannel_id`) REFERENCES `pannels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaction_cryptos`
--
ALTER TABLE `transaction_cryptos`
  ADD CONSTRAINT `transaction_cryptos_crypto_payment_id_foreign` FOREIGN KEY (`crypto_payment_id`) REFERENCES `crypto_payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaction_images`
--
ALTER TABLE `transaction_images`
  ADD CONSTRAINT `transaction_images_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `used_gift_cards`
--
ALTER TABLE `used_gift_cards`
  ADD CONSTRAINT `used_gift_cards_gift_cards_id_foreign` FOREIGN KEY (`gift_cards_id`) REFERENCES `gift_cards` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `used_test_accounts`
--
ALTER TABLE `used_test_accounts`
  ADD CONSTRAINT `used_test_accounts_test_account_id_foreign` FOREIGN KEY (`test_account_id`) REFERENCES `test_accounts` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
