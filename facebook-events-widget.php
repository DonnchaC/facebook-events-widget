<?php
/*
Plugin Name: Facebook Events Widget
Plugin URI: http://roidayan.com
Description: !! This has been modified, don't update!
Version: 99.1.10
Author: Roi Dayan
Author URI: http://roidayan.com
License: GPLv2

Based on code by Mike Dalisay
  http://www.codeofaninja.com/2011/07/display-facebook-events-to-your-website.html


Copyright (C) 2011, 2012  Roi Dayan  (email : roi.dayan@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* TODO
 * setting if to display more info or not
 * link to all events
 * setting for date format for one day event and event that span multiple days
 * force height for widget container
*/

//error_reporting(E_ALL);

// requiring FB PHP SDK
if (!class_exists('Facebook')) {
    require_once('fb-sdk/src/facebook.php');
}

class Facebook_Events_Widget extends WP_Widget {  
    var $default_settings = array(
        'title' => '',
        'pageId' => '',
        'appId' => '',
        'appSecret' => '',
        'accessToken' => '',
        'maxEvents' => 10,
        'smallPic' => false,
        'futureEvents' => false,
        'timeOffset' => 7,
        'newWindow' => false,
        'calSeparate' => false,
        'useUnixtime' => false,
        'useGraphapi' => false
        );

    function Facebook_Events_Widget() {
        // constructor
        $widget_ops = array(
            'classname' => 'widget_Facebook_Events_Widget',
            'description' => __('Display facebook events.')
            );
        $control_ops = array(
            'width' => '',
            'height' => ''
            );
        
        $this->WP_Widget('facebook_events_widget',
            __('Facebook Events Widget'), $widget_ops, $control_ops);
            
        //$this->admin_url = admin_url('admin.php?page=' . urlencode(plugin_basename(__FILE__)));
        $this->admin_url = admin_url('widgets.php');
        
        add_action('init', array($this, 'add_style'));
    }
    
    function add_style() {
        if (!is_admin()) {
            wp_enqueue_style('facebook-events',
                            plugin_dir_url(__FILE__).'style.css',
                            false, '1.0', 'all');
        }
    }

    function widget($args, $instance) {
        // print the widget
        extract($args, EXTR_SKIP);
        $instance = wp_parse_args(
            (array) $instance,
            $this->default_settings
        );
        extract($instance, EXTR_SKIP);
        $title = apply_filters('widget_title', empty($title) ? 'Facebook Events' : $title);
        $all_events_url = "http://www.facebook.com/pages/{$pageId}/?sk=events";

        echo $before_widget;
        if ($title)
            echo $before_title . $title . $after_title;

        if ($useGraphapi) {
            $fqlResult = $this->query_fb_events($appId, $appSecret, $pageId,
                        $accessToken, $maxEvents, $futureEvents, $useUnixtime);
        } else {
            $fqlResult = $this->query_fb_page_events($appId, $appSecret, $pageId,
                        $accessToken, $maxEvents, $futureEvents, $useUnixtime);
        }
        echo '<div class="fb-events-container">';
        
        # looping through retrieved data
        if (!empty($fqlResult)) {
            $last_sep = '';
            foreach ($fqlResult as $keys => $values) {
                $values['start_time'] = $this->fix_time($values['start_time'], $timeOffset);
                $values['end_time'] = $this->fix_time($values['end_time'], $timeOffset);
                
                if ($useGraphapi) {
                    $values['eid'] = $values['id'];
                    $values['pic'] = $values['picture']['data']['url'];
                } else {
                    if ($smallPic)
                        $values['pic'] = $values['pic_small'];
                }
                if ($calSeparate)
                    $last_sep = $this->cal_event($values, $last_sep);
                
                $this->create_event_div_block($values, $instance);
            }
        } else
            $this->create_noevents_div_block();
        
        echo '</div>';

        echo $after_widget;
    }
    
    function fix_time($tm, $offset) {
        // Facebook old reply is unixtime and new reply is "2012-07-21" or "2012-07-21T12:00:00-0400"
        // on new replys end_time could be empty
        if (!$tm)
            return $tm;
        if (ctype_digit($tm)) {
            $n = $tm;
            if ($offset != 0)
                $n -= $offset * 3600;
        } else {
            $r = new DateTime($tm);
            $n = $r->format('U') + $r->getOffset();
        }
        return $n;
    }

    function get_excerpt($str, $startPos=0, $maxLength=100) {
        if(strlen($str) > $maxLength) {
            $excerpt   = substr($str, $startPos, $maxLength-3);
            $lastSpace = strrpos($excerpt, ' ');
            $excerpt   = substr($excerpt, 0, $lastSpace);
            $excerpt  .= '...';
        } else {
            $excerpt = $str;
        }
        return $excerpt;
    }

    function update($new_instance, $old_instance) {
        // save the widget
        $instance = $old_instance;
        foreach ($this->default_settings as $key => $val)
            $instance[$key] = strip_tags(stripslashes($new_instance[$key]));

        return $instance;
    }

