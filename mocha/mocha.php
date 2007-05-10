<?php
/*
Plugin Name: Mocha
Version: 0.1.6
Plugin URI: http://jamietalbot.com/wp-hacks/mocha/
Description: WordPress .po and .mo file generation.  Licensed under the <a href="http://www.opensource.org/licenses/mit-license.php">MIT License</a>, Copyright &copy; 2007 Jamie Talbot.
Author: Jamie Talbot
Author URI: http://jamietalbot.com

/*
Mocha - WordPress .po and .mo file generation.
Copyright (c) 2007 Jamie Talbot

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated
documentation files (the "Software"), to deal in the
Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software,
and to permit persons to whom the Software is furnished to
do so, subject to the following conditions:

The above copyright notice and this permission notice shall
be included in all copies or substantial portions of the
Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY
KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS
OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR
OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// Load some variables for when running as ajax.
if (!defined('ABSPATH')) {
	$gengo_use_default_language = true;
	require_once(dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])))) . '/wp-blog-header.php');
	require_once(dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])))) . '/wp-admin/admin-functions.php');
}

define ("MOCHA_BASE_DIR", "mocha/");
define ("MOCHA_DIR", "wp-content/plugins/" . MOCHA_BASE_DIR);
define ("MOCHA_ADMIN_PAGE", "mocha_options_page.php");
define ("MOCHA_DOMAIN", "mocha");
define ("MOCHA_CORE_PO_DIR", 'wp-includes/languages/');
define ("MOCHA_PLUGINS_DIR", 'wp-content/plugins/');
define ("MOCHA_THEMES_DIR", 'wp-content/themes/');
define ("MOCHA_MODE_CORE", 1);
define ("MOCHA_MODE_PLUGIN", 2);
define ("MOCHA_MODE_THEME", 3);

class Mocha {

	function Mocha($ajax = false) {
		$this->version = '0.1.6';
		$this->site_url = get_settings('siteurl');

		if (false !== strpos($_SERVER['SERVER_SOFTWARE'], 'Win32')) {
			$this->path = ABSPATH . MOCHA_DIR . "lib/";
		}

		if (!$ajax) {
			// Admin UI.
			if (is_admin()) {
				add_action('activate_mocha/mocha.php', array(& $this, 'activated'));
				add_action('admin_head', array(& $this, 'admin_head'));
				add_action('admin_menu', array(& $this, 'admin_menu'));
			}
		}
		add_action('init', array(& $this,'init'));
	}

	// Hook functions.

	function admin_menu() {
		global $language_menu;
		if (current_user_can('use_mocha')) {
			if (!$language_menu) {
				add_menu_page(__('Language Options', MOCHA_DOMAIN), __('Language', MOCHA_DOMAIN), 1, MOCHA_DIR . MOCHA_ADMIN_PAGE);
				$language_menu = MOCHA_DIR . MOCHA_ADMIN_PAGE;
			}
			add_submenu_page($language_menu, __('Mocha Options', MOCHA_DOMAIN), __('Mocha', MOCHA_DOMAIN), 1, MOCHA_DIR . MOCHA_ADMIN_PAGE);
		}
	}

	function activated() {
		global $wp_roles;
		$role = $wp_roles->get_role('administrator');
		$role->add_cap('use_mocha');
		$role = $wp_roles->get_role('author');
		$role->add_cap('use_mocha');
	  $this->retrieve_wordpress_pot();
		header('Location: admin.php?page=' . MOCHA_BASE_DIR . MOCHA_ADMIN_PAGE);
		die();
	}

	function admin_head() {
		?><script type="text/javascript" src="../wp-includes/js/tw-sack.js"></script><?php
	}

	function init() {
	  global $wp_version;
	  if (is_admin()) {
			if (isset($_POST['mocha_po_submit'])) {
				$success = $this->save_po_strings();
			} elseif (isset($_POST['mocha_retrieve_core_pot'])) {
				$success = $this->retrieve_wordpress_pot();
			}
			load_plugin_textdomain(MOCHA_DOMAIN, MOCHA_DIR);
			if (isset($_POST['mocha_po_submit'])) {
				if ($success) {
					$this->update_message(__('Localisation updated successfully.', MOCHA_DOMAIN), true);
				} else {
					$this->error_message(sprintf(__("Compilation of PO file failed: %s", MOCHA_DOMAIN), $this->error), true);
				}
			}	elseif (isset($_POST['mocha_retrieve_core_pot'])) {
				if ($success) {
					$this->update_message(sprintf(__('WordPress %s .pot file successfully retrieved.', MOCHA_DOMAIN), $wp_version), true);
				} else {
					$this->error_message(sprintf(__("A .pot file for WordPress %s is still not available", MOCHA_DOMAIN), $wp_version), true);
				}
			}				
		}
	}

	// Auxilliary functions.

	// Tries to get the POT file specific for the version of WordPress being used.
	function retrieve_wordpress_pot() {
	  global $wp_version;
	  $wordpress_version = (false !== ($position = strpos($wp_version, '-'))) ? substr($wp_version, 0, $position) : $wp_version;
		$wordpress_pot = ABSPATH . MOCHA_DIR . 'wordpress.pot';
	  if (ini_get('allow_url_fopen')) {
      return @copy("http://svn.automattic.com/wordpress-i18n/pot/tags/$wordpress_version/wordpress.pot", $wordpress_pot);
		} else {
			return $this->exec_wrapper("wget -O $wordpress_pot http://svn.automattic.com/wordpress-i18n/pot/tags/$wordpress_version/wordpress.pot");
		}
	}

	function update_message($message, $buffer = true) {
	  if ($buffer) {
      ob_start();
		}
	  ?><div<?= $buffer ? ' id="mocha_feedback" ' : ' ' ?>class="updated fade"><p><?php echo $message ?></p></div><?php
		if ($buffer) {
			$this->message_buffer = ob_get_clean();
		}
		return true;
	}
	
	function error_message($message, $buffer = true) {
	  if ($buffer) {
      ob_start();
		}
	  ?><div<?= $buffer ? ' id="mocha_feedback" ' : ' ' ?>class="error"><p><strong><?php _e('Error: ', MOCHA_DOMAIN) ?></strong><?php echo $message ?></p></div><?php
		if ($buffer) {
			$this->message_buffer = ob_get_clean();
		}
		return false;
	}
	
	function present_feedback() {
		echo $this->message_buffer;
	}

	function exec_wrapper($command, $verbose = false) {
		$string = exec("$command 2>&1", $output, $status);
	  $output = implode("\n", $output);
		if ($status) {
			$this->error = $output;
			return false;
		}
		return true;
	}

	function build_file_list($root) {
		$files = array();
		$dir = @ dir($root);
		if ($dir) {
			while (($file = $dir->read()) !== false) {
				if (preg_match('|^\.+$|', $file))
					continue;
				if (is_dir($root . '/' . $file)) {
					$subdir = @ dir($root . '/' . $file);
					if ($subdir) {
						while (($subfile = $subdir->read()) !== false) {
							if (preg_match('|^\.+$|', $subfile))
								continue;
							if (preg_match('|\.php$|', $subfile))
								$files[] = "$file/$subfile";
						}
					}
				} else {
					if (preg_match('|\.php$|', $file))
						$files[] = $file;
				}
			}
		}
		return $files;
	}

	// Directory parsing code lifted from WordPress core.
	function generate_plugin_pot_file($name) {
		global $wp_plugins;

		$plugin_root = ABSPATH . MOCHA_PLUGINS_DIR . $name;
		$plugin_files = $this->build_file_list($plugin_root);
		
		foreach ($wp_plugins as $internal_name => $plugin_info) {
			if (false !== strpos($internal_name, "$name/")) {
				$version = $plugin_info['Version'];
				$title = $plugin_info['Name'];
				break;
			}
		}

		if ($this->exec_wrapper("{$this->path}xgettext -k__ -k_e -k -d $name -F --from-code=UTF-8 -o $name.pot -p $plugin_root -D $plugin_root " . implode(' ', $plugin_files))) {
			$this->update_pot_headers("$plugin_root/$name.pot", $title, $version);
			@chmod("$plugin_root/$name.pot", 0644);
      return true;
		} else {
			return false;
		}
	}
	
	function generate_theme_pot_file($name) {
		global $wp_themes;

		$theme_root = ABSPATH . MOCHA_THEMES_DIR . $name;
		$theme_files = $this->build_file_list($theme_root);

		foreach ($wp_themes as $title => $theme_info) {
			if ($theme_info['Template'] == $name) {
				$version = $theme_info['Version'];
				break;
			}
		}

		if ($this->exec_wrapper("{$this->path}xgettext -k__ -k_e -k -d $name -F --from-code=UTF-8 -o $name.pot -p $theme_root -D $theme_root " . implode(' ', $theme_files))) {
			$this->update_pot_headers("$theme_root/$name.pot", $title, $version);
			@chmod("$theme_root/$name.pot", 0644);
      return true;
		} else {
			return false;
		}
	}
	
	function update_pot_headers($file, $title, $version) {
	  global $current_user;
	  $host = $_SERVER['HTTP_HOST'];
	  $name = $current_user->user_nicename;
	  $email = "$name@$host";
    $filecontents = file_get_contents($file);
	  $charset = get_settings('blog_charset');
	  $year = date('Y');
    $filecontents = str_replace(array("Copyright (C) YEAR THE PACKAGE'S COPYRIGHT HOLDER", 'under the same license as the PACKAGE package', 'SOME DESCRIPTIVE TITLE', 'charset=CHARSET', 'Last-Translator: FULL NAME <EMAIL@ADDRESS>', 'Project-Id-Version: PACKAGE VERSION'), array("Copyright (C) $year $title's copyright holder", "under the same license as $title", "$title $version POT file", "charset=$charset", "Last-Translator: $name <$email>", "Project-Id-Version: $title $version"), $filecontents);
		file_put_contents($file, $filecontents);
	}
	
	function update_po_headers($file, $language) {
	  global $current_user;
	  $host = $_SERVER['HTTP_HOST'];
	  $name = $current_user->user_nicename;
	  $email = "$name@$host";
    $filecontents = file_get_contents($file);
	  $year = date('Y');
    $filecontents = str_replace(array('Language-Team: LANGUAGE <LL@li.org>', 'FIRST AUTHOR <EMAIL@ADDRESS>, YEAR.'), array("Language-Team: $language <$email>", "$name <$email>, $year."), $filecontents);
    file_put_contents($file, $filecontents);
	}

	function generate_core_pot_file() {
    if (!file_exists(ABSPATH . MOCHA_CORE_PO_DIR)) {
			if (!mkdir(ABSPATH . MOCHA_CORE_PO_DIR, 0755)) {
			  $this->error_message(__('Failed to create the languages directory', MOCHA_DOMAIN));
				return false;
			}
		}
		return true;
	}

	function get_po_inputs($locale, $language, $type, $name = '') {
		global $wp_version;
	  $generated = false;

		switch ($type) {
			case MOCHA_MODE_CORE:
				$title = 'WordPress';
				$version = $wp_version;
				$file = ABSPATH . MOCHA_CORE_PO_DIR . "$locale.po";
				$pot_file = ABSPATH . MOCHA_DIR . 'wordpress.pot';
				$mo_file = ABSPATH . MOCHA_CORE_PO_DIR . "$locale.mo";
				$pot_type = 'core';
			  break;
			
			case MOCHA_MODE_PLUGIN:
			  $plugins = get_plugins();
			  foreach ($plugins as $plugin_name => $plugin) {
					if ($this->string_starts_with($plugin_name, "$name/")) break;
				}
				$title = $plugin['Name'];
				$version = $plugin['Version'];
				$file = ABSPATH . MOCHA_PLUGINS_DIR . $name . "/$name-$locale.po";
				$pot_file = ABSPATH . MOCHA_PLUGINS_DIR . "/$name/$name.pot";
				$mo_file = ABSPATH . MOCHA_PLUGINS_DIR . $name . "/$name-$locale.mo";
				$pot_type = 'plugin';
			  break;

			case MOCHA_MODE_THEME:
			  $themes = get_themes();
			  foreach ($themes as $title => $theme) {
					if ($theme['Template'] == $name) break;
				}
				$version = $theme['Version'];
				$file = ABSPATH . $theme['Template Dir'] . "/$locale.po";
		    $pot_file = ABSPATH . $theme['Template Dir'] . "/" . $theme['Template'] . ".pot";
				$mo_file = ABSPATH . $theme['Template Dir'] . "/$locale.mo";
				$pot_type = 'theme';
			  break;
		}

	  if (!file_exists($file)) {
		  if (!file_exists($pot_file)) {
		    $function = "generate_{$pot_type}_pot_file";
				if (!$this->$function($name)) {
					$this->error_message(sprintf(__('Failed to generate a POT file: %s', MOCHA_DOMAIN), $this->error), false);
					return false;
				}
			}
			if (!@copy($pot_file, $file)) {
				$this->error_message(__('Failed to create a copy of the base POT file', MOCHA_DOMAIN), false);
				return false;
			}
			$this->update_po_headers($file, $language);
			@chmod($file, 0644);
			if (file_exists($mo_file)) {
				if (!$this->populate_po_from_mo($mo_file, $file)) {
					$this->error_message(sprintf(__('Failed to merge existing MO information with new PO: %s', MOCHA_DOMAIN), $this->error), false);
					return false;
				}
				$merged = true;
			}
			$generated = true;
		}

		$po_contents = file($file);
		if ($merged) {
			$po_contents_string = implode('', $po_contents);
			$po_contents_string = preg_replace('/Project-Id-Version:(.*)\\\\n/', "Project-Id-Version: $title $version" . '\\\\n', $po_contents_string);
			file_put_contents($file, $po_contents_string);
		}
		if (!$generated) {
			$po_contents_string = implode('', $po_contents);
			if (preg_match('/"Project-Id-Version: (?:.*) (.*)\\\\n/', $po_contents_string, $matches)) {
				$po_version = $matches[1];
				if (version_compare($po_version, $version, '<')) {
					switch ($type) {
						case MOCHA_MODE_CORE:
						  $this->retrieve_wordpress_pot();
						  break;
						  
						case MOCHA_MODE_PLUGIN:
							if (!$this->generate_plugin_pot_file($name)) {
								$this->error_message(sprintf(__('Failed to generate a POT file: %s', MOCHA_DOMAIN), $this->error), false);
	   						return false;
	   					}
						  break;
						  
						case MOCHA_MODE_THEME:
							if (!$this->generate_theme_pot_file($name)) {
								$this->error_message(sprintf(__('Failed to generate a POT file: %s', MOCHA_DOMAIN), $this->error), false);
	   						return false;
	   					}
						  break;
					}
					if (!$this->exec_wrapper("{$this->path}msgmerge -o $file -F $file $pot_file")) {
						$this->error_message(sprintf(__("Failed to update the .po file to latest version: %s", MOCHA_DOMAIN), $this->error), false);
						return false;
					}

			    $filecontents = file_get_contents($file);
    			$filecontents = preg_replace('/Project-Id-Version:(.*)\\\\n/', "Project-Id-Version: $title $version" . '\\\\n', $filecontents);
					file_put_contents($file, $filecontents);

					$this->update_message(sprintf(__('Updated %1$s to version %2$s from version %3$s.', MOCHA_DOMAIN), $title, $version, $po_version), false);
					$po_contents = file($file);
				}
			}
		}

		$i = 0;
		$mode = 'header';
		$header_parsed = false;
		foreach ($po_contents as $line) {
		  if (!trim($line)) continue;
		  switch ($mode) {
		    case 'header':
					if ($this->string_starts_with($line, '#:')) {
					  $locations[$i] .= substr($line, 3) . ' ';
					  $header_parsed = true;
					  $mode = 'comment';
		 			} elseif (preg_match('|charset=(.*)\\\\n|', $line, $matches)) {
						$charset = $matches[1];
						if ('CHARSET' == $charset) {
							$charset = get_settings('blog_charset');
						}
					}
					break;

				case 'comment':
					if ($this->string_starts_with($line, '#:')) {
						$locations[$i] .= substr($line, 3) . ' ';
					} elseif ('#, fuzzy' == trim($line)) {
						$fuzzy[$i] = true;
					} elseif ($this->string_starts_with($line, 'msgid') && $header_parsed) {
						preg_match('/"(.*)"/', $line, $matches);
						$originals[$i] = stripslashes(htmlspecialchars($matches[1], ENT_COMPAT, $charset));
						$mode = 'msgid';
					} elseif (!$this->string_starts_with($line, '#') && $header_parsed) {
						$this->error_message(__('Unexpected Token in PO file.  Expected comment or msgid.', MOCHA_DOMAIN), false);
						return false;
					}
					break;
					
				case 'msgid':
				  if ($this->string_starts_with($line, 'msgstr')) {
						preg_match('/"(.*)"/', $line, $matches);
						$translations[$i] = stripslashes(htmlspecialchars($matches[1], ENT_COMPAT, $charset));
						$mode = 'msgstr';
					} elseif ($this->string_starts_with($line, '"')) {
						preg_match('/"(.*)"/', $line, $matches);
						$originals[$i] .= stripslashes(htmlspecialchars($matches[1], ENT_COMPAT, $charset));
					} elseif (!$this->string_starts_with($line, '#')) {
						$this->error_message(__('Unexpected Token in PO file.  Expected comment or msgid.', MOCHA_DOMAIN), false);
						return false;
					}
					break;
					
				case 'msgstr':
					if ($this->string_starts_with($line, '#:')) {
						$locations[++$i] .= substr($line, 3) . ' ';
						$mode = 'comment';
					} elseif ($this->string_starts_with($line, 'msgid')) {
						$locations[++$i] = '';
						$originals[$i] = stripslashes(htmlspecialchars($matches[1], ENT_COMPAT, $charset));
						preg_match('/"(.*)"/', $line, $matches);
						$mode = 'msgid';
					} elseif ('#, fuzzy' == trim($line)) {
						$fuzzy[++$i] = true;
						$mode = 'msgid';
					} elseif ($this->string_starts_with($line, '"')) {
						preg_match('/"(.*)"/', $line, $matches);
						$translations[$i] .= stripslashes(htmlspecialchars($matches[1], ENT_COMPAT, $charset));
					} elseif (!$this->string_starts_with($line, '#')) {
						$this->error_message(__('Unexpected Token in PO file.  Expected comment or msgstr.', MOCHA_DOMAIN), false);
						return false;
					}
					break;
			}
		}
		$po_count = count($originals);
		
		if (!$po_count) {
			unlink($file);
			$this->error_message(sprintf(__('%s does not contain any localisable strings.  Please make another selection.', MOCHA_DOMAIN), $title), false);
			return false;
		}

		?>
		<div style="width: 100%">
			<input type="hidden" id="msgstr_prefix" name="translations[0]" value="" />
			<?php
			for ($i = 0; $i < $po_count; $i++) {
				$style = (isset($fuzzy[$i])) ? 'border: 1px solid #f00;"' : '';
				?>
				<div>
						<strong>
						<?php
						if ($l = explode(' ', $locations[$i])) {
						  $location_files = $location_comments = array();
							foreach ($l as $location) {
							  if (preg_match('/(.*):(\d+)/', $location, $matches)) {
									$location_files[$matches[1]][] = $matches[2];
								}
							}
							foreach ($location_files as $location_file => $location_lines) {
								$location_comments[] = "$location_file:" . implode(',', $location_lines);
							}
							echo implode('<br />', $location_comments);
						}
						?>
						</strong>
				</div>
				<div>"<?= $originals[$i] ?>"</div>
				<div style="margin-bottom: 15px;"><textarea name="translations[<?= $i + 1 ?>]" rows="4" style="width: 100%;<?= $style ?>"><?= $translations[$i] ?></textarea></div>
				<?php
			}
			?>
			<div>
				<label for="mocha_po_charset">
					<strong><?php _e("Charset:", MOCHA_DOMAIN) ?></strong>
					<input type="text" name="mocha_po_charset" value="<?= $charset ?>" style="padding: 5px" />
				</label>
			</div>
		<div>
		<?php
	}
	
	function string_starts_with($string, $substring) {
		return ((false !== ($position = strpos($string, $substring))) && !$position);
	}
	
	function populate_po_from_mo($mo_file, $po_file) {
		if (!is_readable($mo_file)) {
			$this->error = sprintf(__('%s is not readable.', MOCHA_DOMAIN), $mo_file);
			return false;
		}

		$temp_file = ABSPATH . MOCHA_DIR . 'temp.po';
		if (!$this->exec_wrapper("{$this->path}msgunfmt $mo_file > $temp_file")) {
			return false;
		}
		$return = $this->exec_wrapper("{$this->path}msgmerge -o $po_file $temp_file $po_file");
		unlink($temp_file);
		return $return;
	}
	
	function update_po_data(& $po_contents, & $translations) {
		$i = 0;
		$mode = '';
		$header_parsed = false;
		foreach ($po_contents as $po_line) {
			if ($this->string_starts_with($po_line, 'msgstr')) {
				$updated_contents[] = 'msgstr "' . str_replace('\\\\\\', '\\\\', $translations[$i++]) . "\"\n";
				$mode = 'msgstr';
			} elseif ($this->string_starts_with($po_line, '"')) {
				if ($header_parsed && ('msgstr' == $mode)) {
					continue;
				} else {
					$updated_contents[] = $po_line;
				}
			} elseif ($header_parsed && ('#, fuzzy' == trim($po_line))) {
			  continue;
			} elseif ($this->string_starts_with($po_line, '#:')) {
				$updated_contents[] = $po_line;
				$header_parsed = true;
			} else {
				$updated_contents[] = $po_line;
				$mode = '';
			}
		}
		return $updated_contents;
	}
	
	function save_po_strings() {
	  global $current_user;

	  $host = $_SERVER['HTTP_HOST'];
	  $user_name = $current_user->user_nicename;
	  $email = "$user_name@$host";
		$locale = $_POST['mocha_po_locale'];
		$language = $_POST['mocha_po_language'];
		$type = $_POST['mocha_po_type'];
		$name = $_POST['mocha_po_name'];
		$charset = $_POST['mocha_po_charset'];
		$translations = $_POST['translations'];

		switch ($type) {
			case MOCHA_MODE_CORE:
			  $file_name = $locale;
				$dir = ABSPATH . MOCHA_CORE_PO_DIR;
			  break;

			case MOCHA_MODE_PLUGIN:
			  $file_name = "$name-$locale";
				$dir = ABSPATH . MOCHA_PLUGINS_DIR . "$name/";
			  break;

			case MOCHA_MODE_THEME:
			  $file_name = $locale;
				$dir = ABSPATH . MOCHA_THEMES_DIR . "$name/";
			  break;
		}
		$file = $dir . $file_name . '.po';
		$updated_contents = implode('', $this->update_po_data(file($file), $translations));
		$date = date('Y-m-d H:iO');
		$search = array('/charset=(.*)\\\\n/', '/Last-Translator: (.*)\\\\n/', '/PO-Revision-Date: (.*)\\\\n/');
		$replace = array ("charset=$charset\\n", "Last-Translator: $user_name <$email>\\n", "PO-Revision-Date: $date\\n");
    $updated_contents = preg_replace($search, $replace, $updated_contents);
		file_put_contents($file, $updated_contents);

		return ($this->exec_wrapper("{$this->path}msgfmt -D $dir -o $dir$file_name.mo $file_name.po"));
	}

	function print_j($data) {
		echo '<pre>' . print_r($data, true) . '</pre>';
	}
}

// Ajax Handler.
if (isset($_POST['mocha_ajax'])) {

	$mocha = new Mocha(true);

	// It's ok to call plugin_textdomain here because all other plugins have already been loaded by this time.
	load_plugin_textdomain(MOCHA_DOMAIN, MOCHA_DIR);

	switch ($_POST['mocha_action']) {
		case 'ajax_get_po_inputs':
		$mocha->get_po_inputs($_POST['locale'], $_POST['language'], $_POST['type'], $_POST['name']);
		break;
	}
	exit;
}

// PHP 4 Compatibility.  Thanks to kingler for the prompting.
if (!function_exists('file_put_contents')) {
	function file_put_contents($filename, $data) {
		if (!$handle = fopen($filename, 'w')) {
			trigger_error(__('Cannot open file for writing', MOCHA_DOMAIN), E_USER_ERROR);
		}		
		fputs($handle, $data);
		fclose($handle);
	}
}

if (!function_exists('file_get_contents')) {
	function file_get_contents($filename) {
		if (!$handle = fopen($filename, 'r')) {
			trigger_error(__('Cannot open file for reading', MOCHA_DOMAIN), E_USER_ERROR);
		}		
		$fcontents = fread($fhandle, filesize($filename));
		fclose($fhandle);
	}
}

$mocha = new Mocha();

/*
Autosubmit to central repository.
Plural Forms
LANGDIR
Breakdown WordPress core files?
Error checking matches %s...
Save po files in different charsets.
*/

?>