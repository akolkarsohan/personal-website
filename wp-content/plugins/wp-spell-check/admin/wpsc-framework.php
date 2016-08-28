<?php
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
	/* WP Spell Check classes */
		
	/* Main WP Spell Check Functions */
	
	// Check a single word for spelling
	function check_word($word, $dict_list) {
		ini_set('memory_limit','256M'); //Sets the PHP memory limit
		if (strlen($word) <= 2) { return true; }
		if (preg_replace('/[^A-Za-z0-9]/', '', $word) == '') { return true; }
		global $wpdb;
		$ignore_table = $wpdb->prefix . 'spellcheck_ignore';
		$words_table = $wpdb->prefix . 'spellcheck_words';
		
		if (is_numeric($word)) { return true; }
		if (preg_match("/^[0-9]{3}-[0-9]{4}-[0-9]{4}$/", $word)) { return true; }
		
		$ignore_word = $wpdb->get_results("SELECT word FROM $words_table WHERE word='" . addslashes($word) . "' AND ignore_word!=0");
		if (sizeof($ignore_word) >= 1) return true;

		return false;
	}

	function check_pages($is_running = false, $page_list = null) {
		try {
		ini_set('memory_limit','512M'); //Sets the PHP memory limit
		set_time_limit(600); //Try to set PHP timeout limit to 10 minutes
		global $wpdb;
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$ignore_table = $wpdb->prefix . 'spellcheck_ignore';
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		global $pro_included;
		$total_pages = 100;
		if ($pro_included) $total_pages = 500;
		$total_words = 0;
		$page_count = 0;
		$word_count = 0;
		if ($page_list == null) $page_list = get_pages(array('number' => PHP_INT_MAX, 'hierarchical' => 0, 'post_type' => 'page', 'post_status' => array('publish', 'draft')));
		if (!$is_running) {
			$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
			$start_time = time();
		}
			$ind_start_time = time();
		
		$max_time = ini_get('max_execution_time'); //Get max execution time for PHP
		
		$options_list = $wpdb->get_results("SELECT option_value FROM $options_table");

		$ignore_pages = $wpdb->get_results('SELECT keyword FROM ' . $ignore_table . ' WHERE type="page";');

		foreach ($page_list as $page) {
			array_shift($page_list);
			$ignore_flag = 'false';
			foreach($ignore_pages as $ignore_check) {
				if (strtoupper(trim($page->post_title)) == strtoupper(trim($ignore_check->keyword))) {
					$ignore_flag = 'true';
				}
			}
			if ($ignore_flag == 'true') { continue; }
			$page_count++;
			
			$words_content = $page->post_content;
			$words_content = do_shortcode($words_content);
			$words_content = preg_replace("@<style[^>]*?>.*?</style>@siu",' ',$words_content);
			$words_content = preg_replace("@<script[^>]*?>.*?</script>@siu",' ',$words_content);
			$words_content = preg_replace("/(\<.*?\>)/",' ',$words_content);
			$words_content = html_entity_decode(strip_tags($words_content), ENT_QUOTES, 'utf-8');
			if ($options_list[23]->option_value == 'true') {
				$words_content = preg_replace('/\S+\@\S+\.\S+/', ' ', $words_content);
			}
			if ($options_list[24]->option_value == 'true') {
				$words_content = preg_replace('/((http|https|ftp)\S+)/', '', $words_content);
				$words_content = preg_replace('/www\.\S+/', '', $words_content);
				$words_content = preg_replace('/([a-z1-3]+\.(com|ca))/i', ' ', $words_content);
			}
			$words_content = preg_replace("/'s/m", "", $words_content);
			$words_content = preg_replace("/[^A-Za-z']+/m", " ", $words_content);
			$words = explode(" ", $words_content);

			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						//Check if word already exists in the database for that page
						if ((strtoupper($word) != $word || $caps_check[0]->option_value == 'false') && $word != '' && !is_numeric($word)) {
							if ($page_count <= $total_pages) {
							$word = addslashes($word);
							$wpdb->insert($table_name, array('word' => $word, 'page_name' => $page->post_title, 'page_type' => 'Page Content'));
							} else {
								$word_count++;
							}
						}
					}
				}
			}
			$end_task = false;
			if (((time() - $ind_start_time) >= $max_time - 3) && count($page_list) > 0) {
				$end_task = true;
				wp_schedule_single_event(time() + 1, 'admincheckpages', array(true, $page_list));
				break;
			}
			if($end_task) break;
		}
		if (!$end_task) {
			$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
			$word_count = $word_count + intval($counter[0]->option_value);
			$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
			$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
			$total_words = $total_words + intval($counter[0]->option_value);
			$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
			if ($page_count > $total_pages) $page_count = $total_pages;
			$wpdb->update($options_table, array('option_value' => $page_count), array('option_name' => 'page_count'));
			if (!$is_running) {
				$wpdb->update($options_table, array('option_value' => 'false'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
				$end_time = time();
				$total_time = time_elapsed($end_time - $start_time);
				$wpdb->update($options_table, array('option_value' => $total_time), array('option_name' => 'last_scan_finished')); // Update the total time of the scan
			}
		}
		} catch(Exception $e) {
			global $wpdb;
			$options_table = $wpdb->prefix . 'spellcheck_options';
			$wpdb->update($options_table, array('option_value' => 'error'), array('option_name' => 'scan_in_progress'));
		}
	}
	add_action ('admincheckpages', 'check_pages', 10, 2);

	function check_posts($is_running = false, $post_list = null) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$ignore_table = $wpdb->prefix . 'spellcheck_ignore';
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		set_time_limit(600); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		global $pro_included;
		$total_posts = 100;
		if ($pro_included) $total_posts = 500;
		if ($ent_included) $total_posts = PHP_INT_MAX;
		$total_words = 0;
		$post_count = 0;
		$word_count = 0;
		$max_time = ini_get('max_execution_time'); //Get max execution time for PHP
		if (!$is_running) {
			$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
			$start_time = time();
		}
		$ind_start_time = time();

		$ignore_posts = $wpdb->get_results('SELECT keyword FROM ' . $ignore_table . ' WHERE type="page";');
		$options_list = $wpdb->get_results("SELECT option_value FROM $options_table");

		if ($post_list == null) {
		$post_types = get_post_types();
		$post_type_list = array();
		foreach ($post_types as $type) {
			if ($type != 'revision' && $type != 'page' && $type != 'slider' && $type != 'attachment' && $type != 'optionsframework' && $type != 'product' && $type != 'wpsc-product' && $type != 'wpcf7_contact_form')
				array_push($post_type_list, $type);
		}

		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => $post_type_list, 'post_status' => array('publish', 'draft')));
		}

		foreach ($posts_list as $post) {
			array_shift($posts_list);
			$ignore_flag = 'false';
			foreach($ignore_posts as $ignore_check) {
				if (strtoupper(trim($post->post_title)) == strtoupper(trim($ignore_check->keyword))) {
					$ignore_flag = 'true';
				}
			}
			if ($ignore_flag == 'true') { continue; }
			$post_count++;
			$words_list = $post->post_content;
			$words_list = do_shortcode($words_list);
			//$words_list = preg_replace("/(\[.*?\])/s",' ',$words_list);
			$words_list = preg_replace("(\<.*?\>)",' ',$words_list);
			$words_list = preg_replace("/<style>\s\S*?<\/style>/",'',$words_list);
			$words_list = html_entity_decode(strip_tags($words_list), ENT_QUOTES, 'utf-8');
			if ($options_list[23]->option_value == 'true') {
				$words_list = preg_replace('/\S+\@\S+\.\S+/', ' ', $words_list);
			}
			if ($options_list[24]->option_value == 'true') {
				$words_list = preg_replace('/((http|https|ftp)\S+)/', '', $words_list);
				$words_list = preg_replace('/www\.\S+/', '', $words_list);
				$words_list = preg_replace('/([a-z1-3]+\.(com|ca))/i', ' ', $words_list);
			}
			//$words_list = htmlspecialchars_decode($words_list);
			$words_list = preg_replace("/[0-9]/", "", $words_list);
			$words_list = preg_replace("/'s/", " ", $words_list);
			$words_list = preg_replace("/[^a-zA-z'’`]/", " ", $words_list);
			$words_list = preg_replace('/\s+/', ' ', $words_list);
			$words_list = str_replace("\xA0", ' ',$words_list);
			$words_list = str_replace("\xC2", '',$words_list);
			$words_list = str_replace("&nbsp;", ' ',$words_list);
			$words_list = str_replace('/',' ',$words_list);
			$words_list = str_replace("-",' ',$words_list);
			$words_list = str_replace("|",' ',$words_list);
			$words_list = str_replace("@",' ',$words_list);
			$words_list = str_replace("&",' ',$words_list);
			$words_list = str_replace("#",' ',$words_list);
			$words_list = str_replace("+",' ',$words_list);
			$words_list = str_replace("*",'',$words_list);
			$words_list = str_replace("?",' ',$words_list);
			$words_list = str_replace("…",' ',$words_list);
			$words_list = str_replace(";",' ',$words_list);
			$words_list = str_replace("’","'",$words_list);
			$words_list = str_replace("`","'",$words_list);
			$words_list = preg_replace("/[’']s[^a-z]/i", ' ', $words_list);
			$words_list = preg_replace("/[^a-z]s[’']/i", 's', $words_list);
			$words_list = str_replace("s'",'s',$words_list);
			$words_list = str_replace(".",' ',$words_list);
			$words = explode(' ', $words_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						//Check if word already exists in the database for that page
						if ((strtoupper($word) != $word || $caps_check[0]->option_value == 'false') && $word != '') {
							if ($post_count <= $total_posts) {
							$word = addslashes($word);
							$wpdb->insert($table_name, array('word' => addslashes($word), 'page_name' => $post->post_title, 'page_type' => 'Post Content'));
							} else {
								$word_count++;
							}
						}
					}
				}	
			}
			$end_task = false;
			if (((time() - $ind_start_time) >= $max_time - 3) && count($page_list) > 0) {
				$end_task = true;
				wp_schedule_single_event(time() + 1, 'admincheckposts', array(true, $posts_list));
				break;
			}
			if($end_task) break;
		}
		if (!$end_task) {
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
		if ($post_count > $total_posts) $post_count = $total_posts;
		$wpdb->update($options_table, array('option_value' => $post_count), array('option_name' => 'post_count'));
		if (!$is_running) {
			$wpdb->update($options_table, array('option_value' => 'false'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
			$end_time = time();
			$total_time = time_elapsed($end_time - $start_time);
			$wpdb->update($options_table, array('option_value' => $total_time), array('option_name' => 'last_scan_finished')); // Update the total time of the scan
		}
		}
	}
	add_action ('admincheckposts', 'check_posts',10,2);

	function check_cf7($is_running = false) {
		global $wpdb;
		global $ent_included;
		global $pro_included;
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$ignore_table = $wpdb->prefix . 'spellcheck_ignore';
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		global $pro_included;
		$total_posts = 100;
		if ($pro_included) $total_posts = 500;
		if ($ent_included) $total_posts = PHP_INT_MAX;
		$total_words = 0;
		$post_count = 0;
		$word_count = 0;
		if (!$is_running) {
			$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
			$start_time = time();
		}

		$ignore_posts = $wpdb->get_results('SELECT keyword FROM ' . $ignore_table . ' WHERE type="page";');
		$options_list = $wpdb->get_results("SELECT option_value FROM $options_table");

		$posts_list = get_posts(array('posts_per_page' => $total_posts, 'post_type' => 'wpcf7_contact_form', 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {
			$ignore_flag = 'false';
			foreach($ignore_posts as $ignore_check) {
				if (strtoupper(trim($post->post_title)) == strtoupper(trim($ignore_check->keyword))) {
					$ignore_flag = 'true';
				}
			}
			if ($ignore_flag == 'true') { continue; }
			$post_count++;
			$words_list = $post->post_content;
			$words_list = preg_replace('/(?<!\\r)\\n.+/msi', '', $words_list); //Removes all additional content from a CF-7 Form for form settings
			$words_list = preg_replace("/(\[.*?\])/s",' ',$words_list);
			$words_list = preg_replace("(\<.*?\>)",' ',$words_list);
			$words_list = preg_replace("/<style>\s\S*?<\/style>/",'',$words_list);
			$words_list = html_entity_decode(strip_tags($words_list), ENT_QUOTES, 'utf-8');
			if ($options_list[23]->option_value == 'true') {
				$words_list = preg_replace('/\S+\@\S+\.\S+/', ' ', $words_list);
			}
			if ($options_list[24]->option_value == 'true') {
				$words_list = preg_replace('/((http|https|ftp)\S+)/', '', $words_list);
				$words_list = preg_replace('/www\.\S+/', '', $words_list);
				$words_list = preg_replace('/([a-z1-3]+\.(com|ca))/i', ' ', $words_list);
			}
			//$words_list = htmlspecialchars_decode($words_list);
			$words_list = preg_replace("/[0-9]/", "", $words_list);
			$words_list = preg_replace("/[^a-zA-z'’`]/", " ", $words_list);
			$words_list = preg_replace('/\s+/', ' ', $words_list);
			$words_list = str_replace("\xA0", ' ',$words_list);
			$words_list = str_replace("\xC2", '',$words_list);
			$words_list = str_replace("&nbsp;", ' ',$words_list);
			$words_list = str_replace('/',' ',$words_list);
			$words_list = str_replace("-",' ',$words_list);
			$words_list = str_replace("|",' ',$words_list);
			$words_list = str_replace("@",' ',$words_list);
			$words_list = str_replace("&",' ',$words_list);
			$words_list = str_replace("#",' ',$words_list);
			$words_list = str_replace("+",' ',$words_list);
			$words_list = str_replace("*",'',$words_list);
			$words_list = str_replace("?",' ',$words_list);
			$words_list = str_replace("…",' ',$words_list);
			$words_list = str_replace(";",' ',$words_list);
			$words_list = str_replace("’","'",$words_list);
			$words_list = str_replace("`","'",$words_list);
			$words_list = preg_replace("/[’']s[^a-z]/i", '', $words_list);
			$words_list = preg_replace("/[^a-z]s[’']/i", 's', $words_list);
			$words_list = str_replace("s'",'s',$words_list);
			$words_list = str_replace(".",' ',$words_list);
			$words = explode(' ', $words_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						//Check if word already exists in the database for that page
						if ((strtoupper($word) != $word || $caps_check[0]->option_value == 'false') && $word != '') {
							if ($post_count <= $total_posts) {
							$word = addslashes($word);
							$wpdb->insert($table_name, array('word' => addslashes($word), 'page_name' => $post->post_title, 'page_type' => 'Contact Form 7'));
							} else {
								$word_count++;
							}
						}
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='post_count';");
		$post_count = $post_count + intval($counter[0]->option_value);
		if ($post_count > $total_posts) $post_count = $total_posts;
		$wpdb->update($options_table, array('option_value' => $post_count), array('option_name' => 'post_count'));
		if (!$is_running) {
			$wpdb->update($options_table, array('option_value' => 'false'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
			$end_time = time();
			$total_time = time_elapsed($end_time - $start_time);
			$wpdb->update($options_table, array('option_value' => $total_time), array('option_name' => 'last_scan_finished')); // Update the total time of the scan
		}
	}
	add_action ('admincheckcf7', 'check_cf7');

	function clear_results() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$wpdb->update($options_table, array('option_value' => '0'), array('option_name' => 'pro_word_count')); // Clear out the pro errors count
		$wpdb->update($options_table, array('option_value' => '0'), array('option_name' => 'total_word_count')); // Clear out the total word count
		$wpdb->update($options_table, array('option_value' => '0'), array('option_name' => 'page_count')); // Clear out the page count
		$wpdb->update($options_table, array('option_value' => '0'), array('option_name' => 'post_count')); // Clear out the post count
		$wpdb->update($options_table, array('option_value' => '0'), array('option_name' => 'media_count')); // Clear out the media count

		$wpdb->delete($table_name, array('ignore_word' => false));
	}
	
		//Main scanning function for the entire website
	function scan_site() {
		global $wpdb;
		global $pro_included;
		global $ent_included;
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		clear_results();
		$wpdb->update($options_table, array('option_value' => '0'), array('option_name' => 'pro_word_count')); // Clear out the pro errors count
		$wpdb->update($options_table, array('option_value' => '0'), array('option_name' => 'total_word_count')); // Clear out the total word count
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
		$start_time = time(); // Get the timestamp for start of the scan

		$settings = $wpdb->get_results('SELECT option_value FROM ' . $options_table); //4 = Pages, 5 = Posts, 6 = Theme, 7 = Menus
		if ($ent_included) {
		if ($settings[4]->option_value == 'true')
			check_pages_ent(true);
		if ($settings[5]->option_value =='true')
			check_posts_ent(true);
		if ($settings[7]->option_value =='true')
			check_menus_ent(true);
		if ($settings[12]->option_value =='true')
			check_page_title_ent(true);
		if ($settings[13]->option_value =='true')
			check_post_title_ent(true);
		if ($settings[14]->option_value =='true')
			check_post_tags_ent(true);
		if ($settings[15]->option_value =='true')
			check_post_categories_ent(true);
		if ($settings[16]->option_value =='true')
			check_yoast_ent(true);
		if ($settings[17]->option_value =='true')
			check_seo_titles_ent(true);
		if ($settings[18]->option_value =='true')
			check_page_slugs_ent(true);
		if ($settings[19]->option_value =='true')
			check_post_slugs_ent(true);
		if ($settings[30]->option_value =='true') {
			check_smart_slider_titles_ent();
			check_smart_slider_captions_ent();
			check_it_slider_captions_ent();
			check_it_slider_titles_ent();
			check_slider_captions_ent();
			check_slider_titles_ent();
		}
		if ($settings[31]->option_value =='true') {
			check_media_titles_ent();
			check_media_descriptions_ent();
			check_media_captions_ent();
			check_media_alt_ent();
		}
		check_cf7(true);
		} else {
		if ($settings[4]->option_value == 'true')
			check_pages(true);
		if ($settings[5]->option_value =='true')
			check_posts(true);
		if ($pro_included) {
		if ($settings[7]->option_value =='true')
			check_menus(true);
		if ($settings[12]->option_value =='true')
			check_page_title(true);
		if ($settings[13]->option_value =='true')
			check_post_title(true);
		if ($settings[14]->option_value =='true')
			check_post_tags(true);
		if ($settings[15]->option_value =='true')
			check_post_categories(true);
		if ($settings[16]->option_value =='true')
			check_yoast(true);
		if ($settings[17]->option_value =='true')
			check_seo_titles(true);
		if ($settings[18]->option_value =='true')
			check_page_slugs(true);
		if ($settings[19]->option_value =='true')
			check_post_slugs(true);
		} else {
			check_menus_free();
			check_page_title_free();
			check_post_title_free();
			check_post_tags_free();
			check_post_tags_desc_free();
			check_post_tags_slug_free();
			check_post_categories_free();
			check_post_categories_desc_free();
			check_post_categories_slug_free();
			check_yoast_free();
			check_seo_titles_free();
			check_page_slugs_free();
			check_post_slugs_free();
			check_smart_slider_titles_free();
			check_smart_slider_captions_free();
			check_it_slider_captions_free();
			check_it_slider_titles_free();
			check_slider_captions_free();
			check_slider_titles_free();
			check_media_titles_free();
			check_media_descriptions_free();
			check_media_captions_free();
			check_media_alt_free();
			check_media_free();
		}
		check_cf7(true);
		}
		$wpdb->update($options_table, array('option_value' => 'false'), array('option_name' => 'scan_in_progress')); // Flag that a scan has finished
		$end_time = time();
		$total_time = time_elapsed($end_time - $start_time);
		$wpdb->update($options_table, array('option_value' => $total_time), array('option_name' => 'last_scan_finished')); // Update the total time of the scan
	}

	function scan_site_event() {
		global $wpdb;
		global $pro_included;
		global $ent_included;
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		set_time_limit(600); // Set PHP timeout limit
		clear_results();
		$wpdb->update($options_table, array('option_value' => '0'), array('option_name' => 'pro_word_count')); // Clear out the pro errors count
		$wpdb->update($options_table, array('option_value' => '0'), array('option_name' => 'total_word_count')); // Clear out the total word count
		$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
		$start_time = time(); // Get the timestamp for start of the scan

		$settings = $wpdb->get_results('SELECT option_value FROM ' . $options_table); //4 = Pages, 5 = Posts, 6 = Theme, 7 = Menus
		
		if ($ent_included) {
		if ($settings[4]->option_value == 'true')
			check_pages_ent(true, null);
		if ($settings[5]->option_value =='true')
			check_posts_ent(true, null);
		if ($settings[7]->option_value =='true')
			check_menus_ent(true);
		if ($settings[12]->option_value =='true')
			check_page_title_ent(true);
		if ($settings[13]->option_value =='true')
			check_post_title_ent(true);
		if ($settings[14]->option_value =='true')
			check_post_tags_ent(true);
		if ($settings[15]->option_value =='true')
			check_post_categories_ent(true);
		if ($settings[16]->option_value =='true')
			check_yoast_ent(true);
		if ($settings[17]->option_value =='true')
			check_seo_titles_ent(true);
		if ($settings[18]->option_value =='true')
			check_page_slugs_ent(true);
		if ($settings[19]->option_value =='true')
			check_post_slugs_ent(true);
		if ($settings[30]->option_value =='true') {
			if (is_plugin_active('smart-slider-2/smart-slider-2.php')) {
				check_smart_slider_titles_ent(true);
				check_smart_slider_captions_ent(true);
			}
			if (is_plugin_active('slider-image/slider.php')) {
				check_it_slider_captions_ent(true);
				check_it_slider_titles_ent(true);
			}
			check_slider_captions_ent(true);
			check_slider_titles_ent(true);
		}
		if ($settings[31]->option_value =='true') {
			check_media_titles_ent(true);
			check_media_descriptions_ent(true);
			check_media_captions_ent(true);
			check_media_alt_ent(true);
		}
		if ($settings[38]->option_value =='true')
			check_post_tag_descriptions_ent(true);
		if ($settings[39]->option_value =='true')
			check_post_tag_slugs_ent(true);
		if ($settings[40]->option_value =='true')
			check_post_categories_description_ent(true);
		if ($settings[41]->option_value =='true')
			check_post_categories_slugs_ent(true);
		if ($settings[37]->option_value =='true')
			check_cf7(true);
		} else {
		if ($settings[4]->option_value == 'true')
			check_pages(true, null);
		if ($settings[5]->option_value =='true')
			check_posts(true, null);
		if ($pro_included) {
		if ($settings[7]->option_value =='true')
			check_menus(true);
		if ($settings[12]->option_value =='true')
			check_page_title(true);
		if ($settings[13]->option_value =='true')
			check_post_title(true);
		if ($settings[14]->option_value =='true')
			check_post_tags(true);
		if ($settings[15]->option_value =='true')
			check_post_categories(true);
		if ($settings[16]->option_value =='true')
			check_yoast(true);
		if ($settings[17]->option_value =='true')
			check_seo_titles(true);
		if ($settings[18]->option_value =='true')
			check_page_slugs(true);
		if ($settings[19]->option_value =='true')
			check_post_slugs(true);
		if ($settings[30]->option_value =='true') {
			if (is_plugin_active('smart-slider-2/smart-slider-2.php')) {
				check_smart_slider_titles(true);
				check_smart_slider_captions(true);
			}
			if (is_plugin_active('slider-image/slider.php')) {
				check_it_slider_captions(true);
				check_it_slider_titles(true);
			}
			check_slider_captions(true);
			check_slider_titles(true);
		}
		if ($settings[31]->option_value =='true') {
			check_media_titles(true);
			check_media_descriptions(true);
			check_media_captions(true);
			check_media_alt(true);
		}
		if ($settings[38]->option_value =='true')
			check_post_tag_descriptions(true);
		if ($settings[39]->option_value =='true')
			check_post_tag_slugs(true);
		if ($settings[40]->option_value =='true')
			check_post_categories_description(true);
		if ($settings[41]->option_value =='true')
			check_post_categories_slugs(true);
		} else {
			check_menus_free();
			check_page_title_free();
			check_post_title_free();
			check_post_tags_free();
			check_post_tags_desc_free();
			check_post_tags_slug_free();
			check_post_categories_free();
			check_post_categories_desc_free();
			check_post_categories_slug_free();
			check_yoast_free();
			check_seo_titles_free();
			check_page_slugs_free();
			check_post_slugs_free();
			check_smart_slider_titles_free();
			check_smart_slider_captions_free();
			check_it_slider_captions_free();
			check_it_slider_titles_free();
			check_slider_captions_free();
			check_slider_titles_free();
			check_media_titles_free();
			check_media_descriptions_free();
			check_media_captions_free();
			check_media_alt_free();
			check_media_free();
		}
		if ($settings[37]->option_value =='true')
			check_cf7(true);
		}
		if ($settings[0]->option_value == 'true')
			email_admin();
		$wpdb->update($options_table, array('option_value' => 'false'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
		$end_time = time();
		$total_time = time_elapsed($end_time - $start_time);
		$wpdb->update($options_table, array('option_value' => $total_time), array('option_name' => 'last_scan_finished')); // Update the total time of the scan
	}
	add_action ('adminscansite', 'scan_site_event');

	function time_elapsed($secs){
		$secs += 6;
	    $bit = array(
	        ' year'        => $secs / 31556926 % 12,
	        ' week'        => $secs / 604800 % 52,
	        ' day'        => $secs / 86400 % 7,
	        ' hour'        => $secs / 3600 % 24,
	        ' minute'    => $secs / 60 % 60,
	        ' second'    => $secs % 60
	        );
        
	    foreach($bit as $k => $v){
	        if($v > 1)$ret[] = $v . $k . 's';
	        if($v == 1)$ret[] = $v . $k;
	        }
	    array_splice($ret, count($ret)-1, 0, ' ');
	    $ret[] = '';
    
	    return join(' ', $ret);
	    }

	function send_test_email() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'spellcheck_options';
		$words_table = $wpdb->prefix . 'spellcheck_words';
		set_time_limit(600); // Set PHP timeout limit in case of large website

		$settings = $wpdb->get_results('SELECT option_value FROM ' . $table_name . ' WHERE option_name="email_address";');
		$words_list = $wpdb->get_results('SELECT word FROM ' . $words_table . ' WHERE ignore_word is false');
		
		$output = 'This is a test email sent from WP Spell Check on ' . get_option( 'blogname' );
		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
		$headers .= "From: " . get_option( 'admin_email' );

		$to_emails = explode(',', $settings[0]->option_value);
		$valid_email = false;
		foreach($to_emails as $email_test) {
			if (!filter_var($email_test, FILTER_VALIDATE_EMAIL) === false) {
				$valid_email = true;
			}
		}
		if (!$valid_email) {
			return 'Please enter a valid email address';
		}
		array_walk($to_emails, 'trim_value');

		if (wp_mail($to_emails, 'Test Email from WP Spell Check', $output, $headers)) {
			return "A test email has been sent";
		} else {
			return "An error has occurring in sending the test email";
		}
	}

	function email_admin() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'spellcheck_options';
		$words_table = $wpdb->prefix . 'spellcheck_words';
		set_time_limit(600); // Set PHP timeout limit in case of large website

		$settings = $wpdb->get_results('SELECT option_value FROM ' . $table_name . ' WHERE option_name="email_address";');
		$words_list = $wpdb->get_results('SELECT word FROM ' . $words_table . ' WHERE ignore_word is false');
		$login_url = wp_login_url();
		
		$output = 'Dear Admin, <br /><br />We have finished the scan of your website and detected ' . sizeof($words_list) . ' spelling errors. To view them you can <a href="' . $login_url . '">click here</a> to log into your website administrator panel';
		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
		$headers .= "From: " . get_option( 'admin_email' );

		$to_emails = explode(',', $settings[0]->option_value);
		array_walk($to_emails, 'trim_value');

		wp_mail($to_emails, 'Misspelled Words on ' . get_option( 'blogname' ), $output, $headers);
	}

	//Set up the request a feature window
	function show_feature_window() {
		echo "<div class='request-feature-container'>";
		echo "<div class='request-feature-popup' style='display: none;'>";
		echo "<a href='' class='close-popup'>X</a>";
		echo "<img src='" . plugin_dir_url( __FILE__ ) . "images/logo.png' alt='WP Spell Check' /><br />";
		echo "<h3>We love hearing from you</h3>";
		echo "<p>Please leave your idea/feature request to make the WP Spell Check plugin better</p>";
		echo "<a href='https://www.wpspellcheck.com/feature-request' target='_blank'><button>Send Feature Request</button></a>";
		echo "<p>Please note: Support requests will not be handled through this form</p>";
		echo "</div>";
		echo "<div class='request-feature'><a href='' class='request-feature-link'>Submit a Feature Request</a></div>";
		echo"</div>";
	}
	
	function check_menus_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'posts';
		$words_table = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$word_count = 0;
		$total_words = 0;
		set_time_limit(600); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$wpdb->delete($words_table, array('page_type' => 'Menu Item')); //Clean out menu entries before rechecking it all

		$menus = $wpdb->get_results('SELECT post_title FROM ' . $table_name . ' WHERE post_type ="nav_menu_item";');
		
		foreach($menus as $menu) {
			$word_list = html_entity_decode(strip_tags($menu->post_title), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$words = explode(' ', $word_list);
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$word_check = $wpdb->get_results("SELECT word FROM " . $words_table . " WHERE word='".$word."' AND page_name='Menu: " . $menu_items->title . "'");
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($word_check) < 1 && sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}	
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	add_action('admincheckmenus', 'check_menus');

	function check_page_title_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		$word_count = 0;
		$page_ids = get_all_page_ids();
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($table_name, array('page_type' => 'Page Title')); //Clean out entries before rechecking it all
		$max_pages = PHP_INT_MAX;
		if (sizeof($page_ids) < PHP_INT_MAX) $max_pages = sizeof($page_ids);

		for ($x=0; $x<$max_pages; $x++) {
			$words = array();
			$page = get_post( $page_ids[$x] );
			$word_list = html_entity_decode(strip_tags($page->post_title), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$words = explode(' ', $word_list);

			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}
			}	
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	add_action('admincheckpagetitles', 'check_page_title');

	function check_post_title_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($table_name, array('page_type' => 'Post Title')); //Clean out entries before rechecking it all

		$post_types = get_post_types();
		$post_type_list = array();
		foreach ($post_types as $type) {
			if ($type != 'revision' && $type != 'page' && $type != 'nav_menu_item' && $type != 'optionsframework' && $type != 'slider' && $type != 'attachment')
				array_push($post_type_list, $type);
		}

		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => $post_type_list, 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {
			$word_list = html_entity_decode(strip_tags($post->post_title), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$words = explode(' ', $word_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	add_action('admincheckposttitles', 'check_post_title');

function check_post_tags_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($table_name, array('page_type' => 'Post Tag')); //Clean out entries before rechecking it all
		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'post', 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {

			$tags = get_the_tags($post->ID);
			//print_r($tags);
			foreach ($tags as $tag) {
			$words = array();
			$words = explode(' ', strip_tags(html_entity_decode($tag->name)));
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	add_action('admincheckposttags', 'check_post_tags');
	
function check_post_tags_desc_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($table_name, array('page_type' => 'Post Tag')); //Clean out entries before rechecking it all
		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'post', 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {

			$tags = get_the_tags($post->ID);
			//print_r($tags);
			foreach ($tags as $tag) {
			$words = array();
			$words = explode(' ', strip_tags(html_entity_decode($tag->description)));
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	
function check_post_tags_slug_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($table_name, array('page_type' => 'Post Tag')); //Clean out entries before rechecking it all
		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'post', 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {

			$tags = get_the_tags($post->ID);
			//print_r($tags);
			foreach ($tags as $tag) {
			$words = array();
			$words = explode('-', strip_tags(html_entity_decode($tag->slug)));
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

	function check_post_categories_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($table_name, array('page_type' => 'Post Category')); //Clean out entries before rechecking it all
		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'post', 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {
			$cats = get_the_category($post->ID);
			foreach ($cats as $cat) {
			$words = array();
			$words = explode(' ', strip_tags(html_entity_decode($cat->name)));
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	add_action('admincheckcategories', 'check_post_categories');
	
		function check_post_categories_desc_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($table_name, array('page_type' => 'Post Category')); //Clean out entries before rechecking it all
		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'post', 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {
			$cats = get_the_category($post->ID);
			foreach ($cats as $cat) {
			$words = array();
			$words = explode(' ', strip_tags(html_entity_decode($cat->description)));
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	
	function check_post_categories_slug_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($table_name, array('page_type' => 'Post Category')); //Clean out entries before rechecking it all
		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'post', 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {
			$cats = get_the_category($post->ID);
			foreach ($cats as $cat) {
			$words = array();
			$words = explode('-', strip_tags(html_entity_decode($cat->slug)));
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

	function check_yoast_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'postmeta';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		$words_table = $wpdb->prefix . 'spellcheck_words';
		$posts_table = $wpdb->prefix . 'posts';
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$options_list = $wpdb->get_results("SELECT option_value FROM $options_table");

		//$wpdb->delete($words_table, array('page_type' => 'Yoast SEO Description')); //Clean out entries before rechecking it all
		//$wpdb->delete($words_table, array('page_type' => 'All in One SEO Description'));
		//$wpdb->delete($words_table, array('page_type' => 'Ultimate SEO Description'));
		//$wpdb->delete($words_table, array('page_type' => 'SEO Description'));

		$results = $wpdb->get_results('SELECT post_id, meta_value, meta_key FROM ' . $table_name . ' WHERE meta_key="_yoast_wpseo_metadesc" OR meta_key="_aioseop_description" OR meta_key="_su_description" LIMIT 50000');

		foreach($results as $desc) {
			$page_results = $wpdb->get_results('SELECT post_title FROM ' . $posts_table . ' WHERE ID=' . $desc->post_id);
			$desc_type = $desc->meta_key;
			$desc = html_entity_decode(strip_tags($desc->meta_value), ENT_QUOTES, 'utf-8');
			if ($options_list[23]->option_value == 'true') {
				$desc = preg_replace('/\S+\@\S+\.\S+/', '', $desc);
			}
			if ($options_list[24]->option_value == 'true') {
				$desc = preg_replace('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', '', $desc);
			}
			$desc = preg_replace("/[0-9]/", " ", $desc);
			$desc = preg_replace('/\s+/', ' ', $desc);
			$desc = str_replace("\xA0", ' ',$desc);
			$desc = str_replace("\xC2", '',$desc);
			$desc = str_replace("&nbsp;", ' ',$desc);
			$desc = str_replace("/",' ',$desc);
			$desc = str_replace("-",' ',$desc);
			$desc = str_replace("@",' ',$desc);
			$desc = str_replace("|",' ',$desc);
			$desc = str_replace("&",' ',$desc);
			$desc = str_replace("*",' ',$desc);
			$desc = str_replace("+",' ',$desc);
			$desc = str_replace("#",' ',$desc);
			$desc = str_replace("?",' ',$desc);
			$desc = str_replace("…",'',$desc);
			$desc = str_replace(";",' ',$desc);
			$desc = str_replace("'s",'',$desc);
			$desc = str_replace("’s",'',$desc);
			$desc = str_replace("’","'",$desc);
			$desc = str_replace("`","'",$desc);
			$desc = str_replace("s'",'s',$desc);
			$desc = str_replace(".",' ',$desc);
			$words = explode(' ', $desc);
			$words = explode(' ', $desc);

			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	add_action('admincheckseodesc', 'check_yoast');

	function check_seo_titles_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'postmeta';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		$words_table = $wpdb->prefix . 'spellcheck_words';
		$posts_table = $wpdb->prefix . 'posts';
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($words_table, array('page_type' => 'Yoast SEO Title')); //Clean out entries before rechecking it all
		//$wpdb->delete($words_table, array('page_type' => 'All in One SEO Title'));
		//$wpdb->delete($words_table, array('page_type' => 'Ultimate SEO Title'));
		//$wpdb->delete($words_table, array('page_type' => 'SEO Title'));

		$results = $wpdb->get_results('SELECT post_id, meta_value, meta_key FROM ' . $table_name . ' WHERE meta_key="_yoast_wpseo_title" OR meta_key="_aioseop_title" OR meta_key="_su_title" LIMIT 50000');

		foreach($results as $desc) {
			$page_results = $wpdb->get_results('SELECT post_title FROM ' . $posts_table . ' WHERE ID=' . $desc->post_id);
			$desc_type = $desc->meta_key;
			$desc = html_entity_decode(strip_tags($desc->meta_value), ENT_QUOTES, 'utf-8');
			$desc = preg_replace("/[0-9]/", " ", $desc);
			$desc = preg_replace('/\s+/', ' ', $desc);
			$desc = str_replace("\xA0", ' ',$desc);
			$desc = str_replace("\xC2", '',$desc);
			$desc = str_replace("&nbsp;", ' ',$desc);
			$desc = str_replace("/",' ',$desc);
			$desc = str_replace("-",' ',$desc);
			$desc = str_replace("@",' ',$desc);
			$desc = str_replace("|",' ',$desc);
			$desc = str_replace("&",' ',$desc);
			$desc = str_replace("*",' ',$desc);
			$desc = str_replace("+",' ',$desc);
			$desc = str_replace("#",' ',$desc);
			$desc = str_replace("?",' ',$desc);
			$desc = str_replace("…",'',$desc);
			$desc = str_replace(";",' ',$desc);
			$desc = str_replace("'s",'',$desc);
			$desc = str_replace("’s",'',$desc);
			$desc = str_replace("’","'",$desc);
			$desc = str_replace("`","'",$desc);
			$desc = str_replace("s'",'s',$desc);
			$desc = str_replace(".",' ',$desc);
			$words = explode(' ', $desc);

			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	add_action('admincheckseotitles', 'check_seo_titles');

function check_page_slugs_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		$words_table = $wpdb->prefix . 'spellcheck_words';
		$posts_table = $wpdb->prefix . 'posts';
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($words_table, array('page_type' => 'Page Slug')); //Clean out entries before rechecking it all
		$results = $wpdb->get_results('SELECT post_name, post_title FROM ' . $posts_table . ' WHERE post_type="page" LIMIT 50000');

		foreach($results as $desc) {
			$desc_title = $desc->post_title;
			$desc = html_entity_decode(strip_tags($desc->post_name), ENT_QUOTES, 'utf-8');
			$words = explode('-', $desc);

			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		//$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	add_action('admincheckpageslugs', 'check_page_slugs');

	function check_post_slugs_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		$words_table = $wpdb->prefix . 'spellcheck_words';
		$posts_table = $wpdb->prefix . 'posts';
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		//$wpdb->delete($words_table, array('page_type' => 'Post Slug')); //Clean out entries before rechecking it all
		$results = $wpdb->get_results('SELECT post_name, post_title FROM ' . $posts_table . ' WHERE post_type="post" LIMIT 50000');

		foreach($results as $desc) {
			$desc_title = $desc->post_title;
			$desc = html_entity_decode(strip_tags($desc->post_name), ENT_QUOTES, 'utf-8');
			$words = explode('-', $desc);

			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		//$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}
	add_action('admincheckpostslugs', 'check_post_slugs');

	function check_slider_titles_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'slider', 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {
			$word_list = html_entity_decode(strip_tags($post->post_title), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$words = explode(' ', $word_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

	function check_slider_captions_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$posts_list = get_posts(array('posts_per_page' => 500, 'post_type' => 'slider', 'post_status' => array('publish', 'draft')));

		foreach ($posts_list as $post) {
			$word_list = get_post_meta ($post->ID, 'my_slider_caption', true );
			$word_list = html_entity_decode(strip_tags($word_list), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$word_list = str_replace("<",' ',$word_list);
			$word_list = str_replace(">",' ',$word_list);
			$words = explode(' ', $word_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

/* Slider Plugins */

function check_it_slider_titles_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'wp_huge_itslider_images';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$posts_list = $wpdb->get_results("SELECT sl_stitle FROM $table_name");

		foreach ($posts_list as $post) {
			$word_list = html_entity_decode(strip_tags($post->sl_stitle), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$words = explode(' ', $word_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
				}	
			}
		}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

	function check_it_slider_captions_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'wp_huge_itslider_images';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$posts_list = $wpdb->get_results("SELECT sl_sdesc, sl_stitle FROM $table_name");

		foreach ($posts_list as $post) {
			$word_list = html_entity_decode(strip_tags($post->sl_sdesc), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$word_list = str_replace("<",' ',$word_list);
			$word_list = str_replace(">",' ',$word_list);
			$words = explode(' ', $word_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

/* Smart Slider 2 */

function check_smart_slider_titles_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'wp_nextend_smartslider_slides';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$posts_list = $wpdb->get_results("SELECT title FROM $table_name");

		foreach ($posts_list as $post) {
			$word_list = html_entity_decode(strip_tags($post->title), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$words = explode(' ', $word_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

	function check_smart_slider_captions_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'wp_nextend_smartslider_slides';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$posts_list = $wpdb->get_results("SELECT description, title FROM $table_name");

		foreach ($posts_list as $post) {
			$word_list = html_entity_decode(strip_tags($post->description), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$word_list = str_replace("<",' ',$word_list);
			$word_list = str_replace(">",' ',$word_list);
			$words = explode(' ', $word_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}


function check_media_titles_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$media_count = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'attachment'));

		foreach ($posts_list as $post) {
			$media_count++;
			$word_list = html_entity_decode(strip_tags($post->post_title), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$word_list = str_replace("_",' ',$word_list);
			$words = explode(' ', $word_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

	function check_media_descriptions_free() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$ignore_table = $wpdb->prefix . 'spellcheck_ignore';
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		$total_words = 0;
		$media_count = 0;
		$word_count = 0;
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$ignore_posts = $wpdb->get_results('SELECT keyword FROM ' . $ignore_table . ' WHERE type="page";');
		$options_list = $wpdb->get_results("SELECT option_value FROM $options_table");

		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'attachment'));

		foreach ($posts_list as $post) {
			$media_count++;
			$ignore_flag = 'false';
			foreach($ignore_posts as $ignore_check) {
				if (strtoupper(trim($post->post_title)) == strtoupper(trim($ignore_check->keyword))) {
					$ignore_flag = 'true';
				}
			}
			if ($ignore_flag == 'true') { continue; }
			$words_list = $post->post_content;
			$words_list = preg_replace("(\[.*?\])",'',$words_list);
			//$words_list = preg_replace("(\<.*?\>)",'',$words_list);
			$words_list = preg_replace("/<style>\s\S*?<\/style>/",'',$words_list);
			$words_list = html_entity_decode(strip_tags($words_list), ENT_QUOTES, 'utf-8');
			if ($options_list[23]->option_value == 'true') {
				$words_list = preg_replace('/\S+\@\S+\.\S+/', '', $words_list);
			}
			if ($options_list[24]->option_value == 'true') {
				$words_list = preg_replace('/http|https|ftp\S+/', '', $words_list);
				$words_list = preg_replace('/www\.\S+/', '', $words_list);
			}
			//$words_list = htmlspecialchars_decode($words_list);
			$words_list = preg_replace("/[0-9]/", "", $words_list);
			$words_list = preg_replace("/[^a-zA-z'’`]/", " ", $words_list);
			$words_list = preg_replace('/\s+/', ' ', $words_list);
			$words_list = str_replace("\xA0", ' ',$words_list);
			$words_list = str_replace("\xC2", '',$words_list);
			$words_list = str_replace("&nbsp;", ' ',$words_list);
			$words_list = str_replace('/',' ',$words_list);
			$words_list = str_replace("-",' ',$words_list);
			$words_list = str_replace("|",' ',$words_list);
			$words_list = str_replace("@",' ',$words_list);
			$words_list = str_replace("&",' ',$words_list);
			$words_list = str_replace("#",' ',$words_list);
			$words_list = str_replace("+",' ',$words_list);
			$words_list = str_replace("*",'',$words_list);
			$words_list = str_replace("?",' ',$words_list);
			$words_list = str_replace("…",' ',$words_list);
			$words_list = str_replace(";",' ',$words_list);
			$words_list = str_replace("’","'",$words_list);
			$words_list = str_replace("`","'",$words_list);
			$words_list = str_replace("'s",'',$words_list);
			$words_list = str_replace("’s",'',$words_list);
			$words_list = str_replace("s'",'s',$words_list);
			$words_list = str_replace(".",' ',$words_list);
			$words = explode(' ', $words_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

	function check_media_captions_free() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$ignore_table = $wpdb->prefix . 'spellcheck_ignore';
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		$total_words = 0;
		$media_count = 0;
		$word_count = 0;
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$ignore_posts = $wpdb->get_results('SELECT keyword FROM ' . $ignore_table . ' WHERE type="page";');
		$options_list = $wpdb->get_results("SELECT option_value FROM $options_table");

		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'attachment'));

		foreach ($posts_list as $post) {
			$media_count++;
			$ignore_flag = 'false';
			foreach($ignore_posts as $ignore_check) {
				if (strtoupper(trim($post->post_title)) == strtoupper(trim($ignore_check->keyword))) {
					$ignore_flag = 'true';
				}
			}
			if ($ignore_flag == 'true') { continue; }
			$words_list = $post->post_excerpt;
			$words_list = preg_replace("(\[.*?\])",'',$words_list);
			//$words_list = preg_replace("(\<.*?\>)",'',$words_list);
			$words_list = preg_replace("/<style>\s\S*?<\/style>/",'',$words_list);
			$words_list = html_entity_decode(strip_tags($words_list), ENT_QUOTES, 'utf-8');
			if ($options_list[23]->option_value == 'true') {
				$words_list = preg_replace('/\S+\@\S+\.\S+/', '', $words_list);
			}
			if ($options_list[24]->option_value == 'true') {
				$words_list = preg_replace('/http|https|ftp\S+/', '', $words_list);
				$words_list = preg_replace('/www\.\S+/', '', $words_list);
			}
			//$words_list = htmlspecialchars_decode($words_list);
			$words_list = preg_replace("/[0-9]/", "", $words_list);
			$words_list = preg_replace("/[^a-zA-z'’`]/", " ", $words_list);
			$words_list = preg_replace('/\s+/', ' ', $words_list);
			$words_list = str_replace("\xA0", ' ',$words_list);
			$words_list = str_replace("\xC2", '',$words_list);
			$words_list = str_replace("&nbsp;", ' ',$words_list);
			$words_list = str_replace('/',' ',$words_list);
			$words_list = str_replace("-",' ',$words_list);
			$words_list = str_replace("|",' ',$words_list);
			$words_list = str_replace("@",' ',$words_list);
			$words_list = str_replace("&",' ',$words_list);
			$words_list = str_replace("#",' ',$words_list);
			$words_list = str_replace("+",' ',$words_list);
			$words_list = str_replace("*",'',$words_list);
			$words_list = str_replace("?",' ',$words_list);
			$words_list = str_replace("…",' ',$words_list);
			$words_list = str_replace(";",' ',$words_list);
			$words_list = str_replace("’","'",$words_list);
			$words_list = str_replace("`","'",$words_list);
			$words_list = str_replace("'s",'',$words_list);
			$words_list = str_replace("’s",'',$words_list);
			$words_list = str_replace("s'",'s',$words_list);
			$words_list = str_replace(".",' ',$words_list);
			$words = explode(' ', $words_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

	function check_media_alt_free() {
		global $wpdb;
		$dict_table = $wpdb->prefix . 'spellcheck_dictionary';
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		$total_words = 0;
		$media_count = 0;
		$word_count = 0;
		set_time_limit(6000); // Set PHP timeout limit in case of large website
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}

		$posts_list = get_posts(array('posts_per_page' => PHP_INT_MAX, 'post_type' => 'attachment'));

		foreach ($posts_list as $post) {
			$media_count++;
			$word_list = get_post_meta ($post->ID, '_wp_attachment_image_alt', true );
			$word_list = html_entity_decode(strip_tags($word_list), ENT_QUOTES, 'utf-8');
			$word_list = preg_replace("/[0-9]/", " ", $word_list);
			$word_list = preg_replace('/\s+/', ' ', $word_list);
			$word_list = str_replace("\xA0", ' ',$word_list);
			$word_list = str_replace("\xC2", '',$word_list);
			$word_list = str_replace("&nbsp;", ' ',$word_list);
			$word_list = str_replace("/",' ',$word_list);
			$word_list = str_replace("-",' ',$word_list);
			$word_list = str_replace("@",' ',$word_list);
			$word_list = str_replace("|",' ',$word_list);
			$word_list = str_replace("&",' ',$word_list);
			$word_list = str_replace("*",' ',$word_list);
			$word_list = str_replace("+",' ',$word_list);
			$word_list = str_replace("#",' ',$word_list);
			$word_list = str_replace("?",' ',$word_list);
			$word_list = str_replace("…",'',$word_list);
			$word_list = str_replace(";",' ',$word_list);
			$word_list = str_replace("'s",'',$word_list);
			$word_list = str_replace("’s",'',$word_list);
			$word_list = str_replace("’","'",$word_list);
			$word_list = str_replace("`","'",$word_list);
			$word_list = str_replace("s'",'s',$word_list);
			$word_list = str_replace(".",' ',$word_list);
			$word_list = str_replace("<",' ',$word_list);
			$word_list = str_replace(">",' ',$word_list);
			$words = explode(' ', $word_list);
		
			foreach($words as $word) {
				$total_words++;
				$word = str_replace(' ', '', $word);
				$word = str_replace('=', '', $word);
				$word = str_replace(',', '', $word);
				$word = trim($word, "?!.,'()`”:“@$#-%\=/");
				$word = trim($word, '"');
				$word = trim($word);
				$word = preg_replace("/[0-9]/", "", $word);
				$ignore_check = str_replace("'", "\'", $word);
				$ignore_word = $wpdb->get_results("SELECT word FROM $table_name WHERE word='" . $ignore_check . "' AND ignore_word = true");
				if ($haystack[strtoupper($word)] != 1 && sizeof($ignore_word) < 1) {
					$dict_word = str_replace("'", "\'", $word);
					$dict_check = $wpdb->get_results("SELECT word FROM " . $dict_table . " WHERE word='".$dict_word."';");
					$caps_check = $wpdb->get_results("SELECT option_name, option_value FROM " . $options_table . " WHERE option_name='ignore_caps';");

					if (sizeof($dict_check) < 1) {
						$word_count++;
					}
				}	
			}
		}
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='pro_word_count';");
		$word_count = $word_count + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $word_count), array('option_name' => 'pro_word_count'));
		$counter = $wpdb->get_results("SELECT option_value FROM $options_table WHERE option_name ='total_word_count';");
		$total_words = $total_words + intval($counter[0]->option_value);
		$wpdb->update($options_table, array('option_value' => $total_words), array('option_name' => 'total_word_count'));
	}

	function check_media_free($is_running = false) {
		set_time_limit(6000);
		global $wpdb;
		global $pro_included;
		global $ent_included;
		$table_name = $wpdb->prefix . 'spellcheck_words';
		$options_table = $wpdb->prefix . 'spellcheck_options';
		//Set up dictionary file
		$language_setting = $wpdb->get_results('SELECT option_value from ' . $options_table . ' WHERE option_name="language_setting";');
		$loc = dirname(__FILE__) . "/dict/" . $language_setting[0]->option_value . ".pws";
		$file = fopen($loc, 'r');
		$contents = fread($file,filesize($loc));
		fclose($file);
	
		$contents = str_replace("\r\n", "\n", $contents);
		$dict_list = explode("\n", $contents);

		foreach ($dict_list as $value) {
			$haystack[strtoupper($value)] = 1;
		}
		if (!$is_running) {
			$wpdb->update($options_table, array('option_value' => 'true'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
			$start_time = time();
		}

		check_media_titles_free(true);
		check_media_descriptions_free(true);
		check_media_captions_free(true);
		check_media_alt_free(true);

		if (!$is_running) {
			$wpdb->update($options_table, array('option_value' => 'false'), array('option_name' => 'scan_in_progress')); // Flag that a scan is in progress
			$end_time = time();
			$total_time = time_elapsed($end_time - $start_time);
			$wpdb->update($options_table, array('option_value' => $total_time), array('option_name' => 'last_scan_finished')); // Update the total time of the scan
		}
	}

function create_pages_bulk() {
for($x = 0; $x++; $x <= 10000) {
	// Create post object
	$my_post = array(
	  'post_title'    => "Page-" . $x,
	  'post_content'  => "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas condimentum vitae lorem hendrerit mattis.",
	  'post_type'  => "page"
	);
	 
	// Insert the post into the database
	wp_insert_post( $my_post );
}

}
add_action ('admincreatepages', 'create_pages_bulk');
?>