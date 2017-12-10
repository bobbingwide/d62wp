<?php // (C) Copyright Bobbing Wide 2013-2017

function d62wp_lazy_admin_menu() {
  add_submenu_page( 'oik_menu', __( 'Drupal 6 to WordPress', 'd62wp') , __("Drupal 6 to WordPress", 'oik'), 'manage_options', 'd62wp', "d62wp_do_page" );
}

/**
 * Drupal 6 to WordPress (3.6.1) migration
 * 
 * 1. Migrate Categories and Tag definitions from vocabulary
 * 2. Migrate post_types from node_type
 * 3. Migrate taxonomies for post_types from vocabulary_node_types
 * 4. Migrate Category and Tags terms from term_node, term_data and term_hierarchy
 * 5. Define fields for any post type $post_type from content_node_field
 * 6. Register fields for object type from content_node_field_instance
 * 7. Migrate different bits of content in the correct order.
 *    dealing with noderef fields will be tricky - how many types
 *    should this be done by hand given that we've only got 341 actual nodes! 
 * 
 */
function d62wp_migrate_steps() {
  sol();
  li( "Migrate Categories and Tag definitions from vocabulary" );
  li( "Migrate post_types from node_type" );
  li( "Migrate taxonomies for post_types from vocabulary_node_types" );
  li( "Migrate Category and Tags terms from term_node, term_data and term_hierarchy" );
  li( "Define fields for any post type \$post_type from content_node_field" );
  li( "Register fields for object type from content_node_field_instance" );
  li( "Migrate different bits of content in the correct order." );
  eol();
  d62wp_migrate_vocabularies();
  d62wp_migrate_types();
  d62wp_import_node();
}  

/**
 * Import a node from the Drupal tables into the WordPress tables 
 */
function d62wp_import_node() {
  $import = bw_array_get( $_REQUEST, "_d62wp_import_vid", null ); 
  if ( $import ) {
    $post_type = bw_array_get( $_REQUEST, "import_as", null );
    $vid = bw_array_get( $_REQUEST, "vid", null );
    if ( $post_type && $vid ) {
      d62wp_perform_import( $vid, $post_type );
    } else {
      p( "Node import: Missing values" );
    }
  }
}

/**
 * Mapping of Drupal status to WordPress status
 * 
  'draft' | 'publish' | 'pending'| 'future' | 'private' | 'custom_registered_status' 
 
 */
function d62wp_status( $status ) {
  $statuses = array( 1 => "publish" 
                   , 0 => "draft" 
                   ); 
  return( $statuses[ $status ] );
}

function d62wp_post_content( $body ) {
  $post_content = $body; 
  $post_content = str_replace( "<!--break-->", "<!--more-->", $post_content );
  return( $post_content );
}  

/**
 * Create a post from the loaded node
 *
 * Assign fields from the loaded result into the $post array
  $node = "SELECT nid,vid,type,language,title,uid,status,created,changed,comment,promote,moderate,sticky,tnid,translate from node WHERE 1 ";
  $revision = "SELECT nid,vid,uid,title,body,teaser,log,timestamp,format from node_revisions where ";
 * 
 * @param object $result - the database object 
 * @param string $post_type - the required post_type
 * @return array $post with the following fields set Y/N 
 
N  'ID'             => [ <post id> ] //Are you updating an existing post?
N  'menu_order'     => [ <order> ] //If new post is a page, it sets the order in which it should appear in the tabs.
N  'comment_status' => [ 'closed' | 'open' ] // 'closed' means no comments.
N  'ping_status'    => [ 'closed' | 'open' ] // 'closed' means pingbacks or trackbacks turned off
N  'pinged'         => [ ? ] //?
N  'post_author'    => [ <user ID> ] //The user ID number of the author.
N  'post_category'  => [ array(<category id>, <...>) ] //post_category no longer exists, try wp_set_post_terms() for setting a post's categories
Y  'post_content'   => [ <the text of the post> ] //The full text of the post.
Y  'post_date'      => [ Y-m-d H:i:s ] //The time post was made.
N  'post_date_gmt'  => [ Y-m-d H:i:s ] //The time post was made, in GMT.
Y  'post_excerpt'   => [ <an excerpt> ] //For all your post excerpt needs.
N  'post_name'      => [ <the name> ] // The name (slug) for your post
N  'post_parent'    => [ <post ID> ] //Sets the parent of the new post.
N  'post_password'  => [ ? ] //password for post?
Y 'post_status'    => [ 'draft' | 'publish' | 'pending'| 'future' | 'private' | 'custom_registered_status' ] //Set the status of the new post.
Y  'post_title'     => [ <the title> ] //The title of your post.
Y  'post_type'      => [ 'post' | 'page' | 'link' | 'nav_menu_item' | 'custom_post_type' ] //You may want to insert a regular post, page, link, a menu item or some custom post type
N  'tags_input'     => [ '<tag>, <tag>, <...>' ] //For tags.
N  'to_ping'        => [ ? ] //?
N  'tax_input'      => [ array( 'taxonomy_name' => array( 'term', 'term2', 'term3' ) ) ] // support for custom taxonomies. 
  
 */ 
