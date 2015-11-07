**This plugin is not working with latest Twitter API.  Contributions are welcome via pull requests**

## ManTweet - a twitter plugin for MantisBT

Copyright (c) 2008 Victor Boctor, mantisbt.org  
Copyright (c) 2012 Mantis Team - mantisbt-dev@lists.sourceforge.net

Released under the [GNU GPL v3 license](http://opensource.org/licenses/GPL-3.0).

### Description

This plugins provides two modes of operation:

1. Private: A Twitter clone that is private to the users of the bug tracker.  All tweets are stored in MantisBT database.

2. Public: A way to import conversations going on Twitter that are relevant to the company or the project.  For example,
for the Mantis Bug Tracker, this mode is used to monitor keywords like mantisbt.  Users submit their tweets to Twitter
and it gets aggregated into the database via this plugin.  The incremental aggregation is triggered by the page view.

Note that the above two modes are exclusive and can't be combined.

### Requirements

- MantisBT 1.3.x
- CURL PHP extension.
- PHP 5.1.x or above.

### Installation

- Drop the source code under mantisbt/plugins/ManTweet (case sensitive).
- Go the Manage -> Manage Plugins and install the plugin.

### Support

- Report bugs to http://www.mantisbt.org/bugs/
- For questions use forums @ http://www.mantisbt.org/forums/

### Source Code

The latest source code can be found on:

    https://github.com/mantisbt-plugins/mantweet
