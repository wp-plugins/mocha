<?php 

$mocha->present_feedback();

// Populate the source options.
if ($gengo) {
	$languages = $gengo->languages ? $gengo->languages : '';
} else {
	$languages = '';
}

$themes = get_themes();
$plugins = get_plugins();

?>
<script type="text/javascript">
var mocha_script_uri = "<?= $mocha->site_url . '/' . MOCHA_DIR . 'mocha.php'; ?>";
var mocha_mode_core = <?= MOCHA_MODE_CORE ?>;
var mocha_mode_plugin = <?= MOCHA_MODE_PLUGIN ?>;
var mocha_mode_theme = <?= MOCHA_MODE_THEME ?>;
var mocha_names = new Array();
mocha_names[mocha_mode_core] = new Array('Empty', '');
mocha_names[mocha_mode_plugin] = new Array(<?php
$i = 0;
foreach ($plugins as $internal_name => $plugin) {
	if (false !== ($position = strpos($internal_name, '/'))) {
		$name = substr($internal_name, 0, $position);
	} else {
		continue;
	}
	if ($i++) echo ', '; echo '"' . $plugin['Name'] . '", "' . $name . '"';
}
?>);
mocha_names[mocha_mode_theme] = new Array(<?php
$i = 0;
foreach ($themes as $theme) { if ($i++) echo ', '; echo '"' . $theme['Name'] . '", "' . $theme['Template'] . '"'; }
?>);

if (typeof(sack) != 'undefined') {
	var mocha = new sack(mocha_script_uri);
	mocha.method = 'POST';
}

function mocha_filter_po_name() {
	var mocha_type = document.getElementById('mocha_po_type').options[document.getElementById('mocha_po_type').selectedIndex].value;
	var mocha_name = document.getElementById('mocha_po_name');

	if (mocha_mode_core == mocha_type) {
		document.getElementById('mocha_po_name_section').style.display = 'none';
	} else {
		document.getElementById('mocha_po_name_section').style.display = '';
	}
	var list = mocha_names[mocha_type];
	mocha_name.options.length = 0;
	for(i = 0; i < list.length; i += 2) {
		mocha_name.options[i / 2] = new Option(list[i], list[i + 1]);
	}
}

function mocha_update_po_strings() {
	var language_input = document.getElementById('mocha_po_language');
	if (language_input.type) {
		var mocha_language = language_input.value;
	} else {
		var mocha_language = language_input.options[language_input.selectedIndex].value;
	}

	var mocha_type = document.getElementById('mocha_po_type').options[document.getElementById('mocha_po_type').selectedIndex].value;
	var mocha_name = document.getElementById('mocha_po_name').options[document.getElementById('mocha_po_name').selectedIndex].value;
	if (document.getElementById('mocha_current_type').value) {
		if (!confirm('<?php _e('If you change your selection now, you will lose any changes you have made.  Continue and discard changes?', MOCHA_DOMAIN) ?>')) {
			return false;
		}
	}

	mocha.onCompletion = function() {
		document.getElementById('mocha_po_strings').innerHTML = mocha.response;
		document.getElementById('mocha_current_type').value = mocha_type;
		document.getElementById('mocha_po_submit').style.display = '';
	}
	document.getElementById('mocha_po_strings').innerHTML = '<?php _e('Loading Strings...', MOCHA_DOMAIN) ?>';
	mocha.runAJAX('mocha_ajax=true&mocha_action=ajax_get_po_inputs&locale=' + mocha_language + '&type=' + mocha_type + '&name=' + mocha_name);
}

</script>
<div class="wrap">
  <h2><?php _e('Localisations', MOCHA_DOMAIN) ?></h2>
  <form method="post">
  <fieldset class="options">
		<p>
    <input type="hidden" id="mocha_current_type" value="" />
		<div>
		<label for="mocha_po_language"><strong><?php _e("Locale:", MOCHA_DOMAIN) ?></strong>
			<?php
			if ($languages) {
				?>
				<select id="mocha_po_language" name="mocha_po_language">
				<?php
				foreach ($gengo->languages as $language) {
					?><option value=<?= $language->locale ?>><?= "$language->language ($language->locale)"?></option><?php
				}
				?>
				</select>
				<?php
			} else {
				?><input type="text" id="mocha_po_language" name="mocha_po_language" style="padding: 4px" /><?php
			}
			?>
		</strong></label>
		<label for="mocha_po_type"><strong><?php _e("Type:", MOCHA_DOMAIN) ?></strong>
			<select id="mocha_po_type" name="mocha_po_type" onchange="mocha_filter_po_name()">
			<option value="<?= MOCHA_MODE_CORE ?>"><?php _e("WordPress Core Files", MOCHA_DOMAIN) ?></option>
			<option value="<?= MOCHA_MODE_PLUGIN ?>"><?php _e("Plugins", MOCHA_DOMAIN) ?></option>
			<option value="<?= MOCHA_MODE_THEME ?>"><?php _e("Themes", MOCHA_DOMAIN) ?></option>
			</select>
		</strong></label>
		<span id="mocha_po_name_section" style="display: none">
		<label for="mocha_po_name"><strong><?php _e("Name:", MOCHA_DOMAIN) ?></strong>
		<select id="mocha_po_name" name="mocha_po_name"><option value=''>Empty</option></select>
		</strong></label>
		</span>
		</div>
		<span class="submit">
    <input type="button" value="<?php _e('Get Strings', MOCHA_DOMAIN); ?>" onclick="mocha_update_po_strings()" />
    </span>
    </p>
		<div id="mocha_po_strings">
		<?php _e("Choose a locale and localisation type to begin translating.", MOCHA_DOMAIN); ?>
		</div>
		<p class="submit">
      <input type="submit" id="mocha_po_submit" name="mocha_po_submit" value="<?php _e('Save localisation &raquo;', MOCHA_DOMAIN); ?>" style="display: none" onclick="if (!document.getElementById('msgstr_prefix')) return false;" />
    </p>
  </fieldset>
  </form>
</div>