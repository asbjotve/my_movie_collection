-- Migration: support multiple related movies per disc (box-set discs
-- that hold more than one title, e.g. Wallace & Gromit 2-disc/4-film set).
--
-- This is purely ADDITIVE: it does not touch disc_in.related_content_id
-- (left in place but deprecated/unused going forward) so rollback is a
-- single DROP TABLE with zero risk to existing data.
--
-- Run as root (media_arkiv_admin has no ALTER/CREATE privilege by design).

CREATE TABLE `disc_related_content` (
  `disc_id` binary(16) NOT NULL,
  `content_id` binary(16) NOT NULL,
  PRIMARY KEY (`disc_id`, `content_id`),
  KEY `idx_disc_related_content__content_id` (`content_id`),
  CONSTRAINT `fk_disc_related_content__disc` FOREIGN KEY (`disc_id`) REFERENCES `disc` (`disc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_disc_related_content__content` FOREIGN KEY (`content_id`) REFERENCES `content` (`content_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

GRANT SELECT, INSERT, UPDATE, DELETE ON db_mediearkiv.disc_related_content TO 'media_arkiv_admin'@'172.19.%';
