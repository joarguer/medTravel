-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:8889
-- Tiempo de generación: 28-01-2026 a las 15:35:59
-- Versión del servidor: 5.7.39
-- Versión de PHP: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bolsacar_medtravel`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `about_header`
--

CREATE TABLE `about_header` (
  `id` int(11) NOT NULL,
  `img` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '0',
  `titulo` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `subtitle_1` varchar(255) DEFAULT NULL,
  `subtitle_2` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `about_header`
--

INSERT INTO `about_header` (`id`, `img`, `activo`, `titulo`, `title`, `subtitle_1`, `subtitle_2`) VALUES
(1, 'img/about-img.jpg', 0, 'About MedTravel', 'About MedTravel', 'Home', 'About'),
(2, 'img/about-header.jpg', 0, 'About MedTravel', 'About MedTravel', 'Home', 'About'),
(3, 'img/about-header.jpg', 0, 'About MedTravel', 'About MedTravel', 'Home', 'About');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `about_us`
--

CREATE TABLE `about_us` (
  `id` int(11) NOT NULL,
  `img` varchar(255) DEFAULT NULL,
  `bg` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '0',
  `titulo` varchar(255) DEFAULT NULL,
  `texto` text,
  `titulo_small` varchar(255) DEFAULT NULL,
  `titulo_1` varchar(255) DEFAULT NULL,
  `titulo_2` varchar(255) DEFAULT NULL,
  `paragrafo` text,
  `list` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `about_us`
--

INSERT INTO `about_us` (`id`, `img`, `bg`, `activo`, `titulo`, `texto`, `titulo_small`, `titulo_1`, `titulo_2`, `paragrafo`, `list`) VALUES
(1, 'img/about-img.jpg', 'img/about-img-bg.png', 0, 'Who We Are', 'MedTravel connects patients with top clinics worldwide.', 'Warning', 'Who We', 'Are', '<p>MedTravel connects patients with top clinics worldwide. We provide personalized care and assistance.</p>', '[]'),
(2, 'img/about-img.jpg', 'img/about-img-bg.png', 0, 'Who We Are', 'MedTravel connects patients with top clinics worldwide.', 'Warning', 'Who We', 'Are', '<p>MedTravel connects patients with top clinics worldwide. We provide personalized care and assistance.</p>', '[]'),
(3, 'img/about-img.jpg', 'img/about-img-bg.png', 0, NULL, NULL, 'Warning', 'Who We', 'Are', '<p>MedTravel connects patients with top clinics worldwide. We provide personalized care and assistance.</p>', '[\"Personalized treatment\",\"Verified specialists\",\"International support\",\"Affordable packages\"]'),
(4, 'img/about-img.jpg', 'img/about-img-bg.png', 0, 'Who We Are', 'MedTravel connects patients with top clinics worldwide.', 'Warning', 'Who We', 'Are', '<p>MedTravel connects patients with top clinics worldwide. We provide personalized care and assistance.</p>', '[]'),
(5, 'img/about-img.jpg', 'img/about-img-bg.png', 0, NULL, NULL, 'Warning', 'Who We', 'Are', '<p>MedTravel connects patients with top clinics worldwide. We provide personalized care and assistance.</p>', '[\"Personalized treatment\",\"Verified specialists\",\"International support\",\"Affordable packages\"]');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carrucel`
--