function d62wp_create_post( $node, $revision, $post_type ) {
  $post = array();
  $post['post_type'] = $post_type;
  $post['post_status'] = d62wp_status( $node->status );
  $post['post_date'] = bw_format_date( $node->created );
  $post['post_title'] = $node->title;  // $revision->title;
  $post['post_content'] = d62wp_post_content( $revision->body );
  if ( $revision->body != $revision->teaser ) { 
    $post['post_excerpt'] = $revision->teaser;
  }  
  return( $post );
}

/**
 * We can't use wpdb->update since the table is not a WordPress table
 */
function d62wp_update_tnid( $vid, $post_id ) {
  global $wpdb;
  p( "Updating tnid on $vid to $post_id ");
  $request = "UPDATE node set tnid=$post_id where vid=$vid and tnid=0" ;
  $result = $wpdb->query( $request );
  bw_trace2( $result );
}

/**
 * Add the new object to the menu - if required
 */
function d62wp_add_to_menu( $page_id, $title ) {
  $menu = bw_array_get( $_REQUEST, "bw_nav_menu", null );    
  if ( $menu > 0 ) {
     oik_require( "includes/oik-menus.inc" );
     bw_insert_menu_item( $title, $menu, $page_id, 0 );
  }
}

/**
 * Display the menu selector 
 *
 * @TODO Indicate how many menus the item will be added to
 * @TODO Allow multiple selection
 * @TODO Allow menu hierarchy positioning
 * 
 */
function d62wp_menu_selector() {
  oik_require( "includes/bw_metadata.php" );
  $menus = wp_get_nav_menus( $args = array() );
  $terms = bw_term_array( $menus );
  $terms[0] = "none";
  $auto_add = get_option( 'nav_menu_options' );
  $auto_add = bw_array_get( $auto_add, "auto_add", 0 );
  $auto_add = bw_array_get( $auto_add, 0, 0 );
  if ( $auto_add ) {
    bw_tablerow( array("&nbsp;", "Any new page will be added to menu: " . $terms[$auto_add] ) );
  }
  bw_select( "bw_nav_menu", "Add to menu", $auto_add, array( '#options' => $terms) );
  return( $menus );
}
  
/**
 * Perform the import of a "post" from a Drupal node
 * including taxonomy and category
 * and perhaps in the future fields! 
 */
function d62wp_perform_import( $vid, $post_type ) {
  $nodes = d62wp_load_nodes( null, $vid );
  $revisions = d62wp_load_node_revisions( $vid );
  
  if ( $nodes && $revisions ) {
    $node = $nodes[0];
    if ( $node->tnid != 0 ) {
      e( "Warning: post may have been imported before as $tnid" );
    } 
    $post = d62wp_create_post( $nodes[0], $revisions[0], $post_type );
    
  } else { 
    p( "Cannot load node: $vid" );
  } 
   
  d62wp_set_metadata( $vid, $post_type );
  
  $post_id = wp_insert_post( $post, TRUE );
  bw_trace2( $post_id, "post_id" );
  if ( !is_wp_error( $post_id ) ) {
    p( "Post created: $post_id " ); 
    d62wp_update_tnid( $vid, $post_id );
  
    d62wp_import_taxonomies( $vid, $post_id );
    d62wp_add_to_menu( $post_id, $post->post_title );
  } else {
    p( "Problem creating post" );
  }  
} 

