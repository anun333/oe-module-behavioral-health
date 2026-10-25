--
-- Behavioral Health 0.1.1 to 0.2.0: treatment plans and their reviews, and the settings for them. The same
-- blocks are in install.sql; they are guarded, so running both changes nothing.
--

-- The module's settings: how long a treatment plan may go between reviews, and how far ahead the Staff
-- Worklist shows a review as due.
#IfNotTable behavioral_health_setting
CREATE TABLE `behavioral_health_setting` (
    `name` VARCHAR(64) NOT NULL,
    `value` TEXT,
    `updated_by` INT(11) NOT NULL COMMENT 'users.id who saved it',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB COMMENT='Behavioral Health: settings';
#EndIf

-- A patient's treatment plan: its goals, interventions and health concerns are in core's care plan form
-- (form_care_plan); this row is the plan's review schedule, from the day the person endorsed it to its end.
#IfNotTable behavioral_health_plan
CREATE TABLE `behavioral_health_plan` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `pid` BIGINT(20) NOT NULL COMMENT 'patient_data.pid',
    `started_on` DATE NOT NULL COMMENT 'the day the first plan was endorsed',
    `interval_days` SMALLINT NOT NULL COMMENT 'the longest time allowed between reviews, from the settings when the plan started',
    `ended_on` DATE DEFAULT NULL,
    `end_reason` VARCHAR(255) NOT NULL DEFAULT '',
    `created_by` INT(11) NOT NULL COMMENT 'users.id',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_by` INT(11) DEFAULT NULL COMMENT 'users.id',
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `pid` (`pid`)
) ENGINE=InnoDB COMMENT='Behavioral Health: treatment plans';
#EndIf

-- Each time the plan was made or reviewed: the care plan form it read, the person's endorsement, who took
-- part, each goal's progress as the goal read then, and the next review date.
#IfNotTable behavioral_health_plan_review
CREATE TABLE `behavioral_health_plan_review` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `plan_id` INT(11) NOT NULL COMMENT 'behavioral_health_plan.id',
    `pid` BIGINT(20) NOT NULL COMMENT 'patient_data.pid',
    `kind` VARCHAR(8) NOT NULL COMMENT 'initial, review or update',
    `reviewed_on` DATE NOT NULL,
    `care_plan_form_id` BIGINT(20) DEFAULT NULL COMMENT 'forms.form_id of the care plan form (formdir care_plan) it read',
    `endorsement` VARCHAR(12) NOT NULL COMMENT 'signed, verbal, declined or unable',
    `participants` TEXT,
    `progress` TEXT COMMENT 'JSON: each goal of the care plan as it read then, and its progress',
    `changes` TEXT,
    `next_due` DATE NOT NULL,
    `document_id` INT(11) DEFAULT NULL COMMENT 'documents.id of the signed plan',
    `reviewed_by` INT(11) NOT NULL COMMENT 'users.id',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `plan_id` (`plan_id`),
    KEY `pid` (`pid`),
    KEY `next_due` (`next_due`)
) ENGINE=InnoDB COMMENT='Behavioral Health: treatment plan reviews';
#EndIf