CREATE TABLE `carrucel` (
  `id` int(11) NOT NULL,
  `img` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '0',
  `orden` int(11) DEFAULT '0',
  `over_title` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `parrafo` text,
  `btn` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `carrucel`
--

INSERT INTO `carrucel` (`id`, `img`, `activo`, `orden`, `over_title`, `title`, `parrafo`, `btn`) VALUES
(3, 'img/carrucel/3_Welcome_to_MedTravel_3_Welcome_to_MedTravel_about.jpeg?1770753341', 0, 1, 'Warning', 'Welcome to MedTravel', 'Discover our medical tourism packages.', 'Learn More'),
(4, 'img/carousel-2.jpg', 0, 2, 'Health', 'Top Specialists', 'Find certified specialists and clinics.', 'View Specialists'),
(5, 'img/carousel-3.jpg', 0, 3, 'Care', 'Quality Services', 'Personalized care for international patients.', 'Contact Us'),
(6, 'img/carousel-1.jpg', 0, 1, 'Warning', 'Welcome to MedTravel', 'Discover our medical tourism packages.', 'Learn More'),
(7, 'img/carousel-2.jpg', 0, 2, 'Health', 'Top Specialists', 'Find certified specialists and clinics.', 'View Specialists'),
(8, 'img/carousel-3.jpg', 0, 3, 'Care', 'Quality Services', 'Personalized care for international patients.', 'Contact Us'),
(9, 'img/carousel-1.jpg', 0, 1, 'Warning', 'Welcome to MedTravel', 'Discover our medical tourism packages.', 'Learn More'),
(10, 'img/carousel-2.jpg', 0, 2, 'Health', 'Top Specialists', 'Find certified specialists and clinics.', 'View Specialists'),
(11, 'img/carousel-3.jpg', 0, 3, 'Care', 'Quality Services', 'Personalized care for international patients.', 'Contact Us');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificado`
--

CREATE TABLE `certificado` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `nombre_comercial` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT '1',
  `email` varchar(255) DEFAULT NULL,
  `rasocial` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id`, `nombre_comercial`, `estado`, `email`, `rasocial`) VALUES
