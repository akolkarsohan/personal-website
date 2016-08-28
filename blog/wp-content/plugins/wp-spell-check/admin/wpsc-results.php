<?php
/* Admin Classes */
/*
	Works in the background: yes
	Pro version scans the entire website: yes
	Sends email reminders: yes
	Finds place holder text: yes
	Custom Dictionary for unusual words: yes
	Scans Password Protected membership Sites: yes
	Unlimited scans on my website: Yes


	Scans Categories: Yes WP Spell Check Pro
	Scans SEO Titles: Yes WP Spell Check Pro
	Scans SEO Descriptions: Yes WP Spell Check Pro
	Scans WordPress Menus: Yes WP Spell Check Pro
	Scans Page Titles: Yes WP Spell Check Pro
	Scans Post Titles: Yes WP Spell Check Pro
	Scans Page slugs: Yes WP Spell Check Pro
	Scans Post Slugs: Yes WP Spell Check Pro
	Scans Post categories: Yes WP Spell Check Pro

	Privacy URI: https://www.wpspellcheck.com/privacy-policy/
	Pro Add-on / Home Page: https://www.wpspellcheck.com/
	Pro Add-on / Prices: https://www.wpspellcheck.com/purchase-options/
*/
class sc_table extends WP_List_Table {

	function __construct() {
		global $status, $page;
		
		//Set Defaults
		parent::__construct( array(
			'singular' => 'word',
			'plural' => 'words',
			'ajax' => true
		) );
	}
	
	function column_default($item, $column_name) {
		return print_r($item,true);
	}
	
	//Set up options for words in the table
	function column_word($item) {
		//Build suggested spellings list
		global $wpdb;
		$table_name = $wpdb->prefix . 'spellcheck_options';
		$dict_table = $wpdb->prefix . "spellcheck_dictionary";
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $table_name . ' WHERE option_name="language_setting";');
		$dict_words = $wpdb->get_results('SELECT word FROM ' . $dict_table . ';');
		
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$word_list = array();
		foreach ($dict_words as $dict_word) {
			array_push($word_list,$dict_word->word);
		}
	
		$contents = str_replace("\r\n", "\n", $contents);
		$main_list = explode("\n", $contents);

		$word_list = array_merge($word_list,$main_list);
	
		$suggestions = array();
		
		foreach ($word_list as $words) {
			//$percentage = 00.00;
			$first_word = stripslashes($item['word']);
			if (gettype($words) == 'string') similar_text(strtoupper($first_word),strtoupper($words),$percentage);
			if ($percentage > 80.00)
				array_push($suggestions,$words);
				
			if (sizeof($suggestions) >= 4) break;
		}
		if (sizeof($suggestions) < 4) {
			foreach ($word_list as $words) {
				//$percentage = 00.00;
				$first_word = stripslashes($item['word']);
				if (gettype($words) == 'string') similar_text(strtoupper($first_word),strtoupper($words),$percentage);
				if ($percentage > 60.00)
					array_push($suggestions,$words);
					
				if (sizeof($suggestions) >= 4) break;
			}
		}
		if (sizeof($suggestions) < 4) {
			foreach ($word_list as $words) {
				//$percentage = 00.00;
				$first_word = stripslashes($item['word']);
				if (gettype($words) == 'string') similar_text(strtoupper($first_word),strtoupper($words),$percentage);
				if ($percentage > 40.00)
					array_push($suggestions,$words);
					
				if (sizeof($suggestions) >= 4) break;
			}
		}

		$sorting = '';
		if ($_GET['orderby'] != '') $sorting .= '&orderby=' . $_GET['orderby'];
		if ($_GET['order'] != '') $sorting .= '&order=' . $_GET['order'];
		if ($_GET['paged'] != '') $sorting .= '&paged=' . $_GET['paged'];

		//build row actions
		if ($item['page_type'] == 'Page Slug' || $item['page_type'] == 'Post Slug' || $item['page_type'] == 'Tag Slug' || $item['page_type'] == 'Category Slug') {
			$actions = array (
				'Ignore'      			=> sprintf('<input type="checkbox" class="wpsc-ignore-checkbox" name="ignore-word[]" value="' . $item['id'] . '" />Ignore'),
				'Add to Dictionary'		=> sprintf('<input type="checkbox" class="wpsc-add-checkbox" name="add-word[]" value="' . $item['id'] . '" />Add to Dictionary')
			);
		} else {
			$actions = array (
				'Ignore'      			=> sprintf('<input type="checkbox" class="wpsc-ignore-checkbox" name="ignore-word[]" value="' . $item['id'] . '" />Ignore'),
				'Suggested Spelling'	=> sprintf('<a href="#" class="wpsc-suggest-button" suggestions="' . $suggestions[0] . '-' . $suggestions[1] . '-' . $suggestions[2] . '-' . $suggestions[3] . '">Suggested Spelling</a>'),
				'Edit'					=> sprintf('<a href="#" class="wpsc-edit-button" page_type="' . $item['page_type'] . '" id="wpsc-word-' . $item['word'] . '">Edit</a>'),
				'Add to Dictionary'		=> sprintf('<br /><input type="checkbox" class="wpsc-add-checkbox" name="add-word[]" value="' . $item['id'] . '" />Add to Dictionary')
			);
		}
		
