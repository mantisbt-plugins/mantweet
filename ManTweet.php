<?php
# Copyright (C) 2008-2009	Victor Boctor
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.

require_once( config_get( 'absolute_path' ) . 'core.php' );
require_once( config_get( 'class_path' ) . 'MantisPlugin.class.php' );

/**
 * A plugin that provides a Twitter like functionality within the bug tracker.
 * The administration has control on who should be able to view the tweets.
 */
class ManTweetPlugin extends MantisPlugin {
	/**
	 *  A method that populates the plugin information and minimum requirements.
	 */
	function register() {
		$this->name		= lang_get( 'plugin_ManTweet_title' );
		$this->description	= lang_get( 'plugin_ManTweet_description' );
		$this->page		= 'config';

		$this->version		= '2.0';
		$this->requires		= array(
			'MantisCore' => '1.2.0',
		);

		$this->author		= 'Victor Boctor';
		$this->contact		= 'vboctor@users.sourceforge.net';
		$this->url		= 'http://www.mantisbt.org';
	}

	/**
	 * Gets the plugin default configuration.
	 */
	function config() {
		return array(
			/**
			 * This options indicates whether the Tweets are
			 * going to be local to the MantisBT instance or
			 * are based on search query from Twitter tweets.
			 * 
			 * MANTWEET_SOURCE_LOCAL
			 * MANTWEET_SOURCE_TWITTER
			 */
			'tweets_source' => 'twitter',

			/**
			 * Access level threshold required to view the ManTweets
			 */
			'view_threshold'	=>	DEVELOPER,
			
			/**
			 * Access level threshold required to post to ManTweet.
			 * This is only applicable if tweets_source is set to
			 * MANTWEET_SOURCE_LOCAL.
			 */
			'post_threshold'	=>	DEVELOPER,
			
			/**
			 * Avatar size.
			 */
			'avatar_size'		=>	48,
			
			/**
			 * Tweets from user above or equal this threshold
			 * are published to the Twitter account used by
			 * core MantisBT. 
			 */
			'post_to_twitter_threshold'	=> NOBODY,
			
			/**
			 * This is the query used to search for relevant
			 * tweets to be imported.  This is done via the
			 * Twitter API.
			 * 
			 * This is only applicable if tweets_source is set
			 * to MANTWEET_SOURCE_TWITTER.
			 * 
			 * e.g. '#mantisbt OR @mantisbt' 
			 */
			'import_query'		=> '#mantisbt OR from:mantisbt OR to:mantisbt OR mantisbt',
			
			/**
			 * This is the default post text.  In case of source
			 * being local, this should typically be empty.  In
			 * case of Twitter source, this should be @ + name
			 * or # + name.  Where such default would match the
			 * import_query.  For example, @mantisbt.
			 */
			'post_default_text' => '@mantisbt ',
		);
	}

	/**
	 * Gets the database schema of the plugin.
	 */
	function schema() {
		return array(
			array( 'CreateTableSQL',
				array( plugin_table( 'updates' ), "
					id				I		NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
					author_id		I		NOTNULL UNSIGNED DEFAULT '0',
					project_id		I		NOTNULL UNSIGNED DEFAULT '0',
					status			C(250)	NOTNULL,
					date_submitted	T		NOTNULL,
					date_updated	T		NOTNULL
				" )
			),
			array( 'AddColumnSQL',
				array( plugin_table( 'updates' ), "
					tw_username		C(64) 	NOTNULL DEFAULT \" '' \""
				)
			),
			array( 'AddColumnSQL',
				array( plugin_table( 'updates' ), "
					tw_avatar		C(250) 	NOTNULL DEFAULT \" '' \""
				)
			),
			array( 'AddColumnSQL',
				array( plugin_table( 'updates' ), "
					tw_id			I	 	UNSIGNED DEFAULT '0'"
				)
			),
		);
	}

	/**
	 * Event hook declaration.
	 * 
	 * @returns An associated array that maps event names to handler names.
	 */
	function hooks() {
		return array(
			'EVENT_MENU_MAIN' => 'process_main_menu' # Main Menu
		);
	}

	/**
	 * If current logged in user can view ManTweet, then add a menu option to the main menu.
	 * 
	 * @returns An array containing the hyper link.
	 */
	function process_main_menu() {
		# return plugin_page( 'index.php' );
		if ( access_has_global_level( plugin_config_get( 'view_threshold' ) ) ) {
			return array( '<a href="' . plugin_page( 'index.php' ) . '">' . lang_get( 'plugin_ManTweet_menu_item' ) . '</a>' );
		}

		return array();
	}
}
