CREATE TABLE IF NOT EXISTS `tile` (
  `tile_id` int(10) unsigned NOT NULL auto_increment COMMENT 'tile unique id',
  `tile_color` mediumint(8) unsigned NOT NULL COMMENT 'color',
  `tile_location` enum('Board','Player','Deck', 'Common', 'dev') NOT NULL,
  `tile_location_arg` int(11) ,
  `board_tile_x` int(11) COMMENT 'tile position relative to origin',
  `board_tile_y` int(11) COMMENT 'tile position relative to origin',
  PRIMARY KEY (`tile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `token` (
  `token_id` int(10) unsigned NOT NULL auto_increment COMMENT 'token unique id',
  `token_player` int(11) unsigned NOT NULL COMMENT 'player id',
  `board_token_x` int(11) COMMENT 'token position relative to origin',
  `board_token_y` int(11) COMMENT 'token position relative to origin',
  `triangleDown` boolean COMMENT 'triangleDown',
  `triangleUpLeft` boolean COMMENT 'triangleUpLeft',
  `triangleDownLeft` boolean COMMENT 'triangleDownLeft',
  `triangleUp` boolean COMMENT 'triangleUp',
  `triangleDownRight` boolean COMMENT 'triangleDownRight',
  `triangleUpRight` boolean COMMENT 'triangleUpRight',
  PRIMARY KEY (`token_id`),
  UNIQUE KEY (`board_token_x`,`board_token_y`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `tokenTile` (
  `token_id` int(10) unsigned NOT NULL COMMENT 'token unique id',
  `tile_id` int(10) unsigned NOT NULL COMMENT 'tile unique id',
  PRIMARY KEY (`token_id`,`tile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


ALTER TABLE `token` ADD FOREIGN KEY ( `token_player` ) REFERENCES `player` (
`player_id`
);

ALTER TABLE `tokenTile` ADD FOREIGN KEY ( `tile_id` ) REFERENCES `tile` (
`tile_id`
);

ALTER TABLE `tokenTile` ADD FOREIGN KEY ( `token_id` ) REFERENCES `token` (
`token_id`
);
