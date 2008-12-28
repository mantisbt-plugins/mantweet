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

require_once( 'twitter_api.php' );

/**
 * A class that contains all the information relating to a tweet.
 */
class MantweetUpdate
{
	/**
	 * The tweet id in the database.
	 */
	var $id = 0;
	
	/**
	 * The tweet content.
	 */
	var $status = '';
	
	/**
	 * The tweet author id (i.e. user id) or 0 for tweets imported
	 * from twitter.
	 */
	var $author_id = 0;
	
	/**
	 * The tweet project id, currently tweets are not associated with projects,
	 * hence, the id is ALL_PROJECTS.
	 */
	var $project_id = ALL_PROJECTS;
	
	/**
	 * For tweets imported from twitters this is set to Twitter
	 * tweet id.  Otherwise it is set to 0.
	 */
	var $tw_id = 0;
	
	/**
	 * For tweets imported from Twitter this is set to Twitter
	 * user name of the author of the tweet, otherwise 0.
	 */
	var $tw_username = '';
	
	/**
	 * For tweets imported from Twitter this is set to the URL
	 * of the user's avatar, otherwise it is left empty.
	 */
	var $tw_avatar = '';

	/**
	 * The submission timestamp for the tweet.  This is setup by the mantweet_add()
	 * function.
	 */
	var $date_submitted = null;
	
	/**
	 * The last update timestamp for the tweet.  This is setup nby the mantweet_add()
	 * function and is currently never changed since ManTweet doesn't support editing.
	 */
	var $date_updated = null;
}

/**
 * Checks if the current logged in user has the necessary access level to submit
 * a tweet.
 * 
 * @returns bool true for yes, otherwise false.
 */
function mantweet_can_post() {
	return 
		( plugin_config_get( 'tweets_source' ) == 'local' ) && 
		access_has_global_level( plugin_config_get( 'post_threshold' ) );
}

/**
 * Adds a tweet.  This functional sets the submitted / last updated timestamps to now.
 * 
 * @param MantweetUpdate $p_mantweet_update  The information about the tweet to be added. 
 */
function mantweet_add( $p_mantweet_update ) {
	if ( !mantweet_can_post() ) {
		access_denied();
	}

	# Trip the string because we don't want spaces around it.
	$p_mantweet_update->status = trim( $p_mantweet_update->status );

	if ( is_blank( $p_mantweet_update->status ) ) {
		error_parameters( lang_get( 'plugin_ManTweet_status_update' ) );
		trigger_error( ERROR_EMPTY_FIELD, ERROR );
	}

	$t_updates_table = plugin_table( 'updates' );

	$t_query = "INSERT INTO $t_updates_table ( author_id, status, date_submitted, date_updated ) VALUES (" . db_param( 0 ) . ", " . db_param( 1 ) . ", '" . db_now() . "', '" . db_now() . "')";

	db_query_bound( $t_query, array( $p_mantweet_update->author_id, $p_mantweet_update->status ) );

	if ( access_has_global_level( plugin_config_get( 'post_to_twitter_threshold' ) ) ) {
		$t_twitter_update = user_get_name( $p_mantweet_update->author_id ) . ': ' . $p_mantweet_update->status;
		twitter_update( $t_twitter_update );
	}

	return db_insert_id( $t_updates_table );
}

/**
 * Gets the tweet visible on a page given the page number (1 based)
 * and the number of tweets per page.
 * 
 * @param int $p_page_id   A 1-based page number.
 * @param int $p_per_page  The number of tweets to display per page.
 * 
 * @returns Array of MantweetUpdate class instances. 
 */
