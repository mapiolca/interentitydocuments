-- ============================================================================
-- Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `llx_thirdparty_entity`
(
    `rowid`     INTEGER  NOT NULL AUTO_INCREMENT,
    `entity`    INTEGER  NOT NULL DEFAULT 1,
    `fk_soc`    INTEGER  NOT NULL,
    `fk_entity` INTEGER  NOT NULL,
    `date_cre`  DATETIME,
    `date_maj`  DATETIME,
    PRIMARY KEY (`rowid`)
) ENGINE=InnoDB;
