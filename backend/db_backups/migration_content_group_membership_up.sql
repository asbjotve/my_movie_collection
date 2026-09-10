-- Migration: allow a movie to belong to MULTIPLE movie groups.
--
-- Until now, content.group_id was a plain FK to movie_group (a movie
-- could belong to at most one group). This adds a proper many-to-many
-- junction table, content_group_membership, with its own per-group
-- sort_order (so the same movie can have a different position in each
-- group it belongs to).
--
-- This is purely ADDITIVE: content.group_id / content.group_sort_order
-- are left in place (deprecated/unused going forward once the backend
-- switches over) so rollback is a single DROP TABLE with zero risk to
-- existing data. Existing group_id/group_sort_order values are copied
-- into the new table by this migration (not removed from content).
--
-- Run as root (media_arkiv_admin has no ALTER/CREATE privilege by design).

CREATE TABLE `content_group_membership` (
  `content_id` binary(16) NOT NULL,
  `group_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT NULL,
  PRIMARY KEY (`content_id`, `group_id`),
  KEY `idx_content_group_membership__group` (`group_id`, `sort_order`),
  CONSTRAINT `fk_content_group_membership__content` FOREIGN KEY (`content_id`) REFERENCES `content` (`content_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_content_group_membership__group` FOREIGN KEY (`group_id`) REFERENCES `movie_group` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- Carry over existing single-group memberships so nothing is lost.
INSERT INTO `content_group_membership` (`content_id`, `group_id`, `sort_order`)
SELECT `content_id`, `group_id`, `group_sort_order`
FROM `content`
WHERE `group_id` IS NOT NULL;

GRANT SELECT, INSERT, UPDATE, DELETE ON db_mediearkiv.content_group_membership TO 'media_arkiv_admin'@'172.19.%';
