
-- Dumping database structure for network
CREATE DATABASE IF NOT EXISTS `network` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_bin */;
USE `network`;

-- Dumping structure for table network.connections
CREATE TABLE IF NOT EXISTS `connections` (
  `mac` varchar(17) COLLATE utf8_bin DEFAULT NULL,
  `src_ip` varchar(15) COLLATE utf8_bin NOT NULL,
  `dst_ip` varchar(15) COLLATE utf8_bin NOT NULL,
  `date` date NOT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `uniqe_per_connection_and_date` (`mac`,`dst_ip`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Data exporting was unselected.
-- Dumping structure for table network.names
CREATE TABLE IF NOT EXISTS `names` (
  `mac` varchar(17) COLLATE utf8_bin NOT NULL,
  `name` varchar(50) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`mac`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

