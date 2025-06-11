<?php
/*
Plugin Name: kPicasa Gallery
Plugin URI: http://www.boloxe.com/techblog/
Description: Display your Picasa Web Galleries in a post or in a page.
Version: 0.2.9
Author: Guillaume Hébert

Version History
---------------------------------------------------------------------------
Please refer to the website.

Todo
---------------------------------------------------------------------------
- Multiple private albums, can it be done without too much trouble?

Licence
---------------------------------------------------------------------------
    Copyright 2007, 2008, 2009  Guillaume Hébert (email : kag@boloxe.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( version_compare(PHP_VERSION, '5.0.0', '<') )
{
	print 'kPicasa Gallery requires PHP version 5 or greater. You are running PHP version '.PHP_VERSION;
	exit;
}
elseif ( !function_exists('add_action') || !function_exists('add_action')
  || !function_exists('add_filter') || !function_exists('wp_enqueue_script')
  || !function_exists('wp_enqueue_script') || !function_exists('wp_remote_get')
  || !function_exists('plugins_url') )
{
	print '<strong>'.__('Error', 'kpicasa_gallery').':</strong> '.__('Your WordPress installation is missing some required functions. Please upgrade your WordPress installation.', 'kpicasa_gallery');
	exit;
}
else
{
	// this way, the plugin can only be activated if all the requirements are met
	register_activation_hook( __FILE__, 'kpicasa_gallery_activate' );
}

if ( defined('KPICASA_GALLERY_FILTER_PRIORITY') )
{
	print '<strong>'.__('Error', 'kpicasa_gallery').':</strong> '.__('WordPress is trying to include kPicasa Gallery more than once. Please check that another plugin is not doing this.', 'kpicasa_gallery');
	exit;
}

define('KPICASA_GALLERY_FILTER_PRIORITY', 9);
define('KPICASA_GALLERY_VERSION', '0.2.4');

global $kpg_config;
$kpg_config = get_option( 'kpicasa_gallery_config' );

if ( !is_admin() )
{
	add_action('wp_head', 'kpicasa_gallery_init');
	add_filter('the_content', 'kpicasa_gallery_load', KPICASA_GALLERY_FILTER_PRIORITY);

	wp_enqueue_style('kpicasa', plugins_url('kpicasa_gallery.css', __FILE__), false, KPICASA_GALLERY_VERSION, 'screen');

	if ( $kpg_config['picEngine'] == 'lightbox' )
	{
		$lightbox_version = '2.04';
		wp_enqueue_script('lightbox2', plugins_url('lightbox2/js/lightbox.js', __FILE__), array('prototype', 'scriptaculous-effects', 'scriptaculous-builder'), $lightbox_version);
		wp_enqueue_style('lightbox2', plugins_url('lightbox2/css/lightbox.css', __FILE__), false, $lightbox_version, 'screen');
	}
	elseif ( $kpg_config['picEngine'] == 'slimbox2' )
	{
		$slimbox2_version = '2.02';
		wp_enqueue_script('slimbox2', plugins_url('slimbox2/js/slimbox2.js', __FILE__), array('jquery'), $slimbox2_version);
		wp_enqueue_style('slimbox2', plugins_url('slimbox2/css/slimbox2.css', __FILE__), false, $slimbox2_version, 'screen');
	}
	elseif ( $kpg_config['picEngine'] == 'thickbox' )
	{
		wp_enqueue_script('thickbox');
		wp_enqueue_style('thickbox', includes_url('js/thickbox/thickbox.css', __FILE__), false, false, 'screen');
	}
	elseif ( $kpg_config['picEngine'] == 'shadowbox' )
	{
		$shadowbox_version = '3.0rc1';
		wp_enqueue_script('shadowbox', plugins_url('shadowbox/shadowbox.js', __FILE__), array('jquery', 'swfobject'), $shadowbox_version);
		wp_enqueue_style('shadowbox', plugins_url('shadowbox/shadowbox.css', __FILE__), false, $shadowbox_version, 'screen');
	}
	elseif ( $kpg_config['picEngine'] == 'fancybox' )
	{
		$fancybox_version = '1.2.6';
		// wp_enqueue_script('fancybox', plugins_url('fancybox/jquery.fancybox.js', __FILE__), array('jquery', 'swfobject'), $fancybox_version);
		// wp_enqueue_style('fancybox', plugins_url('fancybox/jquery.fancybox.css', __FILE__), false, $fancybox_version, 'screen');
	}
}
else
{
	add_action( 'admin_menu', 'kpicasa_gallery_admin_menu' );
	add_action( 'admin_init', 'kpicasa_register_settings' );
}

function kpicasa_gallery_init()
{
	global $kpg_config;

	if ( $kpg_config['picEngine'] == 'lightbox' )
	{
		$picEngineDir = plugins_url('lightbox2', __FILE__);

		print "<script type='text/javascript'>\n";
		print "	LightboxOptions.fileLoadingImage        = '$picEngineDir/images/loading.gif';\n";
		print "	LightboxOptions.fileBottomNavCloseImage = '$picEngineDir/images/closelabel.gif';\n";
		print "</script>\n";
	}
	elseif ( $kpg_config['picEngine'] == 'shadowbox' )
	{
		$picEngineDir = plugins_url('shadowbox', __FILE__);

		print "<script type='text/javascript'>\n";
		print "	Shadowbox.init();\n";
		print "</script>\n";
	}
	elseif ( $kpg_config['picEngine'] == 'fancybox' )
	{
		$picEngineDir = plugins_url('fancybox', __FILE__);

		// print "<script type='text/javascript'>\n";
		// print "	jQuery(document).ready(function() {\n";
		// print "		jQuery('a.fancybox-kpicasa_gallery').fancybox({ 'hideOnContentClick': false });\n";
		// print "	});\n";
		// print "</script>\n";
	}
}

function kpicasa_gallery_load ( $content = '' )
{
	$tmp = strip_tags(trim($content));
	//$regex = '/^KPICASA_GALLERY[\s]*(\(.*\))?$/';
	$regex = '/^[\s]*KPICASA_GALLERY[\s]*(\(.*\))?[\s]*$/m';

	if ( preg_match($regex, $tmp, $matches) )
	{
		$showOnlyAlbums = array();
		$username       = null;

		if ( isset($matches[1]) )
		{
			$args = explode(',', substr( substr($matches[1], 0, strlen($matches[1])-1), 1 ));
			if ( count($args) > 0 )
			{
				foreach( $args as $value )
				{
					$value = str_replace(' ', '', $value);
					if ($username == null && 'username:' == substr($value, 0, 9) && strlen($value) > 9)
					{
						$username = substr($value, 9);
					}
					else
					{
						$showOnlyAlbums[] = $value;
					}
				}
			}
		}

		require_once( dirname(__FILE__).'/kpg.class.php' );

		ob_start();
		$gallery = new KPicasaGallery($username, $showOnlyAlbums);
		$buffer  = ob_get_clean();
		return str_replace($matches[0], $buffer, $content);
	}

	return $content;
}

function kpicasa_gallery_admin_menu()
{
	if ( !function_exists('add_submenu_page') )
	{
		print '<strong>'.__('Error', 'kpicasa_gallery').':</strong> '.__('Your WordPress installation is missing some required functions. Please upgrade your WordPress installation.', 'kpicasa_gallery');
		exit;
	}

	add_submenu_page('plugins.php', __('kPicasa Gallery Configuration', 'kpicasa_gallery'), __('kPicasa Gallery', 'kpicasa_gallery'), 'manage_options', dirname(__FILE__).'/param.php');
}

function kpicasa_register_settings()
{
	register_setting( 'kpicasa_gallery_config', 'kpicasa_gallery_config', 'kpicasa_gallery_config_sanitize' );
}

function kpicasa_gallery_config_sanitize($input)
{
	// no validation on $input['username'] or $input['dateFormat']

	$input['albumPerPage']   = absint( $input['albumPerPage'] );
	$input['albumPerRow']    = absint( $input['albumPerRow'] );
	$input['albumThumbSize'] = absint( $input['albumThumbSize'] );
	$input['albumSummary']   = $input['albumSummary'] == 1 ? 1 : 0;
	$input['albumLocation']  = $input['albumLocation'] == 1 ? 1 : 0;
	$input['albumPublished'] = $input['albumPublished'] == 1 ? 1 : 0;
	$input['albumNbPhoto']   = $input['albumNbPhoto'] == 1 ? 1 : 0;
	$input['photoPerPage']   = absint( $input['photoPerPage'] );
	$input['photoPerRow']    = absint( $input['photoPerRow'] );
	$input['photoThumbSize'] = absint( $input['photoThumbSize'] );
	$input['photoSize']      = absint( $input['photoSize'] );
	$input['albumSlideshow'] = $input['albumSlideshow'] == 1 ? 1 : 0;
	$input['showGooglePlus'] = $input['showGooglePlus'] == 1 ? 1 : 0;

	if ( !in_array($input['picEngine'], array('lightbox', 'slimbox2', 'thickbox', 'shadowbox', 'fancybox', '')) )
	{
		$input['picEngine'] = 'slimbox2';
	}

	if ( $input['albumThumbSize'] > 1600 )
	{
		$input['albumThumbSize'] = 1600;
	}
	elseif ( $input['albumThumbSize'] == 0 )
	{
		$input['albumThumbSize'] = 160;
	}

	if ( $input['photoThumbSize'] > 1000 )
	{
		$input['photoThumbSize'] = 1000;
	}
	elseif ( $input['photoThumbSize'] == 0 )
	{
		$input['photoThumbSize'] = 144;
	}

	if ( $input['photoSize'] > 1000 )
	{
		$input['photoSize'] = 1000;
	}
	elseif ( $input['photoSize'] == 0 )
	{
		$input['photoSize'] = 800;
	}

	if ( $input['albumPerRow'] == 0 )
	{
		$input['albumPerRow'] = 1;
	}

	if ( $input['photoPerRow'] == 0 )
	{
		$input['photoPerRow'] = 2;
	}

	return $input;
}

function kpicasa_gallery_activate()
{
	global $kpg_config;

	// Eventually this will become obsolete.
	// But it is needed while users could still be at version 0.2.4 or lower
	// Could be placed in the activation function
	if ( $kpg_config === false )
	{
		$kpg_config = array();

		$kpg_config['username']       = get_option( 'kpg_username' );
		$kpg_config['picEngine']      = get_option( 'kpg_picEngine' );
		$kpg_config['albumPerPage']   = get_option( 'kpg_albumPerPage' );
		$kpg_config['albumPerRow']    = get_option( 'kpg_albumPerRow' );
		$kpg_config['albumThumbSize'] = get_option( 'kpg_albumThumbSize' );
		$kpg_config['albumSummary']   = get_option( 'kpg_albumSummary' );
		$kpg_config['albumLocation']  = get_option( 'kpg_albumLocation' );
		$kpg_config['albumPublished'] = get_option( 'kpg_albumPublished' );
		$kpg_config['albumNbPhoto']   = get_option( 'kpg_albumNbPhoto' );
		$kpg_config['photoPerPage']   = get_option( 'kpg_photoPerPage' );
		$kpg_config['photoPerRow']    = get_option( 'kpg_photoPerRow' );
		$kpg_config['photoThumbSize'] = get_option( 'kpg_photoThumbSize' );

		add_option( 'kpicasa_gallery_config', $kpg_config );

		delete_option( 'kpg_username' );
		delete_option( 'kpg_picEngine' );
		delete_option( 'kpg_albumPerPage' );
		delete_option( 'kpg_albumPerRow' );
		delete_option( 'kpg_albumThumbSize' );
		delete_option( 'kpg_albumSummary' );
		delete_option( 'kpg_albumLocation' );
		delete_option( 'kpg_albumPublished' );
		delete_option( 'kpg_albumNbPhoto' );
		delete_option( 'kpg_photoPerPage' );
		delete_option( 'kpg_photoPerRow' );
		delete_option( 'kpg_photoThumbSize' );
	}
}

?>