function mantweet_get_page( $p_page_id, $p_per_page ) {
	$t_updates_table = plugin_table( 'updates' );
	$t_offset = ( $p_page_id - 1 ) * $p_per_page;

	$t_query = "SELECT * FROM $t_updates_table ORDER BY date_submitted DESC";
	$t_result = db_query_bound( $t_query, null, $p_per_page, $t_offset );

	$t_updates = array();

	while ( $t_row = db_fetch_array( $t_result ) ) {
		$t_current_update = new MantweetUpdate();
		$t_current_update->id = (integer)$t_row['id'];
		$t_current_update->author_id = (integer)$t_row['author_id'];
		$t_current_update->project_id = (integer)$t_row['project_id'];
		$t_current_update->status = $t_row['status'];
		$t_current_update->tw_id = $t_row['tw_id'];
		$t_current_update->tw_username = $t_row['tw_username'];
		$t_current_update->tw_avatar = $t_row['tw_avatar'];
		$t_current_update->date_submitted = db_unixtimestamp( $t_row['date_submitted'] );
		$t_current_update->date_updated = db_unixtimestamp( $t_row['date_updated'] );

		$t_updates[] = $t_current_update;
	}

	return $t_updates;
}

/**
 * Gets the total number of tweets in the database.
 * 
 * @returns the number of tweets.
 */
function mantweet_get_updates_count() {
	$t_updates_table = plugin_table( 'updates' );

	$t_query = "SELECT count(*) FROM $t_updates_table";
	$t_result = db_query_bound( $t_query, null );

	return db_result( $t_result );
}

/**
 * Deletes all tweets in the database.
 */
function mantweet_purge() {
	$t_updates_table = plugin_table( 'updates' );
	$t_query = "DELETE FROM $t_updates_table";
	db_query( $t_query );	
}

function mantweet_get_max_twitter_id() {
	$t_updates_table = plugin_table( 'updates' );

	$t_query = "SELECT tw_id FROM $t_updates_table ORDER BY tw_id DESC";
	$t_result = db_query( $t_query, 1 );
	
	if ( db_num_rows( $t_result ) == 0 ) {
		return 0;
	}
	
	return db_result( $t_result );
}

function mantweet_import_from_twitter() {
	# just for testing.
	#mantweet_purge();

	$t_connection_options = array(
		'username'	=> config_get( 'twitter_username' ),
		'password'	=> config_get( 'twitter_password' ),
		'type'		=> 'json' //or 'xml'
	);

	$t_q = plugin_config_get( 'import_query' );
	$t_results_per_page = 100;
	$t_page = 1;
	$t_more_work = true;

	$t_search_since_id = mantweet_get_max_twitter_id();

	while ( $t_more_work )
	{
		// Create the Twiter_API object
		$t_twitter_api = new twitter_api( $t_connection_options );

		$t_search_options = array(
			'q' => $t_q,
			'rpp' => $t_results_per_page,
			'page' => $t_page,
			'show_user' => false,
			'since_id' => $t_search_since_id,
		);

		$t_response = $t_twitter_api->search( $t_search_options );

		# if connection to twitter failed, then exit.
		if ( !is_object( $t_response ) ) {
			break;
		}

		#echo '<pre>';
		#print_r( $t_response );
		#echo '</pre>';
		
		$t_result_count = count( $t_response->results );
		
		if ( $t_result_count > 0 ) {
			$t_updates_table = plugin_table( 'updates' );
		
			foreach ( $t_response->results as $t_tweet ) {
				// Check that tweet doesn't exist before adding.
				$t_search_query = "SELECT count(*) FROM $t_updates_table WHERE tw_id = " . db_param( 0 );
				$t_result = db_query_bound( $t_search_query, array( $t_tweet->id ) );

				// If new, then add it.				
				if ( db_result( $t_result ) == 0 ) {
					$t_status = $t_tweet->text;
					$t_created_at = db_date( strtotime( $t_tweet->created_at ), /* gmt */ false );

					$t_query = "INSERT INTO $t_updates_table ( tw_id, tw_username, tw_avatar, status, date_submitted, date_updated ) VALUES (" . db_param( 0 ) . ", " . db_param( 1 ) . ", " . db_param( 2 ) . ", " . db_param( 3 ) . ", " . db_param( 4 ) . ", " . db_param( 5 ) . ")";
					db_query_bound( $t_query, array( $t_tweet->id, $t_tweet->from_user, $t_tweet->profile_image_url, $t_status, $t_created_at, $t_created_at ) );
				}
			}
		}
			
		$t_page++;		
		if ( $t_result_count < $t_results_per_page ) {
			$t_more_work = false;
		}
		
		unset( $t_twitter_api );
	}
}
