<?php
/*
Plugin Name: Drupal 6 to WordPress migration 
Plugin URI: http://www.oik-plugins.com/oik-plugins/d62wp
Plugin URI: http://wordpress.org/extend/plugins/oik/
Description: Import Drupal 6 content into a WordPress site
Version: 0.1
Author: bobbingwide
Author URI: http://www.bobbingwide.com
Text Domain: d62wp
Domain Path: /languages/
License: GPL2

    Copyright 2013 Bobbing Wide (email : herb@bobbingwide.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License version 2,
    as published by the Free Software Foundation.

    You may NOT assume that you can use any other version of the GPL.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    The license for this software can likely be found here:
    http://www.gnu.org/licenses/gpl-2.0.html

*/

function d62wp_loaded() {
  // Nothing to do here   
}

function d62wp_admin_menu() {
  oik_require( "admin/d62wp.php", "d62wp" );
  d62wp_lazy_admin_menu();
  
}



function d62wp_plugin_loaded() {
  add_action( "oik_loaded", "d62wp_loaded" ); 
  add_action( "oik_admin_menu", "d62wp_admin_menu" ); 
}

d62wp_plugin_loaded();

