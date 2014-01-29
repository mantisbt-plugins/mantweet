<?php
# ManTweet - a twitter plugin for MantisBT
#
# Copyright (c) Victor Boctor
# Copyright (c) Mantis Team - mantisbt-dev@lists.sourceforge.net
#
# ManTweet is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# ManTweet is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with ManTweet.  If not, see <http://www.gnu.org/licenses/>.

require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'twitter_api.php' );

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

	$t_query = "INSERT INTO $t_updates_table ( author_id, status, date_submitted, date_updated ) VALUES (" . db_param( 0 ) . ", " . db_param( 1 ) . ", '" . mantweet_db_now() . "', '" . mantweet_db_now() . "')";

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

	if ( plugin_config_get( 'tweets_source' ) == 'local' ) {
		$t_where = 'WHERE tw_id = 0';
	} else {
		$t_where = 'WHERE tw_id <> 0';
	}

	$t_query = "SELECT * FROM $t_updates_table $t_where ORDER BY date_submitted DESC";
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
		$t_current_update->date_submitted = mantweet_db_unixtimestamp( $t_row['date_submitted'] );
		$t_current_update->date_updated = mantweet_db_unixtimestamp( $t_row['date_updated'] );

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

	if ( plugin_config_get( 'tweets_source' ) == 'local' ) {
		$t_where = 'WHERE tw_id = 0';
	} else {
		$t_where = 'WHERE tw_id <> 0';
	}

	$t_query = "SELECT count(*) FROM $t_updates_table $t_where";
	$t_result = db_query_bound( $t_query, null );

	return db_result( $t_result );
}

/**
 * Deletes all tweets in the database matching the current tweets source.
 */
function mantweet_purge() {
	$t_updates_table = plugin_table( 'updates' );

	if ( plugin_config_get( 'tweets_source' ) == 'local' ) {
		$t_where = 'WHERE tw_id = 0';
	} else {
		$t_where = 'WHERE tw_id <> 0';
	}

	$t_query = "DELETE FROM $t_updates_table $t_where";
	db_query_bound( $t_query );
}

function mantweet_get_max_twitter_id() {
	$t_updates_table = plugin_table( 'updates' );

	$t_query = "SELECT tw_id FROM $t_updates_table ORDER BY tw_id DESC";
	$t_result = db_query_bound( $t_query, null, 1 );

	if ( db_num_rows( $t_result ) == 0 ) {
		return 0;
	}

	return db_result( $t_result );
}

function mantweet_import_from_twitter() {
	# just for testing.
	#mantweet_purge_twitter_tweets();

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
					$t_created_at = mantweet_db_date( strtotime( $t_tweet->created_at ), /* gmt */ false );

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

/**
 * Convert db compatible timestamp into a unix timestamp.
 *
 * @param $p_date  The date to convert or null to use current time.
 */
function mantweet_db_unixtimestamp( $p_date = null ) {
	global $g_db;

	if ( null !== $p_date ) {
		$p_timestamp = $g_db->UnixTimeStamp( $p_date );
	} else {
		$p_timestamp = time();
	}

	return $p_timestamp ;
}

/**
 * Get current time as db compatible date.
 */
function mantweet_db_now() {
	global $g_db;

	return $g_db->DBTimeStamp(time());
}

/**
 * Convert unix timestamp to a db compatible date.
 *
 * @param $p_timestamp The time stamp to or null for current time.
 */
function mantweet_db_date( $p_timestamp=null ) {
	global $g_db;

	if ( null !== $p_timestamp ) {
		$p_date = $g_db->UserTimeStamp($p_timestamp);
	} else {
		$p_date = $g_db->UserTimeStamp(time());
	}

	return $p_date;
}

/**
 * Used by the upgrade script to purge cached entries.  This is used to recover from
 * a bug where Tweets were cached more than once.
 */
function install_mantweet_purge_cached_entries() {
	$t_updates_table = plugin_table( 'updates' );

	$t_query = "DELETE FROM $t_updates_table WHERE tw_id <> 0";
	db_query_bound( $t_query );

	return true;
}
