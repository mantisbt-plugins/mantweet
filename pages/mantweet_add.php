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

$f_status = gpc_get_string( 'status' );

$t_status_update = new MantweetUpdate();
$t_status_update->author_id = auth_get_current_user_id();
$t_status_update->project_id = helper_get_current_project();
$t_status_update->status = $f_status;

mantweet_add( $t_status_update );

print_successful_redirect( plugin_page( 'index', true ) );