		//return the word contents
		return sprintf('%1$s<span style="background-color:gray; float: left; margin: 3px 5px 0 -30px; display: block; width: 12px; height: 12px; border-radius: 16px; opacity: 1.0;"></span>%3$s',
            stripslashes(stripslashes($item['word'])),
            $item['ID'],
            $this->row_actions($actions)
        );
	}
	
	//Set up actions for page name
	function column_page_name($item) {
		//build row actions
		//Get page URL
		global $wpdb;
		if ($item['page_type'] == 'Page Content' || $item['page_type'] == 'Post Content') {
			$page = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type!='revision' AND post_type!='nav_menu_item' AND post_title = %s", $item[page_name]));
		} elseif ($item['page_type'] == 'Tag Title' || $item['page_type'] == 'Tag Description' || $item['page_type'] == 'Category Title' || $item['page_type'] == 'Category Description' || $item['page_type'] == 'Category Slug' || $item['page_type'] == 'Tag Slug') {
			$terms = $wpdb->prefix . "terms";
			$page = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $terms WHERE name = %s", $item[page_name]));
		} else {
			$page = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type!='revision' AND post_title = %s", $item[page_name]));
		}
		$link = urldecode ( get_permalink( $page ) );
		$handle = curl_init($url);
		curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);

		$response = curl_exec($handle);

		$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		if($httpCode == 404) {
			$output = '';
		} elseif (get_post_type($page) == 'nav_menu_item') {
			$taxonomy = $wpdb->prefix . "term_relationships";
			$page_name = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM $taxonomy WHERE object_id = %s", $page));
			$output = '<a href="/wp-admin/nav-menus.php?action=edit&menu='.$page_name.'" id="wpsc-page-name" page="' . $page . '" target="_blank">View</a>';
		} elseif (get_post_type($page) == 'wpcf7_contact_form') {
			$output = '<a href="admin.php?page=wpcf7&post='.$page.'&action=edit" id="wpsc-page-name" page="' . $page . '" target="_blank">View</a>';
		} elseif ($item['page_type'] == 'Post Title' || $item['page_type'] == 'Page Title' || $item['page_type'] == 'Yoast SEO Description' || $item['page_type'] == 'All in One SEO Description' || $item['page_type'] == 'Ultimate SEO Description' || $item['page_type'] == 'SEO Description' || $item['page_type'] == 'Yoast SEO Title' || $item['page_type'] == 'All in One SEO Title' || $item['page_type'] == 'Ultimate SEO Title' || $item['page_type'] == 'SEO Title' || $item['page_type'] == 'Post Slug' || $item['page_type'] == 'Page Slug') {
			$output = '<a href="/wp-admin/post.php?post=' . $page . '&action=edit" id="wpsc-page-name" page="' . $page . '" target="_blank">View</a>';
		} elseif ($item['page_type'] == 'Slider Title' || $item['page_type'] == 'Slider Caption' || $item['page_type'] == 'Smart Slider Title' || $item['page_type'] == 'Smart Slider Caption') {
			$output = '<a href="/wp-admin/post.php?post=' . $page . '&action=edit" id="wpsc-page-name" page="' . $page . '" target="_blank">View</a>';
		} elseif ($item['page_type'] == 'Huge IT Slider Title' || $item['page_type'] == 'Huge IT Slider Caption') {
			$it_slider = $wpdb->prefix . "huge_itslider_images";
			$page_name = $wpdb->get_var( $wpdb->prepare( "SELECT slider_id FROM $it_slider WHERE name= %s", $item[page_name]));
			$page_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $it_slider WHERE name= %s", $item[page_name]));
			$output = '<a href="/wp-admin/admin.php?page=sliders_huge_it_slider&task=edit_cat&id=' . $page_name . '" id="wpsc-page-name" page="' . $page_id . '" target="_blank">View</a>';
		} elseif ($item['page_type'] == 'Media Title' || $item['page_type'] == 'Media Description' || $item['page_type'] == 'Media Caption' || $item['page_type'] == 'Media Alternate Text') {
			$output = '<a href="/wp-admin/post.php?post=' . $page . '&action=edit" id="wpsc-page-name" page="' . $page . '" target="_blank">View</a>';
		} elseif ($item['page_type'] == 'Tag Title' || $item['page_type'] == 'Tag Description' || $item['page_type'] == 'Post Category' || $item['page_type'] == 'Category Description' || $item['page_type'] == 'Tag Slug' || $item['page_type'] == 'Category Slug') {
			$output = '<a href="/wp-admin/term.php?taxonomy=post_tag&tag_ID=' . $page . '&post_type=post" id="wpsc-page-name" page="' . $page . '" target="_blank">View</a>';
		} else {
			$output = '<a href="' . $link . '" id="wpsc-page-name" page="' . $page . '" target="_blank">View</a>';
		}

		curl_close($handle);
		$actions = array (
			'View'      			=> sprintf($output),
		);
		
		//return the word contents
		return sprintf('%1$s <span style="color:silver"></span>%3$s',
            $item['page_name'],
            $item['ID'],
            $this->row_actions($actions)
        );
	}

	//Set up actions for page type
	function column_page_type($item) {
		//build row actions
		$actions = array ();
		
		//return the word contents
		return sprintf('%1$s <span style="color:silver"></span>%3$s',
            $item['page_type'],
            $item['ID'],
            $this->row_actions($actions)
        );
	}

	//Get the titles of each column
	function get_columns() {
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'word' => 'Misspelled Words',
			'page_name' => 'Page',
			'page_type' => 'Page Type'
		);
		return $columns;
	}
	
	//Set which columns the table can be sorted by
	function get_sortable_columns() {
		$sortable_columns = array(
			'word' => array('word',false),
			'page_name' => array('page_name',false),
			'page_type' => array('page_type',false)
		);
		return $sortable_columns;
	}

	//Code for displaying a single row
	function single_row( $item ) {
		static $row_class = 'wpsc-row';
		$row_class = ( $row_class == '' ? ' class="alternate"' : '' );

		echo '<tr class="wpsc-row" id="wpsc-row-' . $item['id'] . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}
	
	//Prepares table data for display
	function prepare_items() {
		global $wpdb;
		
		$per_page = 20;
		
		//Define and build an array for column headers
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		//Grab and set up data
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$dictionary_table = $wpdb->prefix . 'spellcheck_dictionary';
		if ($_GET['s'] != '') {
			$results = $wpdb->get_results('SELECT id, word, page_name, page_type FROM ' . $table_name . ' WHERE ignore_word is false AND word LIKE "%' . $_GET['s'] . '%"', OBJECT); // Query that grabs data from database
		} else {
			$results = $wpdb->get_results('SELECT id, word, page_name, page_type FROM ' . $table_name . ' WHERE ignore_word is false', OBJECT);
		}
		$data = array();
		foreach($results as $word) {
			if ($word->word != '') {
				array_push($data, array('id' => $word->id, 'word' => $word->word, 'page_name' => $word->page_name, 'page_type' => $word->page_type, 'page_url' => $word->page_url));
			}
		}
		
		function usort_reorder($a, $b) {
			$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'word'; //Column to sort by, default word
			$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //Order to sort, default ascending
			
			$result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
			return ($order==='asc') ? $result : -$result;
		}
		usort($data, 'usort_reorder');
		
		//Set up pagination
		$current_page = $this->get_pagenum();
		$total_items = count($data);
		$data = array_slice($data,(($current_page-1)*$per_page),$per_page);
		$this->items = $data;
		
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil($total_items/$per_page)
		) );		
	}
}