/**
 * Set values in the $_POST array for updating the metadata
 * This can be achieved using an action call which is handled by other plugins
 * e.g. uc2wc - Ubercart to WooCommerce might handle fields from "products" other product types
 *
 */
function d62wp_set_metadata( $vid, $post_type ) {
  do_action( "d62wp_set_metadata_${post_type}", $vid ); 
}


/**
 * Return the category ID given the term name and the category name
 */
function d62wp_get_category( $term, $category ) {
 $id = term_exists( $term, $category );
 if ( !$id ) {
   $new_term = wp_insert_term( $term, $category );
   bw_trace2( $new_term, "new_term" );
   if ( !is_wp_error( $new_term ) ) {
     $id = $new_term['term_id'];
     p( "Created new term: $term for category: $category. id: $id ");
   } else {
     //p( "Cannot create term: $term for category: $category" );
   } 
 } else {
   $id = $id['term_id'];
   //Returns an array if the pairing exists. (format: array('term_id'=>term id, 'term_taxonomy_id'=>taxonomy id))     
 }  
 p( "term ID: $id"); 
 return( $id );  
}

/**
 * Add a category term 
 * 
 * Note: This does not position the term in the hierarchy, you have to do that later.
 */
function d62wp_add_category_term( $post_id, $term, $category ) {
  bw_trace2();
  $to_category = d62wp_get_category( $term, $category );
  if ( $to_category ) {
    // $categories = wp_get_post_categories( $post_id ); 
    $categories = wp_get_object_terms( $post_id, $category, array( "fields" => "ids") );
    bw_trace2( $categories, "categories" );
    $categories[] = (int) $to_category;
    bw_trace2( $categories, "categories after", false );
    // wp_set_post_categories( $post_id, $categories );
    wp_set_object_terms( $post_id, $categories, $category );
  }  
}

/**
 *
 *
 */
function d62wp_import_taxonomies( $vid, $post_id ) {
  $results = d62wp_list_term_nodes( $vid );   
  // $categories = wp_get_post_categories( $post_id ); 
  $tags = wp_get_post_tags( $post_id );
  foreach ( $results as $result ) {
    if ( $result->hierarchy ) {
      // $categories[] = $result->name; 
      $category = bw_plugin_namify( $result->taxonomy );
      d62wp_add_category_term( $post_id, $result->name, $category );
    } else {  
      $tags[] = $result->name; 
    }  
  }
  wp_set_post_tags( $post_id, $tags, false );
} 

/**
 * vocabulary
 *  
 * vid	name	description	help	relations	hierarchy	multiple	required	tags	module	weight
 */
function d62wp_migrate_vocabularies() {
  $step = bw_array_get( $_REQUEST, "step", null );
  if ( $step == 1 ) {
    oik_require( "includes/bw_register.inc" );
    p( "Migrating Categories" );
    global $wpdb;
    $request = "SELECT vid,name,description,relations,hierarchy,multiple,required,tags from vocabulary" ;
    $results = $wpdb->get_results( $request );
    $total=0;
    foreach ( $results as $result ) {
      $name = bw_plugin_namify( $result->name );
      if ( $result->hierarchy ) { 
        bw_register_custom_category( $name, $result->name );
        p( "bw_register_custom_category( \"$name\", null, \"$result->name\" );" );
      }  
      if ( $result->tags ) {
        bw_register_custom_tags( $result->name );
        p( "bw_register_custom_tags( \"$name\" );" );
      }  
    }
  }   
}

/**
 *         
 * Create code to register the post_type's from the node_type
 *  
 * We assume they have titles, content, excerpts and featured images
 */
