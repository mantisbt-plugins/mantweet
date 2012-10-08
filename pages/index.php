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

require_once( config_get( 'plugin_path' ) . 'ManTweet' . DIRECTORY_SEPARATOR . 'mantweet_api.php' ); 

access_ensure_global_level( plugin_config_get( 'view_threshold' ) ); 

$f_page_id = gpc_get_int( 'page_id', 1 );

html_page_top1( lang_get( 'plugin_ManTweet_title' ) );
html_page_top2();

// Only trigger the import if the page being viewed is page 1.
// This must be done before getting the total number of updates below.
if ( $f_page_id == 1 && ( plugin_config_get( 'tweets_source' ) == 'twitter' ) ) {
	mantweet_import_from_twitter();
}

$t_updates_per_page = 10;
$t_total_updates_count = mantweet_get_updates_count();
$t_total_pages_count = (integer)(( $t_total_updates_count + ( $t_updates_per_page - 1 ) ) / $t_updates_per_page);
$t_post_default_text = plugin_config_get( 'post_default_text' );

$t_updates = mantweet_get_page( $f_page_id, $t_updates_per_page );
?>
<br />

<?php 
# TODO: figure out how to reference images in plugins.
#<div align="right">
#	<img src="images/followme.gif" />
#</div>

	if ( mantweet_can_post() ) {
?>
<form name="tweet_form" action="<?php echo plugin_page( 'mantweet_add' ) ?>" method="post">

<table class="width50" align="center" cellspacing="1">

<tr>
	<td class="form-title">
		<?php echo lang_get( 'plugin_ManTweet_post_your_status' ) ?>
	</td>
</tr>

<tr>
	<td><input name="status" size="120" maxlength="250" value="<?php echo string_attribute( $t_post_default_text ); ?>"/></td>
</tr>

<tr>
	<td class="center">
		<input type="submit" value="<?php echo lang_get( 'plugin_ManTweet_post_status' ); ?>" />
	</td>
</tr>

</table>
</form> 
<br />
<?php
	}
?>

<?php
$t_avatar_size = plugin_config_get( 'avatar_size' );

echo '<center>';

if ( plugin_config_get( 'tweets_source' ) == 'twitter' ) {
	$t_message = plugin_lang_get( 'how_to_post_via_twitter' );
	$t_query = plugin_config_get( 'import_query' );
	$t_post_url = 'http://twitter.com/home?status=' . urlencode( $t_post_default_text );
	echo '<p><em>', sprintf( $t_message, $t_post_url, $t_query ), '</em></p>';
}

if ( $f_page_id > 1 ) {
	echo '[ <a href="', plugin_page( 'index' ), '&amp;page_id=', (int)($f_page_id) - 1, '">', lang_get( 'plugin_ManTweet_newer_posts' ), '</a> ]&nbsp;';
} else {
	echo '[ ', lang_get( 'plugin_ManTweet_newer_posts' ), ' ]&nbsp;';
}

if ( $f_page_id < $t_total_pages_count ) {
	echo '[ <a href="', plugin_page( 'index' ), '&amp;page_id=', (int)($f_page_id) + 1, '">', lang_get( 'plugin_ManTweet_older_posts' ), '</a> ]';
} else {
	echo '[ ', lang_get( 'plugin_ManTweet_older_posts' ), ' ]';
}

echo '<br /><br /><table border="0" width="50%">';
foreach ( $t_updates as $t_current_update ) {
echo '<tr><td>';
#if ( ON  == config_get("show_avatar") ) {
if ( $t_current_update->author_id != 0 ) {
	print_avatar( $t_current_update->author_id, $t_avatar_size );
} else {
	echo '<a href="http://twitter.com/', urlencode( $t_current_update->tw_username ), '"><img src="', $t_current_update->tw_avatar ,'" border="0" width="', $t_avatar_size, '" height="', $t_avatar_size, '" /></a>';
}
#}
echo '</td><td>';
$t_date_format = config_get( 'complete_date_format' );

if ( $t_current_update->author_id != 0 ) {
	$t_username = user_get_name( $t_current_update->author_id );
} else {
	$t_username = $t_current_update->tw_username;
}

echo '<b>', string_display( $t_username ), '</b> - ', date( $t_date_format, $t_current_update->date_submitted );
#echo ' - <small>[ <a href="http://twitter.com/', urlencode( $t_username ), '/statuses/', $t_current_update->tw_id, '">view</a> ]</small>';
echo '<br />';
echo string_display_links( $t_current_update->status );
echo '</td></tr>';
}
echo '</table>';

echo '</center>';

html_page_bottom1( __FILE__ );
?>

<?php if ( mantweet_can_post() ) { ?>
<!-- Autofocus JS -->
<?php if ( ON == config_get( 'use_javascript' ) ) { ?>
<script type="text/javascript" language="JavaScript">
<!--
	window.document.tweet_form.status.focus();
// -->
</script>
<?php } } ?>