/* Admin Functions */
function ignore_word($ids) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'spellcheck_words';
	$word_list = '';
	foreach ($ids as $id) {
		$words = $wpdb->get_results('SELECT word FROM ' . $table_name . ' WHERE id='. $id . ';');
		$word = $words[0]->word;
		$wpdb->update($table_name, array('ignore_word' => true), array('id' => $id));
		$wpdb->query("DELETE FROM $table_name WHERE id != $id AND word='$word'");
		$word_list .= $word . ", ";
	}

	return "The following words have been added to ignore list: " . $word_list;
}

function add_to_dictionary($ids) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'spellcheck_words';
	$dictionary_table = $wpdb->prefix . 'spellcheck_dictionary';
	$word_list = '';
	foreach ($ids as $id) {
		$words = $wpdb->get_results('SELECT word FROM ' . $table_name . ' WHERE id='. $id . ';');
		$word = $words[0]->word;
		$word = str_replace('%28', '(', $word);
		$check = $wpdb->get_results('SELECT word FROM ' . $dictionary_table . ' WHERE word = "' . $word . '"'); // Check to see if word is already in the dictionary

		if (sizeof($check) < 1)
			$wpdb->insert($dictionary_table, array('word' => $word)); // Add word to the dictionary

		$wpdb->delete($table_name, array('word' => $word)); //Delete all occurrences of the word from existing list of errors
		$word_list .= $word . ", ";
	}

	return "The following words have been added to the dictionary: " . $word_list;
}

function update_word_admin($old_words, $new_words, $page_names, $page_types, $old_word_ids) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'posts';
	$words_table = $wpdb->prefix . 'spellcheck_words';
	$terms_table = $wpdb->prefix . 'terms';
	$meta_table = $wpdb->prefix . 'postmeta';
	$taxonomy_table = $wpdb->prefix . 'term_taxonomy';
	$word_list = '';

