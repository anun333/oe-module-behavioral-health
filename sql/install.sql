--
-- Behavioral Health: install. Every statement is inside an #If...#EndIf guard, so running it again changes
-- nothing (tools/footprint.php checks that). Later versions add sql/X_Y_Z-to-A_B_C_upgrade.sql with
-- the same guarded blocks; Module Manager offers an upgrade when one's from-version is at or after the
-- installed one.
--

-- Patients' Part 2 consents (42 CFR 2.31), as staff record them from the signed forms.
#IfNotTable behavioral_health_part2_consent
CREATE TABLE `behavioral_health_part2_consent` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `pid` BIGINT(20) NOT NULL COMMENT 'patient_data.pid',
    `recipient` VARCHAR(255) NOT NULL COMMENT 'name or general designation of who may receive the records',
    `recipient_address` VARCHAR(255) NOT NULL DEFAULT '',
    `purpose` VARCHAR(255) NOT NULL,
    `information` TEXT NOT NULL COMMENT 'how much and what kind of information',
    `signed_on` DATE NOT NULL,
    `expires_on` DATE DEFAULT NULL,
    `expiration_event` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'when the consent names an event instead of a date',
    `ended_on` DATE DEFAULT NULL COMMENT 'the day the expiration event happened',
    `document_id` INT(11) DEFAULT NULL COMMENT 'documents.id of the signed form',
    `revoked_on` DATE DEFAULT NULL,
    `note` TEXT,
    `created_by` INT(11) NOT NULL COMMENT 'users.id',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_by` INT(11) DEFAULT NULL COMMENT 'users.id',
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `pid` (`pid`),
    KEY `expires_on` (`expires_on`)
) ENGINE=InnoDB COMMENT='Behavioral Health: Part 2 consents';
#EndIf

-- Beside each Part 2 disclosure in OpenEMR's Disclosures record (extended_log): the consent it relied
-- on and what went with the records (42 CFR 2.32).
#IfNotTable behavioral_health_part2_disclosure
CREATE TABLE `behavioral_health_part2_disclosure` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `extended_log_id` BIGINT(20) NOT NULL COMMENT 'extended_log.id',
    `consent_id` INT(11) NOT NULL COMMENT 'behavioral_health_part2_consent.id',
    `pid` BIGINT(20) NOT NULL COMMENT 'patient_data.pid',
    `statement` TINYINT(1) NOT NULL COMMENT '42 CFR 2.32(a) statement 1 or 2',
    `accompaniment` VARCHAR(8) NOT NULL COMMENT 'copy: a copy of the consent; scope: an explanation of its scope',
    `recorded_by` INT(11) NOT NULL COMMENT 'users.id',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `extended_log_id` (`extended_log_id`),
    KEY `consent_id` (`consent_id`),
    KEY `pid` (`pid`)
) ENGINE=InnoDB COMMENT='Behavioral Health: Part 2 disclosures';
#EndIf

-- The disclosure type these are recorded under in OpenEMR's Disclosures, after core's three.
#IfNotRow2D list_options list_id disclosure_type option_id disclosure-part2-consent
INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`)
    VALUES ('disclosure_type', 'disclosure-part2-consent', 'Part 2: with written consent', 40, 0, 1);
#EndIf
