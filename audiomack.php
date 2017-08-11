<?php
/*
  Plugin Name: Audiomack
  Plugin URI: http://www.audiomack.com/wordpress/
  Description: Audiomack is the place for artists to effortlessly share their music and for fans to discover and download free songs and albums.
  Version: 1.2.3
  Author: Audiomack.com
  Author URI: http://audiomack.com
  License: GPL2
 */

// Developed by Svetoslav Marinov (SLAVI) | orbisius.com for Audiomack
// use widgets_init action hook to execute custom function
add_action('init', 'audiomack_init');

add_action('admin_init', 'audiomack_admin_init');
add_action('admin_menu', 'audiomack_create_menu');

/**
 * Setups loading of assets (css, js - if any), registers shortcode and as oembed provider.
 * @return void
 */
function audiomack_init() {
    add_shortcode('audiomack', 'audiomack_shortcode_audiomack');

   /*
   * Audiomack embed
   * example URL: http://www.audiomack.com/song/360-media-uk/foreign-remix
   * @see https://gist.github.com/jkudish/bc5ba7387f6382e01c88
   */
   // Register oEmbed provider
   // The WP user will need to only paste a link to a song or album and the audio player will be generated.
   wp_oembed_add_provider( '#https?://(www.)?audiomack.com/([^/]+)/([^/]+)/([^/]+)[/]{0,1}#i', 'https://audiomack.com/oembed', true );
   wp_oembed_add_provider( '#https?://(www.)?audiomack.com/song/([^/]+)/([^/]+)[/]{0,1}#i', 'https://audiomack.com/oembed', true );
   wp_oembed_add_provider( '#https?://(www.)?audiomack.com/album/([^/]+)/([^/]+)[/]{0,1}#i', 'https://audiomack.com/oembed', true );
}

/**
 * Setups some actions only needed for WP admin.
 * @return void
 */
function audiomack_admin_init() {
    audiomack_load_assets();
    audiomack_setup_editor_buttons();
}

/**
 * Setups the actions that are used in the admin such as
 * a button in the rich text editor.
 */
function audiomack_setup_editor_buttons() {
    // Add only in Rich Editor mode
    if (get_user_option('rich_editing') == 'true') {
        add_filter("mce_external_plugins", "audiomack_add_tinymce_plugin", 5);
        add_filter('mce_buttons', 'audiomack_register_button', 5);

        // Required by TinyMCE button
        add_action('wp_ajax_audiomack_ajax_render_popup_content', 'audiomack_ajax_render_popup_content');
        add_action('wp_ajax_audiomack_ajax_render_popup_content', 'audiomack_ajax_render_popup_content');
    }
}

/**
 * Registers a rich text editor button in wordpress 2.5x editor
 * 
 * @param array $plugin_array
 * @return array
 */
function audiomack_add_tinymce_plugin($plugin_array) {
    $suffix = audiomack_get_asset_suffix();
    $plugin_array['tinymce_audiomack'] = plugins_url("tinymce/editor_plugin{$suffix}.js", __FILE__);

    return $plugin_array;
}

/**
 * Registers a rich text editor button in wordpress 2.5x editor
 * 
 * @param array $buttons
 * @return array
 */
function audiomack_register_button($buttons) {
    array_push($buttons, "separator", 'tinymce_audiomack');

    return $buttons;
}

/**
 * Returns some plugin data such name and URL. This info is inserted as HTML
 * comment surrounding the embed code.
 * @return array
 */
function audiomack_get_plugin_data() {
    // pull only these vars
    $default_headers = array(
        'Name' => 'Plugin Name',
        'PluginURI' => 'Plugin URI',
    );

    $plugin_data = get_file_data(__FILE__, $default_headers, 'plugin');

    $url = $plugin_data['PluginURI'];
    $name = $plugin_data['Name'];

    $data['name'] = $name;
    $data['url'] = $url;

    return $data;
}

/**
 * This function processes [audiomack src=""] shortcode and replaces it with Audiomack player.
 * It expects the src to contain album or song prefix e.g.
 * - http://www.audiomack.com/song/hiphopfeeling/nowish
 * - http://www.audiomack.com/album/tutankhamun-brothers/whats-a-black-beatle
 * @param array $attr
 * @return string
 */
