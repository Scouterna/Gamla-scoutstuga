SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE IF NOT EXISTS `wp_rent_object` (
  `rent_object_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rent_organisation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rent_object_type_id` bigint(20) NOT NULL DEFAULT '1',
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `ingress` text NOT NULL,
  `description` mediumtext NOT NULL,
  `main_image` int(11) DEFAULT NULL,
  `url` tinytext NOT NULL,
  `beds` int(10) UNSIGNED NOT NULL,
  `visit_adress` text NOT NULL,
  `post_adress` text NOT NULL,
  `position_latitude` double NOT NULL COMMENT '+: North, -: South',
  `position_longitude` double NOT NULL COMMENT '+: East, -: West',
  `city` tinytext NOT NULL,
  `price_description` text NOT NULL,
  `contact_name` tinytext NOT NULL,
  `contact_phone` tinytext NOT NULL,
  `contact_email` tinytext NOT NULL,
  `contact_other` text NOT NULL,
  `object_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `object_status` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rent_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_object_images` (
  `rent_object_id` bigint(20) UNSIGNED NOT NULL,
  `image_id` bigint(20) UNSIGNED NOT NULL,
  `pos` int(11) NOT NULL DEFAULT '0',
  `assigned` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rent_object_id`,`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_object_permissions` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `rent_object_id` bigint(20) UNSIGNED NOT NULL,
  `assigned` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`rent_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_object_settings` (
  `rent_object_id` bigint(20) UNSIGNED NOT NULL,
  `rent_object_settings_name_id` bigint(20) UNSIGNED NOT NULL,
  `option_value` int(10) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`rent_object_id`,`rent_object_settings_name_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_object_settings_names` (
  `rent_object_settings_name_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_name` tinytext NOT NULL,
  PRIMARY KEY (`rent_object_settings_name_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_object_settings_options` (
  `rent_object_settings_name_id` bigint(20) UNSIGNED NOT NULL,
  `option_name` tinytext NOT NULL,
  `option_value` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`rent_object_settings_name_id`,`option_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_object_types` (
  `rent_object_type_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_name` tinytext NOT NULL,
  `url` tinytext NOT NULL,
  `price_scenario_id` bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`rent_object_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_organisations` (
  `rent_organisation_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_organisation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `organisation_name` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `object_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_status` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`rent_organisation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_organisation_permissions` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `rent_organisation_id` bigint(20) UNSIGNED NOT NULL,
  `assigned` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`rent_organisation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_prices` (
  `rent_object_id` bigint(20) UNSIGNED NOT NULL,
  `price_scenario_id` bigint(20) UNSIGNED NOT NULL,
  `price` int(10) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `price_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rent_object_id`,`price_scenario_id`),
  KEY `price_example_id` (`price_scenario_id`,`rent_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_price_scenarios` (
  `price_scenario_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `prio` int(11) NOT NULL DEFAULT '0',
  `price_scenario_name` varchar(255) NOT NULL,
  `price_scenario` text NOT NULL,
  `days` int(11) NOT NULL,
  `people` int(11) NOT NULL,
  PRIMARY KEY (`price_scenario_id`),
  KEY `prio` (`prio`,`price_scenario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wp_rent_user` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `main_organisation_id` bigint(20) UNSIGNED NOT NULL,
  `user_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_status` tinyint(4) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