    function form($instance) {
        // widget form in backend
        $instance = wp_parse_args(
            (array) $instance,
            $this->default_settings
        );
        extract($instance, EXTR_SKIP);
        $title = htmlspecialchars($instance['title']);

        $this->create_input('title', $title, 'Title:');
        $this->create_input('pageId', $pageId, 'Facebook Page ID:');
        $this->create_input('appId', $appId, 'Facebook App ID:');
        $this->create_input('appSecret', $appSecret, 'Facebook App secret:');
        
        if (!empty($appId) && !empty($appSecret) && empty($accessToken) &&
            isset($_GET['wid']) && isset($_GET['code']) && $_GET['wid'] == $this->id)
        {
            $accessToken = $this->get_facebook_access_token($appId, $appSecret, $_GET['code']);
        }
        
        $this->create_input('accessToken', $accessToken, 'Access token:');
        echo '*Only needed if calendar is private.<br/><br/>';
        
        if (empty($access_token)) {
            echo '<p><a class="button-secondary" ';
            echo 'href="https://www.facebook.com/dialog/oauth?client_id=';
            echo urlencode($appId);
            echo '&redirect_uri=' . urlencode($this->admin_url.'?wid=' . $this->id);
            echo '&scope=' . urlencode('offline_access,user_events') . '">';
            echo __('Get facebook access token') . '</a></p>';
        }
        
        $this->create_input('maxEvents', $maxEvents, 'Maximum Events:', 'number');
        $this->create_input('smallPic', $smallPic, 'Use Small Picture:', 'checkbox');
        $this->create_input('futureEvents', $futureEvents, 'Show Future Events Only:', 'checkbox');
        $this->create_input('timeOffset', $timeOffset, 'Adjust events times in hours:', 'number');
        $this->create_input('newWindow', $newWindow, 'Open events in new window:', 'checkbox');
        $this->create_input('calSeparate', $calSeparate, 'Show calendar separators:', 'checkbox');
        $this->create_input('useUnixtime', $useUnixtime, 'old timestamps:', 'checkbox');
        $this->create_input('useGraphapi', $useGraphapi, 'Use graph api:', 'checkbox');
        
        echo '*To edit the style you need to edit the style.css file.<br/><br/>';
    }
    
    function get_facebook_access_token($appId, $appSecret, $code) {
        $request = new WP_Http;
        $api_url = 'https://graph.facebook.com/oauth/access_token?client_id='
                . urlencode($appId).'&redirect_uri='
                . urlencode($this->admin_url . '?wid=' . $this->id)
                . '&client_secret=' . urlencode($appSecret)
                . '&code=' . urlencode($code);
        $response = $request->get($api_url);
        if (isset($response->errors))
            return false;
        $json_response = json_decode($response['body']);
        if (is_object($json_response) &&
            property_exists($json_response,'error'))
        {
            echo '<p style="color: red;">Error getting access token.</p>';
            return false;
        }
        $token = explode('=', $response['body'], 2);
        if ($token[0] != 'access_token') {
            echo '<p style="color: red;">Error with access token.</p>';
            return false;
        }
        return $token[1];
    }
    
    function create_input($key, $value, $title, $type='text') {
        $name = $this->get_field_name($key);
        $id = $this->get_field_id($key);
        echo '<p><label for="' . $id . '">' . __($title);
        echo '&nbsp;<input id="' . $id . '" name="' . $name . '" type="' . $type . '"';
        $width = 80;
        if ($type == 'number')
            $width = 35;
        if ($type == 'checkbox') {
            checked( (bool) $value, true);
            $width = 0;
        } else
            echo ' value="' . $value . '"';
        if ($width > 0)
            echo ' style="width: '.$width.'px;"';
        echo ' /></label></p>';
    }
    
