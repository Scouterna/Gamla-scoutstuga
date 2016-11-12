<?php
/**
 * @package Rent_Objects
 * @author Puggan
 * @version 0.0.1
 * @filelocation: wp/wp-content/plugins/rent_object/rent_object.php
 */
/*
Plugin Name: Rent_Objects
Description: Tool for showing rentable objects
Version: 0.0.1
Author: Puggan
Author URI: https://scoutstuga.se/
*/

DEFINE("RENT_OBJECT_PLUGIN_VERSION",'0.0.1');
DEFINE("RENT_OBJECT_USER_CAP", "edit_posts");
DEFINE("RENT_OBJECT_ADMIN_CAP", "edit_others_posts");

add_action("admin_menu", array("rent_object_holder", "init_admin"));
add_action("wp", array("rent_object_holder", "init_wp"));
add_action('admin_post_nopriv_add_rent_object', array("rent_object_holder", 'add_new_user') );
add_action('admin_post_add_rent_object', array("rent_object_holder", 'add_new_user') );

// make sure we can send redirect-headers
if(ob_get_level() < 1)
{
	ob_start();
}

class rent_object_holder
{
	public $admin_user = FALSE;

	static function init_admin()
	{
		$GLOBALS["rent_object_holder"] = new rent_object_holder(TRUE);
	}

	static function init_wp()
	{
		$GLOBALS["rent_object_holder"] = new rent_object_holder(FALSE);
	}

	function __construct($admin)
	{
		if($admin)
		{
			$this->admin_user = current_user_can(RENT_OBJECT_ADMIN_CAP);
			if($this->admin_user)
			{
				add_object_page("Anläggningar", "Stugor", RENT_OBJECT_ADMIN_CAP, "rent_objects", array($this, "rent_object_admin_page"));
			}
			else
			{
				add_object_page("Anläggningar", "Stugor", RENT_OBJECT_USER_CAP, "rent_objects", array($this, "rent_object_admin_page"));
			}
			add_action("admin_enqueue_scripts", array($this, "rent_object_css_admin"));
		}
		else
		{
			add_action("wp_enqueue_scripts", array($this, "rent_object_css_and_script"));
			add_shortcode('rent_object', array($this, "shortcode_stuga"));
			add_shortcode('stuga', array($this, "shortcode_stuga"));
			add_shortcode('Stuga', array($this, "shortcode_stuga"));
			add_shortcode('stugor', array($this, "shortcode_stuga"));
			add_shortcode('Stugor', array($this, "shortcode_stuga"));
		}
	}

	function rent_object_css_and_script()
	{
		wp_register_style('rent_object', plugin_dir_url( __FILE__) . 'rent_object.css', FALSE, '1.0.0');
		wp_enqueue_style('rent_object' );

// 		wp_register_script('rent_object_map', plugin_dir_url( __FILE__) . 'map.js', FALSE, '1.0.0');
		wp_enqueue_script('rent_object_map');
	}

	function rent_object_css_admin()
	{
		wp_register_style( 'rent_object_css_admin', plugin_dir_url( __FILE__) . 'rent_object.admin.css', false, '1.0.0' );
		wp_enqueue_style( 'rent_object_css_admin' );
	}