for ($x= 0; $x < sizeof($old_words); $x++) {
	$old_words[$x] = str_replace('%28', '(', $old_words[$x]);
	$new_words[$x] = str_replace('%28', '(', $new_words[$x]);
	$old_words[$x] = str_replace('%27', "'", $old_words[$x]);
	$new_words[$x] = str_replace('%27', "'", $new_words[$x]);
	$old_words[$x] = stripslashes(stripslashes($old_words[$x]));
	$new_words[$x] = stripslashes($new_words[$x]);
	if ($page_types[$x] == 'Post Content' || $page_types[$x] == 'Page Content' || $page_types[$x] == 'Media Description' || $page_types[$x] == 'WooCommerce Product' || $page_types[$x] == 'WP eCommerce Product') {
		//****PAGE AND POST CONTENT****
		$page_result = $wpdb->get_results('SELECT post_content, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $page_result[0]->post_content);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($table_name, array('post_content' => $updated_content), array('ID' => $page_names[$x]));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the word from existing list of errors for this page
	} elseif ($page_types[$x] == 'Contact Form 7') {
		//****PAGE AND POST CONTENT****
		$page_result = $wpdb->get_results('SELECT post_content, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$meta_result = $wpdb->get_results('SELECT meta_value FROM ' . $meta_table . ' WHERE post_id="' . $page_names[$x] . '"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $page_result[0]->post_content);
		$updated_meta = str_replace($old_words[$x], $new_words[$x], $meta_result[0]->meta_value);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($table_name, array('post_content' => $updated_content), array('ID' => $page_names[$x]));
		$wpdb->update($meta_table, array('meta_value' => $updated_meta), array('post_id' => $page_names[$x]));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the word from existing list of errors for this page
	} elseif ($page_types[$x] == 'WooCommerce Product Excerpt') {
		//****WOO COMMERCE****
		$page_result = $wpdb->get_results('SELECT post_content, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $page_result[0]->post_content);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($table_name, array('post_excerpt' => $updated_content), array('ID' => $page_names[$x]));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the word from existing list of errors for this page
	} elseif ($page_types[$x] == 'Menu Item' || $page_types[$x] == 'Post Title' || $page_types[$x] == 'Page Title' || $page_types[$x] == 'Slider Title' || $page_types[$x] == 'Media Title') {
		//****MENU ITEMS AND PAGE/POST TITLES****
		$menu_result = $wpdb->get_results('SELECT post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$updated_content = str_replace($old_words[$x], $new_words[$x], $menu_result[0]->post_title);

		$old_name = $menu_result[0]->post_title;
		$wpdb->update($table_name, array('post_title' => $updated_content), array('ID' => $page_names[$x]));
		$wpdb->update($words_table, array('page_name' => $updated_content), array('page_name' => $old_name)); //Update the title of the page/post/menu in the spellcheck database
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $updated_content)); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Slider Caption') {
		//****SLIDER CAPTIONS****
		$menu_result = $wpdb->get_results('SELECT ID, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$caption = get_post_meta($menu_result[0]->ID, 'my_slider_caption', true);
		$updated_content = str_replace($old_words[$x], $new_words[$x], $caption);

		update_post_meta($menu_result[0]->ID, 'my_slider_caption', $updated_content);
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $menu_result[0]->post_title)); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Huge IT Slider Caption') {
		//****SLIDER CAPTIONS****
		$it_table = $wpdb->prefix . 'huge_itslider_images';
		$menu_result = $wpdb->get_results('SELECT name, description FROM ' . $it_table . ' WHERE id="' . $page_names[$x] . '"');
		
		$updated_content = str_replace($old_words[$x], $new_words[$x], $menu_result[0]->description);
		
		$wpdb->update($it_table, array('description' => $updated_content), array('id' => $page_names[$x]));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $menu_result[0]->name)); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Huge IT Slider Title') {
		//****SLIDER CAPTIONS****
		$it_table = $wpdb->prefix . 'huge_itslider_images';
		$menu_result = $wpdb->get_results('SELECT name FROM ' . $it_table . ' WHERE id="' . $page_names[$x] . '"');
		
		$updated_content = str_replace($old_words[$x], $new_words[$x], $menu_result[0]->name);	

		$wpdb->update($it_table, array('name' => $updated_content), array('id' => $page_names[$x]));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $menu_result[0]->name)); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Smart Slider Caption') {
		//****SLIDER CAPTIONS****
		$slider_table = $wpdb->prefix . 'wp_nextend_smartslider_slides';
		$menu_result = $wpdb->get_results('SELECT description FROM ' . $slider_table . ' WHERE title="' . $page_names[$x] . '"');
		$updated_content = str_replace($old_words[$x], $new_words[$x], $menu_result[0]->description);

		$wpdb->update($slider_table, array('description' => $updated_content), array('title' => $page_names[$x]));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $menu_result[0]->post_title)); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Smart Slider Title') {
		//****SLIDER CAPTIONS****
		$slider_table = $wpdb->prefix . 'wp_nextend_smartslider_slides';
		$menu_result = $wpdb->get_results('SELECT title FROM ' . $slider_table . ' WHERE title="' . $page_names[$x] . '"');
		$updated_content = str_replace($old_words[$x], $new_words[$x], $menu_result[0]->title);

		$wpdb->update($slider_table, array('title' => $updated_content), array('title' => $page_names[$x]));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $menu_result[0]->post_title)); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Media Alternate Text') {
		//****SLIDER CAPTIONS****
		$menu_result = $wpdb->get_results('SELECT ID, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$caption = get_post_meta($menu_result[0]->ID, '_wp_attachment_image_alt', true);
		$updated_content = str_replace($old_words[$x], $new_words[$x], $caption);

		update_post_meta($menu_result[0]->ID, '_wp_attachment_image_alt', $updated_content);
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $menu_result[0]->post_title)); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Media Caption') {
		//****MEDIA CAPTIONS****
		$page_result = $wpdb->get_results('SELECT post_excerpt, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $page_result[0]->post_excerpt);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($table_name, array('post_excerpt' => $updated_content), array('ID' => $page_names[$x]));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the word from existing list of errors for this page
	} elseif ($page_types[$x] == 'Tag Title' || $page_types[$x] == 'Category Title') {
		//****POST TAGS AND CATEGORIES****
		$tag_result = $wpdb->get_results('SELECT name FROM ' . $terms_table . ' WHERE name LIKE "%' . $old_words[$x] . '%"');
		$title_result = $wpdb->get_results('SELECT post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $tag_result[0]->name);

		$old_name = $title_result[0]->post_title;
		$wpdb->update($terms_table, array('name' => $updated_content), array('name' => $tag_result[0]->name));
		$wpdb->delete($words_table, array('word' => $old_words[$x])); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Tag Description') {
		//****POST TAGS AND CATEGORIES****
		
		$tag_result = $wpdb->get_results('SELECT description FROM ' . $taxonomy_table . ' WHERE taxonomy="post_tag" AND description LIKE "%' . $old_words[$x] . '%"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $tag_result[0]->description);

		$wpdb->update($taxonomy_table, array('description' => $updated_content), array('description' => $tag_result[0]->description));
		$wpdb->delete($words_table, array('word' => $old_words[$x])); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Category Description') {
		//****POST TAGS AND CATEGORIES****
		$tag_result = $wpdb->get_results('SELECT description FROM ' . $taxonomy_table . ' WHERE taxonomy="category" AND description LIKE "%' . $old_words[$x] . '%"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $tag_result[0]->description);

		$wpdb->update($taxonomy_table, array('description' => $updated_content), array('description' => $tag_result[0]->description));
		$wpdb->delete($words_table, array('word' => $old_words[$x])); //Delete all occurrences of the word from existing list of errors
	} elseif ($page_types[$x] == 'Yoast SEO Description') {
		//****YOAST SEO DESCRIPTION****
		$page_result = $wpdb->get_results('SELECT ID, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$desc_result = $wpdb->get_results('SELECT meta_value FROM ' . $meta_table . ' WHERE post_id=' . $page_result[0]->ID . ' AND meta_key="_yoast_wpseo_metadesc"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $desc_result[0]->meta_value);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($meta_table, array('meta_value' => $updated_content), array('post_id' => $page_result[0]->ID, 'meta_key' => '_yoast_wpseo_metadesc'));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the old word.
	} elseif ($page_types[$x] == 'All in One SEO Description') {
		//****ALL IN ONE SEO DESCRIPTION****
		$page_result = $wpdb->get_results('SELECT ID, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$desc_result = $wpdb->get_results('SELECT meta_value FROM ' . $meta_table . ' WHERE post_id=' . $page_result[0]->ID . ' AND meta_key="_aioseop_description"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $desc_result[0]->meta_value);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($meta_table, array('meta_value' => $updated_content), array('post_id' => $page_result[0]->ID, 'meta_key' => '_aioseop_description'));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the old word.
	} elseif ($page_types[$x] == 'Ultimate SEO Description') {
		//****ULTIMATE SEO DESCRIPTION****
		$page_result = $wpdb->get_results('SELECT ID, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$desc_result = $wpdb->get_results('SELECT meta_value FROM ' . $meta_table . ' WHERE post_id=' . $page_result[0]->ID . ' AND meta_key="_su_description"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $desc_result[0]->meta_value);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($meta_table, array('meta_value' => $updated_content), array('post_id' => $page_result[0]->ID, 'meta_key' => '_su_description'));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the old word.
	} elseif ($page_types[$x] == 'Yoast SEO Title') {
		//****YOAST SEO TITLE
		$page_result = $wpdb->get_results('SELECT ID, post_title FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$desc_result = $wpdb->get_results('SELECT meta_value FROM ' . $meta_table . ' WHERE post_id=' . $page_result[0]->ID . ' AND meta_key="_yoast_wpseo_title"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $desc_result[0]->meta_value);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($meta_table, array('meta_value' => $updated_content), array('post_id' => $page_result[0]->ID, 'meta_key' => '_yoast_wpseo_title'));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the old word.
	} elseif ($page_types[$x] == 'All in One SEO Title') {
		$page_result = $wpdb->get_results('SELECT ID FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$desc_result = $wpdb->get_results('SELECT meta_value FROM ' . $meta_table . ' WHERE post_id=' . $page_result[0]->ID . ' AND meta_key="_aioseop_title"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $desc_result[0]->meta_value);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($meta_table, array('meta_value' => $updated_content), array('post_id' => $page_result[0]->ID, 'meta_key' => '_aioseop_title'));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the old word.
	} elseif ($page_types[$x] == 'Ultimate SEO Title') {
		$page_result = $wpdb->get_results('SELECT ID FROM ' . $table_name . ' WHERE ID="' . $page_names[$x] . '"');
		$desc_result = $wpdb->get_results('SELECT meta_value FROM ' . $meta_table . ' WHERE post_id=' . $page_result[0]->ID . ' AND meta_key="_su_title"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $desc_result[0]->meta_value);

		$old_name = $page_result[0]->post_title;
		$wpdb->update($meta_table, array('meta_value' => $updated_content), array('post_id' => $page_result[0]->ID, 'meta_key' => '_su_title'));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $old_name)); //Delete all occurrences of the old word.
	} elseif ($page_types[$x] == 'Post Slug' || $page_types[$x] == 'Page Slug') {
		//****PAGE AND POST SLUGS****
		$page_result = $wpdb->get_results('SELECT post_name FROM ' . $table_name . ' WHERE post_title="' . $page_names[$x] . '"');

		$updated_content = str_replace($old_words[$x], $new_words[$x], $page_result[0]->post_name);
		$wpdb->update($table_name, array('post_name' => $updated_content), array('post_title' => $page_names[$x]));
		$wpdb->delete($words_table, array('word' => $old_words[$x], 'page_name' => $page_names[$x])); //Delete all occurrences of the word from existing list of errors
	}
	

	//Log file for pro features to keep track of updates made
	$page_url = get_permalink( $page_names[$x] );
	$page_title = get_the_title( $page_names[$x] );
	$current_time = date( 'l F d, g:i a' );
	$loc = dirname(__FILE__) . "/spellcheck.debug";
	$debug_file = fopen($loc, 'a');
	$debug_var = fwrite( $debug_file, "Old Word: " . $old_words[$x] . " | New Word: " . $new_words[$x] . " | Type: " . $page_types[$x] . " | Page Name: " . $page_title . " | Page URL: " . $page_url . " | Timestamp: " . $current_time . "\r\n\r\n" );
	fclose($debug_file);
	$word_list .= $old_words[$x] . ", ";
	}
	return "The following words have been updated: " . $word_list;
}

