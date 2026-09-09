CREATE TABLE IF NOT EXISTS `faq_embeddings` (
  `faq_id` int UNSIGNED NOT NULL,
  `model` varchar(120) NOT NULL DEFAULT '',
  `vector_json` mediumtext NOT NULL,
  `content_hash` varchar(64) NOT NULL DEFAULT '',
  `updated_at` timestamp NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`faq_id`),
  CONSTRAINT `fk_faq_embeddings_faq` FOREIGN KEY (`faq_id`) REFERENCES `faqs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