	function rent_object_admin_page()
	{
		global $wpdb;

		echo '<div class="form-wrap">' . PHP_EOL;
		echo '<form action="#" method="post" id="rent_object_form">' . PHP_EOL;
		echo '<div id="poststuff">' . PHP_EOL;
		
		// wordpress uses magic quotes, even if its depricated :-( remove!!!
		$postdata = stripslashes_deep($_POST);
		if(isset($postdata['publish']) OR isset($postdata['unpublish']))
		{
			$postdata['save'] = TRUE;
			$postdata['object_status'] = (int) isset($postdata['publish']);
		}
		if(isset($postdata['save']) AND isset($postdata['before']))
		{
			$user_id = get_current_user_id();
			$old_rent_object = json_decode($postdata['before'], TRUE);
			
			// update (not add)
			if(isset($old_rent_object['rent_object_id']) AND (int) $old_rent_object['rent_object_id'])
			{
				$id = (int) $old_rent_object['rent_object_id'];
				$allowed = $this->check_permission($id);
				if(!$allowed)
				{
					$id = 0;
				}
			}
			else
			{
				$wpdb->insert("{$wpdb->prefix}rent_object", array('name' => $postdata['name']));
				$id = $wpdb->insert_id;

				// add permission
				$wpdb->insert(
					"{$wpdb->prefix}rent_object_permissions",
					array(
						'user_id' => $user->ID,
						'rent_object_id' => $id,
					),
					array('%d', '%d')
				);
				$allowed = TRUE;
			}
			$db_rent_object = $wpdb->get_row("SELECT rent_object.* FROM {$wpdb->prefix}rent_object AS rent_object WHERE rent_object_id = {$id}", ARRAY_A);
			
			$rent_object_updates = array();
			foreach($db_rent_object as $column => $db_value)
			{
				if(isset($postdata[$column]))
				{
					$postdata[$column] = trim($postdata[$column]);
					if(isset($old_rent_object[$column]) AND $postdata[$column] == $old_rent_object[$column])
					{
						continue;
					}
					if($postdata[$column] == $db_value)
					{
						continue;
					}

					$rent_object_updates[$column] = $postdata[$column];
				}
			}
			
			$db_rent_object['options'] = $this->object_settings($id);
			
			$rent_option_updates = array();
			foreach($this->object_settings_names() as $option_id => $option_name)
			{
				if(isset($postdata['rent_object_option'][$option_id]))
				{
					if(isset($old_rent_object['options'][$option_id]) AND $postdata['rent_object_option'][$option_id] == $old_rent_object['options'][$option_id])
					{
						continue;
					}
					if(isset($db_rent_object['options'][$option_id]) AND $postdata['rent_object_option'][$option_id] == $db_rent_object['options'][$option_id])
					{
						continue;
					}
					$rent_option_updates[$option_id] = ($postdata['rent_object_option'][$option_id] == '' ? NULL : $postdata['rent_object_option'][$option_id]);
				}
			}
			
			$result = FALSE;
			if($allowed AND $rent_option_updates)
			{
				foreach($rent_option_updates as $option_id => $option_value)
				{
					if(is_null($option_value))
					{
						$result = $wpdb->delete(
							"{$wpdb->prefix}rent_object_settings", 
							array(
								'rent_object_id' => $id,
								'rent_object_settings_name_id' => $option_id,
							)
						);
					}
					else
					{
						$result = $wpdb->replace(
							"{$wpdb->prefix}rent_object_settings", 
							array(
								'rent_object_id' => $id,
								'rent_object_settings_name_id' => $option_id,
								'option_value' => $option_value, 
								'user_id' => $user_id, 
							)
						);
					}
				}
			}
			
			if($allowed AND $rent_object_updates)
			{
				$rent_object_updates['user_id'] = $user_id;
				$result = $wpdb->update("{$wpdb->prefix}rent_object", $rent_object_updates, array('rent_object_id' => $id));

				if($result)
				{
					
				}
				else
				{
					
				}
			}
		}
		else if(isset($_GET['id']))
		{
			$rent_object = NULL;
			$id = (int) $_GET['id'];
			if($id)
			{
				$rent_object = $wpdb->get_row("SELECT rent_object.* FROM {$wpdb->prefix}rent_object AS rent_object WHERE rent_object_id = {$id}", ARRAY_A);
			}

			if(!$rent_object) $rent_object = array();

			$rent_object += array(
				'rent_object_id' => 0,
				'name' => '(missing object)',
			);
			if($rent_object['rent_object_id'])
			{
				if(!$this->check_permission($id))
				{
					$id = 0;
					$rent_object = array(
						'rent_object_id' => 0,
						'name' => '(Permission denied)',
					);
				}
			}

			$rent_object['options'] = $this->object_settings($rent_object['rent_object_id']);

			printf('<input type="hidden" name="before" value="%s" />' . PHP_EOL, htmlentities(json_encode($rent_object)));
			echo '<div class="postbox">' . PHP_EOL;

			if($rent_object['rent_object_id'])
			{
				printf('<h2 class="hndle ui-sortable-handle">Uppdatera %s</h2>' . PHP_EOL, htmlentities($rent_object['name']));
			}
			else
			{
				echo '<h2 class="hndle ui-sortable-handle">Lägg till anläggning.</h2>' . PHP_EOL;
				$rent_object['name'] = '';
			}
			echo '<div class="inside">' . PHP_EOL;

			// Name
			echo '<div class="form-field form-required term-name-wrap">' . PHP_EOL;
			echo '<label for="name">Namn</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="name" name="name">' . PHP_EOL, htmlentities($rent_object['name']));
			echo '<p>Namnet på stugan/anläggningen/platsen.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// Organistation
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="rent_organisation_id">Organistation</label>' . PHP_EOL;
// 			printf('<select aria-required="true" id="rent_organisation_id" name="rent_organisation_id"><option value="%d">%s</option></select>' . PHP_EOL, $rent_object['rent_organisation_id'], htmlentities($rent_object['organisation_name'] ?: 'Organistation ' . $rent_object['rent_organisation_id']));
			echo '<select aria-required="true" id="rent_organisation_id" name="rent_organisation_id">' . PHP_EOL;
			echo '<option value="">-- ' . htmlentities('Välj Organistation') . ' --</option>' . PHP_EOL;
			foreach($wpdb->get_results("SELECT rent_organisation_id, organisation_name FROM {$wpdb->prefix}rent_organisations AS rent_organisations", ARRAY_A) as $row)
			{
				echo '<option value="' . htmlentities($row['rent_organisation_id']) . '"' . ($row['rent_organisation_id'] == $rent_object['rent_organisation_id'] ? ' selected="selected"': '') . '>' . htmlentities($row['organisation_name']) . '</option>' . PHP_EOL;
			}
			echo '</select>' . PHP_EOL;
// 			$rent_object['rent_organisation_id'], htmlentities($rent_object['organisation_name'] ?: 'Organistation ' . $rent_object['rent_organisation_id']));
			echo '<p>Organistation som har stugan/anläggningen/platsen.</p>' . PHP_EOL;
			echo '<p><b>TODO</b>: Add a new</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// Anläggningstyp
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="rent_object_type_id">Anläggningstyp</label>' . PHP_EOL;
			echo '<select aria-required="true" id="rent_object_type_id" name="rent_object_type_id">' . PHP_EOL;
// 			, $rent_object['rent_object_type_id'], htmlentities($rent_object['organisation_name'] ?: 'Typ ' . $rent_object['rent_object_type_id']));
			echo '<option value="">-- ' . htmlentities('Välj Typ') . ' --</option>' . PHP_EOL;
			foreach($wpdb->get_results("SELECT rent_object_type_id, type_name FROM {$wpdb->prefix}rent_object_types AS rent_object_types", ARRAY_A) as $row)
			{
				echo '<option value="' . htmlentities($row['rent_object_type_id']) . '"' . ($row['rent_object_type_id'] == $rent_object['rent_object_type_id'] ? ' selected="selected"': '') . '>' . htmlentities($row['type_name']) . '</option>' . PHP_EOL;
			}
			echo '</select>' . PHP_EOL;
			echo '<p>Typ av anläggning.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// city
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="city">Stad</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="city" name="city">' . PHP_EOL, htmlentities($rent_object['city']));
			echo '<p>Stad/ort där anläggning finns.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// ingress
			echo '<div class="form-field">' . PHP_EOL;
			echo '<label for="ingress">Ingress</label>' . PHP_EOL;
			echo '<textarea id="ingress" name="ingress">' . PHP_EOL;
			echo htmlentities($rent_object['ingress']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Kort beskrivning av anlägningen.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// description
			echo '<div class="form-field term-description-wrap">' . PHP_EOL;
			echo '<label for="description">Beskrivning</label>' . PHP_EOL;
			echo '<textarea id="description" name="description">' . PHP_EOL;
			echo htmlentities($rent_object['description']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Lång beskrivning som visas efter Ingressen.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// price_description
			echo '<div class="form-field">' . PHP_EOL;
			echo '<label for="price_description">Prismodel</label>' . PHP_EOL;
			echo '<textarea id="price_description" name="price_description">' . PHP_EOL;
			echo htmlentities($rent_object['price_description']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Beskrivning hur prissättningen är.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// url
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="url">URL</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="url" name="url">' . PHP_EOL, htmlentities($rent_object['url']));
			echo '<p>Webaddress till aktuell information om anläggningen.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// beds
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="beds">Sovplatser</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="beds" name="beds">' . PHP_EOL, htmlentities($rent_object['beds']));
			echo '<p>Antal sovplatser på anläggningen.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// visit_adress
			echo '<div class="form-field">' . PHP_EOL;
			echo '<label for="visit_adress">Besöksadress</label>' . PHP_EOL;
			echo '<textarea id="visit_adress" name="visit_adress">' . PHP_EOL;
			echo htmlentities($rent_object['visit_adress']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Address till anläggning.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// post_adress
			echo '<div class="form-field">' . PHP_EOL;
			echo '<label for="post_adress">Postadress</label>' . PHP_EOL;
			echo '<textarea id="post_adress" name="post_adress">' . PHP_EOL;
			echo htmlentities($rent_object['post_adress']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Adress till postmotagare för anläggningen.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// position_latitude
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="position_latitude">Position</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="position_latitude" name="position_latitude">' . PHP_EOL, htmlentities($rent_object['position_latitude']));
			// position_longitude
			printf('<input type="text" aria-required="true" value="%s" id="position_longitude" name="position_longitude">' . PHP_EOL, htmlentities($rent_object['position_longitude']));
			echo '<p>Var ska anlägningen synas på kartvyn.</p>' . PHP_EOL;
			echo '<p><b>TODO</b>: Select by adress or map, using google-map-api</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// contact_name
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="contact_name">Kontaktperson - namn</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="contact_name" name="contact_name">' . PHP_EOL, htmlentities($rent_object['contact_name']));
			echo '<p>Namn på kontaktperson.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// contact_phone
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="contact_phone">Kontaktperson - Telefon</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="contact_phone" name="contact_phone">' . PHP_EOL, htmlentities($rent_object['contact_phone']));
			echo '<p>Telefonnummer till kontaktperson, tex för bokning av anläggningen.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// contact_email
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="contact_email">Kontaktperson - E-post</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="contact_email" name="contact_email">' . PHP_EOL, htmlentities($rent_object['contact_email']));
			echo '<p>E-post-adress till kontaktperson, tex för bokning av anläggningen..</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// contact_other
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="contact_other">Kontakt - Information</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="contact_other" name="contact_other">' . PHP_EOL, htmlentities($rent_object['contact_other']));
			echo '<p>Mer info om kontakt, tex länk till bokningssida.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			$setting_names = $this->object_settings_names();
			$setting_options = $this->object_settings_options();
			
			foreach($setting_names as $name_id => $setting_name)
			{
				echo '<div class="form-field">' . PHP_EOL;
				echo "<label for=\"rent_object_option_{$name_id}\">{$setting_name}</label>" . PHP_EOL;
				echo "<select id=\"rent_object_option_{$name_id}\" name=\"rent_object_option[{$name_id}]\">" . PHP_EOL;
				echo '<option value="">-- Ej vald --</option>' . PHP_EOL;
				foreach($setting_options[$name_id] as $option_value => $option_name)
				{
					echo '<option value="' . htmlentities($option_value) . '"' . (((string) $option_value) == ((string) $rent_object['options'][$name_id]) ? ' selected="selected"': '') . '>' . htmlentities($option_name) . '</option>' . PHP_EOL;
				}
				echo '</select>' . PHP_EOL;
				// echo '<p>???.</p>' . PHP_EOL;
				echo '</div>' . PHP_EOL;
			}
			
			echo '<p><b>TODO</b>: Bilder</p>' . PHP_EOL;
			echo '<p><b>TODO</b>: Priser</p>' . PHP_EOL;

			echo '<p>';
			if($rent_object['object_status'])
			{
				submit_button("Spara", 'primary', 'save', FALSE);
				echo ' ';
				submit_button("Avpublisera", 'secondary', 'unpublish', FALSE);
			}
			else
			{
				submit_button("Spara", 'secondary', 'save', FALSE);
				echo ' ';
				submit_button("Publisera", 'primary', 'publish', FALSE);
			}
			echo '</p>' . PHP_EOL;

			echo '</div>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

// 			echo "<pre>" . htmlentities(json_encode($rent_object, JSON_PRETTY_PRINT)) . "</pre>" . PHP_EOL;
		}
		echo '</div>' . PHP_EOL;

		echo "<div class='wrap'>";
		echo "<h1>Anläggningar <a class=\"page-title-action\" href=\"?page=rent_objects&amp;id=0\">Lägg till</a></h1>";
		$table = new rent_object_table();
		$table->admin_user = $this->admin_user;
		$table->prepare_items();
		$table->display();
// echo "<pre>" . json_encode($table->items, JSON_PRETTY_PRINT) . "</pre>";
		echo "<p><b>TODO:</b>(Koppla till organistation)</p>";
		echo "<p><b>TODO:</b>Permission check, not done yet</p>";
		echo '</form>' . PHP_EOL;
		echo '</div>' . PHP_EOL;
		echo "</div>";
	}

	function object_settings($id, $textual = FALSE)
	{
		global $wpdb;

		if($textual)
		{
			$query = <<<SQL_BLOCK
SELECT
	rent_object_settings_names.setting_name,
	rent_object_settings_options.option_name
FROM {$wpdb->prefix}rent_object_settings
LEFT JOIN {$wpdb->prefix}rent_object_settings_names AS rent_object_settings_names USING (rent_object_settings_name_id)
LEFT JOIN {$wpdb->prefix}rent_object_settings_options AS rent_object_settings_options USING (rent_object_settings_name_id, option_value)
WHERE rent_object_id =
SQL_BLOCK;
		}
		else
		{
			$query = <<<SQL_BLOCK
SELECT rent_object_settings_name_id, option_value
FROM {$wpdb->prefix}rent_object_settings
WHERE rent_object_id =
SQL_BLOCK;
		}
		$query .= (int) $id;

		return array_combine($wpdb->get_col($query, 0), $wpdb->get_col(NULL, 1));
	}

	function object_settings_names()
	{
		global $wpdb;

		return array_combine($wpdb->get_col("SELECT rent_object_settings_name_id, setting_name FROM {$wpdb->prefix}rent_object_settings_names", 0), $wpdb->get_col(NULL, 1));
	}
	
	function object_settings_options()
	{
		global $wpdb;

		$options = array();
		foreach($wpdb->get_results("SELECT * FROM {$wpdb->prefix}rent_object_settings_options", 'ARRAY_A') as $row)
		{
			$options[$row['rent_object_settings_name_id']][$row['option_value']] = $row['option_name'];
		}
		return $options;
	}
	
	function shortcode_stuga($raw_attributes)
	{
		$default_attributes = array('id' => NULL, 'mode' => '', 'filter' => NULL, 'def' => NULL);
		$attributes = shortcode_atts($default_attributes, $raw_attributes);
		foreach(array_keys($default_attributes) as $key)
		{
			if(isset($_GET[$key]) AND !isset($raw_attributes[$key]))
			{
				$attributes[$key] = $_GET[$key];
			}
		}

		if(!$attributes['id'] AND preg_match("#/(?<id>[1-9][0-9]*)(/|$)#", $_SERVER['REQUEST_URI'], $m))
		{
			$attributes['id'] = $m['id'];
		}

		switch($attributes['mode'])
		{
			case 'title':
			{
				if($attributes['id'])
				{
					return $this->display_rent_object($attributes['id'], 'title');
				}
				else if($attributes['def'])
				{
					return $attributes['def'];
				}
				else
				{
					return "";
				}
			}
			case '';
			case 'object':
			{
				if($attributes['id'])
				{
					return $this->display_rent_object($attributes['id']);
				}
				else
				{
					return "";
				}
			}

			case 'map':
			{
				if($attributes['id'])
				{
					return "";
				}
				else
				{
					return $this->add_json_items() . $this->display_map();
				}
			}

			case 'list':
			{
				if($attributes['id'])
				{
					return "";
				}
				else
				{
					return $this->add_json_items() . $this->display_list();
				}
			}

			case 'filter':
			{
				if($attributes['id'])
				{
					return "";
				}
				else
				{
					return $this->add_json_items() . $this->display_filters();
				}
			}

			case 'reg':
			{
				return $this->display_reg_form();
			}
		}

		return "";
	}

	function add_json_items()
	{
		global $wpdb;
		static $added = FALSE;

		if($added)
		{
			return "";
		}

		$added = TRUE;

		$settings = array();
		foreach($wpdb->get_results("SELECT rent_object_settings_name_id AS option, setting_name AS name FROM {$wpdb->prefix}rent_object_settings_names", 'ARRAY_A') as $row)
		{
			$settings[$row['option']] = $row;
		}
		foreach($wpdb->get_results("SELECT rent_object_settings_name_id AS option, option_name AS name, option_value AS value FROM {$wpdb->prefix}rent_object_settings_options", 'ARRAY_A') as $row)
		{
			$settings[$row['option']]['options'][] = $row;
		}

		$items = array();
		foreach($wpdb->get_results("SELECT rent_object_id, rent_organisation_id, rent_object_type_id, name, beds, position_latitude, position_longitude, city, object_updated, type_name, CONCAT(types.url, rent_object_id) AS url FROM {$wpdb->prefix}rent_object LEFT JOIN {$wpdb->prefix}rent_object_types AS types USING (rent_object_type_id) WHERE object_status = 1", 'ARRAY_A') as $row)
		{
			$items[$row['rent_object_id']] = $row;
		}
		foreach($wpdb->get_results("SELECT * FROM {$wpdb->prefix}rent_object_settings_options", 'ARRAY_A') as $row)
		{
			if(empty($items[$row['rent_object_id']]))
			{
				continue;
			}
			$items[$row['rent_object_id']]['options'][] = array('option' => $row['rent_object_settings_name_id'], 'value' => $row['option_value']);
		}

		$types = $wpdb->get_results("SELECT rent_object_type_id AS id, type_name AS name FROM {$wpdb->prefix}rent_object_types", 'ARRAY_A');

		$organisations = $wpdb->get_results("SELECT rent_organisation_id AS id, parent_organisation_id AS parent_id, organisation_name AS name FROM {$wpdb->prefix}rent_organisations WHERE object_status = 1", 'ARRAY_A');

		$rent_objects_json = json_encode(
			array(
				'settings' => array_values($settings),
				'objects' => array_values($items),
				'organisations' => $organisations,
				'object_types' => $types,
			), JSON_NUMERIC_CHECK + JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);

		return <<<HTML_BLOCK
<script type="text/javascript">
	window.rent_objects = {$rent_objects_json};
	window.rent_objects.fetch_item_by_id = function(list, id_field, id_value, select_field)
	{
		var l = list.length;
		for(var li = 0; li < l; li++)
		{
			if(list[li][id_field] == id_value)
			{
				if(select_field)
				{
					return list[li][select_field];
				}
				else
				{
					return list[li];
				}
			}
		}
	};
	window.rent_objects.filter = function()
	{
		var objects = window.rent_objects.objects;
		var objects_length = objects.length;
		var active_objects = [];
		var filters = window.rent_objects.filters;
		var filter_count = filters.length;

		for(var oi = 0; oi < objects_length; oi++)
		{
			var ok = true;
			var object = objects[oi];
			for(var fi = 0; fi < filter_count; fi++)
			{
				var filter = filters[fi];
				switch(filter.type)
				{
					case 'option':
					{
						// TODO
					}
					case 'type':
					{
						if(filter.value > 0)
						{
							if(object.rent_object_type_id != filter.value)
							{
								ok = false;
								break;
							}
						}
						else
						{
							if(object.rent_object_type_id == -filter.value)
							{
								ok = false;
								break;
							}
						}
					}
					case 'name':
					{
						// TODO
					}
					case 'organisation':
					{
						// TODO
					}
					case 'distance':
					{
						// TODO
					}
				}
			}
			if(ok)
			{
				active_objects.push(object);
			}
		}

		// TODO sort

		window.rent_objects.active_objects = active_objects;
		window.rent_objects.update_list();
		window.rent_objects.update_map();
	};
	window.rent_objects.update_list = function()
	{
		var objects = window.rent_objects.active_objects;
		var objects_count = objects.length;

		var org = window.rent_objects.organisations;
		var types = window.rent_objects.object_types;

		var lists = document.getElementsByClassName('rent_object_list');
		var lists_count = lists.length;
		for(var li = 0; li < lists_count; li++)
		{
			var list = lists[li];
			list.innerHTML = '';

			for(var oi = 0; oi < objects_count; oi++)
			{
				var object = objects[oi];

				var tr = document.createElement('tr');
				tr.setAttribute('data-rent-object-id', object.rent_object_id);

				// Name
				var td = document.createElement('td');
				var a = document.createElement('a');
				a.innerText = object.name;
				a.href = object.url;
				td.appendChild(a);
				tr.appendChild(td);

				// Type
				var td = document.createElement('td');
				td.innerText = window.rent_objects.fetch_item_by_id(types, 'id', object.rent_object_type_id, 'name');
				tr.appendChild(td);

				// City
				var td = document.createElement('td');
				td.innerText = object.city;
				tr.appendChild(td);

				// Organisation
				var td = document.createElement('td');
				td.innerText = window.rent_objects.fetch_item_by_id(org, 'id', object.rent_organisation_id, 'name');
				tr.appendChild(td);

				// Distance
				var td = document.createElement('td');
				if(false)
				{
					// TODO
				}
				else
				{
					td.innerText = '';
				}
				tr.appendChild(td);

				// Price
				var td = document.createElement('td');
				if(false)
				{
					// TODO
				}
				else
				{
					td.innerText = '';
				}
				tr.appendChild(td);

				list.appendChild(tr);
			}
		}
		// TODO
		// ...
	};
	window.rent_objects.update_map = function()
	{
		// TODO
		// ...
	};
	window.rent_objects.init = function()
	{
		if(!window.rent_objects.filters)
		{
			window.rent_objects.filters = [];
		}
		if(!window.rent_objects.sort_order)
		{
			window.rent_objects.sort_order = 'name';
		}
		window.rent_objects.filter();
	};

	// W3C
	if(window.addEventListener) window.addEventListener('load', window.rent_objects.init, false);
	// IE
	else window.attachEvent('onload', window.rent_objects,init);
</script>
HTML_BLOCK;
	}

	function display_filters()
	{
		return "[TODO Filters]";
	}

	function display_list()
	{
		return <<<HTML_BLOCK
<table>
	<thead>
		<tr>
			<th>Anläggning</th>
			<th>Typ</th>
			<th>Ort</th>
			<th>Organisation</th>
			<th>Avstång</th>
			<th>Pris</th>
		</tr>
	</thead>
	<tbody class="rent_object_list">
		<tr>
			<td colspan="6">Loading...</td>
		</tr>
	</tbody>
</table>
HTML_BLOCK;
	}

	function display_map()
	{
		return "[TODO Map]";
	}

	function display_reg_form()
	{
		// check if there is a user logged in
		$current_user = wp_get_current_user();
		$current_user_email = $current_user ? $current_user->get('user_email') : '';
		$email_html = htmlentities($add_data['email'] ? $add_data['email'] : $current_user_email);

		$action_url = esc_url(admin_url('admin-post.php?action=add_rent_object'));

		// TODO: dynamic rent_object_type_id
		return <<<HTML_BLOCK
<form action='{$action_url}' method='post'>
	<fieldset>
		<legend>Lägg till en ny anläggning</legend>
		<label>
			<span>Anläggningsyp:</span><br />
			<select id="rent_object_type_id" name="add_rent_object[type_id]">
				<option value="">-- Välj Typ --</option>
				<option value="1">Stuga</option>
				<option value="2">Lägerplats</option>
			</select>
		</label><br />
		<label>
			<span>Anläggnings name:</span><br />
			<input name="add_rent_object[name]" type="name" />
		</label><br />
		<label>
			<span>Kår / Organisation:</span><br />
			<input name="add_rent_object[organisation]" type="name" />
		</label><br />
		<label>
			<span>E-post-adress för konto:</span><br />
			<input name="add_rent_object[email]" type="name" value="{$email_html}" />
		</label><br />
		<label>
			<span>Lösenord för konto:</span><br />
			<input name="add_rent_object[password]" type="password" />
		</label><br />
		<input type="submit" value="Lägg till anläggning" />
	</fieldset>
</form>
HTML_BLOCK;
	}

	function display_rent_object($rent_object, $part = NULL)
	{
		if(!is_object($rent_object))
		{
			if(is_numeric($rent_object))
			{
				global $wpdb;

				$rent_object = (int) $rent_object;

				$query = <<<SQL_BLOCK
SELECT rent_object.*, rent_organisations.organisation_name, users.display_name AS user_name
FROM {$wpdb->prefix}rent_object AS rent_object
	LEFT JOIN {$wpdb->prefix}rent_organisations AS rent_organisations USING (rent_organisation_id)
	LEFT JOIN {$wpdb->prefix}users AS users ON (users.ID = rent_object.user_id)
WHERE rent_object.rent_object_id = {$rent_object}
SQL_BLOCK;
				$rent_object = $wpdb->get_row($query);
			}
			else
			{
				return FALSE;
			}
		}

		if(!$rent_object)
		{
			return FALSE;
		}

		if($part)
		{
			switch($part)
			{
				case 'title':
				{
					return $rent_object->name;
				}
			}
		}

		$html = array();
		$html[] = '<div class="rent_object" id="rent_object_' . ((int) $rent_object->rent_object_id) . '">';
		$object_name = htmlentities($rent_object->name);
		$html[] = '<h2 class="rent_object_name">' . $object_name . '</h2>';
		$html[] = '<p class="ingress rent_object_ingress">' . htmlentities($rent_object->ingress) . '</p>';
		if($rent_object->main_image)
		{
			$html[] = '<p class="rent_object_main_image rent_object_image">' . wp_get_attachment_image((int) $rent_object->main_image, 'large') . '</p>';
		}
		$html[] = '<p class="description rent_object_description">' . make_clickable(htmlentities($rent_object->description)) . '</p>';
		$url = htmlentities($rent_object->url);
		$html[] = '<p class="rent_object_link"><a class="rent_object_link" target="_bland" href="' . $url . '">' . $url . '</a></p>';

		$html[] = '<h3 class="visit_adress rent_object_adress">Plats</h3>';
		$html[] = '<p class="visit_adress rent_object_adress">' . nl2br(htmlentities($rent_object->visit_adress)) . '</p>';
		$map_attributes = array();
		$map_attributes['name'] = 'data-name="' . $object_name . '"';
		$map_attributes['adress'] = 'data-address="' . htmlentities(str_replace("\n", ", ", $rent_object->visit_adress)) . '"';

		if($rent_object->position_latitude AND $rent_object->position_longitude)
		{
			$map_attributes['position_lat'] = 'data-lat="' . htmlentities($rent_object->position_latitude) . '"';
			$map_attributes['position_long'] = 'data-long="' . htmlentities($rent_object->position_longitude) . '"';
		}

		$html[] = '<div class="map_wrapper" ' . implode(" ", $map_attributes) . '><div class="map_canvas mapping"></div></div>';
		$html[] = '<h3 class="price rent_object_price">Pris</h3>';
		$html[] = '<p class="price rent_object_price">' . nl2br(htmlentities($rent_object->price_description)) . '</p>';

		$html[] = '<h3 class="rent_object_data">Kort Fakta</h3>';
		$html[] = '<dl class="rent_object_data">';
		$html[] = '<dt>Organistation:</dt>	<dd>' . htmlentities($rent_object->organisation_name) . '</dd>';
		if($rent_object->beds)
		{
			$html[] = '<dt>Sovplatser:</dt>	<dd>' . htmlentities($rent_object->beds) . 'st</dd>';
		}

		$settings = $this->object_settings($rent_object->rent_object_id, TRUE);

		foreach($settings as $setting_name => $setting_value)
		{
			$html[] = '<dt>' . htmlentities($setting_name) . ':</dt>	<dd>' . htmlentities($setting_value) . '</dd>';
		}

		$html[] = '</dl>';

		$html[] = '<h3 class="rent_object_contact">Kontaktuppgifter</h3>';
		$html[] = '<dl class="rent_object_contact">';
		$contact_name = htmlentities($rent_object->contact_name);
		$contact_email = htmlentities($rent_object->contact_email);
		$contact_phone = htmlentities($rent_object->contact_phone);
		$html[] = "<dt>Kontaktperson:</dt>	<dd>{$contact_name}</dd>";
		$html[] = "<dt>E-post:</dt>	<dd><a href=\"mailto:{$contact_name} <{$contact_email}>?subject={$object_name}\">{$contact_email}</a></dd>";
		$html[] = "<dt>Telefon:</dt>	<dd><a href=\"tel:{$contact_phone}\">{$contact_phone}</a></dd>";
		$html[] = '<dt>Övrigt:</dt>	<dd>' . make_clickable(htmlentities($rent_object->contact_other)) . '</dd>';
		$html[] = '</dl>';

		$html[] = '<h3 class="rent_object_images">Bilder</h3>';
		$html[] = '<p class="rent_object_image">';
		foreach($wpdb->get_col("SELECT image_id FROM {$wpdb->prefix}rent_object_images WHERE rent_object_id = " . (int) $rent_object->rent_object_id . " ORDER BY pos, image_id", 0) AS $image_id)
		{
			$html[] = wp_get_attachment_image($image_id, 'large');
		}
		$html[] = '</p>';

		$html[] = '<p class="rent_object_footer">' . htmlentities("Updated {$rent_object->object_updated} by {$rent_object->user_name}, Löpnummer: {$rent_object->rent_object_id}") . '</p>';

// 		$html[] = "<pre>" . print_r($rent_object, TURE) . "</pre>";
		$html[] = '</div>';
		return implode(PHP_EOL, $html);
	}

	static function add_new_user()
	{
		global $wpdb;

		// abort if no data send
		if(empty($_POST['add_rent_object']))
		{
			return FALSE;
		}

		// populate $add_data with post-data or default values
		$add_data = $_POST['add_rent_object'] + array('name' => NULL, 'organisation' => NULL, 'type_id' => NULL, 'email' => NULL, 'password' => NULL);

		// check if there is a user logged in
		$current_user = wp_get_current_user();

		// email-adress provided?
		if($add_data['email'])
		{
			// if email-adress matches loged in user, use current user
			if($current_user AND $current_user->get('user_email') == $add_data['email'])
			{
				$user = $current_user;
			}
			else
			{
				// if there is a current user, that don't match the email, log out that user
				if($current_user)
				{
					wp_logout();
					$current_user = NULL;
				}

				// Check if the email-adress match a current user
				$user = get_user_by('email', $add_data['email']);

				// email match a current user
				if($user)
				{
					// Try to log in that user, to see if password matches
					$user = wp_signon(array('user_login' => $user->get('user_login'), 'user_password' => $add_data['password']));
					if(is_wp_error($user))
					{
						return $user;
					}
				}
				// email is unknown/new
				else
				{
					// create a user
					$user = wp_insert_user(array('user_login' => $add_data['email'], 'user_pass' => $add_data['password'], 'user_email' => $add_data['email']));
					if(is_wp_error($user))
					{
						return $user;
					}

					// login as the new user
					$user = wp_signon(array('user_login' => $add_data['email'], 'user_password' => $add_data['password']));
					if(is_wp_error($user))
					{
						return $user;
					}
				}
			}
		}
		// no email provided
		else
		{
			// use current user if any
			$user = $current_user;
		}

		if(!$user)
		{
			return $user;
		}

		// Name for object provided, known (or newly created) user
		if(!$add_data['name'])
		{
			return TRUE;
		}

		// No organisation givven, use default organisation
		if(!$add_data['organisation'])
		{
			$org_id = 1;
		}
		else
		{
			// look up organisation in database
			$org_id = $wpdb->get_var($wpdb->prepare("SELECT rent_organisation_id FROM {$wpdb->prefix}rent_organisations WHERE organisation_name = %s", $add_data['organisation']));

			// organisation not found
			if(!$org_id)
			{
				// add organisation
				$db_af_rows = $wpdb->insert(
					"{$wpdb->prefix}rent_organisations",
					array(
						'parent_organisation_id' => 1,
						'organisation_name' => $add_data['organisation'],
						'user_id' => $user->ID,
					),
					array('%d', '%s', '%d')
				);
				$org_id = $db_af_rows ? $wpdb->insert_id : $db_af_rows;

				// new organisation created
				if($org_id)
				{
					// add permission for current user
					$wpdb->insert(
						"{$wpdb->prefix}rent_organisation_permissions",
						array(
							'user_id' => $user->ID,
							'rent_organisation_id' => $org_id,
						),
						array('%d', '%d')
					);
				}
			}
		}

		// organisation not found and not created
		if(!$org_id)
		{
			return $org_id;
		}

		// add rent-object
		$db_af_rows = $wpdb->insert(
			"{$wpdb->prefix}rent_object",
			array(
				'rent_organisation_id' => $org_id,
				'rent_object_type_id' => (int) $add_data['type_id'],
				'user_id' => $user->ID,
				'name' => $add_data['name']
			),
			array('%d', '%d', '%d', '%s')
		);
		$rent_object_id = $db_af_rows ? $wpdb->insert_id : $db_af_rows;

		// object not created
		if(!$rent_object_id)
		{
			return $rent_object_id;
		}

		// add permission for current user
		$wpdb->insert(
			"{$wpdb->prefix}rent_object_permissions",
			array(
				'user_id' => $user->ID,
				'rent_object_id' => $rent_object_id,
			),
			array('%d', '%d')
		);

		// redirect the user to the page where the user can update the object with more information
		wp_redirect(admin_url("admin.php?page=rent_objects&id={$rent_object_id}"));
		die();
	}

	static function check_permission($rent_object_id)
	{
		global $wpdb;

		if(current_user_can(RENT_OBJECT_ADMIN_CAP))
		{
			return TRUE;
		}

		$user_id = (int) get_current_user_id();
		$rent_object_id = (int) $rent_object_id;

		if(!$user_id OR !$rent_object_id)
		{
			return FALSE;
		}

		$direct_access = $wpdb->get_var("SELECT 1 FROM {$wpdb->prefix}rent_object_permissions WHERE user_id = {$user_id} AND rent_object_id = {$rent_object_id}");

		if($direct_access)
		{
			return TRUE;
		}

		$rent_organisation_id = $wpdb->get_var("SELECT rent_organisation_id FROM {$wpdb->prefix}rent_object WHERE rent_object_id = {$rent_object_id}");

		$rent_organisation_ids = rent_object_holder::allowed_organisations($user_id);
		$rent_organisation_ids = array_combine($rent_organisation_ids, $rent_organisation_ids);

		return isset($rent_organisation_ids[$rent_organisation_id]);
	}

	static function allowed_organisations($user_id)
	{
		global $wpdb;

		$ro_table = "{$wpdb->prefix}rent_organisations";
		$rop_table = "{$wpdb->prefix}rent_organisation_permissions";
		$query = <<<SQL_BLOCK
(
	SELECT rent_organisation_id
	FROM {$rop_table}
	WHERE user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = rop.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS link_1 ON (link_1.parent_organisation_id = rop.rent_organisation_id)
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = link_1.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS link_1 ON (link_1.parent_organisation_id = rop.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_2 ON (link_2.parent_organisation_id = link_1.rent_organisation_id)
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = link_2.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS link_1 ON (link_1.parent_organisation_id = rop.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_2 ON (link_2.parent_organisation_id = link_1.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_3 ON (link_3.parent_organisation_id = link_2.rent_organisation_id)
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = link_3.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS link_1 ON (link_1.parent_organisation_id = rop.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_2 ON (link_2.parent_organisation_id = link_1.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_3 ON (link_3.parent_organisation_id = link_2.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_4 ON (link_4.parent_organisation_id = link_3.rent_organisation_id)
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = link_4.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
SQL_BLOCK;

		return $wpdb->get_col($query);
	}
}

require_once(__DIR__ . '/class-wp-list-table.php');
class rent_object_table extends RO_WP_List_Table
{
	public $admin_user = FALSE;

	/** Class constructor */
	public function __construct()
	{
		parent::__construct(
			array(
				'singular' => 'Anlägning',
				'plural'   => 'Anlägningar',
// 				'ajax'     => TRUE,
				'ajax'     => FALSE,
			)
		);
	}

	function prepare_items()
	{
		global $wpdb;

		$this->_column_headers = array($this->get_columns(), array(), array());
// 		$this->process_bulk_action();

		if($this->admin_user)
		{
			$query = <<<SQL_BLOCK
SELECT rent_object.*, rent_organisations.organisation_name, users.display_name AS user_name
FROM {$wpdb->prefix}rent_object AS rent_object
	LEFT JOIN {$wpdb->prefix}rent_organisations AS rent_organisations USING (rent_organisation_id)
	LEFT JOIN {$wpdb->prefix}users AS users ON (users.ID = rent_object.user_id)
ORDER BY rent_object.rent_object_id
SQL_BLOCK;
			$this->items = $wpdb->get_results($query, 'ARRAY_A');
		}
		else
		{
			$user_id = (int) get_current_user_id();
			$rent_organisations = rent_object_holder::allowed_organisations($user_id);
			if($rent_organisations)
			{
				$rent_organisations = array_map(function ($a) {return (int) $a;}, $rent_organisations);
				$rent_organisations_where = 'OR rent_object.rent_organisation_id IN (' . implode(', ', $rent_organisations) . ')';
			}
			else
			{
				$rent_organisations_where = '';
			}

			$query = <<<SQL_BLOCK
SELECT rent_object.*, rent_organisations.organisation_name, users.display_name AS user_name
FROM {$wpdb->prefix}rent_object AS rent_object
	LEFT JOIN {$wpdb->prefix}rent_organisations AS rent_organisations USING (rent_organisation_id)
	LEFT JOIN {$wpdb->prefix}users AS users ON (users.ID = rent_object.user_id)
	LEFT JOIN {$wpdb->prefix}rent_object_permissions AS rent_object_permissions ON (rent_object_permissions.rent_object_id = rent_object.rent_object_id AND rent_object_permissions.user_id = {$user_id})
WHERE rent_object_permissions.rent_object_id IS NOT NULL {$rent_organisations_where}
ORDER BY rent_object.rent_object_id
SQL_BLOCK;
$GLOBALS['debug_query'] = $query;

			$this->items = $wpdb->get_results($query, 'ARRAY_A');
		}
		return TRUE;
	}

	function get_columns()
	{
		return array(
			"cb" => '<input type="checkbox" />',
			"rent_object_id" => "ID",
			"name"=> "Namn",
			"organisation_name"=> "Organistation",
			"user_name"=> "Användare",
			"object_updated"=> "Uppdaterad",

// 			"rent_object_type_id"=> "2",
// 			"main_image"=> "30",
// 			"visit_adress"=> "Kragen\u00e4s\r\nMyren 3\r\n457 91 Tanumshede",
// 			"url"=> "http=>\/\/kragenas.scout.se\/",
// 			"position_latitude"=> "58.799048",
// 			"position_longitude"=> "11.226496",

// 			"ingress"=> "Kragen\u00e4s - m\u00f6jligheternas m\u00f6tesplats\r\n",
// 			"description"=> "Med det vidstr\u00e4ckta havet i v\u00e4ster, inomsk\u00e4rs milsl\u00e5nga kanotleder i norr, utbredda hajkomr\u00e5den i \u00f6ster och hela Tanums kommuns kulturarv i s\u00f6der bjuder Kragen\u00e4s in till \u00e4ventyr och minnen f\u00f6r livet. Vackert bel\u00e4get vid Tanums naturreservat 14 mil fr\u00e5n G\u00f6teborg d\u00e4r land m\u00f6ter hav tar Kragen\u00e4s tillvara p\u00e5 terr\u00e4ngens variation och erbjuder o\u00e4ndliga m\u00f6jligheter f\u00f6r l\u00e4ger med programaktiviteter ut\u00f6ver det vanliga. Genom skog och berg g\u00e5r l\u00e5nga vandringsleder och vid vattnet guppar bryggorna av kluckande v\u00e5gor. Med sina m\u00e5nga l\u00e4ger\u00e4ngar, roliga aktiviteter och inspirerande programutbud finns det plats f\u00f6r h\u00e4rliga upplevelser och \u00e4ventyr ut\u00f6ver det vanliga. V\u00e4lkommen till Kragen\u00e4s \u2013  m\u00f6jligheternas m\u00f6tesplats!",
// 			"beds"=> "0",
// 			"post_adress"=> "",
// 			"city"=> "",
// 			"price_description"=> "40 kr*\/person\/natt exklusive mat\r\n125 kr*\/person\/natt inklusive mat, servicefunktioner och programutbud (ej kanoter, kajaker och hantverksmaterial)\r\n\r\n* Scouter tillh\u00f6rande G\u00f6teborgs scoutdistrikt erh\u00e5ller 15 kr rabatt\/person\/natt.\r\n\r\nSenaste priser p\u00e5=>\r\nhttp=>\/\/kragenas.scout.se\/boka\/prislista\/",
// 			"contact_name"=> "Kragen\u00e4sgruppen",
// 			"contact_phone"=> "0525-23380",
// 			"contact_email"=> "kragenas@gbgscout.se",
// 			"contact_other"=> "",
// 			"object_status"=> "1",
		);
	}

	function column_cb($item)
	{
		return sprintf('<input type="checkbox" name="buld[]" value="%d" />', $item['rent_object_id']);
	}

	function column_name($item)
	{
		return sprintf('<a href="?page=rent_objects&amp;id=%d">%s</a>', $item['rent_object_id'], htmlentities($item['name']));
	}

	function column_default($item, $column_name)
	{
		return htmlentities($item[$column_name]);
	}

	public function no_items()
	{
		echo "Listan är tom";
	}
}