function audiomack_shortcode_audiomack($attr = array()) {
    $plugin_data = audiomack_get_plugin_data();
    $opts = audiomack_get_options();

    // should be like this.
    // - http://www.audiomack.com/song/djsemtex/say-my-name-kendrick-lamar-response
    // - http://www.audiomack.com/album/tutankhamun-brothers/whats-a-black-beatle
    $src = empty($attr['src']) ? '' : $attr['src'];

    $buff = '';
    $buff .= "\n<!-- audiomack | {$plugin_data['name']} | {$plugin_data['url']} -->\n";

    // Embed source needs to be like this
    // http://www.audiomack.com/embed3/hiphopfeeling/nowish?c1=fc881e&bg=f2f2f2&c2=222222
    // http://www.audiomack.com/embed3-album/tutankhamun-brothers/whats-a-black-beatle?c1=fc881e&bg=f2f2f2&c2=222222
    $embed_src = $src;
    $width = empty($opts['width']) ? '100%' : $opts['width']; // % or a number
    $height = 110;
    $embed_ver_prefix = 'embed4';

    /*
     * The height of the player based on the embedded media
     * - Album - 352px
     * - Song Regular - 144px
     * - Song Slim - 62px
     */
    if (stripos($embed_src, '/song/') !== false) { // song
        if ($opts['player_style'] == 'thin') {
            $height = 62;
            $embed_src = str_replace('/song/', "/$embed_ver_prefix-thin/", $embed_src);
        } elseif ($opts['player_style'] == 'large') {
            $height = 250;
            $embed_src = str_replace('/song/', "/$embed_ver_prefix-large/", $embed_src);
        } else {
            $height = 110;
            $embed_src = str_replace('/song/', "/$embed_ver_prefix/", $embed_src);
        }
    } else {
        $height = 352;
        $embed_src = str_replace('/album/', "/$embed_ver_prefix-album/", $embed_src);
    }

    // the embed code expects the colours not to have pound signs
    // tmp deactivate color customizations.
    /*$player_opts['c1'] = $opts['player_color'];
    $player_opts['c2'] = $opts['text_color'];
    $player_opts['bg'] = $opts['background_color'];

    $player_params = http_build_query($player_opts);
    $embed_src .= '?' . $player_params;*/

    $height_str = "height='$height'";
    $embed_code = "<iframe src='$embed_src' scrolling='no' width='$width' $height_str scrollbars='no' frameborder='0'></iframe>\n";

    $buff .= "<div class='audiomack_player_container'>\n";
    $buff .= $embed_code;
    $buff .= "</div> <!-- /audiomack_player_container -->\n";

    $buff .= "\n<!-- /audiomack | {$plugin_data['name']} | {$plugin_data['url']} -->\n";

    return $buff;
}

/**
 * This functions returns .min suffix for live installations and none on dev machine.
 * The idea is to load different css/js files depending on the environment.
 * e.g. for live: use main.min.js and dev main.js.
 * Minified version should load faster.
 */
function audiomack_get_asset_suffix() {
    $dev = empty($_SERVER['DEV_ENV']) ? 0 : 1;
    $suffix = $dev ? '' : '.min';

    return $suffix;
}

/**
 * Schdules css, js for loading when WP is ready.
 */
function audiomack_load_assets() {
    $suffix = audiomack_get_asset_suffix();

    wp_enqueue_script('jquery');

    //Access the global $wp_version variable to see which version of WordPress is installed.
    global $wp_version;

    $color_picker = version_compare($wp_version, '3.5') >= 0 ? 'wp-color-picker' // new WP
            : 'farbtastic'; // old WP

    wp_enqueue_style($color_picker);
    wp_enqueue_script($color_picker);

    wp_register_style('audiomack_css', plugins_url("/assets/main{$suffix}.css", __FILE__));
    wp_enqueue_style('audiomack_css');

    wp_register_script('audiomack_js', plugins_url("/assets/main{$suffix}.js", __FILE__), array('jquery',), '1.0', true);
    wp_enqueue_script('audiomack_js');
}

/**
 * Adds the menu under Settings > Audiomack
 */
function audiomack_create_menu() {
    //create a submenu under Settings
    add_options_page('Audiomack', 'Audiomack', 'manage_options', __FILE__, 'audiomack_settings_page');

    // when plugins are shown add a settings link near my plugin for a quick access to the settings page.
    add_filter('plugin_action_links', 'audiomack_add_plugin_settings_link', 10, 2);
}

// Add the ? settings link in Plugins page very good
function audiomack_add_plugin_settings_link($links, $file) {
    if ($file == plugin_basename(__FILE__)) {
        $link = admin_url('options-general.php?page=' . plugin_basename(__FILE__));
        $dashboard_link = "<a href=\"{$link}\">Settings</a>";
        array_unshift($links, $dashboard_link);
    }

    return $links;
}

/**
 * Loads the options for the current plugin. If the some variables do not exist
 * defaults will be used instead.
 *
 * @params void
 * @return array
 */