function admin_render() {
	ini_set('memory_limit','8192M'); //Sets the PHP memory limit
	global $wpdb;
	global $ent_included;
	$table_name = $wpdb->prefix . "spellcheck_words";
	$word_count = $wpdb->get_var ( "SELECT COUNT(*) FROM $table_name WHERE ignore_word='false'" );
	$options_table = $wpdb->prefix . "spellcheck_options";

	//Get the settings to determine which submit buttons to grey out
	$settings = $wpdb->get_results('SELECT option_name, option_value FROM ' . $options_table);
	$check_pages = $settings[4]->option_value;
	$check_posts = $settings[5]->option_value;
	$check_menus = $settings[7]->option_value;
	$page_titles = $settings[12]->option_value;
	$post_titles = $settings[13]->option_value;
	$tags = $settings[14]->option_value;
	$categories = $settings[15]->option_value;
	$seo_desc = $settings[16]->option_value;
	$seo_titles = $settings[17]->option_value;
	$page_slugs = $settings[18]->option_value;
	$post_slugs = $settings[19]->option_value;
	$check_sliders = $settings[30]->option_value;
	$check_media = $settings[31]->option_value;
	$check_ecommerce = $settings[36]->option_value;
	$check_cf7 = $settings[37]->option_value;
	$check_tag_desc = $settings[38]->option_value;
	$check_tag_slug = $settings[39]->option_value;
	$check_cat_desc = $settings[40]->option_value;
	$check_cat_slug = $settings[41]->option_value;

	$message = '';
	$total_pages = sizeof(get_pages(array('number' => PHP_INT_MAX, 'hierarchical' => 0, 'post_type' => 'page', 'post_status' => array('publish', 'draft'))));
	$total_posts = sizeof(get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'post', 'post_status' => array('publish', 'draft'))));
	$total_media = sizeof(get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'attachment', 'post_status' => array('publish', 'draft'))));
	if (!$ent_included) {
		if ($total_pages > 500) $total_pages = 500;
		if ($total_posts > 500) $total_posts = 500;
		if ($total_media > 500) $total_posts = 500;
	}
	$estimated_time = intval(($total_pages + $total_posts + $total_media) / 1.2);
	$scan_message = '';
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Create Pages') {
				$message = "Page Creation Started";
		for($x = 1250; $x++; $x <= 2000) {
			// Create post object
			$my_post = array(
	  			'post_title'    => "Post-" . $x,
	  			'post_content'  => "Cras felis diam, viverra vitae maximus, malesuada accumsan augue. Nulla",
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_category' => array( 48,49 ),
				'tags_input' => array("tagew", "tgae2"),
	  			'post_type'  => "post"
			);
	 
			// Insert the post into the database
			wp_insert_post( $my_post );
		}
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Pages') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Page Content</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckpages_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckpages');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Posts') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Post Content</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckposts_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckposts');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Menus') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Menus</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckmenus_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckmenus');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Page Titles') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Page Titles</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckpagetitles_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckpagetitles');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Post Titles') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Post Titles</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckposttitles_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckposttitles');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Tags') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Tags</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckposttags_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckposttags');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Tag Descriptions') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Tag Descriptions</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckposttagsdesc_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckposttagsdesc');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Tag Slugs') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Tag Slugs</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckposttagsslugs_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckposttagsslugs');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Categories') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Categories</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckcategories_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckcategories');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Category Descriptions') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Category Descriptions</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckcategoriesdesc_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckcategoriesdesc');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Category Slugs') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Category Slugs</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckcategoriesslugs_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckcategoriesslugs');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'SEO Descriptions') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">SEO Descriptions</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckseodesc_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckseodesc');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'SEO Titles') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">SEO Titles</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckseotitles_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckseotitles');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Page Slugs') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Page Slugs</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckpageslugs_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckpageslugs');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Post Slugs') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Post Slugs</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckpostslugs_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckpostslugs');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Sliders') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Sliders</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'adminchecksliders_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'adminchecksliders_pro');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Media Files') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Media Files</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckmedia_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckmedia_pro');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'WooCommerce and WP-eCommerce Products') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">eCommerce Products</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		if ($ent_included) { 
		wp_schedule_single_event(time() + 6, 'admincheckecommerce_ent');
		} else {
		wp_schedule_single_event(time() + 6, 'admincheckecommerce');
		}
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Contact Form 7') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for <span style="color: rgb(0, 150, 255); font-weight: bold;">Contact Form 7</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		wp_schedule_single_event(time() + 6, 'admincheckcf7');
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Entire Site') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> Scan has been started for the <span style="color: rgb(0, 150, 255); font-weight: bold;">Entire Site</span>. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
		clear_results();
		wp_schedule_single_event(time() + 2, 'adminscansite');
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress'));
	}
	if ($_GET['action'] == 'check' && $_GET['submit'] == 'Clear Results') {
		$message = 'All results have been cleared';
		clear_results();
	}
	if ($_GET['old_words'] != '' && $_GET['new_words'] != '' && $_GET['page_types'] != '' && $_GET['old_word_ids'] != '') 
		$message = update_word_admin($_GET['old_words'], $_GET['new_words'], $_GET['page_names'], $_GET['page_types'], $_GET['old_word_ids']);
	if ($_GET['ignore_word'] != '')
		$message = ignore_word($_GET['ignore_word']); //Flag words to be ignored by the plug_in
	if ($_GET['add_word'] != '')
		$message = add_to_dictionary($_GET['add_word']); //Add words to the plug_in dictionary
		
	$list_table = new sc_table();
	$list_table->prepare_items();
	
	//Set up Javascript for refreshing the page on scan finish
	wp_enqueue_script( 'results-ajax', plugin_dir_url( __FILE__ ) . '/ajax.js', array('jquery') );
	wp_localize_script( 'results-ajax', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

	$path = plugin_dir_path( __FILE__ ) . '../premium-functions.php';
	global $pro_included;

	//Get the number of words scanned by the last scan
	$pro_words = 0;
	if (!$pro_included && !$ent_included) {
		$pro_word_count = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name='pro_word_count';");
		$pro_words = $pro_word_count[0]->option_value;
	}
	$total_word_count = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name='total_word_count';");
	$total_words = $total_word_count[0]->option_value;
	if ($total_words > 0) { $literacy_factor = (($total_words - $word_count - $pro_words) / $total_words) * 100;
	} else { $literacy_factor = 100; }
	$literacy_factor = number_format((float)$literacy_factor, 2, '.', '');
	
	$cron_tasks = _get_cron_array();
	$scan_progress = false;
	$scan_site = 0;
	
	foreach ($cron_tasks as $task) {
		if (key($task) == 'adminscansite') {
			$scan_site++;
		} elseif (substr(key($task), 0, strlen('admincheck')) === 'admincheck') {
			$scan_progress = true;
		}
	}
	if ($scan_site >= 2) $scan_progress = true;
	
	$scanning = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name='scan_in_progress';");
	if ($scanning[0]->option_value == "true" && $scan_message == '') {
		$scan_message = '<img src="'. plugin_dir_url( __FILE__ ) . 'images/loading.gif" alt="Scan in Progress" /> A scan is currently in progress. Estimated time for completion is '.$estimated_time.' Seconds. <a href="/wp-admin/admin.php?page=wp-spellcheck.php">Click here</a> to see scan results. <span class="wpsc-mouseover-button-page" style="border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;">?</span><span class="wpsc-mouseover-text-page">The page will automatically refresh when the scan is finished. You do not need to remain on this page for the scan to run.<br /><br />Time estimate may vary based on server strength.</span>';
	} elseif ($scanning[0]->option_value == "error" && $scan_message == '' && !$scan_progress) {
		$scan_message = "<span style='color:red;'>No scan currently running. The previous scan was unable to finish scanning</style>";
	} elseif ($scan_message == '') {
		$scan_message = "No scan currently running";
	}
	$time_of_scan = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name='last_scan_finished';"); 
	if ($time_of_scan[0]->option_value == "0") {
		$time_of_scan = "0 Minutes";
	} else {
		$time_of_scan = $time_of_scan[0]->option_value;
		if ($time_of_scan == '') $time_of_scan = "0 Seconds";
	}

	$post_types = get_post_types();
	$post_type_list = array();
	foreach ($post_types as $type) {
		if ($type != 'revision' && $type != 'page' && $type != 'optionsframework' && $type != 'attachment' && $type != 'leadpages_post' && $type != 'slider')
			array_push($post_type_list, $type);
	}

	$page_count = get_pages(array('number' => PHP_INT_MAX, 'hierarchical' => 0, 'post_type' => 'page', 'post_status' => array('publish', 'draft')));
	$post_count = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => $post_type_list, 'post_status' => array('publish', 'draft')));
	$media_count = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'attachment'));
	$page_scan = $wpdb->Get_results("SELECT option_value FROM $options_table WHERE option_name='page_count';");
	$post_scan = $wpdb->Get_results("SELECT option_value FROM $options_table WHERE option_name='post_count';");
	$media_scan = $wpdb->Get_results("SELECT option_value FROM $options_table WHERE option_name='media_count';");
	$options_list = $wpdb->Get_results("SELECT option_value FROM $options_table;");
	?>
		<?php show_feature_window(); ?>
		<?php check_install_notice(); ?>
