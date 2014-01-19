=== Facebook Events Widget ===
Contributors: roidayan
Donate link: http://roidayan.com
Tags: events, facebook
Requires at least: 3.2.1
Tested up to: 3.3.1
Stable tag: 1.1.10

A widget for displaying facebook events of a fan page.

== Description ==

A widget for displaying facebook events of a fan page.
Based on code by Mike Dalisay
http://www.codeofaninja.com/2011/07/display-facebook-events-to-your-website.html

Upgrade note: 
If you modified style.css remember to save it before doing updates to the plugin.

== Installation ==

1. Extract the folder into your wordpress plugins directory.
2. You'll have a new widget in the widgets page.
3. Create a facebook app to get an app id and app secret.
4. Add a widget to a sidebar you want.
5. Fill in the widget settings.
The app id and app secret are from step 3.


== Frequently Asked Questions ==

= How to modify the style? =

You need to edit the style.css file.

== Screenshots ==

1. example
2. example2

== Changelog ==

= 1.1.10 =
* fixed showing event too far in the future and not next events.

= 1.1.9 =
* fixed missing future events
* added option to use graph api instead of fql.
with the graph api it is possible to access groups events
and not just fan page events.
when using graph api then currently the following options
are not relevent: small picture, future events only,

= 1.1.8 =
* fix event times with daylight saving times.

= 1.1.7 =
* displaying no events message where there are no events.

= 1.1.6 =
* fix not displaying times for events
* added new checkbox for old timestamps. if your using an old app id and you notice events from the past then you should mark this.

= 1.1.5 =
* fix parsing events timestamps

= 1.1.4 =
* fix something with time offsets.
* fix to support new time format in facebook replies.

= 1.1.3 =
* fixed time offsets.

= 1.1.2 =
* fixed missing div element when there are no events.
* fixed not accepting negative time offsets.

= 1.1.1 =
* fixed external css issue.

= 1.1.0 =
* Option for facebook access token to access private calendar.
* External css file.
* Option for date separators like in facebook.
* Option to open events in new window.

= 1.0.1 =
* fixed bug in echo statement.

= 1.0 =
* first