function d62wp_migrate_types() {
  $step = bw_array_get( $_REQUEST, "step", null );
  if ( $step == 2 ) {
    oik_require( "includes/bw_register.inc" );
    p( "Migrating Types" );
    global $wpdb;
    $request = "SELECT type,name,module,description,help,has_title,title_label,has_body,body_label,min_word_count,custom,modified,locked,orig_type from node_type order by 1" ;
    $results = $wpdb->get_results( $request );
    $total=0;
    foreach ( $results as $result ) {
      $post_type = $result->type;
      $post_type_args = array();
      $post_type_args['label'] = $result->name;
      $post_type_args['description'] = $result->description;
      $post_type_args['supports'] = array( 'title', 'editor', 'thumbnail', 'excerpt' );
      //$post_type_args['has_archive'] = true;
      bw_register_post_type( $post_type, $post_type_args );
      
      p( "bw_register_post_type( \"$post_type\", \$post_type_args );" );
    }
  }   
}

/**
 *  Drupal 6 to WordPress migration support page
 * 
 * Displays the boxes to help perform the migration of content from a Drupal database to WordPress
 *  
 */
function d62wp_do_page() {
  oik_menu_header( "Drupal migration" );
  oik_box( null, null, "Steps", "d62wp_migrate_steps" );
  oik_box( null, null, "Node revisions", "d62wp_list_node_revisions" );
  oik_box( null, null, "Term nodes", "d62wp_display_term_nodes" );
  oik_box( null, null, "Nodes", "d62wp_display_nodes" );
  oik_box( NULL, NULL, "Node summary", "d62wp_count_post_types" );
  //oik_box( null, null, "Terms", "d62wp_list_terms" );
  oik_menu_footer();
  bw_flush();
}


/** 
 * Return records from the node table
 * 
 * @param string $post_type - the node type to load
 * @param integer $vid - the version ID of the node to load
 * @return array $results 
 * 
 */
function d62wp_load_nodes( $post_type=null, $vid=null ) {
  global $wpdb;
  $wpdb->show_errors();
  $request = "SELECT nid,vid,type,language,title,uid,status,created,changed,comment,promote,moderate,sticky,tnid,translate from node WHERE 1 ";
  if ( $post_type ) { 
    $request .= " AND type = '$post_type' ";
  }
  if ( $vid ) { 
    $request .= " AND vid = $vid"; 
  } 
  $request .= " order by nid";
  $results = $wpdb->get_results( $request );
  return( $results );
}  

    
/**
 * Drupal nodes
        

CREATE TABLE `node` (
  `nid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vid` int(10) unsigned NOT NULL DEFAULT '0',
  `type` varchar(32) NOT NULL DEFAULT '',
  `language` varchar(12) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `uid` int(11) NOT NULL DEFAULT '0',
  `status` int(11) NOT NULL DEFAULT '1',
  `created` int(11) NOT NULL DEFAULT '0',
  `changed` int(11) NOT NULL DEFAULT '0',
  `comment` int(11) NOT NULL DEFAULT '0',
  `promote` int(11) NOT NULL DEFAULT '0',
  `moderate` int(11) NOT NULL DEFAULT '0',
  `sticky` int(11) NOT NULL DEFAULT '0',
  `tnid` int(10) unsigned NOT NULL DEFAULT '0',
  `translate` int(11) NOT NULL DEFAULT '0',
 */