    function query_fb_events($appId, $appSecret, $groupId, $accessToken,
            $maxEvents, $futureOnly=false, $use_unixtime=false)
    {
        //initializing keys
        $facebook = new Facebook(array(
            'appId'  => $appId,
            'secret' => $appSecret,
            'cookie' => true // enable optional cookie support
        ));
   
	$p = array(
            "fields" => "id,name,picture,start_time,end_time,location,invited.summary(1),cover,description"
        );

	// Show events that are in the future or less than 6 hours old.
	if($futureOnly){
		$since = time() - 60*60*6;
		$p['since'] = $since; 
	}
        
        if (!empty($accessToken))
            $p["access_token"] = $accessToken;
        
        $url = "/{$groupId}/events";

        try {
            $fqlResult = $facebook->api($url, 'GET', $p);
            $fqlResult = $fqlResult['data'];
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        return $fqlResult;
    }
    
    function query_fb_page_events($appId, $appSecret, $pageId, $accessToken, $maxEvents, $futureOnly=false, $use_unixtime=false) {
        //initializing keys
        $facebook = new Facebook(array(
            'appId'  => $appId,
            'secret' => $appSecret,
            'cookie' => true // enable optional cookie support
        ));

        //query the events
        
        if ($use_unixtime)
            $future = $futureOnly ? ' AND start_time > now() ' : '';
        else
            $future = $futureOnly ? ' AND start_time > "' . date("Y-m-d") . '" ' : '';
        
        $maxEvents = intval($maxEvents) <= 0 ? 1 : intval($maxEvents);
        $fql = "SELECT eid, name, pic, pic_small, start_time, end_time, location, description, attending_count 
            FROM event WHERE eid IN 
            (   SELECT eid FROM event_member 
                WHERE uid = '{$pageId}' {$future} ORDER BY start_time ASC
                LIMIT {$maxEvents}
            )
            ORDER BY start_time ASC ";
        
        $param = array (
            'method' => 'fql.query',
            'query' => $fql,
            'callback' => ''
        );
        
        if (!empty($accessToken))
            $param['access_token'] = $accessToken;

        $fqlResult = '';

        try {
            $fqlResult = $facebook->api($param);
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        return $fqlResult;
    }

    function cal_event($values, $last_sep = '') {
        $today = false;
        $tomorrow = false;
        $this_week = false;
        $this_month = false;

        if (date('Ymd') == date('Ymd', $values['start_time']))
            $today = true;
            
        if (date('Ymd') == date('Ymd', $values['start_time'] - 86400))
            $tomorrow = true;

        if (date('Ym') == date('Ym', $values['start_time'])) {
            $this_month = true;
            
            if (( date('j', $values['start_time']) - date('j') ) < 7) {
                if (date('w', $values['start_time']) >= date('w') ||
                    date('w', $values['start_time']) == 0)
                {
                    // comparing to 0 because seems its 0-6 where sunday is 1 and saturday is 0
                    // docs says otherwise.. need to check,
                    $this_week = true;
                }
            }
        }
        
        $month = date('F', $values['start_time']);
        
        if ($today) {
            $t = 'Today';
            $r = 'today';
        } else if ($tomorrow) {
            $t = 'Tomorrow';
            $r = 'tomorrow';
        } else if ($this_week) {
            $t = 'This Week';
            $r = 'thisweek';
        } else if ($this_month) {
            $t = 'This Month';
            $r = 'thismonth';
        } else {
            $t = $month;
            $r = $month;
        }
        
        if ($r != $last_sep) {
            echo '<div class="fb-event-cal-head">';
            echo $t;
            echo '</div>';
        }
        
        return $r;
    }
    
    function create_event_div_block($values, $instance) {
        extract($instance, EXTR_SKIP);
        $start_date = date_i18n(get_option('date_format'), $values['start_time']);
        if (date("His", $values['start_time']) != "000000")
            $start_time = date_i18n(get_option('time_format'), $values['start_time']);
        else
            $start_time = "";
        
        if (!empty($values['end_time'])) {
            $end_date = date_i18n(get_option('date_format'), $values['end_time']);
            if (date("His", $values['end_time']) != "000000")
                $end_time = date_i18n(get_option('time_format'), $values['end_time']);
            else
                $end_time = "";
        } else {
            $end_date = "";
            $end_time = "";
        }
        
        if ($start_date == $end_date)
            $end_date = "";
        
        $on = "$start_date";
        if (!empty($start_time))
            $on .= " &#183; $start_time";
        if (($start_date != $end_date) && !empty($end_date))
            $on .= " -<br>$end_date";
        if (!empty($end_time))
            $on .= " &#183; $end_time";
        
        $event_url = 'http://www.facebook.com/event.php?eid=' . $values['eid'];

        if(!empty($values['cover'])){
            $cover_photo = $values['cover'];
        }

        //printing the data
        echo "<div class='fb-event'>";
        echo '<a class="fb-event-anchor" href="' . $event_url . '"';
        if ($newWindow)
            echo ' target="_blank"';
        echo '>';
        echo "<div class='fb-event-info'>";
        echo "<h4 class='fb-event-title'>{$values['name']}</h4>";
        echo "<time datetime='".date('c',$values['start_time'])."' class='fb-event-time'>{$on}</time>";
        if (!empty($values['description']))
            echo "<p class='fb-event-description'>" . $this->get_excerpt(nl2br($values['description'])) . "</p>";
        //echo "<div style='clear: both'></div>"
        echo "<div class='fb-event-meta'>";
        if (!empty($values['location']))
            echo "<span class='fb-event-location'>" . $values['location'] . "</span>";
        if (!empty($values['invited']['summary']['attending_count']))
            if (!empty($values['location']))
                echo ' - ';
            echo "<span class='fb-event-attending'>". $values['invited']['summary']['attending_count'] . ' attending</span>';
        echo "</div></div>";
        if (!empty($cover_photo)){
            echo "<img  src='".$cover_photo['source']."' />";
            // $cover_photo['offset_y']
	    echo '<div class="fade-event-overlay"></div>';
        }
        echo "</a>";
        echo "</div>";
    }
    
    function create_noevents_div_block() {
        echo "<div class='fb-event'>";
        echo "<div class='fb-event-description'>There are no events</div>";
        echo "<style>.widget_Facebook_Events_Widget { display: none; }</style>";
        echo "</div>";
    }
}

// register the widget
add_action('widgets_init',
            create_function('', "return register_widget('Facebook_Events_Widget');"));
