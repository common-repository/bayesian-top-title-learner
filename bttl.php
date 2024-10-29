<?php
/*Plugin Name: BTTL
Plugin URI: http://www.its.caltech.edu/~gregv/projects.html
Description: Display links to your most 'interesting' posts, where interestingness is determined automatically by a Bayesian learning algorithm. 
Author: Greg Ver Steeg
Version: 0.24
Author URI: http://www.its.caltech.edu/~gregv/
*/
/* Copyright 2008 Greg Ver Steeg(email : gversteeg@gmail.com) This program is free software;
you can redistribute it and/or modify it under the terms of the GNU General
Public License as published by the Free Software Foundation;
either version 2 of the License, or(at your option) any later version. This program is distributed in the hope that it will be useful,  but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
Public License for more details. You should have received a copy of the GNU General Public License along with this program;
if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

$interest = 'interest';//meta data tags for wp
$scen = 'scenario';
$processdelayinsec=60;//TODO ADD option
$processnow = FALSE;

function bttl_widget()
{
	// Check for the required plugin functions. 
	if (!function_exists('register_sidebar_widget') ){return;}
	
	//Display defaultnum posts picked randomly, weighted based on interest.
	//Records which ones were displayed in the SQL database
	//Checks if we need to process our data based on time interval or user request
	function bttl_display($args)
	{
		global $processdelayinsec, $processnow;
		global $interest, $scen;
		global $wpdb;
		extract($args);
		$wpdb->bttl_data = $wpdb->prefix.'bttl_data';
		$options=get_option('bttl_control');
		$defaultnum = (isset($options['count'])) ? $options['count'] : 4;
		$title = ($options['title']) ? $options['title'] : "Featured" ;
		$testmode = ($options['showscore']) ? TRUE : FALSE;
		$items = pickrandomweighted($defaultnum);
		$timestamp = rand(1000000,2000000);
		$plugindirarr = explode('wp-content',dirname(__FILE__));
		$plugindir = (count($plugindirarr)==2) ? '/wp-content'.$plugindirarr[1] : '/wp-content/plugins';
		//microtime(get_as_float);
		//microtime seemed like a good unique identifier, but its implementation
		//is not standard on all systems
		//rand may collide, but not often and the effect on stats would be negligible

		echo $before_widget;
		echo "$before_title $title $after_title <ul>";
		foreach ($items as $item)
		{
			$ab=get_post_meta($item->ID,$interest,TRUE);
			$score =  ($testmode) ? round(expect($ab),2)." <a href='http://www.srcf.ucam.org/~sea31/what_multi.cgi?plot=plot+%5B0%3A1%5D+x**$ab[0]+*%281-x%29**$ab[1]&amp;button=plot'>plot</a>"  : ""; 
			echo '<li><a href="' . get_bloginfo('wpurl') . $plugindir.'/bttl.php?guid='.rawurlencode(get_permalink($item->ID)).'&amp;items='.$item->ID.'&amp;stamp='.$timestamp.'">'.$item->post_title." $score".'</a>'.'</li>';
		}
		if ($testmode) echo "<li>".round(expect($ab=get_option($scen)),2)." <a href='http://www.srcf.ucam.org/~sea31/what_multi.cgi?plot=plot+%5B0%3A1%5D+x**$ab[0]+*%281-x%29**$ab[1]&amp;button=plot'>plot</a> </li>";
		echo '</ul> '.$after_widget;
		$table_name=$wpdb->bttl_data;
		
		if ($wpdb->get_var("show tables like '$table_name'") != $table_name)
		{
			 $sql = "CREATE TABLE " . $table_name . " (
			 	timestamp char(20), 
				items BIGINT(20), 
				clicked int(8) NOT NULL DEFAULT 0, 
				key timestamp(timestamp)
				);
			";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			 dbDelta($sql);
		 }

		//Commented statement is safer, but unnecessary because users can't affect these values. The subsequent statement is more compatible
	        //foreach ($items as $item) $wpdb->insert($table_name,array('timestamp'=>$timestamp,'items'=>$item->ID));
	        foreach ($items as $item) {$id = $item->ID;$wpdb->query("INSERT INTO $table_name (timestamp, items) VALUES ('$timestamp','$id')");}
		$oldest = $wpdb->get_var('SELECT timestamp FROM '.$table_name.' ORDER BY timestamp ASC LIMIT 1');
		$newest = $wpdb->get_var('SELECT timestamp FROM '.$table_name.' ORDER BY timestamp DESC LIMIT 1');
		
		$oldtime=get_option('lastupdatetime');
		$newtime=time();
		if (($newtime - $oldtime > $processdelayinsec) or($processnow==true))
		{
			updateinterest();
			update_option('lastupdatetime',$newtime);
		}
		
	}
	
	//Make sure all the posts have a prior interest setting
	function checkforblanks()
	{
		$defaultprior = array(0, 0);//initializes or resets for interest/scenario tags
	        $defaultscen = array(0, 3);
		global $interest;
		global $scen;
		$all_posts = get_posts('numberposts=-1');
		//RESET
		$options=get_option('bttl_control');
		$resetparams = ($options['reset']==1)? TRUE: FALSE;
		
		if ($resetparams)
		{
			foreach ($all_posts as $post)
			{
				update_option($scen, $defaultscen);
				delete_post_meta($post->ID, $interest);
			}
			//DROP TABLE ADD
			$options['reset']= 0;
			update_option('bttl_control',$options);
		}
		
		foreach($all_posts as $post) {
			if (get_post_meta($post->ID, $interest, TRUE)==FALSE)
				add_post_meta($post->ID, $interest, $defaultprior);
		}
	}
	
	//Pick $number posts at random weighted based on interest
	function pickrandomweighted($number)
	{
		global $interest;
		global $wpdb;
		checkforblanks();
		$numposts = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'publish'");
		$number = floor($number);
		
		if ($number < 0)
			$number = 0;
		if ($number > $numposts)
			$number = $numposts;
		
		$all_posts = get_posts('numberposts=-1');
		$maxinterest = 0;
		foreach ($all_posts as $post)
			$maxinterest+=expect(get_post_meta($post->ID, $interest, TRUE));
		
		for ($i=0; $i < $number ; $i++)
		{
			$pick=rand(0, 10000*$maxinterest)/10000;
			$temp=0;
			foreach ($all_posts as $key=>$post)
			{
				$pickpostkey = $key;
				$temp+=expect(get_post_meta($post->ID, $interest, true));
				if ($temp > $pick)
					break;
			}
			$maxinterest -= expect(get_post_meta($all_posts[$pickpostkey]->ID, $interest, TRUE));
			$pickpost[$i]=$all_posts[$pickpostkey];
			unset($all_posts[$pickpostkey]);
		}
		
		return $pickpost;
	}
	
	
	function expect($beta)
	{
		//Another nice property of Beta distributions: easy expectation values
		return((1+$beta[0])/(2+$beta[0]+$beta[1]));
	}
	
	//Input an array of weights, output the key of a choice made randomly based on the weights
	function randwgt($weightarray)
	{
		$pick = rand(0, 10000*array_sum($weightarray))/10000;
		$temp=0;
		
		foreach ($weightarray as $key=>$i)
		{
			$item=$key;
			$temp+=$i;
			
			if ($temp>$pick)
				break;
		}
		return $item;
	}
	
	//Process raw data into a form that can be used in our updating algorithm
	function processrawdata()
	{
		global $interest;
		global $wpdb;
		$options=get_option('bttl_control');
		$defaultnum = ($options['count']) ? $options['count'] : 4;
		$all_posts=get_posts('numberposts=-1');
		
	       $table_name=$wpdb->prefix.'bttl_data';
	       $timestamp = $wpdb->get_col("select timestamp,items,clicked from $table_name order by timestamp desc limit $defaultnum");
	       $items = $wpdb->get_col("", 1);
	       $clicked = $wpdb->get_col("", 2);
	       $result = array('posts'=>$items, 'clicked'=>$clicked);
	       
	       if ($items)
	       {
		    $wpdb->query("delete from $table_name where timestamp = '$timestamp[0]'");
	       }
	       
	       return $result;
	}
	
	//Update interest based on recent data
	function updateinterest()
	{
		global $interest;
		global $scen;

		$datum = processrawdata();
		while($datum['posts']){
			//update interest
			$somethingclicked = array_sum($datum['clicked'])>0 ? 1 : 0;

			$probnoneinteresting = 1;
			foreach ($datum['posts'] as $postid)
			{
				$probnoneinteresting *= (1-expect(get_post_meta($postid, $interest, true)));
			}
			$j=0;
			foreach($datum['posts'] as $postid)
			{
				$probthisnotinteresting = (1-expect(get_post_meta($postid, $interest, true)));
				$nothingterm = 1/(1+((1/expect(get_option($scen))-1)/($probnoneinteresting/$probthisnotinteresting)));
				$oldinterest=get_post_meta($postid, $interest, true);
				$adda = ($datum['clicked'][$j]);
				$addb = (1-$datum['clicked'][$j])*($somethingclicked+(1-$somethingclicked)*$nothingterm);
				update_post_meta($postid, $interest, array($oldinterest[0]+$adda, $oldinterest[1]+$addb));
				$j++;
			}
			
			//update scenario
			$oldscen = get_option($scen);
			update_option($scen, array($oldscen[0]+$somethingclicked, $oldscen[1]+(1-$somethingclicked)*$probnoneinteresting));
			$datum = processrawdata();
		}
		
	}
	
	
	function bttl_control()
	{
		$options = get_option('bttl_control');
		$newoptions = $options;
		
		if (!is_array($options) )
		{
			 add_option('bttl_control', array('title'=>'Featured', 'reset'=>'0', 'count'=>'4', 'showscore'=>'0'));
			 $options = get_option('bttl_control');
			 $newoptions = $options;
		}
		
		
		if ($_POST['bttl-submit'] )
		{
			 $newoptions['title'] = strip_tags(stripslashes($_POST['bttl-title']));
			 $newoptions['reset'] = (int) $_POST['bttl-reset'];
			 $newoptions['count'] = (int) $_POST['bttl-count'];
			 $newoptions['showscore'] = (int) $_POST['bttl-showscore'];
		}
		
		
		if ($options != $newoptions )
		{
			 $options = $newoptions;
			 update_option('bttl_control', $options);
		}
		
		
		?><div style="text-align:right"> <label for="bttl-title" style="line-height:35px;display:block;"><?php
		_e('Widget title:', 'widgets');
		?><input type="text" id="bttl-title" name="bttl-title" value="<?php
		echo wp_specialchars($options['title'], true);
		?>" /></label> <label for="bttl-count" style="line-height:35px;display:block;"><?php
		_e('Number of links:', 'widgets');
		?><input type="text" id="bttl-count" name="bttl-count" value="<?php
		echo $options['count'];
		?>" /></label> <input type="hidden" name="bttl-submit" id="bttl-submit" value="1" /> <label for="bttl-reset" style="line-height:35px;display:block;"><?php
		_e('Reset, 0 or 1:', 'widgets');
		?><input type="text" id="bttl-reset" name="bttl-reset" value="<?php
		echo $options['reset'];
		?>" /></label> <label for="bttl-showscore" style="line-height:35px;display:block;"><?php
		_e('Show scores, 0 or 1:', 'widgets');
		?><input type="text" id="bttl-showscore" name="bttl-showscore" value="<?php
		echo $options['showscore'];
		?>" /></label> <input type="hidden" name="bttl-submit" id="bttl-submit" value="1" /> </div><?php
	}
	
	
	function init_bttl()
	{
		register_sidebar_widget(array('Bayesian Top Title Learner', 'widgets'), 'bttl_display');
	}
	
	// This registers our widget so it appears with the other available
	register_sidebar_widget(array('Bayesian Top Title Learner', 'widgets'), 'bttl_display');
	register_widget_control(array('Bayesian Top Title Learner', 'widgets'), 'bttl_control', 300, 100);
}




//First check how we got here, either record a link, or put up your hooks

if (isset($_GET['stamp']))
{
	//RECORD LINKS
	 $timestamp = $_GET['stamp'];
	 $items = $_GET['items'];
	 $clicked = $_GET['clicked'];


	 $dir_tries = 0;
	 $dir = dirname( __FILE__ );
	 while ( !file_exists( "$dir/wp-load.php" ) && $dir_tries < 5 ) {
        	$dir = dirname( $dir );
              	$dir_tries++;
	 }
         require_once( "$dir/wp-load.php" );
	 $table_name=$wpdb->prefix.'bttl_data';
	 $bob=$wpdb->update($table_name, array('clicked'=>1), array('timestamp'=>$timestamp, 'items'=>$items) ) ;
	 header('Location: '.rawurldecode($_GET['guid']));
}

else{
	add_action('widgets_init', 'bttl_widget');
}

//TO DO
//Avoid function collisions?
//Make links not rely on permalink structure
//Fix require_once to be flexible
//Options page w/ reset, $defaultnum, show score, scen
//Improve updating of beta parameters

?>