function d62wp_display_nodes() {
  $post_type = bw_array_get( $_REQUEST, "node_type", null );
  if ( $post_type ) {
    $vid = bw_array_get( $_REQUEST, "vid", null );
    $results = d62wp_load_nodes( $post_type, $vid );
    $total=0;
    stag( "table", "widefat bw_posts" );
    stag( "tr" );
    th( "type" );
    th( "title" );
    th( "created" );
    th( "changed" );
    th( "status" );
    th( "nid" );
    th( "vid" );
    th( "tnid - Import status" );
    etag( "tr" );
    foreach ( $results as $result ) {
      $total += 1; //$count;
      stag( "tr" );
      td( $result->type );
      td( $result->title );
      td( bw_format_date( $result->created ) );
      td( bw_format_date( $result->changed ) );
      td( $result->status );
      td( $result->nid );
      //td( $result->vid );
      $type = $result->type;
      $vid = $result->vid;
      td( retlink( null, admin_url("admin.php?page=d62wp&node_type=$type&vid=$vid" ), $vid ) );
      td( d62wp_tnid_link( $result->tnid ) );
      etag( "tr" );
      $total++;
    }
    etag( "table" );
    e( "Total: $total " );
  }
  return( $result );
}

/** 
 * Reuse tnid as imported node 
 */
function d62wp_tnid_link( $tnid ) {
  if ( $tnid ) {
    $url = get_permalink( $tnid );
    $title = get_the_title( $tnid );
    $link = retlink( null, $url, $title, null, "id-tnid" );
  } else {
    $link = "Not imported";
  }
  return( $link );   
} 

 

/**
 * Produce a table of post types to be migrated
 
This tells us how many fields there are for each type_name

SELECT COUNT( * ) , type_name FROM  `content_node_field_instance`  GROUP BY type_name

 */
function d62wp_count_post_types() {
  global $wpdb;
  $wpdb->show_errors();
  
  $request = "SELECT count(*) 'count' ,type from node group by type";
  $results = $wpdb->get_results( $request );
  $total=0;
  stag( "table", "widefat bw_post_types" );
  stag( "tr" );
  th( "post_type" );
  //th( "Version" );
  th( "Count" );
  etag( "tr" );
  foreach ( $results as $result ) {  
    // print_r( $result );
    $type = $result->type;
    $count = $result->count;
    stag( "tr" );
    td( retlink( null, admin_url("admin.php?page=d62wp&node_type=$type" ), $type) );
    td( $count );
    etag( "tr" );
    $total += $count;
  }
  
  etag( "table" );
  e( $total );

}

/**
 * List node revisions
 */
function d62wp_list_node_revisions( ) {
  $vid = bw_array_get( $_REQUEST, 'vid', null );
  $nid = bw_array_get( $_REQUEST, 'nid', null );
  if ( $vid || $nid ) {
    $results = d62wp_load_node_revisions( $vid, $nid );
    $total=0; 
    foreach ( $results as $result ) {
      p( "Nid: " . $result->nid );
      p( "Vid: " . $result->vid );
      p( "Title: " . $result->title );
      p( "Body: " . $result->body );
      p( "Teaser: " . $result->teaser ); 
      $total++;
      d62wp_import_vid_form( $result->vid );
    }
  }
}

/**
 * Get Drupal node revision
 
nid	int(10)		UNSIGNED	No	0		 Browse distinct values	 Change	 Drop	 Primary	 Unique	 Index	Fulltext
	vid	int(10)		UNSIGNED	No	None	auto_increment	 Browse distinct values	 Change	 Drop	 Primary	 Unique	 Index	Fulltext
	uid	int(11)			No	0		 Browse distinct values	 Change	 Drop	 Primary	 Unique	 Index	Fulltext
	title	varchar(255)	utf8_general_ci		No			 Browse distinct values	 Change	 Drop	 Primary	 Unique	 Index	Fulltext
	body	longtext	utf8_general_ci		No	None		 Browse distinct values	 Change	 Drop	 Primary	 Unique	 Index	Fulltext
	teaser	longtext	utf8_general_ci		No	None		 Browse distinct values	 Change	 Drop	 Primary	 Unique	 Index	Fulltext
	log	longtext	utf8_general_ci		No	None		 Browse distinct values	 Change	 Drop	 Primary	 Unique	 Index	Fulltext
	timestamp	int(11)			No	0		 Browse distinct values	 Change	 Drop	 Primary	 Unique	 Index	Fulltext
	format
 *
 */