function audiomack_get_options() {
    $defaults = array(
        'width' => '100%',
        'player_color' => 'fc881e',
        'background_color' => 'f2f2f2',
        'text_color' => '222222',
        'player_style' => 'large', // for songs only; new since 1.2.1
        'slim' => '', // for songs only ; not used anymore.
    );

    // if you change the key update the uninstall.php too
    $current_options = get_option('audiomack_options', $defaults);
    $current_options = array_merge($defaults, $current_options);

    // Let's take care of old users who have used the 'slim' player option.
    // We'll remove that option and set 'player_style' property to 'thin'
    if (!empty($current_options['slim'])) {
        $current_options['slim'] = '';
        $current_options['player_style'] = 'thin';
    }

    return $current_options;
}

/**
 * Saving options. Options are passed in an array. They should have been
 * filtered and cleaned already.
 * 
 * @param array $opts
 * @return array
 */
function audiomack_set_options($opts) {
    // let's do some cleanup
    foreach ($opts as $key => $value) {
        $value = wp_kses($value, array());
        $value = trim($value);

        $opts[$key] = $value;
    }

    update_option('audiomack_options', $opts);

    return $opts;
}

// Generates Options for the plugin
function audiomack_settings_page() {
    $saved = 0;

    $current_options = audiomack_get_options();

    if (!empty($_POST)) {
        // this is a checkbox so if no value is passed we'll assume it's unchecked.
        
        $current_options_keys = array_keys($current_options);

        foreach ($_REQUEST as $key => $value) {
            // Is the current variable expected (part of options array) ?
            if (in_array($key, $current_options_keys)) {
                $value = wp_kses($value, array());
                $value = trim($value);

                // Are we processing a color field? We need hex color
                if (strpos($key, 'color') !== false) {
                    $value = preg_replace('#[^a-z0-9]#si', '', $value); // clean up non alpha nums
                    // if nothing is left then the user is being lazy and didn't enter the color
                    // correctly so we'll skip it.
                    if (empty($value)) {
                        continue;
                    }
                } elseif ($key == 'width') { // this could be 100% or 250
                    $value = preg_replace('#[^0-9%]#si', '', $value);
                    $value = empty($value) ? '100%' : $value;
                }

                $current_options[$key] = $value;
            }
        }

        $current_options = audiomack_set_options($current_options);

        $saved = 1;
    }
    ?>

<div class="wrap audiomack_container">
	<div id="icon-options-general" class="icon32"></div>
    <h2>Audiomack</h2>
	<p>
        This plugin allows you to embed a song or an album from <a href="http://www.audiomack.com/?utm_source=audiomack_plugin&utm_medium=plugin_settings" target="_blank">Audiomack</a> on your site.
    </p>
    
    <?php if (!empty($saved)) : ?>
        <div class="updated">
            <p>Settings were saved.</p>
        </div>
    <?php endif; ?>

	<div id="poststuff">

		<div id="post-body" class="metabox-holder columns-2">

			<!-- main content -->
			<div id="post-body-content">

				<div class="meta-box-sortables ui-sortable">

					<div class="postbox">
						<!--<h3><span>Settings</span></h3>-->
						<div class="inside">
                            <form method="post">
                                <table class="widefat form-table000">
                                    <tr valign="top">
                                        <td valign="top" width="50%">
                                            <!--<h2>Player Settings</h2>-->

                                            <!-- Settings Table -->
                                            <table class="widefat0">
                                                <?php if (0) : /* D asked me to hide these just for now */ ?>
                                                    <tr valign="top">
                                                        <th scope="row"><label for="player_color">Player Color:</label></th>
                                                        <td><input maxlength="10" size="4" id="player_color" name="player_color"
                                                                   autocomplete="off"
                                                                   value="#<?php echo esc_attr($current_options['player_color']); ?>" />
                                                            <div id="player_color_picker"></div>
                                                        </td>
                                                    </tr>
                                                    <tr valign="top">
                                                        <th scope="row"><label for="background_color">Player Background Color:</label></th>
                                                        <td><input maxlength="10" size="4" id="background_color" name="background_color"
                                                                   autocomplete="off"
                                                                   value="#<?php echo esc_attr($current_options['background_color']); ?>" />
                                                            <div id="background_color_picker"></div>
                                                        </td>
                                                    </tr>
                                                    <tr valign="top">
                                                        <th scope="row"><label for="text_color">Text Color:</label></th>
                                                        <td><input maxlength="10" size="4" id="text_color" name="text_color"
                                                                   autocomplete="off"
                                                                   value="#<?php echo esc_attr($current_options['text_color']); ?>" />
                                                            <div id="text_color_picker"></div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>

                                                <tr valign="top">
                                                    <th scope="row"><label for="thin">Song Player Style</label></th>
                                                    <td>

                                                        <input id="app_player_thin" name="player_style" type="radio" value="thin"
                                                            <?php checked('thin', $current_options['player_style']); ?> />
                                                            <label for="app_player_thin"> Thin</label> <br/>

                                                        <input id="app_player_standard" name="player_style" type="radio" value="standard"
                                                            <?php checked('standard', $current_options['player_style']); ?> />
                                                            <label for="app_player_standard"> Standard</label> <br/>
                                                            
                                                        <input id="app_player_large" name="player_style" type="radio" value="large"
                                                            <?php checked('large', $current_options['player_style']); ?> />
                                                            <label for="app_player_large"> Large</label> <br/>
                                                    </td>
                                                </tr>

                                                <tr valign="top">
                                                    <th scope="row"><label for="width">Width (% or number):</label></th>
                                                    <td>&nbsp;&nbsp;<input maxlength="10" size="4" id="width" name="width"
                                                                           autocomplete="off"
                                                                           value="<?php echo esc_attr($current_options['width']); ?>" />

                                                        e.g. 100% or 250 (&larr; in pixels)
                                                    </td>
                                                </tr>
                                                
                                                <tr valign="top">
                                                    <td>
                                                        <input type="submit" name="save_settings" value="Save Changes" class="button-primary" />
                                                    </td>
                                                </tr>
                                            </table>
                                            <!-- /Settings Table -->
                                        </td>
                                        <td valign="top">
                                            <div>
                                                <label for="">
                                                To see the preview please save the settings.</label>
                                            </div>

                                            <div class="">
                                                <p>
                                                    <?php //echo do_shortcode('[audiomack src="http://www.audiomack.com/song/hiphopfeeling/nowish"]');   ?>
                                                    <?php echo do_shortcode('[audiomack src="http://www.audiomack.com/song/nas/let-nas-down-remix-feat-nas"]'); ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </form>

						</div> <!-- .inside -->

					</div> <!-- .postbox -->

                    <div class="postbox">
                        <h3><span>Usage</span></h3>
						<div class="inside">
                            <div class="audiomack_help hide00">
                                 <p>
                                     You can use the plugin in several ways. <br/>

                                     1. Click on this icon: <img src="<?php echo plugins_url('/tinymce/icon.jpg', __FILE__); ?>" alt="" /> in edit post/page
                                     <br/>OR
                                     <br/>2. Paste the shortcodes below with <strong>src</strong> attribute pointing to a song or an album.
                                     The plugin will generate the necessary embed code.
                                     <!--<br/>OR
                                     <br/>3. JetPack (with activated Shortcode Embeds) or WordPress.com users: Just paste the link to a song/album
                                     http://www.audiomack.com/song/nas/let-nas-down-remix-feat-nas-->
                                 </p>
                                 <p>
                                    <table class="widefat">
                                        <tr>
                                            <td><strong>[audiomack src="http://www.audiomack.com/song/nas/let-nas-down-remix-feat-nas"]</strong></td>
                                            <td>&larr; This will generate the embed code for a song</td>
                                        </tr>
                                        </tr>
                                        <td><strong>[audiomack src="http://www.audiomack.com/album/tutankhamun-brothers/whats-a-black-beatle"]</strong></td>
                                        <td>&larr; This will generate the embed code for an album</td>
                                        </tr>
                                    </table>
                                 </p>
                             </div>
						</div> <!-- .inside -->
					</div> <!-- .postbox -->
                    
				</div> <!-- .meta-box-sortables .ui-sortable -->

			</div> <!-- post-body-content -->

			<!-- sidebar -->
			<div id="postbox-container-1" class="postbox-container">
				<div class="meta-box-sortables">
					<div class="postbox">
                        <h3><span>Support &amp; Feature Requests</span></h3>
						<div class="inside">
                            If you have suggestions or run into an issue please email us at
                                <a href="mailto:support@audiomack.com?subject=audiomack wp plugin">support@audiomack.com</a>
						</div> <!-- .inside -->
					</div> <!-- .postbox -->

					<div class="postbox">
                        <h3><span>Video Demo</span></h3>
						<div class="inside">
                            <div>
                                <div class="audiomack_plugin_demo hide0">
                                    <a href="http://www.youtube.com/watch?v=dA3PU91jf0c&feature=youtu.be" class="button-primary"
                                                        target="_blank">View Demo</a>
                                </div>
                            </div>
						</div> <!-- .inside -->
					</div> <!-- .postbox -->

					<div class="postbox">
                        <h3><span>Social Media</span></h3>
						<div class="inside">
                            <div>
                                <a class="twitter-follow-button"
                                    href="https://twitter.com/audiomack"
                                    data-show-count="false"
                                    data-lang="en">
                                  Follow @Audiomack
                                  </a>
                                  <script type="text/javascript">
                                  window.twttr = (function (d, s, id) {
                                    var t, js, fjs = d.getElementsByTagName(s)[0];
                                    if (d.getElementById(id)) return;
                                    js = d.createElement(s); js.id = id;
                                    js.src= "https://platform.twitter.com/widgets.js";
                                    fjs.parentNode.insertBefore(js, fjs);
                                    return window.twttr || (t = { _e: [], ready: function (f) { t._e.push(f) } });
                                  }(document, "script", "twitter-wjs"));
                                  </script>
                            </div>
                            <div>
                                <iframe src="//www.facebook.com/plugins/likebox.php?href=https%3A%2F%2Fwww.facebook.com%2Faudiomack&amp;width=250&amp;height=258&amp;colorscheme=light&amp;show_faces=true&amp;header=false&amp;stream=false&amp;show_border=false&amp;appId=1514354758831453" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:250px; height:258px;" allowTransparency="true"></iframe>
                            </div>
						</div> <!-- .inside -->
					</div> <!-- .postbox -->

					<div class="postbox">
                        <h3><span>Share</span></h3>
						<div class="inside">
                             <?php
                                $plugin_data = get_plugin_data(__FILE__);

                                $app_link = urlencode($plugin_data['PluginURI']);
                                $app_title = urlencode($plugin_data['Name']);
                                $app_descr = urlencode($plugin_data['Description']);
                                ?>
                                <p>
                                    <!-- AddThis Button BEGIN -->
                                <div class="addthis_toolbox addthis_default_style addthis_32x32_style">
                                    <a class="addthis_button_facebook" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_twitter" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_google_plusone" g:plusone:count="false" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_linkedin" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_email" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <!--<a class="addthis_button_myspace" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>-->
                                    <a class="addthis_button_google" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_digg" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_delicious" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_stumbleupon" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_tumblr" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_favorites" addthis:url="<?php echo $app_link ?>" addthis:title="<?php echo $app_title ?>" addthis:description="<?php echo $app_descr ?>"></a>
                                    <a class="addthis_button_compact"></a>
                                </div>
                                <!-- The JS code is in the footer -->

                                <script type="text/javascript">
                                        var addthis_config = {"data_track_clickback": true};
                                        var addthis_share = {
                                            templates: {twitter: 'Check out {{title}} #WordPress #plugin at {{lurl}} (via @Audio_Mack)'}
                                        }
                                </script>
                                <!-- AddThis Button START part2 -->
                                <script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=lordspace"></script>
                                <!-- AddThis Button END part2 -->
						</div> <!-- .inside -->
					</div> <!-- .postbox -->
				</div> <!-- .meta-box-sortables -->
			</div> <!-- #postbox-container-1 .postbox-container -->
		</div> <!-- #post-body .metabox-holder .columns-2 -->

		<br class="clear">
	</div> <!-- #poststuff -->

</div> <!-- .wrap -->
    <?php
}