(1, 'Empresa Demo', 1, 'demo@example.com', 'Empresa Demo'),
(2, 'Empresa Demo', 1, 'demo@example.com', 'Empresa Demo'),
(3, 'Empresa Demo', 1, 'demo@example.com', 'Empresa Demo'),
(4, 'Empresa Demo', 1, 'demo@example.com', 'Empresa Demo'),
(5, 'Empresa Demo', 1, 'demo@example.com', 'Empresa Demo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `offer_media`
--

CREATE TABLE `offer_media` (
  `id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `offer_media`
--

INSERT INTO `offer_media` (`id`, `offer_id`, `path`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 1, 'img/offers/1769552963_e098a7ef53b7.jpg', 1, 1, '2026-01-27 17:29:23'),
(2, 1, 'img/offers/1769557209_225e9eb6d7ca.jpg', 1, 1, '2026-01-27 18:40:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `providers`
--

CREATE TABLE `providers` (
  `id` int(11) NOT NULL,
  `type` enum('medico','clinica') NOT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `description` text,
  `city` varchar(120) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `providers`
--

INSERT INTO `providers` (`id`, `type`, `name`, `slug`, `description`, `city`, `address`, `phone`, `email`, `website`, `is_verified`, `is_active`, `created_at`) VALUES
(1, 'clinica', 'Clínica Demo', 'clinica-demo', 'Prestador demo', 'Ciudad Demo', NULL, NULL, NULL, NULL, 1, 1, '2026-01-27 16:28:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `provider_catalog_services`
--

CREATE TABLE `provider_catalog_services` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `provider_categories`
--

CREATE TABLE `provider_categories` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `provider_service_offers`
--

CREATE TABLE `provider_service_offers` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `description` text,
  `price_from` decimal(12,2) DEFAULT NULL,
  `currency` varchar(5) NOT NULL DEFAULT 'USD',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `provider_service_offers`
--

INSERT INTO `provider_service_offers` (`id`, `provider_id`, `service_id`, `title`, `description`, `price_from`, `currency`, `is_active`, `created_at`) VALUES
(1, 1, 1, 'Oferta demo 2', 'Oferta de ejemplo para pruebas 2', '100.02', 'USD', 1, '2026-01-27 16:45:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `provider_users`
--

CREATE TABLE `provider_users` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_in_provider` varchar(30) NOT NULL DEFAULT 'owner'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `provider_users`
--

INSERT INTO `provider_users` (`id`, `provider_id`, `user_id`, `role_in_provider`) VALUES
(1, 1, 1, 'owner');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `service_catalog`
--

CREATE TABLE `service_catalog` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(180) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_description` text,
  `sort_order` int(11) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `service_catalog`
--

INSERT INTO `service_catalog` (`id`, `category_id`, `name`, `slug`, `short_description`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 1, 'Limpieza dental', 'limpieza-dental', 'Servicio de limpieza dental profesional', 1, 1, '2026-01-27 16:24:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `service_categories`
--

CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `service_categories`
--

INSERT INTO `service_categories` (`id`, `name`, `slug`, `description`, `image`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Odontología', 'odontologia', 'Servicios de odontología', NULL, 1, 1, '2026-01-27 16:18:30'),
(2, 'Dermatología', 'dermatologia', 'Servicios de dermatología', NULL, 2, 1, '2026-01-27 16:18:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sessiones_activas`
--

CREATE TABLE `sessiones_activas` (
  `id` int(11) NOT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `visitante` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitud` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitud` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cobrador` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hora2` time DEFAULT NULL,
  `ips` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sessiones_activas`
--

INSERT INTO `sessiones_activas` (`id`, `fecha`, `hora`, `visitante`, `usuario`, `ip`, `latitud`, `longitud`, `cobrador`, `hora2`, `ips`) VALUES
(12, '2026-01-27', '19:39:14', 'Empresa Demo', 'admin', '::1', '0', '0', '0', '00:00:00', NULL),
(13, '2026-01-28', '09:18:39', 'Empresa Demo', 'admin', '127.0.0.1', '0', '0', '0', '00:00:00', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `social_media`
--

CREATE TABLE `social_media` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `specialist`
--

CREATE TABLE `specialist` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `specialist_list`
--

CREATE TABLE `specialist_list` (
  `id` int(11) NOT NULL,
  `specialist_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '0',
  `img` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `twiter` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `subtitulo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `specialist_list`
--

INSERT INTO `specialist_list` (`id`, `specialist_id`, `activo`, `img`, `facebook`, `twiter`, `instagram`, `titulo`, `subtitulo`) VALUES
(1, NULL, 0, 'img/guide-1.jpg', '#', '#', '#', 'Dr. John Doe', 'Cardiologist'),
(2, NULL, 0, 'img/guide-2.jpg', '#', '#', '#', 'Dr. Jane Smith', 'Dentist'),
(3, NULL, 0, 'img/guide-3.jpg', '#', '#', '#', 'Dr. Pablo Ruiz', 'Surgeon');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `token` varchar(255) NOT NULL DEFAULT '',
  `empresa` varchar(255) DEFAULT '',
  `ppal` tinyint(1) DEFAULT '0',
  `usrlogin` varchar(100) DEFAULT 'admin',
  `rol` varchar(50) DEFAULT 'admin',
  `cargo` varchar(100) DEFAULT '',
  `email` varchar(255) DEFAULT '',
  `ciudad` varchar(100) DEFAULT '',
  `telefono` varchar(50) DEFAULT '',
  `celular` varchar(50) DEFAULT '',
  `cambio_password` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `password`, `avatar`, `nombre`, `activo`, `token`, `empresa`, `ppal`, `usrlogin`, `rol`, `cargo`, `email`, `ciudad`, `telefono`, `celular`, `cambio_password`) VALUES
(1, 'admin', '3627909a29c31381a071ec27f7c9ca97726182aed29a7ddd2e54353322cfb30abb9e3a6df2ac2c20fe23436311d678564d0c8d305930575f60e2d3d048184d79', 'img/perfil/1_avatar.jpg?1805764377', 'Administrador', 1, '', 'Empresa Demo', 0, 'admin', 'admin', '', 'correo@gmail.com', '', '', '', 0),
(8, 'testuser2@example.com', '5fef01518d0a5ae6683f605d3e3bc933fb1b891794a5e4db772173da0b0ca761f8b1969650b9af4e2afa15bb4f80b139fd34208303690ab4acaef592e31a2c87', 'img/perfil/default.png', 'Test User', 1, '4d49901fbf8f4f2332510f383d45f8b4', '', 0, 'testuser2@example.com', '', '', 'testuser2@example.com', 'City', '12345', '', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visitas`
--

CREATE TABLE `visitas` (
  `id` int(11) NOT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `hora2` datetime DEFAULT NULL,
  `visitante` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dispositivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `visitas`
--

INSERT INTO `visitas` (`id`, `fecha`, `hora`, `hora2`, `visitante`, `usuario`, `dispositivo`, `ip`) VALUES
(1, '2026-01-27', '14:54:13', NULL, '', 'admin', '', '127.0.0.1'),
(2, '2026-01-27', '14:54:40', NULL, '', 'admin', '', '127.0.0.1'),
(3, '2026-01-27', '15:28:59', NULL, 'Empresa Demo', 'admin', '', '::1'),
(4, '2026-01-27', '15:29:22', NULL, 'Empresa Demo', 'admin', '', '::1'),
(5, '2026-01-27', '15:29:43', NULL, 'Empresa Demo', 'admin', '', '::1'),
(6, '2026-01-27', '19:10:05', NULL, 'Empresa Demo', 'admin', '', '::1'),
(7, '2026-01-27', '19:14:06', NULL, 'Empresa Demo', 'admin', '', '::1'),
(8, '2026-01-27', '19:15:11', NULL, 'Empresa Demo', 'admin', '', '::1'),
(9, '2026-01-27', '19:17:21', NULL, 'Empresa Demo', 'admin', '', '::1'),
(10, '2026-01-27', '19:19:40', NULL, 'Empresa Demo', 'admin', '', '::1'),
(11, '2026-01-27', '19:35:05', NULL, 'Empresa Demo', 'admin', '', '::1'),
(12, '2026-01-27', '19:39:14', NULL, 'Empresa Demo', 'admin', '', '::1'),
(13, '2026-01-28', '09:18:39', NULL, 'Empresa Demo', 'admin', '', '127.0.0.1');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `about_header`
--
ALTER TABLE `about_header`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `about_us`
--
ALTER TABLE `about_us`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `carrucel`
--
ALTER TABLE `carrucel`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `certificado`
--
ALTER TABLE `certificado`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `offer_media`
--
ALTER TABLE `offer_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_offer_id` (`offer_id`);

--
-- Indices de la tabla `providers`
--
ALTER TABLE `providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indices de la tabla `provider_catalog_services`
--
ALTER TABLE `provider_catalog_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_provider_service` (`provider_id`,`service_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indices de la tabla `provider_categories`
--
ALTER TABLE `provider_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_provider_category` (`provider_id`,`category_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indices de la tabla `provider_service_offers`
--
ALTER TABLE `provider_service_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_service_id` (`service_id`);

--
-- Indices de la tabla `provider_users`
--
ALTER TABLE `provider_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_provider_user` (`provider_id`,`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indices de la tabla `service_catalog`
--
ALTER TABLE `service_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_category` (`category_id`);

--
-- Indices de la tabla `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indices de la tabla `sessiones_activas`
--
ALTER TABLE `sessiones_activas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `social_media`
--
ALTER TABLE `social_media`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `specialist`
--
ALTER TABLE `specialist`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `specialist_list`
--
ALTER TABLE `specialist_list`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Indices de la tabla `visitas`
--
ALTER TABLE `visitas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `about_header`
--
ALTER TABLE `about_header`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `about_us`
--
ALTER TABLE `about_us`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `carrucel`
--
ALTER TABLE `carrucel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `certificado`
--
ALTER TABLE `certificado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `offer_media`
--
ALTER TABLE `offer_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `providers`
--
ALTER TABLE `providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `provider_catalog_services`
--
ALTER TABLE `provider_catalog_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `provider_categories`
--
ALTER TABLE `provider_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `provider_service_offers`
--
ALTER TABLE `provider_service_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `provider_users`
--
ALTER TABLE `provider_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `service_catalog`
--
ALTER TABLE `service_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `sessiones_activas`
--
ALTER TABLE `sessiones_activas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `social_media`
--
ALTER TABLE `social_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `specialist`
--
ALTER TABLE `specialist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `specialist_list`
--
ALTER TABLE `specialist_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `visitas`
--
ALTER TABLE `visitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `offer_media`
--
ALTER TABLE `offer_media`
  ADD CONSTRAINT `fk_media_offer` FOREIGN KEY (`offer_id`) REFERENCES `provider_service_offers` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `provider_catalog_services`
--
ALTER TABLE `provider_catalog_services`
  ADD CONSTRAINT `fk_ps_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ps_service` FOREIGN KEY (`service_id`) REFERENCES `service_catalog` (`id`);

--
-- Filtros para la tabla `provider_categories`
--
ALTER TABLE `provider_categories`
  ADD CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`),
  ADD CONSTRAINT `fk_pc_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `provider_service_offers`
--
ALTER TABLE `provider_service_offers`
  ADD CONSTRAINT `fk_offers_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_offers_service` FOREIGN KEY (`service_id`) REFERENCES `service_catalog` (`id`);

--
-- Filtros para la tabla `provider_users`
--
ALTER TABLE `provider_users`
  ADD CONSTRAINT `fk_provider_users_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_provider_users_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `service_catalog`
--
ALTER TABLE `service_catalog`
  ADD CONSTRAINT `fk_service_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