function d62wp_load_node_revisions( $vid=null, $nid=null ) {
  global $wpdb;
  $request = "SELECT nid,vid,uid,title,body,teaser,log,timestamp,format from node_revisions where ";
  if ( $vid )
    $request .= " vid = $vid";
  if ( $nid )  
    $request .= " nid = $nid";
  $results = $wpdb->get_results( $request );
  return( $results );
}

function d62wp_import_vid_form( $vid ) {
  bw_form();
  stag( "table", "widefat" );
  e( ihidden( "vid", $vid ) );
  $post_types = get_post_types();
  bw_select( "import_as", "Post type", null, array( "#options" =>  $post_types )) ;
  d62wp_menu_selector();
  etag( "table" );
  p( isubmit( "_d62wp_import_vid", "Import node", null, "button-primary" ) );
  etag( "form" );
}

/**
 *
  REPLACE INTO wordpress.wp_terms
    (term_id, `name`, slug, term_group)
    SELECT DISTINCT
        d.tid, d.name, REPLACE(LOWER(d.name), ' ', '_'), 0
    FROM drupal.term_data d
    INNER JOIN drupal.term_hierarchy h
        USING(tid)
    INNER JOIN drupal.term_node n
        USING(tid)
    WHERE (1
         # This helps eliminate spam tags from import; uncomment if necessary.
         # AND LENGTH(d.name) < 50
    )
; 
*/
function d62wp_list_terms() {
  global $wpdb;
  $request = "SELECT tid, vid, name, description, weight from term_data";
  $results = $wpdb->get_results( $request );
  
  $total=0;
  stag( "table", "widefat bw_post_types" );
  stag( "thead");
  bw_tablerow( bw_as_array( "tid,vid,name,description,weight,parent") );
  etag( "thead");
  
  foreach ( $results as $result ) {  
    stag( "tr" );
    bw_tablerow( array( $result->tid, $result->vid, $result->name, $result->description, $result->weight ));
    etag( "tr" );
    $total += $count;
  }
  
  etag( "table" );
  
  

}

/**
 * List the taxonomy terms associated with the selected node ( chosen by nid or vid )
 * showing the description and parent term
 *
 * Note: term_node.vid = vocabulary ID not version ID
 *
 */ 
function d62wp_list_term_nodes( $vid=null, $nid=null ) {
  global $wpdb;
  $request = "SELECT n.nid, n.vid, n.tid, d.name, d.description, h.parent, v.name `taxonomy`, v.hierarchy from term_node n, term_data d, term_hierarchy h, vocabulary v " ;
  $request .= " WHERE n.tid = d.tid AND n.tid = h.tid AND d.vid = v.vid ";
  if ( $vid ) {
     $request .= " AND n.vid = $vid";
  }
  if ( $nid ) {
     $request .= " AND n.nid = $nid";
  }
  $results = $wpdb->get_results( $request );
  return( $results );
}

function d62wp_display_term_nodes() {
  $vid = bw_array_get( $_REQUEST, 'vid', null );
  $nid = bw_array_get( $_REQUEST, 'nid', null );
  if ( $nid || $vid ) {
    $results = d62wp_list_term_nodes( $vid, $nid );   
    bw_results_table( $results, "nid,vid,tid,name,description,parent,taxonomy,hierarchy" );
  }  
} 


/** 
 * Display the heading for a results table
 */
function bw_results_head( $cols ) {
  stag( "table", "widefat bw_post_types" );
  stag( "thead");
  bw_tablerow( bw_as_array( $cols ) );
  etag( "thead");
}

/**
 * Display the contents for a results table
 */
function bw_results_content( $results, $cols ) {
  $cols = bw_as_array( $cols );
  stag( "tbody" );
  foreach ( $results as $result ) {  
    stag( "tr" );
    foreach ( $cols as $key => $col ) { 
      // bw_tablerow( );
      td(  $result->$col );
    }  
    etag( "tr" );
    
  } 
  etag( "tbody" );
  etag( "table" );
}

/**
 * Display a results table 
 */
function bw_results_table( $results, $cols ) {
  bw_results_head( $cols );
  bw_results_content( $results, $cols );
}


