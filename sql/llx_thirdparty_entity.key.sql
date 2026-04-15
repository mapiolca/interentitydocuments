ALTER TABLE `llx_thirdparty_entity` ADD INDEX idx_thirdparty_entity_entity (`entity`);
ALTER TABLE `llx_thirdparty_entity` ADD INDEX idx_thirdparty_entity_fk_soc (`fk_soc`);
ALTER TABLE `llx_thirdparty_entity` ADD INDEX idx_thirdparty_entity_fk_entity (`fk_entity`);