/**
 * Are we running WP 3.9 or higher?
 * We need this because some of the TinyMCE API has changed.
 */
function audiomack_39up() {
    global  $wp_version;
    $wp_3_9_plus = floatval($wp_version) >= 3.9;

    return $wp_3_9_plus ? 1 : 0;
}

/**
 * This is triggered by editor_plugin.min.js and WP proxies the ajax calls to this action.
 *
 * @return void
 */
function audiomack_ajax_render_popup_content() {
    // check for rights
    if (!is_user_logged_in()) {
        wp_die(__("You must be logged in order to use this plugin."));
    }

    $site_url = site_url();
    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
            <title>Audiomack</title>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <script language="javascript" type="text/javascript" src="<?php echo $site_url; ?>/wp-includes/js/jquery/jquery.js"></script>

            <script language="javascript" type="text/javascript" src="<?php echo $site_url; ?>/wp-includes/js/tinymce/tiny_mce_popup.js"></script>
            <script language="javascript" type="text/javascript" src="<?php echo $site_url; ?>/wp-includes/js/tinymce/utils/mctabs.js"></script>
            <script language="javascript" type="text/javascript" src="<?php echo $site_url; ?>/wp-includes/js/tinymce/utils/form_utils.js"></script>

            <script language="javascript" type="text/javascript">
                var audiomack = {
                    is_new_wp : <?php echo audiomack_39up(); ?>,
                template : '<p>[audiomack src="%%AUDIO_SRC_URL%%"]</p><br/>',
                        /**
                         *
                         */
                        init : function() {
                    tinyMCEPopup.resizeToInnerSize();
                    document.getElementById('audiomack_audio_src_url').focus();
                },

                close : function () {
                    if (this.is_new_wp) {
                        top.tinymce.activeEditor.windowManager.close();
                    } else {
                        tinyMCEPopup.close();
                    }
                },
                        /**
                         * This is necessary to be setup later because the data comes from AJAX.
                         */
                        setup_result_item_select : function(audio_src_url) {
                    // This handles the search results. When any of them is clicked
                    // it will generate the shortcode and insert it in the post/page
                    // the button uses a data attribute to store url param.
                    jQuery('.result_item_select').on('click', function() {
                        var audio_src_url = jQuery(this).data('url');
                        var content = audiomack.generate_shortcode(audio_src_url);
                        audiomack.insert_content(content);
                    });
                },
                        /**
                         * Generates the shortcode that WP plugin understands.
                         * @param string audio_src_url
                         * @returns string
                         */
                        generate_shortcode : function(audio_src_url) {
                    var content = audiomack.template;
                    content = content.replace(/%%AUDIO_SRC_URL%%/ig, audio_src_url);

                    return content;
                },
                        insert_content : function(content) {

                    if (this.is_new_wp) {
                        parent.tinyMCE.execCommand('mceInsertContent', false, content);
                    } else if (window.tinyMCE) {
                        window.tinyMCE.execInstanceCommand('content', 'mceInsertContent', false, content);
                        //Peforms a clean up of the current editor HTML.
                        //tinyMCEPopup.editor.execCommand('mceCleanup');
                        //Repaints the editor. Sometimes the browser has graphic glitches.
                        tinyMCEPopup.editor.execCommand('mceRepaint');
                    }

                    this.close();
                },
                        /* // formatted using: http://jsonformatter.curiousconcept.com/
                         the response JSON should look like this:
                         some_callback({
                         "error":"",
                         "status":1,
                         "total_found":"10",
                         "start":1,
                         "matches":[
                         {
                         "title":"The Rockwellington ft. Moses Rockwell",
                         "artist":"Jinesis",
                         "uploader":"BarrelhouseBKLYN",
                         "url":"song\/barrelhousebklyn\/the-rockwellington-ft-moses-rockwell",
                         "type":"song",
                         "url_slug":"the-rockwellington-ft-moses-rockwell",
                         "uploader_url_slug":"barrelhousebklyn"
                         },
                         {
                         "title":"Marco Polo & Ruste Juxx - 'The Exxecution: Live From The ROC'",
                         "artist":"ACT LIVE MUSIC",
                         "uploader":"ACT LIVE MUSIC",
                         "url":"album\/act-live-music\/marco-polo-ruste-juxx-the-exxecution-live-from-the-roc",
                         "type":"album",
                         "url_slug":"marco-polo-ruste-juxx-the-exxecution-live-from-the-roc",
                         "uploader_url_slug":"act-live-music"
                         },
                         {
                         "title":"Moses Rockwell - 'The Nervous Wreck'd Tape'",
                         "artist":"ACT LIVE MUSIC",
                         "uploader":"ACT LIVE MUSIC",
                         "url":"album\/act-live-music\/moses-rockwell-the-nervous-wreckd-tape",
                         "type":"album",
                         "url_slug":"moses-rockwell-the-nervous-wreckd-tape",
                         "uploader_url_slug":"act-live-music"
                         }
                         ],
                         "total":10
                         })
                         */

                        /**
                         * This method is called after a success reply from Audiomack servers.
                         * It iterates over the results and creates nice result boxes which the use can click
                         * and it will insert the shortcode within the post/page.
                         * The data is appended using jQuery to '.results' container.
                         *
                         * @param json
                         * @returns void
                         */
                        search_render_results : function(json) {
                    audiomack.loader(0);

                    if (json.status) {
                        jQuery.each(json.matches, function(index, item_rec) {
                            var result_item_buff = '';
                            var link = item_rec.url;
                            var title = item_rec.title;
                            var audiomack_ssl_active = false; // change to true when Audiomack.com has ssl certificate.
                            var ssl = <?php echo is_ssl() ? 1 : 0; ?>;

                            // Let's add the URL prefix if it doesn't exist
                            if (link.indexOf('://') == -1) {
                                var prefix = ssl && audiomack_ssl_active ? 'https://' : 'http://';
                                link = prefix + 'www.audiomack.com/' + link;
                            }

                            if (title.length > 32) {
                                title = title.substring(0, 30) + '&hellip;';
                            }

                            result_item_buff += '<div class="result_item_wrapper">';
                            result_item_buff += '<div class="result_item">';
                            //result_item_buff += '<h3><a href="' + link + '" taget="_blank" title="Opens in a new window/tab">' + item_rec.title + '</a></h3>';
                            result_item_buff += '<h3 title="' + item_rec.title + '">' + title + '</h3>';
                            result_item_buff += '<div class="artist">' + item_rec.artist + '</div>';
                            result_item_buff += '<div class="type">Type: ' + item_rec.type + '</div>';
                            result_item_buff += '</div> <!-- /result_item -->';

                            // create the Select button
                            result_item_buff += '<div class="select_wrapper">'
                                    + '<input type="button" class="result_item_select app_positive_button mceButton" '
                                    + 'data-url="' + link + '" value="Select" /> </div><br/>';

                            result_item_buff += '</div> <!-- /result_item_wrapper -->';

                            jQuery('.results').append(result_item_buff);
                        });

                        audiomack.setup_result_item_select();
                    } else {
                        jQuery('.results').html('Error retrieving search results.');
                    }
                },
                        search_render_error : function(json) {
                    audiomack.loader(0);
                    jQuery('.results').html('Error retrieving the search results (API).');
                },
                        /**
                         * Shows/hides loading text
                         * @param {type} json
                         * @returns {undefined}
                         */
                        loader: function(status) {
                    if (status) {
                        jQuery('.results').html('Loading...');
                    } else {
                        jQuery('.results').html('');
                    }
                },
                        /**
                         * Sends a JSONP request to audiomack servers to get some song/album info
                         * @param {type} params_obj
                         * @returns {undefined}
                         */
                        search : function(params_obj) {
                    params_obj = params_obj || {
                        keywords: jQuery('#audiomack_search_kwd').val().trim(),
                        limit: 3 * 10, // 3 results per row so we want N rows
                    };

                    audiomack.loader(1);

                    jQuery.ajax({
                        type: 'GET',
                        url: 'http://www.audiomack.com/api/music/search?callback=?',
                        async: false,
                        //jsonpCallback: 'foo',
                        success: audiomack.search_render_results,
                        error: audiomack.search_render_error,
                        contentType: 'application/json',
                        dataType: 'jsonp',
                        data: params_obj
                    });
                },
                        // http://www.tipsandtricks-hq.com/ecommerce/simple-wp-shopping-cart-installation-usage-290
                        handle_regular_embed : function() {
                    var content = '';

                    var tab = document.getElementById('audiomack_panel');
                    var search_tab = document.getElementById('audiomack_search_panel');

                    // what tab is active?
                    if (tab.className.indexOf('current') != - 1) {
                    var audio_src_url = document.getElementById('audiomack_audio_src_url').value;
                            // Let's do some cleanup
                            audio_src_url = audio_src_url.replace(/[<>]/g, '').replace(/[\r\n]/g, '').replace(/^\s*/g, '').replace(/\s*$/g, '');
                            audio_src_url_lc = audio_src_url.toLowerCase();
                            // Validations. Empty source or doesn't have http://
                            if (audio_src_url == '' || audio_src_url.indexOf('://') == - 1) {
                    jQuery('#audiomack_audio_src_status').html('Please enter a valid song/album source web link').addClass('error');
                            //alert('Please enter a valid song/album source web link');
                            document.getElementById('audiomack_audio_src_url').focus();
                            return false;
                    } else if (audio_src_url_lc.indexOf('audiomack.com') == - 1) {
                    //alert("The entered link does't point to audiomack.");
                    jQuery('#audiomack_audio_src_status').html("The entered link does't point to audiomack.").addClass('error');
                            document.getElementById('audiomack_audio_src_url').focus();
                            return false;
                    }

                    content = audiomack.generate_shortcode(audio_src_url);
                    } else if (search_tab.className.indexOf('current') != -1) {
                            // not used
                        }

                        audiomack.insert_content(content);

                        return ;
                    }
                };
            </script>
            <script type="text/javascript">
                            jQuery.noConflict();
                            jQuery(document).ready(function($) {
                    audiomack.init();
                });</script>
            <style>
                /* Overiding defaults */
                .panel_wrapper div.current {
                    min-height: 500px;
                    height: auto;
                }
                
                body, html, #audiomack_form .tabs li a {
                    font-size: 14px !important;
                }

                .audiomack_tinymce_plugin .error {
                    color:red;
                }

                .audiomack_tinymce_plugin .success {
                    color:green;
                }

                .audiomack_tinymce_plugin .app_positive_button {
                    background:#99CC66 !important;
                }

                .audiomack_tinymce_plugin .app_negative_button {
                    background:#F19C96 !important;
                }

                .audiomack_tinymce_plugin .app_max_width {
                    width: 100%;
                }

                .audiomack_tinymce_plugin .app_text_field {
                    border: 1px solid #888888;
                    padding: 3px;
                    font-size: 14px;
                }

                .audiomack_tinymce_plugin #audiomack_search {
                    font-size: 12px;
                    margin: 2px 0;
                    padding: 2px 0;
                    font-size: 14px;
                }

                /* the result container */
                .audiomack_tinymce_plugin .results {
                    min-height: 250px;
                    overflow-y: auto;
                }

                .audiomack_tinymce_plugin .result_item {
                    height: 65px;
                    overflow: hidden;
                }

                .audiomack_tinymce_plugin .result_item_wrapper {
                    display: inline-block;
                    width: 30%;
                    height: 95px;
                    min-height: 50;
                    margin: 1%;
                    border: 1px solid #888888;
                    padding: 2px;
                    text-align: center;
                }

                .audiomack_tinymce_plugin .result_item_wrapper:hover {
                    border: 1px solid #777;
                    background: #FFFF99;
                }

                .audiomack_tinymce_plugin .mceActionPanel {
                    margin: 15px 0;
                }

                .audiomack_tinymce_plugin .select_wrapper {
                    text-align: center;
                    clear: both;
                    margin: 5px 0;
                }
            </style>
            <base target="_self" />
        </head>
        <body id="audiomack_tinymce_plugin" class="audiomack_tinymce_plugin">
            <form id="audiomack_form" name="audiomack_form" action="#">
                <div class="tabs">
                    <ul>
                        <li id="audiomack_tab" class="current"><span>
                                <a href="javascript:void(0);"
                                   onclick="mcTabs.displayTab('audiomack_tab','audiomack_panel');return false;"><?php _e("Audiomack", 'audiomack'); ?></a></span></li>
                        <li id="audiomack_search_tab"><span>
                                <a href="javascript:void(0);"
                                   onclick="mcTabs.displayTab('audiomack_search_tab','audiomack_search_panel');return false;"><?php _e("Search", 'audiomack'); ?></a></span></li>
                    </ul>
                </div>

                <div class="panel_wrapper">
                    <!-- audiomack_panel -->
                    <div id="audiomack_panel" class="panel current">
                        <p>You can paste the web link to an album or song and this window will generate the shortcode for you.</p>
                        <table border="0" cellpadding="4" cellspacing="0">
                            <tr>
                                <td nowrap="nowrap" width="25%">
                                    <label for="audiomack_audio_src_url"><?php _e("Song/Album Link", 'audiomack'); ?></label>
                                </td>
                                <td>
                                    <input type="text" id="audiomack_audio_src_url" name="audiomack_audio_src_url" value=""
                                           autocomplete='off'
                                           class='app_max_width app_text_field' />
                                    <div id='audiomack_audio_src_status'></div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    Example: http://www.audiomack.com/song/nas/let-nas-down-remix-feat-nas
                                    <br/>Example: http://www.audiomack.com/album/tutankhamun-brothers/whats-a-black-beatle
                                </td>
                            </tr>
                        </table>

                        <div class="mceActionPanel">
                            <div style="float: left;">
                                <input type="button" id="audiomack_insert" name="insert" class='app_positive_button mceButton'
                                       value="<?php _e("Insert", 'audiomack'); ?>" onclick="audiomack.handle_regular_embed();" />
                            </div>

                            <div style="float: right;">
                                <input type="button" id="cancel" name="cancel" class='app_negative_button'
                                       value="<?php _e("Close", 'audiomack'); ?>" onclick="audiomack.close();" />
                            </div>
                        </div>
                    </div> <!-- /end audiomack_panel -->

                    <!-- audiomack_search_panel -->
                    <div id="audiomack_search_panel" class="panel">
                        <p>Search for an album or song. Type a keyword.</p>
                        <table border="0" cellpadding="4" cellspacing="0">
                            <tr>
                                <td nowrap="nowrap" width="25%">
                                    <label for="audiomack_search_kwd"><?php _e("Keyword", 'audiomack'); ?></label>
                                </td>
                                <td>
                                    <input type="text" id="audiomack_search_kwd" name="audiomack_search_kwd" value=""
                                           autocomplete='off' class='app_text_field' />

                                    <input type="button" id="audiomack_search" name="audiomack_search" class='app_positive_button mceButton'
                                           value="<?php _e("Search", 'audiomack'); ?>" onclick="audiomack.search();" />
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <div class="results">
                                        Example: moses rockwell
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div> <!-- /panel_wrapper -->
            </form>
        </body>
    </html>
    <?php
    die(); // This is required to return a proper result
}