<div id="wpsc-dialog-confirm" title="Are you sure?" style="display: none;">
  <p>Would you like to Proceed with the changes?</p>
</div>
		<div class="wrap wpsc-table">
			<h2><a href="admin.php?page=wp-spellcheck.php"><img src="<?php echo plugin_dir_url( __FILE__ ) . '../images/logo.png'; ?>" alt="WP Spell Check" /></a> <span style="position: relative; top: -15px;">Scan Results</span></h2>
			<form action="<?php echo admin_url('admin.php'); ?>" method='GET'>
				<input type="hidden" name="page" value="wp-spellcheck.php">
				<input type="hidden" name="action" value="check">
				<style>.search-box input[type=submit] { color: white; background-color: #00A0D2; border-color: #0073AA; } #cb-select-all-1,#cb-select-all-2 { display: none; } td.word { font-size: 15px; } p.submit { display: inline-block; margin-left: 10px; } h3.sc-message { width: 49%; display: inline-block; } .wpsc-mouseover-text-page,.wpsc-mouseover-text-post { color: black; font-size: 12px; width: 225px; display: inline-block; position: absolute; margin: -13px 0 0 -270px; padding: 3px; border: 1px solid black; border-radius: 10px; opacity: 0; background: white; } .row-actions { visibility: visible!important; color: black; } </style>
				<?php echo "<h3 class='sc-message'style='color: rgb(0, 150, 255); font-size: 1.4em;'>Website Literacy Factor: " . $literacy_factor . "%"; ?>
				<?php echo "<h3 class='sc-message' style='color: rgb(0, 115, 0);'>The last scan found {$word_count} errors".$pro_message."</h3>"; ?>
				<?php echo "<h3 class='sc-message' style='color: rgb(0, 115, 0);'>" . $page_scan[0]->option_value . " pages scanned out of " . sizeof($page_count);
					if ($pro_included && sizeof($page_count) >= 500) { echo "<span class='wpsc-mouseover-button-page' style='border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;'>?<span class='wpsc-mouseover-text-page'>Our pro version scans up to 500 pages.<br /><a href='https://www.wpspellcheck.com/purchase-options' target='_blank'>Click here</a> to upgrade to enterprise</span></span>";
					} elseif (!$pro_included && !$ent_included && sizeof($page_count) >= 100) { echo "<span class='wpsc-mouseover-button-page' style='border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;'>?<span class='wpsc-mouseover-text-page'>Our free version scans up to 100 pages.<br /><a href='https://www.wpspellcheck.com/purchase-options' target='_blank'>Click here</a> to upgrade to pro</span></span>"; }
					echo "</h3>"; ?>
				<?php echo "<h3 class='sc-message' style='color: rgb(0, 115, 0);'>" . $post_scan[0]->option_value . " posts scanned out of " . sizeof($post_count);
				if ($pro_included && sizeof($post_count) >= 500) { echo "<span class='wpsc-mouseover-button-post' style='border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;'>?<span class='wpsc-mouseover-text-post'>Our pro version scans up to 500 posts.<br /><a href='https://www.wpspellcheck.com/purchase-options' target='_blank'>Click here</a> to upgrade to enterprise</span></span>";
				} elseif (!$pro_included && !$ent_included && sizeof($post_count) >= 100) { echo "<span class='wpsc-mouseover-button-post' style='border-radius: 29px; border: 1px solid green; display: inline-block; margin-left: 10px; padding: 4px 10px; cursor: help;'>?<span class='wpsc-mouseover-text-post'>Our free version scans up to 100 posts.<br /><a href='https://www.wpspellcheck.com/purchase-options' target='_blank'>Click here</a> to upgrade to pro</span></span>"; }
				echo "</h3>"; ?>
				<?php if ($pro_included || $ent_included) { echo "<h3 class='sc-message' style='color: rgb(0, 115, 0);'>" . $media_scan[0]->option_value . " media files scanned out of " . sizeof($media_count) . "</h3>"; } ?>
				<?php echo "<h3 class='sc-message' style='color: rgb(0, 115, 0);'>$total_words words were scanned on your entire website</h3>"; ?>
				<?php echo "<h3 class='sc-message' style='color: rgb(0, 115, 0);'>The last scan took $time_of_scan</h3>"; ?>
				<?php echo "<h3 class='sc-message' style='color: rgb(0, 115, 0);'>$scan_message</h3><br />"; ?>
				<?php if (!$pro_included && !$ent_included) echo "<h3 class='sc-message' style='color: rgb(225, 0, 0);'>$pro_words errors have been found on other parts of your website. <a href='https://www.wpspellcheck.com/purchase-options' target='_blank'>Click here</a> to update to pro version to fix them.</h3><br />"; ?>
			<?php if($message != '') echo "<div class='wpsc-message' style='font-size: 1.3em; color: rgb(0, 115, 0); font-weight: bold;'>" . $message . "</div>"; ?>
				<div class="wpsc-scan-buttons">
				<h3 style="display: inline-block;">Scan:</h3>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Entire Site" <?php if ($checked_pages == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Pages" <?php if ($check_pages == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Posts" <?php if ($check_posts == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Contact Form 7" <?php if ($check_cf7 == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>				
				<?php if ($pro_included || $ent_included) { ?>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Menus" <?php if ($check_menus == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Page Titles" <?php if ($page_titles == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Post Titles" <?php if ($post_titles == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Tags" <?php if ($tags == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Tag Descriptions" <?php if ($check_tag_desc == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Tag Slugs" <?php if ($check_tag_slug == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Categories" <?php if ($categories == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>	
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Category Descriptions" <?php if ($check_cat_desc == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>	
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Category Slugs" <?php if ($check_cat_slug == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>	
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="SEO Descriptions" <?php if ($seo_desc == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="SEO Titles" <?php if ($seo_titles == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Page Slugs" <?php if ($page_slugs == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Post Slugs" <?php if ($post_slugs == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Sliders" <?php if ($check_sliders == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Media Files" <?php if ($check_media == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="WooCommerce and WP-eCommerce Products" <?php if ($check_ecommerce == 'false') echo "style='background: darkgrey!important; color: white!important; border-color: grey!important;' disabled" ?>></p>
				<?php } ?>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Clear Results"></p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" style="background-color: red;" value="See Scan Results"></p>
				<!--<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" style="background-color: red;" value="Create Pages"></p>-->
</div>
			</form>
			<div style="float: right; width:23%; margin-left: 2%;">
				<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.0";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
				<a href="https://www.wpspellcheck.com/" target="_blank"><img src="<?php echo plugin_dir_url( __FILE__ ) . '../images/logo.png'; ?>" alt="WP Spell Check" /></a>
<script type="text/javascript">
//<![CDATA[
if (typeof newsletter_check !== "function") {
window.newsletter_check = function (f) {
    var re = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-]{1,})+\.)+([a-zA-Z0-9]{2,})+$/;
    if (!re.test(f.elements["ne"].value)) {
        alert("The email is not correct");
        return false;
    }
    for (var i=1; i<20; i++) {
    if (f.elements["np" + i] && f.elements["np" + i].value == "") {
        alert("");
        return false;
    }
    }
    if (f.elements["ny"] && !f.elements["ny"].checked) {
        alert("You must accept the privacy statement");
        return false;
    }
    return true;
}
}
//]]>
</script>

<div class="newsletter newsletter-subscription" style="padding: 5px 5px 10px 5px; border: 1px solid #008200; border-radius: 5px; background: white;">
<div class="wpsc-sidebar" style="margin-bottom: 15px;"><h2>Help to improve this plugin!</h2><center>Enjoyed this plugin? You can help by <a class="review-button" href="https://en-ca.wordpress.org/plugins/wp-spell-check/" target="_blank">rating this plugin on wordpress.org</a></center></div>
</div>
<hr>
<div style="padding: 5px 5px 10px 5px; border: 1px solid #0096FF; border-radius: 5px; background: white;">
				<a href="https://www.wpspellcheck.com/tutorials" target="_blank"><img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/wp-spellcheck-tutorials.jpg'; ?>" style="max-width: 99%;" alt="Watch WP Spell Check Tutorials" /></a>
</div>
<hr>
<div style="padding: 5px 5px 10px 5px; border: 1px solid #D60000; border-radius: 5px; background: white; text-align: center;">
				<h2>Follow us on Facebook</h2>
				<div class="fb-page" data-href="https://www.facebook.com/wpspellcheck/" data-width="180px" data-small-header="true" data-adapt-container-width="true" data-hide-cover="false" data-show-facepile="true"><blockquote cite="https://www.facebook.com/wpspellcheck/" class="fb-xfbml-parse-ignore"><a href="https://www.facebook.com/wpspellcheck/">WP Spell Check</a></blockquote></div>
</div>
<hr>
<div style="padding: 5px 5px 10px 5px; border: 1px solid #73019A; border-radius: 5px; background: white;">
<h2>Stay up to date with news and software updates</h2>
<form method="post" action="https://www.wpspellcheck.com/wp-content/plugins/newsletter/do/subscribe.php" onsubmit="return newsletter_check(this)">

<table cellspacing="0" cellpadding="3" border="0">

<!-- email -->
<tr>
	<th>Email</th>
	<td align="left"><input class="newsletter-email" type="email" name="ne" style="width: 100%;" size="30" required></td>
</tr>

<tr>
	<td colspan="2" class="newsletter-td-submit">
		<input class="newsletter-submit" type="submit" value="Sign me up"/>
	</td>
</tr>

</table>
</form>
</div>
<hr>
<div style="padding: 5px 5px 10px 5px; border: 1px solid #00BBC1; border-radius: 5px; background: white;">
				<div class="wpsc-sidebars" style="margin-bottom: 15px;"><h2>Want your entire website scanned?</h2>
					<p><a href="https://www.wpspellcheck.com/purchase-options/" target="_blank">Upgrade to WP Spell Check Pro<br />
					See Benefits and Features here »</a></p>
				</div>
</div>
			</div>
				<form method-"POST" style="position:absolute; right: 26%; margin-top: 37px;">
					<input type="hidden" name="page" value="wp-spellcheck.php" />
					<?php $list_table->search_box('search', 'search_id'); ?>
				</form>
			<form id="words-list" method="get" style="width: 75%; float: left;">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
				<input name="wpsc-edit-update-button" class="wpsc-edit-update-button" type="submit" value="Save all Changes" class="button button-primary" style="width: 100%; background: #008200; border-color: #005200; color: white; font-weight: bold;"/>
				<?php $list_table->display() ?>
				<input name="wpsc-edit-update-buttom" class="wpsc-edit-update-button" type="submit" value="Save all Changes" class="button button-primary" style="width: 100%; background: #008200; border-color: #005200; color: white; font-weight: bold;"/>
			</form>
<form method-"post"="" style="float: right; margin-top: -65px; position: relative; z-index: 999999; clear: left; margin-right: 26%;">
				<input type="hidden" name="page" value="wp-spellcheck.php">
				<p class="search-box">
	<label class="screen-reader-text" for="search_id-search-input">search:</label>
	<input type="search" id="search_id-search-input" name="s" value="">
	<input type="submit" id="search-submit" class="button" value="search"></p>
			</form>
		</div>
		<!-- Quick Edit Clone Field -->
		<table style="display: none;">
			<tbody>
				<tr id="wpsc-editor-row" class="wpsc-editor">
					<td colspan="4">
						<div class="wpsc-edit-content">
							<h4>Edit Word</h4>
							<label><span>Word</span><input type="text" name="word_update[]" style="margin-left: 3em;" value class="wpsc-edit-field"></label>
							<input type="hidden" name="edit_page_name[]" value>
							<input type="hidden" name="edit_page_type[]" value>
							<input type="hidden" name="edit_old_word[]" value>
							<input type="hidden" name="edit_old_word_id[]" value>
						</div>
						<div class="wpsc-buttons">
							<input type="button" class="button-secondary cancel alignleft wpsc-cancel-button" value="Cancel">
							<!--<input type="checkbox" name="global-edit" value="global-edit"> Apply changes to entire website-->
							<div style="clear: both;"></div>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
		<!-- Suggested Spellings Clone Field -->
		<table style="display: none;">
			<tbody>
				<tr id="wpsc-suggestion-row" class="wpsc-editor">
					<td colspan="4">
						<div class="wpsc-suggestion-content">
							<label><span>Suggested Spellings</span>
							<select class="wpsc-suggested-spelling-list" name="suggested_word[]">
								<option id="wpsc-suggested-spelling-1" value></option>
								<option id="wpsc-suggested-spelling-2" value></option>
								<option id="wpsc-suggested-spelling-3" value></option>
								<option id="wpsc-suggested-spelling-4" value></option>
							</select>
							<input type="hidden" name="suggest_page_name[]" value>
							<input type="hidden" name="suggest_page_type[]" value>
							<input type="hidden" name="suggest_old_word[]" value>
							<input type="hidden" name="suggest_old_word_id[]" value>
						</div>
						<div class="wpsc-buttons">
							<input type="button" class="button-secondary cancel alignleft wpsc-cancel-suggest-button" value="Cancel">
							<!--<input type="checkbox" name="global-suggest" value="global-suggest"> Apply changes to entire website-->
							<div style="clear: both;"></div>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	<?php 
	}
?>