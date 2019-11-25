<?php

/**
 * Class FMModelForm_maker
 */
class FMModelForm_maker {
  /**
   * PLUGIN = 2 points to Contact Form Maker
   */
  const PLUGIN = 1;

  public $fm_css_content;

  public $custom_fields = array();

  /**
   * @param int $id
   * @param string $type
   *
   * @return array
   */
  public function showform( $id = 0, $type = 'embedded' ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'formmaker WHERE id="%d"' . (!WDFMInstance(self::PLUGIN)->is_free ? '' : ' AND id' . (WDFMInstance(self::PLUGIN)->is_free == 1 ? ' NOT ' : ' ') . 'IN (' . (get_option( 'contact_form_forms', '' ) != '' ? get_option( 'contact_form_forms' ) : 0) . ')'), $id ) );

    if ( !$row ) {
      echo WDW_FM_Library(self::PLUGIN)->message( __( 'There is no form selected or the form was deleted.', WDFMInstance(self::PLUGIN)->prefix ), 'fm-notice-error' );
      return FALSE;
    }

    if ( $row->type != $type ) {
      echo WDW_FM_Library(self::PLUGIN)->message( __( 'The form you are trying to view does not have Embedded display type.', WDFMInstance(self::PLUGIN)->prefix ), 'fm-notice-error' );
      return FALSE;
    }

    $form_preview = (WDW_FM_Library(self::PLUGIN)->get( 'wdform_id', '' ) == $id) ? TRUE : FALSE;
    if ( !$form_preview && !$row->published ) {
      // If the form has been unpublished.
      echo WDW_FM_Library(self::PLUGIN)->message( __( 'The form you are trying to view has been unpublished.', WDFMInstance(self::PLUGIN)->prefix ), 'fm-notice-error' );
      return FALSE;
    }
    $theme_id = WDW_FM_Library(self::PLUGIN)->get( 'test_theme', '' );

    if ( $theme_id == '' ) {
      $theme_id = $row->theme;
    }
    $form_theme = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'formmaker_themes WHERE id="%d"', $theme_id ) );
    if ( !$form_theme ) {
      $form_theme = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . 'formmaker_themes' );
      if ( !$form_theme ) {
        return FALSE;
      }
    }
    $params_decoded = json_decode( html_entity_decode( $form_theme->css ), TRUE );
    if ( $params_decoded != NULL ) {
      $old = $form_theme->version == 1;
      $form_theme = $params_decoded;
    } else {
      $old = true;
      $form_theme = array( "CUPCSS" => $form_theme->css );
    }
    $cssver = isset( $form_theme[ 'version' ] ) ? $form_theme[ 'version' ] : 1;
    $this->create_css( $theme_id, $form_theme, $old );
    $front_urls = WDFMInstance(self::PLUGIN)->front_urls;
    $wp_upload_dir = wp_upload_dir();
    $frontend_dir = '/form-maker-frontend/';
    $fm_style_dir = $wp_upload_dir[ 'basedir' ] . $frontend_dir . 'css/fm-style-' . $theme_id . '.css';
    $fm_style_url = $front_urls[ 'upload_url' ] . $frontend_dir . 'css/fm-style-' . $theme_id . '.css';

	// Elementor plugin is active.
	if ( WDW_FM_Library(self::PLUGIN)->elementor_is_active() ) {
		wp_register_style(WDFMInstance(self::PLUGIN)->handle_prefix . '-style-' . $theme_id, $fm_style_url, array(), $cssver);
		wp_print_styles(WDFMInstance(self::PLUGIN)->handle_prefix . '-style-' . $theme_id);
	}

	if (!file_exists($fm_style_dir)) {
      if ( function_exists('wp_add_inline_style') ) {
        wp_add_inline_style(WDFMInstance(self::PLUGIN)->handle_prefix . 'frontend', $this->fm_css_content);
      } else {
        echo '<style>' . $this->fm_css_content . '</style>';
      }
    } else {
      wp_register_style(WDFMInstance(self::PLUGIN)->handle_prefix . '-style-' . $theme_id, $fm_style_url, array(), $cssver);
      wp_enqueue_style(WDFMInstance(self::PLUGIN)->handle_prefix . '-style-' . $theme_id);
    }

    $label_id = array();
    $label_type = array();
    $label_all = explode( '#****#', $row->label_order );
    $label_all = array_slice( $label_all, 0, count( $label_all ) - 1 );
    foreach ( $label_all as $key => $label_each ) {
      $label_id_each = explode( '#**id**#', $label_each );
      array_push( $label_id, $label_id_each[ 0 ] );
      $label_order_each = explode( '#**label**#', $label_id_each[ 1 ] );
      array_push( $label_type, $label_order_each[ 1 ] );
    }

    return array(
      $row,
      1,
      $label_id,
      $label_type,
      $form_theme,
    );
  }

  /**
   * @param $value
   * @param $key
   */
  public static function set_empty_values_transparent( &$value = '', $key = '' ) {
    if ( strpos( $key, 'Color' ) > -1 ) {
      /*
       * New themes colorpicker conflict with others.
       * Remove comments if no '#' is beeing saved with colors.
       * */
      if ( $value == '' ) {
        $value = 'transparent';
      }
      /*elseif (strpos($value, '#') === false) {
        $value = '#' . $value;
      }*/
    }
  }

  /**
   * @param int   $theme_id
   * @param array $form_theme
   * @param bool $old
   * @param bool $force_rewrite
   */
  public function create_css( $theme_id = 0, $form_theme = array(), $old = TRUE, $force_rewrite = FALSE ) {
    $wp_upload_dir = wp_upload_dir();
    $frontend_dir = '/form-maker-frontend/';
    if ( !is_dir( $wp_upload_dir[ 'basedir' ] . $frontend_dir ) ) {
      mkdir( $wp_upload_dir[ 'basedir' ] . $frontend_dir );
      file_put_contents( $wp_upload_dir[ 'basedir' ] . $frontend_dir . 'index.html', WDW_FM_Library(self::PLUGIN)->forbidden_template() );
    }
    if ( !is_dir( $wp_upload_dir[ 'basedir' ] . $frontend_dir . 'css' ) ) {
      mkdir( $wp_upload_dir[ 'basedir' ] . $frontend_dir . 'css' );
      file_put_contents( $wp_upload_dir[ 'basedir' ] . $frontend_dir . 'css/index.html', WDW_FM_Library(self::PLUGIN)->forbidden_template() );
    }
    $frontend_css = $wp_upload_dir[ 'basedir' ] . $frontend_dir . 'css/fm-style-' . $theme_id . '.css';
    if ( $theme_id && !$force_rewrite && file_exists( $frontend_css ) ) {
      return;
    }

    $plugin_relative_url = trim(str_replace(site_url(), '', WDFMInstance(self::PLUGIN)->plugin_url), '/');
    $plugin_relative_url = '../../../../' . $plugin_relative_url . '/';

    $prefixes = array(
      'HP',
      'AGP',
      'GP',
      'IP',
      'SBP',
      'SCP',
      'MCP',
      'SP',
      'SHP',
      'BP',
      'BHP',
      'NBP',
      'NBHP',
      'PBP',
      'PBHP',
      'PSAP',
      'PSDP',
      'CBP',
      'CBHP',
      'MBP',
      'MBHP',
    );
    $border_types = array( 'top', 'left', 'right', 'bottom' );
    $borders = array();
    foreach ( $prefixes as $prefix ) {
      $borders[ $prefix ] = array();
      foreach ( $border_types as $border_type ) {
        if ( isset( $form_theme[ $prefix . 'Border' . ucfirst( $border_type ) ] ) ) {
          array_push( $borders[ $prefix ], $form_theme[ $prefix . 'Border' . ucfirst( $border_type ) ] );
        }
      }
    }
    clearstatcache();
    $css_content = '';
    if ( !$old ) {
      $css_content = '.fm-form-container.fm-theme' . $theme_id . ' {' .
        (!empty( $form_theme[ 'AGPWidth' ] ) ? 'width:' . $form_theme[ 'AGPWidth' ] . '%;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form {' .
        (!empty( $form_theme[ 'AGPMargin' ] ) ? 'margin:' . $form_theme[ 'AGPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'AGPPadding' ] ) ? 'padding:' . $form_theme[ 'AGPPadding' ] . ' !important;' : '') .
        ((isset( $form_theme[ 'AGPBorderRadius' ] ) && $form_theme[ 'AGPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'AGPBorderRadius' ] . 'px;' : '') .
        (!empty( $form_theme[ 'AGPBoxShadow' ] ) ? 'box-shadow:' . $form_theme[ 'AGPBoxShadow' ] . ';' : '') .
        '}';
      if ( !empty( $borders[ 'AGP' ] ) ) {
        foreach ( $borders[ 'AGP' ] as $border ) {
          if ( !empty( $form_theme[ 'AGPBorderType' ] ) && ($form_theme[ 'AGPBorderType' ] == 'inherit' || $form_theme[ 'AGPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form {' .
              'border-' . $border . ': ' . $form_theme[ 'AGPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form {' .
              ((isset( $form_theme[ 'AGPBorderWidth' ] ) && $form_theme[ 'AGPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'AGPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'AGPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'AGPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'AGPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'AGPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .fm-header-bg {' .
        'display:' . ((!empty( $form_theme[ 'HPAlign' ] ) && ($form_theme[ 'HPAlign' ] == 'left' || $form_theme[ 'HPAlign' ] == 'right')) ? 'table-cell;' : 'block;') .
        (!empty( $form_theme[ 'HPWidth' ] ) ? 'width:' . $form_theme[ 'HPWidth' ] . '%;' : '') .
        (!empty( $form_theme[ 'HPBGColor' ] ) ? 'background-color:' . $form_theme[ 'HPBGColor' ] . ';' : '') .
        // 'vertical-align: top;'.
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .fm-header {' .
        (!empty( $form_theme[ 'HPWidth' ] ) ? 'width:' . $form_theme[ 'HPWidth' ] . '%;' : '') .
        (!empty( $form_theme[ 'HPMargin' ] ) ? 'margin:' . $form_theme[ 'HPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'HPPadding' ] ) ? 'padding:' . $form_theme[ 'HPPadding' ] . '!important;' : '') .
        ((isset( $form_theme[ 'HPBorderRadius' ] ) && $form_theme[ 'HPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'HPBorderRadius' ] . 'px;' : '') .
        (!empty( $form_theme[ 'HPTextAlign' ] ) ? 'text-align:' . $form_theme[ 'HPTextAlign' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .image_left_right.fm-header {' .
        'padding: 0 !important;' .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .image_left_right > div {' .
        (!empty( $form_theme[ 'HPPadding' ] ) ? 'padding:' . $form_theme[ 'HPPadding' ] . '!important;' : '') .
        '}';
      if ( !empty( $borders[ 'HP' ] ) ) {
        foreach ( $borders[ 'HP' ] as $border ) {
          if ( !empty( $form_theme[ 'HPBorderType' ] ) && ($form_theme[ 'HPBorderType' ] == 'inherit' || $form_theme[ 'HPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .fm-header {' .
              'border-' . $border . ':' . $form_theme[ 'HPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .fm-header {' .
              ((isset( $form_theme[ 'HPBorderWidth' ] ) && $form_theme[ 'HPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'HPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'HPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'HPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'HPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'HPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form.header_left_right .wdform-page-and-images {' .
        (!empty( $form_theme[ 'GPWidth' ] ) ? 'width:' . $form_theme[ 'GPWidth' ] . '%;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form.header_left_right .fm-header {' .
        (!empty( $form_theme[ 'HPWidth' ] ) ? 'width:' . $form_theme[ 'HPWidth' ] . '%;' : '') .
        '}';
      $css_content .= '.fm-topbar .fm-form-container.fm-theme' . $theme_id . ' .fm-form .fm-header {' .
        (!empty( $form_theme[ 'HTPWidth' ] ) ? 'width:' . $form_theme[ 'HTPWidth' ] . '% !important;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .fm-header-title {' .
        (!empty( $form_theme[ 'HTPFontSize' ] ) ? 'font-size:' . $form_theme[ 'HTPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'HTPColor' ] ) ? 'color:' . $form_theme[ 'HTPColor' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .fm-header-description {' .
        (!empty( $form_theme[ 'HDPFontSize' ] ) ? 'font-size:' . $form_theme[ 'HDPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'HDPColor' ] ) ? 'color:' . $form_theme[ 'HDPColor' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-scrollbox {' .
        (!empty( $form_theme[ 'AGPSPWidth' ] ) ? 'width:' . $form_theme[ 'AGPSPWidth' ] . '%;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-minimize-text div {' .
        (!empty( $form_theme[ 'MBPPadding' ] ) ? 'padding:' . $form_theme[ 'MBPPadding' ] . ';' : '') .
        (!empty( $form_theme[ 'MBPMargin' ] ) ? 'margin:' . $form_theme[ 'MBPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'MBPTextAlign' ] ) ? 'text-align:' . $form_theme[ 'MBPTextAlign' ] . ';' : '') .
        (!empty( $form_theme[ 'MBPFontSize' ] ) ? 'font-size:' . $form_theme[ 'MBPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'MBPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'MBPFontWeight' ] . ';' : '') .
        ((isset( $form_theme[ 'MBPBorderRadius' ] ) && $form_theme[ 'MBPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'MBPBorderRadius' ] . 'px;' : '') .
        '}';
      if ( !empty( $borders[ 'MBP' ] ) ) {
        foreach ( $borders[ 'MBP' ] as $border ) {
          if ( !empty( $form_theme[ 'MBPBorderType' ] ) && ($form_theme[ 'MBPBorderType' ] == 'inherit' || $form_theme[ 'MBPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-minimize-text div {' .
              'border-' . $border . ':' . $form_theme[ 'MBPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-minimize-text div {' .
              ((isset( $form_theme[ 'MBPBorderWidth' ] ) && $form_theme[ 'MBPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'MBPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'MBPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'MBPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'MBPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'MBPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-minimize-text div:hover {' .
        (!empty( $form_theme[ 'MBHPBGColor' ] ) ? 'background-color:' . $form_theme[ 'MBHPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'MBHPColor' ] ) ? 'color:' . $form_theme[ 'MBHPColor' ] . ';' : '') .
        '}';
      if ( $borders[ 'MBHP' ] ) {
        foreach ( $borders[ 'MBHP' ] as $border ) {
          if ( !empty( $form_theme[ 'MBHPBorderType' ] ) && ($form_theme[ 'MBHPBorderType' ] == 'inherit' || $form_theme[ 'MBHPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-minimize-text div:hover {' .
              'border-' . $border . ':' . $form_theme[ 'MBHPBorderType' ] . ' !important;' .
              ';';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-minimize-text div:hover { ' .
              ((isset( $form_theme[ 'MBHPBorderWidth' ] ) && $form_theme[ 'MBHPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'MBHPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'MBHPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'MBHPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'MBHPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'MBHPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .wdform-page-and-images {' .
        (!empty( $form_theme[ 'GPWidth' ] ) ? 'width:' . $form_theme[ 'GPWidth' ] . '%;' : '') .
        (!empty( $form_theme[ 'GPMargin' ] ) ? 'margin:' . $form_theme[ 'GPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'GPPadding' ] ) ? 'padding:' . $form_theme[ 'GPPadding' ] . ';' : '') .
        ((isset( $form_theme[ 'GPBorderRadius' ] ) && $form_theme[ 'GPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'GPBorderRadius' ] . 'px;' : '') .
        (!empty( $form_theme[ 'GPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'GPFontWeight' ] . ';' : '') .
        (!empty( $form_theme[ 'GPFontSize' ] ) ? 'font-size:' . $form_theme[ 'GPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'GPColor' ] ) ? 'color:' . $form_theme[ 'GPColor' ] . ';' : '') .
        '}';
      $css_content .= '.fm-topbar .fm-form-container.fm-theme' . $theme_id . ' .fm-form .wdform-page-and-images {' .
        (!empty( $form_theme[ 'GTPWidth' ] ) ? 'width:' . $form_theme[ 'GTPWidth' ] . '% !important;' : '') .
        '}';
      if ( $borders[ 'GP' ] ) {
        foreach ( $borders[ 'GP' ] as $border ) {
          if ( !empty( $form_theme[ 'GPBorderType' ] ) && ($form_theme[ 'GPBorderType' ] == 'inherit' || $form_theme[ 'GPBorderType' ] == 'initial') ) {
            $css_content .= '
						.fm-form-container.fm-theme' . $theme_id . ' .fm-form .wdform-page-and-images,
						.fm-form-container.fm-theme' . $theme_id . ' .fm-minimize-text {' .
              'border-' . $border . ':' . $form_theme[ 'GPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '
						.fm-form-container.fm-theme' . $theme_id . ' .fm-form .wdform-page-and-images,
						.fm-form-container.fm-theme' . $theme_id . ' .fm-minimize-text {' .
              ((isset( $form_theme[ 'GPBorderWidth' ] ) && $form_theme[ 'GPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'GPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'GPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'GPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'GPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'GPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .mini_label {' .
        (!empty( $form_theme[ 'GPMLMargin' ] ) ? 'margin:' . $form_theme[ 'GPMLMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'GPMLPadding' ] ) ? 'padding:' . $form_theme[ 'GPMLPadding' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'GPMLFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'GPMLFontWeight' ] . ';' : '') .
        (!empty( $form_theme[ 'GPMLFontSize' ] ) ? 'font-size:' . $form_theme[ 'GPMLFontSize' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'GPMLColor' ] ) ? 'color:' . $form_theme[ 'GPMLColor' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .wdform-page-and-images label {' .
        (!empty( $form_theme[ 'GPFontSize' ] ) ? 'font-size:' . $form_theme[ 'GPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'GPColor' ] ) ? 'color:' . $form_theme[ 'GPColor' ] . ';' : '') .
        '}';
      if ( !empty( $form_theme[ 'GPAlign' ] ) ) {
        if ( $form_theme[ 'GPAlign' ] == 'center' ) {
          $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' { margin: 0 auto; }';
        }
        else {
          $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' { float: ' . $form_theme['GPAlign'] . '; }';
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .wdform_section {' .
        (!empty( $form_theme[ 'SEPMargin' ] ) ? 'margin:' . $form_theme[ 'SEPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'SEPPadding' ] ) ? 'padding:' . $form_theme[ 'SEPPadding' ] . ';' : '') .
        'background: transparent;' .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . '.fm-form .wdform_column {' .
        (!empty( $form_theme[ 'COPMargin' ] ) ? 'margin:' . $form_theme[ 'COPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'COPPadding' ] ) ? 'padding:' . $form_theme[ 'COPPadding' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-slider {' .
        (!empty( $form_theme[ 'IPBGColor' ] ) ? 'background:' . $form_theme[ 'IPBGColor' ] . ' !important;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-scrollbox .fm-scrollbox-form {' .
        (!empty( $form_theme[ 'AGPMargin' ] ) ? 'margin:' . $form_theme[ 'AGPMargin' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-popover .fm-popover-content {' .
        (!empty( $form_theme[ 'AGPMargin' ] ) ? 'margin:' . $form_theme[ 'AGPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'AGPWidth' ] ) ? 'width:' . $form_theme[ 'AGPWidth' ] . '%;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages.wdform_page_navigation {' .
        (!empty( $form_theme[ 'AGPMargin' ] ) ? 'margin:' . $form_theme[ 'AGPMargin' ] . '%;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .wdform_footer {' .
        (!empty( $form_theme[ 'FPWidth' ] ) ? 'width:' . $form_theme[ 'FPWidth' ] . '%;' : '') .
        (!empty( $form_theme[ 'FPMargin' ] ) ? 'margin:' . $form_theme[ 'FPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'FPPadding' ] ) ? 'padding:' . $form_theme[ 'FPPadding' ] . ';' : '') .
        (!empty( $form_theme[ 'GPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'GPFontWeight' ] . ';' : '') .
        (!empty( $form_theme[ 'GPFontSize' ] ) ? 'font-size:' . $form_theme[ 'GPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'GPColor' ] ) ? 'color:' . $form_theme[ 'GPColor' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_active {' .
        (!empty( $form_theme[ 'PSAPMargin' ] ) ? 'margin:' . $form_theme[ 'PSAPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPPadding' ] ) ? 'padding:' . $form_theme[ 'PSAPPadding' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPWidth' ] ) ? 'width:' . $form_theme[ 'PSAPWidth' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSAPHeight' ] ) ? 'height:' . $form_theme[ 'PSAPHeight' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSAPBGColor' ] ) ? 'background-color:' . $form_theme[ 'PSAPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPFontSize' ] ) ? 'font-size:' . $form_theme[ 'PSAPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSAPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'PSAPFontWeight' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPColor' ] ) ? 'color:' . $form_theme[ 'PSAPColor' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPLineHeight' ] ) ? 'line-height:' . $form_theme[ 'PSAPLineHeight' ] . 'px;' : '') .
        ((isset( $form_theme[ 'PSAPBorderRadius' ] ) && $form_theme[ 'PSAPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'PSAPBorderRadius' ] . 'px;' : '') .
        '}';
      if ( $borders[ 'PSAP' ] ) {
        foreach ( $borders[ 'PSAP' ] as $border ) {
          if ( !empty( $form_theme[ 'PSAPBorderType' ] ) && ($form_theme[ 'PSAPBorderType' ] == 'inherit' || $form_theme[ 'PSAPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_active {' .
              'border:' . $form_theme[ 'PSAPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_active {' .
              ((isset( $form_theme[ 'PSAPBorderWidth' ] ) && $form_theme[ 'PSAPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'PSAPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'PSAPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'PSAPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'PSAPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'PSAPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_deactive {' .
        (!empty( $form_theme[ 'PSDPBGColor' ] ) ? 'background-color:' . $form_theme[ 'PSDPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPWidth' ] ) ? 'width:' . $form_theme[ 'PSAPWidth' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSDPHeight' ] ) ? 'height:' . $form_theme[ 'PSDPHeight' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSDPMargin' ] ) ? 'margin:' . $form_theme[ 'PSDPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'PSDPPadding' ] ) ? 'padding:' . $form_theme[ 'PSDPPadding' ] . ';' : '') .
        (!empty( $form_theme[ 'PSDPLineHeight' ] ) ? 'line-height:' . $form_theme[ 'PSDPLineHeight' ] . 'px;' : '') .
        ((isset( $form_theme[ 'PSAPBorderRadius' ] ) && $form_theme[ 'PSAPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'PSAPBorderRadius' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSDPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'PSDPFontWeight' ] . ';' : '') .
        (!empty( $form_theme[ 'PSDPFontSize' ] ) ? 'font-size:' . $form_theme[ 'PSDPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSDPColor' ] ) ? 'color:' . $form_theme[ 'PSDPColor' ] . ';' : '') .
        '}';
      if ( $borders[ 'PSDP' ] ) {
        foreach ( $borders[ 'PSDP' ] as $border ) {
          if ( !empty( $form_theme[ 'PSDPBorderType' ] ) && ($form_theme[ 'PSDPBorderType' ] == 'inherit' || $form_theme[ 'PSDPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_deactive {' .
              'border:' . $form_theme[ 'PSDPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_deactive {' .
              ((isset( $form_theme[ 'PSDPBorderWidth' ] ) && $form_theme[ 'PSDPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'PSDPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'PSDPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'PSDPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'PSDPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'PSDPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_percentage_active {' .
        (!empty( $form_theme[ 'PSAPWidth' ] ) ? 'width:' . $form_theme[ 'PSAPWidth' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSAPHeight' ] ) ? 'height:' . $form_theme[ 'PSAPHeight' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSAPMargin' ] ) ? 'margin:' . $form_theme[ 'PSAPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPPadding' ] ) ? 'padding:' . $form_theme[ 'PSAPPadding' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPBGColor' ] ) ? 'background-color:' . $form_theme[ 'PSAPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'PSAPFontWeight' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPFontSize' ] ) ? 'font-size:' . $form_theme[ 'PSAPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSAPColor' ] ) ? 'color:' . $form_theme[ 'PSAPColor' ] . ';' : '') .
        (!empty( $form_theme[ 'PSAPLineHeight' ] ) ? 'line-height:' . $form_theme[ 'PSAPLineHeight' ] . 'px;' : '') .
        ((isset( $form_theme[ 'PSAPBorderRadius' ] ) && $form_theme[ 'PSAPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'PSAPBorderRadius' ] . 'px;' : '') .
        '}';
      if ( $borders[ 'PSAP' ] ) {
        foreach ( $borders[ 'PSAP' ] as $border ) {
          if ( !empty( $form_theme[ 'PSAPBorderType' ] ) && ($form_theme[ 'PSAPBorderType' ] == 'inherit' || $form_theme[ 'PSAPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_percentage_active {' .
              'border:' . $form_theme[ 'PSAPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_percentage_active {' .
              ((isset( $form_theme[ 'PSAPBorderWidth' ] ) && $form_theme[ 'PSAPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'PSAPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'PSAPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'PSAPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'PSAPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'PSAPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_percentage_deactive {' .
        (!empty( $form_theme[ 'PPAPWidth' ] ) ? 'width:' . $form_theme[ 'PPAPWidth' ] . ';' : '') .
        (!empty( $form_theme[ 'PSDPHeight' ] ) ? 'height:' . $form_theme[ 'PSDPHeight' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSDPMargin' ] ) ? 'margin:' . $form_theme[ 'PSDPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'PSDPPadding' ] ) ? 'padding:' . $form_theme[ 'PSDPPadding' ] . ';' : '') .
        (!empty( $form_theme[ 'PSDPBGColor' ] ) ? 'background-color:' . $form_theme[ 'PSDPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'PSDPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'PSDPFontWeight' ] . ';' : '') .
        (!empty( $form_theme[ 'PSDPFontSize' ] ) ? 'font-size:' . $form_theme[ 'PSDPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PSDPColor' ] ) ? 'color:' . $form_theme[ 'PSDPColor' ] . ';' : '') .
        (!empty( $form_theme[ 'PSDPLineHeight' ] ) ? 'line-height:' . $form_theme[ 'PSDPLineHeight' ] . 'px;' : '') .
        ((isset( $form_theme[ 'PSDPBorderRadius' ] ) && $form_theme[ 'PSDPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'PSDPBorderRadius' ] . 'px;' : '') .
        '}';
      if ( $borders[ 'PSDP' ] ) {
        foreach ( $borders[ 'PSDP' ] as $border ) {
          if ( !empty( $form_theme[ 'PSDPBorderType' ] ) && ($form_theme[ 'PSDPBorderType' ] == 'inherit' || $form_theme[ 'PSDPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_percentage_deactive {' .
              'border:' . $form_theme[ 'PSDPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-pages .page_percentage_deactive {' .
              ((isset( $form_theme[ 'PSDPBorderWidth' ] ) && $form_theme[ 'PSDPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'PSDPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'PSDPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'PSDPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'PSDPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'PSDPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-action-buttons {' .
        (!empty( $form_theme[ 'CBPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'CBPFontWeight' ] . ';' : '') .
        (!empty( $form_theme[ 'CBPFontSize' ] ) ? 'font-size:' . $form_theme[ 'CBPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'CBPColor' ] ) ? 'color:' . $form_theme[ 'CBPColor' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .closing-form,
						 .fm-form-container.fm-theme' . $theme_id . ' .minimize-form {' .
        (!empty( $form_theme[ 'CBPMargin' ] ) ? 'margin:' . $form_theme[ 'CBPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'CBPPadding' ] ) ? 'padding:' . $form_theme[ 'CBPPadding' ] . ';' : '') .
        (!empty( $form_theme[ 'CBPPosition' ] ) ? 'position:' . $form_theme[ 'CBPPosition' ] . ';' : '') .
        (!empty( $form_theme[ 'CBPBGColor' ] ) ? 'background-color:' . $form_theme[ 'CBPBGColor' ] . ';' : '') .
        ((isset( $form_theme[ 'CBPBorderRadius' ] ) && $form_theme[ 'CBPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'CBPBorderRadius' ] . 'px;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .closing-form {' .
        (!empty( $form_theme[ 'CBPTop' ] ) ? 'top:' . $form_theme[ 'CBPTop' ] . ';' : '') .
        (!empty( $form_theme[ 'CBPRight' ] ) ? 'right:' . $form_theme[ 'CBPRight' ] . ';' : '') .
        (!empty( $form_theme[ 'CBPBottom' ] ) ? 'bottom:' . $form_theme[ 'CBPBottom' ] . ';' : '') .
        (!empty( $form_theme[ 'CBPLeft' ] ) ? 'left:' . $form_theme[ 'CBPLeft' ] . ';' : '') .
        '}';
      $for_mini = !empty( $form_theme[ 'CBPLeft' ] ) ? 'left' : 'right';
      $cbp_for_mini = ($form_theme[ 'CBP' . ucfirst( $for_mini ) ]) ? $form_theme[ 'CBP' . ucfirst( $for_mini ) ] : 0;
      $cbpfontsize = !empty( $form_theme[ 'CBPFontSize' ] ) ? (int)$form_theme[ 'CBPFontSize' ] : 0;
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .minimize-form {' .
        (!empty( $form_theme[ 'CBPTop' ] ) ? 'top:' . $form_theme[ 'CBPTop' ] . ';' : '') .
        (!empty( $form_theme[ 'CBPBottom' ] ) ? 'bottom:' . $form_theme[ 'CBPBottom' ] . ';' : '') .
        $for_mini . ': ' . (2 * (int)$cbp_for_mini + $cbpfontsize + 3) . 'px;' .
        '}';
      if ( $borders[ 'CBP' ] ) {
        foreach ( $borders[ 'CBP' ] as $border ) {
          if ( !empty( $form_theme[ 'CBPBorderType' ] ) && ($form_theme[ 'CBPBorderType' ] == 'inherit' || $form_theme[ 'CBPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .closing-form,
									 .fm-form-container.fm-theme' . $theme_id . ' .minimize-form {' .
              'border-' . $border . ':' . $form_theme[ 'CBPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .closing-form,
									 .fm-form-container.fm-theme' . $theme_id . ' .minimize-form {' .
              ((isset( $form_theme[ 'CBPBorderWidth' ] ) && $form_theme[ 'CBPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'CBPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'CBPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'CBPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'CBPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'CBPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .closing-form:hover,
						 .fm-form-container.fm-theme' . $theme_id . ' .minimize-form:hover {' .
        (!empty( $form_theme[ 'CBHPBGColor' ] ) ? 'background:' . $form_theme[ 'CBHPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'CBHPColor' ] ) ? 'color:' . $form_theme[ 'CBHPColor' ] . ';' : '') .
        'border:none;' .
        '}';
      if ( $borders[ 'CBHP' ] ) {
        foreach ( $borders[ 'CBHP' ] as $border ) {
          if ( !empty( $form_theme[ 'CBHPBorderType' ] ) && ($form_theme[ 'CBHPBorderType' ] == 'inherit' || $form_theme[ 'CBHPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .closing-form:hover,
									 .fm-form-container.fm-theme' . $theme_id . ' .minimize-form:hover {' .
              'border-' . $border . ':' . $form_theme[ 'CBHPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .closing-form:hover,
									 .fm-form-container.fm-theme' . $theme_id . ' .minimize-form:hover {' .
              ((isset( $form_theme[ 'CBHPBorderWidth' ] ) && $form_theme[ 'CBHPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'CBHPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'CBHPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'CBHPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'CBHPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'CBHPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $user_agent = $_SERVER[ 'HTTP_USER_AGENT' ];
      if ( stripos( $user_agent, 'Safari' ) !== FALSE && stripos( $user_agent, 'Chrome' ) === FALSE ) {
        $css_content .= '.fm-popover-container:before {
								position:absolute;
							}';
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .wdform-required {' .
        (!empty( $form_theme[ 'OPRColor' ] ) ? 'color:' . $form_theme[ 'OPRColor' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form input(not:active) {' .
        (!empty( $form_theme[ 'OPFontStyle' ] ) ? 'font-style:' . $form_theme[ 'OPFontStyle' ] . ';' : '') .
        (!empty( $form_theme[ 'OPDeInputColor' ] ) ? 'color:' . $form_theme[ 'OPDeInputColor' ] . ' !important;' : '') .
        '}';

      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .file-picker {' .
        (!empty( $form_theme[ 'OPFBgUrl' ] ) ? 'display: inline-block; width: 22px; height: 22px; background: url("' . $plugin_relative_url . $form_theme[ 'OPFBgUrl' ] . '");' : '') .
        (!empty( $form_theme[ 'OPFBGRepeat' ] ) ? 'background-repeat:' . $form_theme[ 'OPFBGRepeat' ] . ';' : '') .
        (!empty( $form_theme[ 'OPFPos1' ] ) ? 'background-position-x:' . $form_theme[ 'OPFPos1' ] . ';' : '') .
        (!empty( $form_theme[ 'OPFPos2' ] ) ? 'background-position-y:' . $form_theme[ 'OPFPos2' ] . ';' : '') .
        '}';
      if ( empty( $form_theme[ 'OPFBgUrl' ] ) ) {
        $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .file-upload-status {' .
          'display: none;' .
          '}';
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .file-upload input {' .
        (!empty( $form_theme[ 'OPFBgUrl' ] ) ? 'position: absolute; visibility: hidden;' : 'border: none;') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form {' .
        (!empty( $form_theme[ 'GPBGColor' ] ) ? 'background:' . $form_theme[ 'GPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'GPFontFamily' ] ) ? 'font-family:' . $form_theme[ 'GPFontFamily' ] . ';' : '') .
        '}';
      if ( !empty( $form_theme[ 'SEPBGColor' ] ) ) {
        $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .wdform_section { background:' . $form_theme['SEPBGColor'] . '; }';
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="text"],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-corner-all,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="number"],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=password],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=url],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=email],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form textarea,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-spinner-input,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form select,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form .captcha_img,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form .arithmetic_captcha_img {' .
        (!empty( $form_theme[ 'IPHeight' ] ) ? 'height:' . $form_theme[ 'IPHeight' ] . 'px;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="text"],
              .fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-corner-all:not(.ui-spinner):not(.ui-slider-horizontal),
              .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="number"],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=password],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=url],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=email],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form textarea,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-spinner-input,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form .file-upload-status,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form select {' .
        (!empty( $form_theme[ 'IPPadding' ] ) ? 'padding:' . $form_theme[ 'IPPadding' ] . ';' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="text"],
              .fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-corner-all,
              .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="number"],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=password],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=url],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=email],
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form textarea,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-spinner-input,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form .file-upload-status,
						 .fm-form-container.fm-theme' . $theme_id . ' .fm-form select {' .
        (!empty( $form_theme[ 'IPMargin' ] ) ? 'margin:' . $form_theme[ 'IPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'IPBGColor' ] ) ? 'background-color:' . $form_theme[ 'IPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'IPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'IPFontWeight' ] . ';' : '') .
        (!empty( $form_theme[ 'IPFontSize' ] ) ? 'font-size:' . $form_theme[ 'IPFontSize' ] . 'px;' : '') .
        (!empty( $form_theme[ 'IPColor' ] ) ? 'color:' . $form_theme[ 'IPColor' ] . ';' : '') .
        ((isset( $form_theme[ 'IPBorderRadius' ] ) && $form_theme[ 'IPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'IPBorderRadius' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'IPBoxShadow' ] ) ? 'box-shadow:' . $form_theme[ 'IPBoxShadow' ] . ';' : '') .
        '}';
      if ( $borders[ 'IP' ] ) {
        foreach ( $borders[ 'IP' ] as $border ) {
          if ( !empty( $form_theme[ 'IPBorderType' ] ) && ($form_theme[ 'IPBorderType' ] == 'inherit' || $form_theme[ 'IPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="text"]:not(.ui-spinner-input),
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="number"]:not(.ui-spinner-input),
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=password],
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=url],
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=email],
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form textarea,
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-spinner,
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-slider,
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-slider-handle,
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form select {' .
              'border-' . $border . '-style:' . $form_theme[ 'IPBorderType' ] . ' !important;' .
              '}';
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-spinner-button {' .
              'border-left-style:' . $form_theme[ 'IPBorderType' ] . ' !important;' .
              '}';
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-slider-range {' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="text"]:not(.ui-spinner-input),
									.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="number"]:not(.ui-spinner-input),
									.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=password],
									.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=url],
									.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type=email],
									.fm-form-container.fm-theme' . $theme_id . ' .fm-form textarea,
									.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-spinner,
									.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-slider,
									.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-slider-handle,
									.fm-form-container.fm-theme' . $theme_id . ' .fm-form select {' .
              ((isset( $form_theme[ 'IPBorderWidth' ] ) && $form_theme[ 'IPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'IPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'IPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'IPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'IPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'IPBorderColor' ] . ' !important;' : '') .
              '}';
            if ( $border == 'left' ) {
              $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-spinner-button {' .
                ((isset( $form_theme[ 'IPBorderWidth' ] ) && $form_theme[ 'IPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'IPBorderWidth' ] . 'px !important;' : '') .
                (!empty( $form_theme[ 'IPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'IPBorderType' ] . ' !important;' : '') .
                (!empty( $form_theme[ 'IPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'IPBorderColor' ] . ' !important;' : '') .
                '}';
            }
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .ui-slider-range {' .
              (!empty( $form_theme[ 'IPBorderColor' ] ) ? 'background:' . $form_theme[ 'IPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form select {' .
        (!empty( $form_theme[ 'IPBGColor' ] ) ? 'background-color:' . $form_theme[ 'IPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'SBPBackground' ] ) ? 'background-image: url("' . $plugin_relative_url . $form_theme[ 'SBPBackground' ] . '");' : '') .
        (!empty( $form_theme[ 'SBPBGRepeat' ] ) ? 'background-repeat:' . $form_theme[ 'SBPBGRepeat' ] . ';' : '') .
        (!empty( $form_theme[ 'SBPBackground' ] ) ? 'background-position-x: calc(100% - 8px);' : '') .
        (!empty( $form_theme[ 'SBPBackground' ] ) ? 'background-position-y: 50%;' : '') .
        (!empty( $form_theme[ 'SBPBackground' ] ) ? 'background-size: 12px;' : '') .
        (!empty( $form_theme[ 'SBPAppearance' ] ) ? 'appearance:' . $form_theme[ 'SBPAppearance' ] . ';' : '') .
        (!empty( $form_theme[ 'SBPAppearance' ] ) ? '-moz-appearance:' . $form_theme[ 'SBPAppearance' ] . ';' : '') .
        (!empty( $form_theme[ 'SBPAppearance' ] ) ? '-webkit-appearance:' . $form_theme[ 'SBPAppearance' ] . ';' : '') .
        '}';
      $css_content .= '.rtl  .fm-form-container.fm-theme' . $theme_id . ' .fm-form select {' .
        (!empty( $form_theme[ 'SBPBackground' ] ) ? 'background-position-x: 8px;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .radio-div label span {' .
        (!empty( $form_theme[ 'SCPWidth' ] ) ? 'width:' . $form_theme[ 'SCPWidth' ] . 'px;' : '') .
        (!empty( $form_theme[ 'SCPHeight' ] ) ? 'height:' . $form_theme[ 'SCPHeight' ] . 'px;' : '') .
        (!empty( $form_theme[ 'SCPMargin' ] ) ? 'margin:' . $form_theme[ 'SCPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'SCPBGColor' ] ) ? 'background-color:' . $form_theme[ 'SCPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'SCPBoxShadow' ] ) ? 'box-shadow:' . $form_theme[ 'SCPBoxShadow' ] . ';' : '') .
        ((isset( $form_theme[ 'SCPBorderRadius' ] ) && $form_theme[ 'SCPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'SCPBorderRadius' ] . 'px;' : '') .
        (!empty( $form_theme[ 'SCPWidth' ] ) ? 'min-width:' . $form_theme[ 'SCPWidth' ] . 'px;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .radio-div input[type="radio"]:checked + label span:after {' .
        (!empty( $form_theme[ 'SCCPWidth' ] ) ? 'content:""; display: block;' : '') .
        (!empty( $form_theme[ 'SCCPWidth' ] ) ? 'width:' . $form_theme[ 'SCCPWidth' ] . 'px;' : '') .
        (!empty( $form_theme[ 'SCCPHeight' ] ) ? 'height:' . $form_theme[ 'SCCPHeight' ] . 'px;' : '') .
        (!empty( $form_theme[ 'SCCPMargin' ] ) ? 'margin:' . $form_theme[ 'SCCPMargin' ] . 'px;' : '') .
        (!empty( $form_theme[ 'SCCPBGColor' ] ) ? 'background-color:' . $form_theme[ 'SCCPBGColor' ] . ';' : '') .
        ((isset( $form_theme[ 'SCCPBorderRadius' ] ) && $form_theme[ 'SCCPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'SCCPBorderRadius' ] . 'px;' : '') .
        '}';
      if ( !empty( $borders[ 'SCP' ] ) ) {
        foreach ( $borders[ 'SCP' ] as $border ) {
          if ( !empty( $form_theme[ 'SCPBorderType' ] ) && ($form_theme[ 'SCPBorderType' ] == 'inherit' || $form_theme[ 'SCPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .radio-div label span {' .
              'border-' . $border . '-style:' . $form_theme[ 'SCPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .radio-div label span {' .
              ((isset( $form_theme[ 'SCPBorderWidth' ] ) && $form_theme[ 'SCPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'SCPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'SCPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'SCPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'SCPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'SCPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .checkbox-div label span {' .
        (!empty( $form_theme[ 'MCPWidth' ] ) ? 'width:' . $form_theme[ 'MCPWidth' ] . 'px;' : '') .
        (!empty( $form_theme[ 'MCPHeight' ] ) ? 'height:' . $form_theme[ 'MCPHeight' ] . 'px;' : '') .
        (!empty( $form_theme[ 'MCPMargin' ] ) ? 'margin:' . $form_theme[ 'MCPMargin' ] . ';' : '') .
        (!empty( $form_theme[ 'MCPBGColor' ] ) ? 'background-color:' . $form_theme[ 'MCPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'MCPBoxShadow' ] ) ? 'box-shadow:' . $form_theme[ 'MCPBoxShadow' ] . ';' : '') .
        ((isset( $form_theme[ 'MCPBorderRadius' ] ) && $form_theme[ 'MCPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'MCPBorderRadius' ] . 'px;' : '') .
        (!empty( $form_theme[ 'MCPWidth' ] ) ? 'min-width:' . $form_theme[ 'MCPWidth' ] . 'px;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .checkbox-div input[type="checkbox"]:checked + label span:after {' .
        ((!empty( $form_theme[ 'MCCPBackground' ] ) || !empty( $form_theme[ 'MCCPBGColor' ] )) ? 'content:""; display: block;' : '') .
        (!empty( $form_theme[ 'MCCPWidth' ] ) ? 'width:' . $form_theme[ 'MCCPWidth' ] . 'px;' : '') .
        (!empty( $form_theme[ 'MCCPHeight' ] ) ? 'height:' . $form_theme[ 'MCCPHeight' ] . 'px;' : '') .
        (!empty( $form_theme[ 'MCPMargin' ] ) ? 'margin:' . $form_theme[ 'MCCPMargin' ] . 'px;' : '') .
        (!empty( $form_theme[ 'MCCPBGColor' ] ) ? 'background-color:' . $form_theme[ 'MCCPBGColor' ] . ';' : '') .
        (!empty( $form_theme[ 'MCCPBackground' ] ) ? 'background-image: url("' . $plugin_relative_url . $form_theme[ 'MCCPBackground' ] . '");' : '') .
        (!empty( $form_theme[ 'MCCPBGRepeat' ] ) ? 'background-repeat:' . $form_theme[ 'MCCPBGRepeat' ] . ';' : '') .
        (!empty( $form_theme[ 'MCCPBGPos1' ] ) ? 'background-position-x:' . $form_theme[ 'MCCPBGPos1' ] . ';' : '') .
        (!empty( $form_theme[ 'MCCPBGPos2' ] ) ? 'background-position-y:' . $form_theme[ 'MCCPBGPos2' ] . ';' : '') .
        ((isset( $form_theme[ 'MCCPBorderRadius' ] ) && $form_theme[ 'MCCPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'MCCPBorderRadius' ] . 'px;' : '') .
        '}';
      if ( !empty( $borders[ 'MCP' ] ) ) {
        foreach ( $borders[ 'MCP' ] as $border ) {
          if ( !empty( $form_theme[ 'MCPBorderType' ] ) && ($form_theme[ 'MCPBorderType' ] == 'inherit' || $form_theme[ 'MCPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .checkbox-div label span {' .
              'border-' . $border . '-style:' . $form_theme[ 'MCPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .checkbox-div label span {' .
              ((isset( $form_theme[ 'MCPBorderWidth' ] ) && $form_theme[ 'MCPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'MCPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'MCPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'MCPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'MCPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'MCPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .button-submit,
						  .fm-form-container.fm-theme' . $theme_id . ' .button-reset {' .
        (!empty( $form_theme[ 'SPBGColor' ] ) ? 'background-image: none; text-transform: none;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-submit {' .
        (!empty( $form_theme[ 'SPWidth' ] ) ? 'width:' . $form_theme[ 'SPWidth' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'SPHeight' ] ) ? 'height:' . $form_theme[ 'SPHeight' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'SPMargin' ] ) ? 'margin:' . $form_theme[ 'SPMargin' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'SPPadding' ] ) ? 'padding:' . $form_theme[ 'SPPadding' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'SPBGColor' ] ) ? 'background-color:' . $form_theme[ 'SPBGColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'SPBGColor' ] ) ? 'background-image: none; border: none;' : '') .
        (!empty( $form_theme[ 'SPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'SPFontWeight' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'SPFontSize' ] ) ? 'font-size:' . $form_theme[ 'SPFontSize' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'SPColor' ] ) ? 'color:' . $form_theme[ 'SPColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'SPBoxShadow' ] ) ? 'box-shadow:' . $form_theme[ 'SPBoxShadow' ] . ' !important;' : '') .
        ((isset( $form_theme[ 'SPBorderRadius' ] ) && $form_theme[ 'SPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'SPBorderRadius' ] . 'px !important;' : '') .
        '}';
      if ( !empty( $form_theme[ 'SPBorderType' ] ) && ($form_theme[ 'SPBorderType' ] == 'none' || $form_theme[ 'SPBorderType' ] == 'inherit' || $form_theme[ 'SPBorderType' ] == 'initial') ) {
        $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-submit {' . 'border: ' . $form_theme['SPBorderType'] . '}';
      }
      if ( !empty( $borders[ 'SP' ] ) ) {
        foreach ( $borders[ 'SP' ] as $border ) {
          if ( !empty( $form_theme[ 'SPBorderType' ] ) && ($form_theme[ 'SPBorderType' ] == 'none' || $form_theme[ 'SPBorderType' ] == 'inherit' || $form_theme[ 'SPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-submit {' .
              'border-' . $border . '-style:' . $form_theme[ 'SPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-submit {' .
              ((isset( $form_theme[ 'SPBorderWidth' ] ) && $form_theme[ 'SPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'SPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'SPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'SPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'SPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'SPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-submit:hover {' .
        (!empty( $form_theme[ 'SHPBGColor' ] ) ? 'background-color:' . $form_theme[ 'SHPBGColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'SHPColor' ] ) ? 'color:' . $form_theme[ 'SHPColor' ] . ' !important;' : '') .
        '}';
      if ( !empty( $borders[ 'SHP' ] ) ) {
        foreach ( $borders[ 'SHP' ] as $border ) {
          if ( !empty( $form_theme[ 'SHPBorderType' ] ) && ($form_theme[ 'SHPBorderType' ] == 'inherit' || $form_theme[ 'SHPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-submit:hover {' .
              'border-' . $border . '-style:' . $form_theme[ 'SHPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-submit:hover {' .
              ((isset( $form_theme[ 'SHPBorderWidth' ] ) && $form_theme[ 'SHPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'SHPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'SHPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'SHPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'SHPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'SHPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-reset,
		.fm-form-container.fm-theme' . $theme_id . ' .fm-form button { ' .
        (!empty( $form_theme[ 'BPWidth' ] ) ? 'width:' . $form_theme[ 'BPWidth' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'BPHeight' ] ) ? 'height:' . $form_theme[ 'BPHeight' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'BPMargin' ] ) ? 'margin:' . $form_theme[ 'BPMargin' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'BPPadding' ] ) ? 'padding:' . $form_theme[ 'BPPadding' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'BPBGColor' ] ) ? 'background-color:' . $form_theme[ 'BPBGColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'BPBGColor' ] ) ? 'background-image: none;' : '') .
        (!empty( $form_theme[ 'BPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'BPFontWeight' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'BPFontSize' ] ) ? 'font-size:' . $form_theme[ 'BPFontSize' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'BPColor' ] ) ? 'color:' . $form_theme[ 'BPColor' ] . ' !important;' : '') .
        ((isset( $form_theme[ 'BPBorderRadius' ] ) && $form_theme[ 'BPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'BPBorderRadius' ] . 'px;' : '') .
        (!empty( $form_theme[ 'BPBoxShadow' ] ) ? 'box-shadow:' . $form_theme[ 'BPBoxShadow' ] . ' !important;' : '') .
        '}';
      if ( !empty( $form_theme[ 'BPBorderType' ] ) && ($form_theme[ 'BPBorderType' ] == 'none' || $form_theme[ 'BPBorderType' ] == 'inherit' || $form_theme[ 'BPBorderType' ] == 'initial') ) {
        $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-reset,
									 .fm-form-container.fm-theme' . $theme_id . ' .fm-form button:not(.button-submit) {' .
          'border: ' . $form_theme[ 'BPBorderType' ] . '; }';
      }
      if ( !empty( $borders[ 'BP' ] ) ) {
        foreach ( $borders[ 'BP' ] as $border ) {
          if ( !empty( $form_theme[ 'BPBorderType' ] ) && ($form_theme[ 'BPBorderType' ] == 'none' || $form_theme[ 'BPBorderType' ] == 'inherit' || $form_theme[ 'BPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-reset,
									 .fm-form-container.fm-theme' . $theme_id . ' .fm-form button:not(.button-submit) {' .
              'border-' . $border . '-style:' . $form_theme[ 'BPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-reset,
									 .fm-form-container.fm-theme' . $theme_id . ' .fm-form butto:not(.button-submit)n {' .
              ((isset( $form_theme[ 'BPBorderWidth' ] ) && $form_theme[ 'BPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'BPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'BPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'BPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'BPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'BPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-reset:hover,
							.fm-form-container.fm-theme' . $theme_id . ' .fm-form button:hover {' .
        (!empty( $form_theme[ 'BHPBGColor' ] ) ? 'background-color:' . $form_theme[ 'BHPBGColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'BHPColor' ] ) ? 'color:' . $form_theme[ 'BHPColor' ] . ' !important;' : '') .
        '}';
      if ( !empty( $borders[ 'BHP' ] ) ) {
        foreach ( $borders[ 'BHP' ] as $border ) {
          if ( !empty( $form_theme[ 'BHPBorderType' ] ) && ($form_theme[ 'BHPBorderType' ] == 'inherit' || $form_theme[ 'BHPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-reset:hover,
									 .fm-form-container.fm-theme' . $theme_id . ' .fm-form button:hover {' .
              'border-' . $border . '-style:' . $form_theme[ 'BHPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .button-reset:hover,
										.fm-form-container.fm-theme' . $theme_id . ' .fm-form button:hover {' .
              ((isset( $form_theme[ 'BHPBorderWidth' ] ) && $form_theme[ 'BHPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'BHPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'BHPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'BHPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'BHPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'BHPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .next-page div.wdform-page-button {' .
        (!empty( $form_theme[ 'NBPWidth' ] ) ? 'width:' . $form_theme[ 'NBPWidth' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'NBPHeight' ] ) ? 'height:' . $form_theme[ 'NBPHeight' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'NBPMargin' ] ) ? 'margin:' . $form_theme[ 'NBPMargin' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'NBPPadding' ] ) ? 'padding:' . $form_theme[ 'NBPPadding' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'NBPBGColor' ] ) ? 'background-color:' . $form_theme[ 'NBPBGColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'BPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'BPFontWeight' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'BPFontSize' ] ) ? 'font-size:' . $form_theme[ 'BPFontSize' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'NBPColor' ] ) ? 'color:' . $form_theme[ 'NBPColor' ] . ' !important;' : '') .
        ((isset( $form_theme[ 'NBPBorderRadius' ] ) && $form_theme[ 'NBPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'NBPBorderRadius' ] . 'px;' : '') .
        (!empty( $form_theme[ 'NBPBoxShadow' ] ) ? 'box-shadow:' . $form_theme[ 'NBPBoxShadow' ] . ' !important;' : '') .
        '}';
      if ( !empty( $borders[ 'NBP' ] ) ) {
        foreach ( $borders[ 'NBP' ] as $border ) {
          if ( !empty( $form_theme[ 'NBPBorderType' ] ) && ($form_theme[ 'NBPBorderType' ] == 'inherit' || $form_theme[ 'NBPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .next-page div.wdform-page-button {' .
              'border-' . $border . '-style:' . $form_theme[ 'NBPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .next-page div.wdform-page-button {' .
              ((isset( $form_theme[ 'NBPBorderWidth' ] ) && $form_theme[ 'NBPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'NBPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'NBPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'NBPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'NBPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'NBPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .next-page div.wdform-page-button:hover {' .
        (!empty( $form_theme[ 'NBHPBGColor' ] ) ? 'background-color:' . $form_theme[ 'NBHPBGColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'NBHPColor' ] ) ? 'color:' . $form_theme[ 'NBHPColor' ] . ' !important;' : '') .
        '}';
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-minimize-text div {' .
        (!empty( $form_theme[ 'MBPBGColor' ] ) ? 'background-color:' . $form_theme[ 'MBPBGColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'MBPColor' ] ) ? 'color:' . $form_theme[ 'MBPColor' ] . ' !important;' : '') .
        '}';
      if ( !empty( $borders[ 'NBHP' ] ) ) {
        foreach ( $borders[ 'NBHP' ] as $border ) {
          if ( !empty( $form_theme[ 'NBHPBorderType' ] ) && ($form_theme[ 'NBHPBorderType' ] == 'inherit' || $form_theme[ 'NBHPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .next-page div.wdform-page-button:hover {' .
              'border-' . $border . '-style:' . $form_theme[ 'NBHPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .next-page div.wdform-page-button:hover {' .
              ((isset( $form_theme[ 'NBHPBorderWidth' ] ) && $form_theme[ 'NBHPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'NBHPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'NBHPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'NBHPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'NBHPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'NBHPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .previous-page div.wdform-page-button {' .
        (!empty( $form_theme[ 'PBPWidth' ] ) ? 'width:' . $form_theme[ 'PBPWidth' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'PBPHeight' ] ) ? 'height:' . $form_theme[ 'PBPHeight' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'PBPMargin' ] ) ? 'margin:' . $form_theme[ 'PBPMargin' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'PBPPadding' ] ) ? 'padding:' . $form_theme[ 'PBPPadding' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'PBPBGColor' ] ) ? 'background-color:' . $form_theme[ 'PBPBGColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'BPFontWeight' ] ) ? 'font-weight:' . $form_theme[ 'BPFontWeight' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'BPFontSize' ] ) ? 'font-size:' . $form_theme[ 'BPFontSize' ] . 'px !important;' : '') .
        (!empty( $form_theme[ 'PBPColor' ] ) ? 'color:' . $form_theme[ 'PBPColor' ] . ' !important;' : '') .
        ((isset( $form_theme[ 'PBPBorderRadius' ] ) && $form_theme[ 'PBPBorderRadius' ] !== '') ? 'border-radius:' . $form_theme[ 'PBPBorderRadius' ] . 'px;' : '') .
        (!empty( $form_theme[ 'PBPBoxShadow' ] ) ? 'box-shadow:' . $form_theme[ 'PBPBoxShadow' ] . ' !important;' : '') .
        '}';
      if ( !empty( $borders[ 'PBP' ] ) ) {
        foreach ( $borders[ 'PBP' ] as $border ) {
          if ( !empty( $form_theme[ 'PBPBorderType' ] ) && ($form_theme[ 'PBPBorderType' ] == 'inherit' || $form_theme[ 'PBPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .previous-page div.wdform-page-button {' .
              'border-' . $border . '-style:' . $form_theme[ 'PBPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .previous-page div.wdform-page-button {' .
              ((isset( $form_theme[ 'PBPBorderWidth' ] ) && $form_theme[ 'PBPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'PBPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'PBPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'PBPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'PBPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'PBPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .previous-page div.wdform-page-button:hover {' .
        (!empty( $form_theme[ 'PBHPBGColor' ] ) ? 'background-color:' . $form_theme[ 'PBHPBGColor' ] . ' !important;' : '') .
        (!empty( $form_theme[ 'PBHPColor' ] ) ? 'color:' . $form_theme[ 'PBHPColor' ] . ' !important;' : '') .
        '}';
      if ( !empty( $borders[ 'PBHP' ] ) ) {
        foreach ( $borders[ 'PBHP' ] as $border ) {
          if ( !empty( $form_theme[ 'PBHPBorderType' ] ) && ($form_theme[ 'PBHPBorderType' ] == 'inherit' || $form_theme[ 'PBHPBorderType' ] == 'initial') ) {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .previous-page div.wdform-page-button:hover {' .
              'border-' . $border . '-style:' . $form_theme[ 'PBHPBorderType' ] . ' !important;' .
              '}';
            break;
          } else {
            $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form .previous-page div.wdform-page-button:hover {' .
              ((isset( $form_theme[ 'PBHPBorderWidth' ] ) && $form_theme[ 'PBHPBorderWidth' ] !== '') ? 'border-' . $border . ':' . $form_theme[ 'PBHPBorderWidth' ] . 'px !important;' : '') .
              (!empty( $form_theme[ 'PBHPBorderType' ] ) ? 'border-' . $border . '-style:' . $form_theme[ 'PBHPBorderType' ] . ' !important;' : '') .
              (!empty( $form_theme[ 'PBHPBorderColor' ] ) ? 'border-' . $border . '-color:' . $form_theme[ 'PBHPBorderColor' ] . ' !important;' : '') .
              '}';
          }
        }
      }
      $css_content .= '.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="radio"] {' .
        (!empty( $form_theme[ 'SCCPWidth' ] ) ? 'display: none;' : '') .
        '}
						.fm-form-container.fm-theme' . $theme_id . ' .fm-form input[type="checkbox"] {' .
        (!empty( $form_theme[ 'MCCPBackground' ] ) || !empty( $form_theme[ 'MCCPBGColor' ] ) ? 'display: none;' : '') .
        '}';
      if ( !empty( $form_theme[ 'CUPCSS' ] ) ) {
        $css_content .= $form_theme['CUPCSS'];
      }
    }
    if ( !empty( $form_theme[ 'CUPCSS' ] ) ) {
      $form_theme_css = $form_theme[ 'CUPCSS' ];
      $pattern = '/\/\/(.+)(\r\n|\r|\n)/';
      if ( strpos( $form_theme_css, ':checked + label' ) !== FALSE ) {
        $form_theme_css .= '
        .checkbox-div label span {
          border: 1px solid #868686  !important;
          display: inline-block;
          height: 16px;
          width: 16px;
        }
        .radio-div label span {
          border: 1px solid #868686  !important;
          border-radius: 100%;
          display: inline-block;
          height: 16px;
          width: 16px;
        }
        .checkbox-div input[type=\'checkbox\']:checked + label span:after {
          content: \'\';
          width: 16px;
          height: 16px;
          background:transparent url("' . $plugin_relative_url . 'images/themes/checkboxes/1.png") no-repeat;
          background-size: 100%;
          border-radius: 0px;
          margin: 0px;
          display: block;
        }
        .radio-div input[type=\'radio\']:checked + label span:after {
          content: \'\';
          width: 6px;
          height: 6px;
          background: #777777;
          border-radius: 10px;
          margin: 5px;
          display: block;
        }
        .checkbox-div, .radio-div {
          border: none;
          box-shadow: none;
          height: 17px;
          background: none;
        }
        .checkbox-div label, .radio-div label, .checkbox-div label:hover, .radio-div label:hover {
          opacity: 1;
          background: none;
          border: none;
          min-width: 140px;
          line-height: 13px;
        }';
      }
      $form_theme_css = explode( '{', $form_theme_css );
      $count_after_explod_theme = count( $form_theme_css );
      for ( $i = 0; $i < $count_after_explod_theme; $i++ ) {
        $body_or_classes[ $i ] = explode( '}', $form_theme_css[ $i ] );
      }
      for ( $i = 0; $i < $count_after_explod_theme; $i++ ) {
        if ( $i == 0 ) {
          $body_or_classes[ $i ][ 0 ] = '.fm-form-container.fm-theme' . $theme_id . ' .fm-form' . ' ' . str_replace( ',', ', .fm-form-container.fm-theme' . $theme_id . ' .fm-form', $body_or_classes[ $i ][ 0 ] );
        } else {
          $body_or_classes[ $i ][ 1 ] = '.fm-form-container.fm-theme' . $theme_id . ' .fm-form' . ' ' . str_replace( ',', ', .fm-form-container.fm-theme' . $theme_id . ' .fm-form', $body_or_classes[ $i ][ 1 ] );
        }
      }
      for ( $i = 0; $i < $count_after_explod_theme; $i++ ) {
        $body_or_classes_implode[ $i ] = implode( '}', $body_or_classes[ $i ] );
      }
      $theme = implode( '{', $body_or_classes_implode );
      $theme = preg_replace( $pattern, ' ', $theme );
      $css_content .= $theme;
    }
    $this->fm_css_content = $css_content;
    file_put_contents( $frontend_css, $css_content );
  }

  /**
   * @param int $form
   * @param int $id
   *
   * @return array|mixed
   */
  public function savedata( $form = 0, $id = 0 ) {
    $fm_settings = get_option(WDFMInstance(self::PLUGIN)->is_free == 2 ? 'fmc_settings' : 'fm_settings');
    $all_files = array();
    $correct = FALSE;
    $id_for_old = $id;
    if ( !$form->form_front ) {
      $id = '';
    }
    if ( isset( $_POST[ "counter" . $id ] ) ) {
      WDW_FM_Library(self::PLUGIN)->start_session();
      if ( (isset( $_POST[ "save_or_submit" . $id ] ) && $_POST[ "save_or_submit" . $id ] != 'save') ) {
        if ( isset( $_POST[ "captcha_input" ] ) ) {
          $captcha_input = esc_html( $_POST[ "captcha_input" ] );
          $session_wd_captcha_code = isset( $_SESSION[ $id . '_wd_captcha_code' ] ) ? $_SESSION[ $id . '_wd_captcha_code' ] : '-';
          if ( md5( $captcha_input ) == $session_wd_captcha_code ) {
            $correct = TRUE;
          } else {
            $_SESSION[ 'massage_after_submit' . $id ] = addslashes( addslashes( __( 'Error, incorrect Security code.', WDFMInstance(self::PLUGIN)->prefix ) ) );
            $_SESSION[ 'message_captcha' ] = $_SESSION[ 'massage_after_submit' . $id ];
            $_SESSION[ 'error_or_no' . $id ] = 1;
          }
        } elseif ( isset( $_POST[ "arithmetic_captcha_input" ] ) ) {
          $arithmetic_captcha_input = esc_html( $_POST[ "arithmetic_captcha_input" ] );
          $session_wd_arithmetic_captcha_code = isset( $_SESSION[ $id . '_wd_arithmetic_captcha_code' ] ) ? $_SESSION[ $id . '_wd_arithmetic_captcha_code' ] : '-';
          if ( md5( $arithmetic_captcha_input ) == $session_wd_arithmetic_captcha_code ) {
            $correct = TRUE;
          } else {
            $_SESSION[ 'massage_after_submit' . $id ] = addslashes( addslashes( __( 'Error, incorrect Security code.', WDFMInstance(self::PLUGIN)->prefix ) ) );
            $_SESSION[ 'message_captcha' ] = $_SESSION[ 'massage_after_submit' . $id ];
            $_SESSION[ 'error_or_no' . $id ] = 1;
          }
        } elseif ( isset( $_POST[ "g-recaptcha-response" ] ) ) {
          $privatekey = isset( $fm_settings[ 'private_key' ] ) ? $fm_settings[ 'private_key' ] : '';
          $captcha = $_POST[ 'g-recaptcha-response' ];
          $url = 'https://www.google.com/recaptcha/api/siteverify';
          $data = array(
            'secret' => $privatekey,
            'response' => $captcha,
            'remoteip' => $_SERVER[ 'REMOTE_ADDR' ],
          );
          $response = wp_remote_post( $url, array( 'body' => $data ) );
          if ( !is_wp_error( $response ) ) {
            $jsonResponse = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( $jsonResponse[ 'success' ] == "true" ) {
              $correct = TRUE;
            } else {
              if ( isset( $jsonResponse[ 'error-codes' ] ) ) {
                foreach ( $jsonResponse[ 'error-codes' ] as $errorcode ) {
                  switch ( $errorcode ) {
                    case 'missing-input-secret' :
                    case 'invalid-input-secret' : {
                      $_SESSION[ 'massage_after_submit' . $id ] = addslashes( addslashes( __( 'Error, incorrect secret code.', WDFMInstance(self::PLUGIN)->prefix ) ) );
                      break;
                    }
                    case 'missing-input-response' :
                    case 'invalid-input-response' :
                    case 'bad-request' :
                    default: {
                      $_SESSION[ 'massage_after_submit' . $id ] = addslashes( addslashes( __( 'Verification failed.', WDFMInstance(self::PLUGIN)->prefix ) ) );
                      break;
                    }
                  }
                }
              } else {
                $_SESSION[ 'massage_after_submit' . $id ] = addslashes( addslashes( __( 'Verification failed.', WDFMInstance(self::PLUGIN)->prefix ) ) );
              }
              $_SESSION[ 'message_captcha' ] = $_SESSION[ 'massage_after_submit' . $id ];
              $_SESSION[ 'error_or_no' . $id ] = 1;
              $correct = FALSE;
            }
          } else {
            $_SESSION[ 'massage_after_submit' . $id ] = addslashes( addslashes( __( 'Verification failed.', WDFMInstance(self::PLUGIN)->prefix ) ) );
            $_SESSION[ 'message_captcha' ] = $_SESSION[ 'massage_after_submit' . $id ];
            $_SESSION[ 'error_or_no' . $id ] = 1;
            $correct = FALSE;
          }
        } else {
          if ( preg_match( '(type_arithmetic_captcha|type_captcha|type_recaptcha)', $form->label_order_current ) === 1 ) {
            $_SESSION[ 'massage_after_submit' . $id ] = addslashes( addslashes( __( 'Error, incorrect Security code.', WDFMInstance(self::PLUGIN)->prefix ) ) );
            $_SESSION[ 'message_captcha' ] = $_SESSION[ 'massage_after_submit' . $id ];
            $_SESSION[ 'error_or_no' . $id ] = 1;
            $correct = FALSE;
          } else {
            $correct = TRUE;
          }
        }
      } else {
        $correct = TRUE;
      }
	    if ( !$this->fm_empty_field_validation( $id ) ) {
		$_SESSION['massage_after_submit' . $id] = addslashes( addslashes( __('Error, something went wrong.', WDFMInstance(self::PLUGIN)->prefix) ) );
		$_SESSION['error_or_no' . $id] = 1;
		$correct = FALSE;
	  }
      if ( $correct ) {
        $ip = $_SERVER[ 'REMOTE_ADDR' ];
        global $wpdb;
        $blocked_ip = $wpdb->get_var( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'formmaker_blocked WHERE ip="%s"', $ip ) );
        if ( $blocked_ip ) {
          $_SESSION[ 'massage_after_submit' . $id ] = addslashes( __( 'Your ip is blacklisted. Please contact the website administrator.', WDFMInstance(self::PLUGIN)->prefix ) );
          wp_redirect( $_SERVER[ "REQUEST_URI" ] );//to be checked
          exit;
        }
        if ( isset( $_POST[ "save_or_submit" . $id ] ) && $_POST[ "save_or_submit" . $id ] == 'save' ) {
          if ( WDFMInstance(self::PLUGIN)->is_free != 2 ) {
            $current_user = wp_get_current_user();
            $userid = '';
            $username = '';
            $useremail = '';
            $adminemail = get_option( 'admin_email' );
            $current_page_url = WDW_FM_Library(self::PLUGIN)->get_current_page_url();
            if ( $current_user->ID != 0 ) {
              $userid = $current_user->ID;
              $username = $current_user->display_name;
              $useremail = $current_user->user_email;
            }
            $custom_fields = array(
              "ip" => $ip,
              "subid" => '',
              "userid" => $userid,
              'adminemail' => $adminemail,
              "useremail" => $useremail,
              "username" => $username,
              'pageurl' => $current_page_url,
              'formtitle' => $form->title
            );
            do_action( 'WD_FM_SAVE_PROG_save_progress_init', array( 'id' => $id, 'addon_task' => 'save_progress', 'form' => $form, 'custom_fields' => $custom_fields ) );
          }
          return $all_files;
        }
        else {
          $result_temp = $this->save_db( $id_for_old );
          // Enqueue any message from an add-on to display.
          if ( isset( $result_temp[ 'message' ] ) ) {
            $_SESSION['massage_after_submit' . $id] = $result_temp['message'];
            $_SESSION['error_or_no' . $id ] = 0;
          }
          if ( isset( $result_temp['error'] ) ) {
            $this->remove( $result_temp['group_id'] );
            $_SESSION[ 'error_or_no' . $id ] = 1;
          }
          else {
            if ( WDFMInstance(self::PLUGIN)->is_free != 2 ) {
              do_action( 'WD_FM_SAVE_PROG_save_progress_init', array( 'id' => $id, 'addon_task' => 'clear_data' ) );
            }
            $this->gen_mail( $result_temp['group_id'], $result_temp['all_files'], $id_for_old, $result_temp['redirect_url'] );
          }
        }
      }
    }

    return $all_files;
  }

  /**
   * Select data from db for labels.
   *
   * @param string $db_info
   * @param string $label_column
   * @param string $table
   * @param string $where
   * @param string $order_by
   * @return mixed
   */
  public function select_data_from_db_for_labels( $db_info = '', $label_column = '', $table = '', $where = '', $order_by = '' ) {
    global $wpdb;
    $query = "SELECT `" . $label_column . "` FROM " . $table . $where . " ORDER BY " . $order_by;
    $db_info = trim($db_info, '[]');
    if ( $db_info ) {
      $temp = explode( '@@@wdfhostwdf@@@', $db_info );
      $host = $temp[ 0 ];
      $temp = explode( '@@@wdfportwdf@@@', $temp[ 1 ] );
      $port = $temp[ 0 ];
      if ($port) {
        $host .= ':' . $port;
      }
      $temp = explode( '@@@wdfusernamewdf@@@', $temp[ 1 ] );
      $username = $temp[ 0 ];
      $temp = explode( '@@@wdfpasswordwdf@@@', $temp[ 1 ] );
      $password = $temp[ 0 ];
      $temp = explode( '@@@wdfdatabasewdf@@@', $temp[ 1 ] );
      $database = $temp[ 0 ];
      $wpdb_temp = new wpdb( $username, $password, $database, $host );
      $choices_labels = $wpdb_temp->get_results( $query, ARRAY_N );
    } else {
      $choices_labels = $wpdb->get_results( $query, ARRAY_N );
    }

    return $choices_labels;
  }

  /**
   * Select data from db for values.
   *
   * @param string $db_info
   * @param string $value_column
   * @param string $table
   * @param string $where
   * @param string $order_by
   *
   * @return array|null|object
   */
  public function select_data_from_db_for_values( $db_info = '', $value_column = '', $table = '', $where = '', $order_by = '' ) {
    global $wpdb;
    $query = "SELECT `" . $value_column . "` FROM " . $table . $where . " ORDER BY " . $order_by;
    $db_info = trim($db_info, '[]');
    if ( $db_info ) {
      $temp = explode( '@@@wdfhostwdf@@@', $db_info );
      $host = $temp[ 0 ];
      $temp = explode( '@@@wdfportwdf@@@', $temp[ 1 ] );
      $port = $temp[0];
      if ($port) {
        $host .= ':' . $port;
      }
      $temp = explode( '@@@wdfusernamewdf@@@', $temp[ 1 ] );
      $username = $temp[ 0 ];
      $temp = explode( '@@@wdfpasswordwdf@@@', $temp[ 1 ] );
      $password = $temp[ 0 ];
      $temp = explode( '@@@wdfdatabasewdf@@@', $temp[ 1 ] );
      $database = $temp[ 0 ];
      $wpdb_temp = new wpdb( $username, $password, $database, $host );
      $choices_values = $wpdb_temp->get_results( $query, ARRAY_N );
    } else {
      $choices_values = $wpdb->get_results( $query, ARRAY_N );
    }

    return $choices_values;
  }

  /**
   * Save DB.
   *
   * @param int $id
   * @return array( 'error' => true, 'group_id' => $max, 'message' => '' ); in case of error | array('group_id' => $max, 'all_files' => '', 'redirect_url' => '')
   */
  public function save_db( $id = 0 ) {
    global $wpdb;
    $wp_userid = '';
    $wp_username = '';
    $wp_useremail = '';
    $current_user = wp_get_current_user();
    if ( $current_user->ID != 0 ) {
      $wp_userid = $current_user->ID;
      $wp_username = $current_user->display_name;
      $wp_useremail = $current_user->user_email;
    }
    $chgnac = TRUE;
    $paypal = array();
    $all_files = array();
	  $frontend_parmas = array();
    $paypal['item_name'] = array();
    $paypal['quantity'] = array();
    $paypal['amount'] = array();
    $paypal['on_os'] = array();
    $is_amount = FALSE;
    $total = 0;
    $form_currency = '$';
    $ip = $_SERVER['REMOTE_ADDR'];
    $adminemail = get_option('admin_email');
    $current_page_url = WDW_FM_Library(self::PLUGIN)->get_current_page_url();
    $form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "formmaker WHERE id= %d", $id ) );

    $form->gdpr_checkbox = 0;
    $form->gdpr_checkbox_text = __('I consent collecting this data and processing it according to {{privacy_policy}} of this website.', WDFMInstance(self::PLUGIN)->prefix);
    $form->save_ip = 1;
    $form->save_user_id = 1;
    if ( $form && isset($form->privacy) ) {
      if ( $form->privacy ) {
        $privacy = json_decode($form->privacy);
        $form->gdpr_checkbox = isset($privacy->gdpr_checkbox) ? $privacy->gdpr_checkbox : 0;
        $form->gdpr_checkbox_text = isset($privacy->gdpr_checkbox_text) ? $privacy->gdpr_checkbox_text : __('I consent collecting this data and processing it according to {{privacy_policy}} of this website.', WDFMInstance(self::PLUGIN)->prefix);
        $form->save_ip = isset($privacy->save_ip) ? $privacy->save_ip : 1;
        $form->save_user_id = isset($privacy->save_user_id) ? $privacy->save_user_id : 1;
      }
    }

    $formtitle = $form->title;
  	if ( !$form->form_front ) {
      $id = '';
    }
    $form_currency = '$';
    if ( $form->payment_currency ) {
      $form_currency = $form->payment_currency;
    }
    $form_currency = apply_filters('fm_form_currency', $form_currency, $id);
    $form_currency = WDW_FM_Library(self::PLUGIN)->replace_currency_code( $form_currency );
    $label_id = array();
    $label_label = array();
    $label_type = array();
    $disabled_fields = explode( ',', WDW_FM_Library(self::PLUGIN)->get('disabled_fields' . $id, ''));
    $disabled_fields = array_slice( $disabled_fields, 0, count( $disabled_fields ) - 1 );
    $label_all = explode( '#****#', $form->label_order_current );
    $label_all = array_slice( $label_all, 0, count( $label_all ) - 1 );
    foreach ( $label_all as $key => $label_each ) {
      $label_id_each = explode( '#**id**#', $label_each );
      array_push( $label_id, $label_id_each[ 0 ] );
      $label_order_each = explode( '#**label**#', $label_id_each[ 1 ] );
      array_push( $label_label, $label_order_each[ 0 ] );
      array_push( $label_type, $label_order_each[ 1 ] );
    }
    $group_id = $this->get_group_id();
    $fvals = array();

    $params = array();
    $fields = explode('*:*new_field*:*', $form->form_fields);
    $fields = array_slice($fields, 0, count($fields) - 1);
    foreach ( $fields as $field ) {
      $temp = explode('*:*id*:*', $field);
      $field_id = $temp[0];
      $temp = explode('*:*type*:*', $temp[1]);
      $temp = explode('*:*w_field_label*:*', $temp[1]);
      $params[$field_id] = $temp[1];
    }

    foreach ( $label_type as $key => $type ) {
      $value = '';

      if ( $type == "type_submit_reset"
        or $type == "type_map"
        or $type == "type_editor"
        or $type == "type_captcha"
        or $type == "type_arithmetic_captcha"
        or $type == "type_recaptcha"
        or $type == "type_button"
        or $type == "type_paypal_total" ) {
        continue;
      }
      $i = $label_id[ $key ];

      $missing_required_field = FALSE;
      $invalid_email_address = FALSE;
      $required = (isset($params[$i]) && strpos($params[$i], '*:*yes*:*w_required*:*') !== FALSE ? 1 : 0);

      if ( !in_array( $i, $disabled_fields ) ) {
        switch ( $type ) {
          case 'type_text':
          case 'type_password':
          case "type_own_select":
          case "type_country":
          case "type_number":
          case "type_phone_new":
          case "type_date_new":
          case "type_textarea":
          case "type_send_copy":
          case "type_spinner": {
            $value = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : "";
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_submitter_mail": {
            $value = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : "";
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            if ( $value !== '' && is_email($value) === FALSE ) {
              $invalid_email_address = TRUE;
            }
            break;
          }
          case "type_date": {
            $value = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : "";
            $date_format = isset( $_POST[ 'wdform_' . $i . "_date_format" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_date_format" . $id ] ) : "";
            if ( $value ) {
              if ( !$this->fm_validateDate( $value, $date_format ) ) {
                return array( 'error' => true, 'group_id' => $group_id, 'message' => __( "This is not a valid date format.", WDFMInstance(self::PLUGIN)->prefix ) );
              }
            }
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_date_range": {
            $value0 = isset( $_POST[ 'wdform_' . $i . "_element" . $id . "0" ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . "0" ] ) : "";
            $value1 = isset( $_POST[ 'wdform_' . $i . "_element" . $id . "1" ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . "1" ] ) : "";
            $value = ($value0) . ' - ' . ($value1);
            if ( $required && ( !isset( $_POST[ 'wdform_' . $i . "_element" . $id . "0" ] ) || !isset( $_POST[ 'wdform_' . $i . "_element" . $id . "1" ] ) ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_wdeditor": {
            $value = isset( $_POST[ 'wdform_' . $i . '_wd_editor' . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . '_wd_editor' . $id ] ) : "";
            break;
          }
          case "type_mark_map": {
            $value = (isset( $_POST[ 'wdform_' . $i . "_long" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_long" . $id ] ) : "") . '***map***' . (isset( $_POST[ 'wdform_' . $i . "_lat" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_lat" . $id ] ) : "");
            break;
          }
          case "type_date_fields": {
            $value0 = isset( $_POST[ 'wdform_' . $i . "_day" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_day" . $id ] ) : "";
            $value1 = isset( $_POST[ 'wdform_' . $i . "_month" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_month" . $id ] ) : "";
            $value2 = isset( $_POST[ 'wdform_' . $i . "_year" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_year" . $id ] ) : "";
            $value = ($value0) . '-' . ($value1) . '-' . ($value2);
            if ( $required && ( !isset( $_POST[ 'wdform_' . $i . "_day" . $id ] ) || !isset( $_POST[ 'wdform_' . $i . "_month" . $id ] ) || !isset( $_POST[ 'wdform_' . $i . "_year" . $id ] ) ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_time": {
            $value0 = isset( $_POST[ 'wdform_' . $i . "_hh" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_hh" . $id ] ) : "";
            $value1 = isset( $_POST[ 'wdform_' . $i . "_mm" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_mm" . $id ] ) : "";
            $value2 = isset( $_POST[ 'wdform_' . $i . "_ss" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_ss" . $id ] ) : "";
            $value3 = isset( $_POST[ 'wdform_' . $i . "_am_pm" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_am_pm" . $id ] ) : '';
            $value = ($value0) . ':' . ($value1);
            if ( $value2 ) {
              $value .= ':' . ($value2);
            }
            if ( $value3 ) {
              $value .= ' ' . $value3;
            }
            if ( $required && ( !isset( $_POST[ 'wdform_' . $i . "_hh" . $id ] ) || !isset( $_POST[ 'wdform_' . $i . "_mm" . $id ] ) ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_phone": {
            $value0 = isset( $_POST[ 'wdform_' . $i . "_element_first" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_first" . $id ] ) : "";
            $value1 = isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) : "";
            $value = ($value0) . ' ' . ($value1);
            if ( $required && ( !isset( $_POST[ 'wdform_' . $i . "_element_first" . $id ] ) || !isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_name": {
            $value0 = isset( $_POST[ 'wdform_' . $i . "_element_first" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_first" . $id ] ) : "";
            $value1 = isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) : "";
            $value2 = isset( $_POST[ 'wdform_' . $i . "_element_title" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_title" . $id ] ) : "";
            $value3 = isset( $_POST[ 'wdform_' . $i . "_element_middle" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_middle" . $id ] ) : "";

            $value = $value0 . '@@@' . $value1;
            if ( $value2 ) {
              $value = $value2 . '@@@' . $value;
            }
            if ( $value3 ) {
              $value .= '@@@' . $value3;
            }
            if ( $required && ( !isset( $_POST[ 'wdform_' . $i . "_element_first" . $id ] ) || !isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_file_upload": {
            if ( WDFMInstance(self::PLUGIN)->is_demo ) {
              $value = '';
            } else {
              if ( isset( $_POST[ 'wdform_' . $i . "_file_url" . $id . '_save' ] ) ) {
                $file_url = isset( $_POST[ 'wdform_' . $i . "_file_url" . $id . '_save' ] ) ? stripslashes( $_POST[ 'wdform_' . $i . "_file_url" . $id . '_save' ] ) : NULL;
                if ( isset( $file_url ) ) {
                  $all_files = isset( $_POST[ 'wdform_' . $i . "_all_files" . $id . '_save' ] ) ? json_decode( stripslashes( $_POST[ 'wdform_' . $i . "_all_files" . $id . '_save' ] ), TRUE ) : array();
                  $value = $file_url;
                }
              } else {
                $upload_dir = wp_upload_dir();
                $files = isset( $_FILES[ 'wdform_' . $i . '_file' . $id ] ) ? $_FILES[ 'wdform_' . $i . '_file' . $id ] : array();
                if ( !empty($files) ) {
			          	foreach ( $files[ 'name' ] as $file_key => $file_name ) {
                    if ( $file_name ) {
                      $untilupload = $form->form_fields;
                      $untilupload = substr( $untilupload, strpos( $untilupload, $i . '*:*id*:*type_file_upload' ), -1 );
                      $untilupload = substr( $untilupload, 0, strpos( $untilupload, '*:*new_field*:' ) );
                      $untilupload = explode( '*:*w_field_label_pos*:*', $untilupload );
                      $untilupload = $untilupload[ 1 ];
                      $untilupload = explode( '*:*w_destination*:*', $untilupload );
                      $destination = explode( '*:*w_hide_label*:*', $untilupload[ 0 ] );
                      $destination = $destination[ 1 ];
                      $destination = str_replace( $upload_dir[ 'baseurl' ], '', $destination );
                      $destination = ltrim( $destination, '/' );
                      $destination = rtrim( $destination, '/' );
                      $untilupload = $untilupload[ 1 ];
                      $untilupload = explode( '*:*w_extension*:*', $untilupload );
                      $extension = $untilupload[ 0 ];
                      $untilupload = $untilupload[ 1 ];
                      $untilupload = explode( '*:*w_max_size*:*', $untilupload );
                      $max_size = $untilupload[ 0 ];
                      $untilupload = $untilupload[ 1 ];
                      $fileName = $files[ 'name' ][ $file_key ];
                      $fileSize = $files[ 'size' ][ $file_key ];
                      if ( $fileSize > $max_size * 1024 ) {
                        return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes(sprintf( __('The file exceeds the allowed size of %s KB.', WDFMInstance(self::PLUGIN)->prefix ), $max_size )));
                      }
                      $uploadedFileNameParts = explode( '.', $fileName );
                      $uploadedFileExtension = array_pop( $uploadedFileNameParts );
                      $to = strlen( $fileName ) - strlen( $uploadedFileExtension ) - 1;
                      $fileNameFree = substr( $fileName, 0, $to );
                      $invalidFileExts = explode( ',', $extension );
                      $extOk = FALSE;
                      foreach ( $invalidFileExts as $key => $valuee ) {
                        if ( is_numeric( strpos( strtolower( $valuee ), strtolower( $uploadedFileExtension ) ) ) ) {
                          $extOk = TRUE;
                        }
                      }
                      if ( $extOk == FALSE ) {
                        return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( __( 'Can not upload this type of file.', WDFMInstance(self::PLUGIN)->prefix ) ) );
                      }
                      $fileTemp = $files[ 'tmp_name' ][ $file_key ];
                      $p = 1;
                      if ( !file_exists( $upload_dir[ 'basedir' ] . '/' . $destination ) ) {
                        $array_dir = explode( '/', $destination );
                        if ( !empty( $array_dir ) ) {
                          $dirTmp = $upload_dir[ 'basedir' ] . '/';
                          foreach ( $array_dir as $dir ) {
                            if ( !empty( $dir ) ) {
                              $dirTmp .= $dir . '/';
                              if ( !is_dir( $dirTmp ) ) {
                                mkdir( $dirTmp, 0777 );
                              }
                            }
                          }
                        }
                      }
                      if ( file_exists( $upload_dir[ 'basedir' ] . '/' . $destination . "/" . $fileName ) ) {
                        $fileName1 = $fileName;
                        while ( file_exists( $upload_dir[ 'basedir' ] . '/' . $destination . "/" . $fileName1 ) ) {
                          $to = strlen( $file_name ) - strlen( $uploadedFileExtension ) - 1;
                          $fileName1 = substr( $fileName, 0, $to ) . '(' . $p . ').' . $uploadedFileExtension;
                          //  $file['name'] = $fileName;
                          $p++;
                        }
                        $fileName = $fileName1;
                      }
                      // for dropbox & google drive integration addons
                      $check_both = 0;
                      if ( $form->save_uploads == 0 ) {
                        if( !function_exists('is_plugin_active') ) {
                          include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                        }
                        if ( defined( 'WD_FM_DBOX_INT' ) && is_plugin_active( constant( 'WD_FM_DBOX_INT' ) ) ) {
                          $enable = $wpdb->get_var( "SELECT enable FROM " . $wpdb->prefix . "formmaker_dbox_int WHERE form_id=" . $form->id );
                          if ( $enable == 1 ) {
                            $selectable_upload = $wpdb->get_var( "SELECT selectable_upload FROM " . $wpdb->prefix . "formmaker_dbox_int WHERE form_id=" . $form->id );
                            if ( (int)$selectable_upload == 1 ) {
                              $temp_dir_dbox = explode( '\\', $fileTemp );
                              $temp_dir_dbox = implode( '%%', $temp_dir_dbox );
                              $value .= $temp_dir_dbox . '*@@url@@*' . $fileName;
                            } else {
                              $dbox_folder_name = preg_replace( '/[^A-Z|a-z|0-9|\-|\\|\/]/', '', $form->title );
                              $dlink_dbox = '<a href="' . add_query_arg( array(
                                  'action' => 'WD_FM_DBOX_INT_init',
                                  'addon_task' => 'upload_dbox_file',
                                  'form_id' => $form->id,
                                ), admin_url( 'admin-ajax.php' ) ) . '&dbox_file_name=' . $fileName . '&dbox_folder_name=/' . $dbox_folder_name . '" target="_blank">' . $fileName . '</a>';
                              $value .= $dlink_dbox;
                            }
                            $files[ 'tmp_name' ][ $file_key ] = $fileTemp;
                            $temp_file = array(
                              "name" => $files[ 'name' ][ $file_key ],
                              "type" => $files[ 'type' ][ $file_key ],
                              "tmp_name" => $files[ 'tmp_name' ][ $file_key ],
                              'field_key' => $i,
                            );
                          } else {
                            $check_both++;
                          }
                        } else {
                          $check_both++;
                        }
                        if ( defined( 'WD_FM_GDRIVE_INT' ) && is_plugin_active( constant( 'WD_FM_GDRIVE_INT' ) ) ) {
                          $enable = $wpdb->get_var( "SELECT enable FROM " . $wpdb->prefix . "formmaker_gdrive_int WHERE form_id=" . $form->id );
                          if ( $enable == 1 ) {
                            $selectable_upload = $wpdb->get_var( "SELECT selectable_upload FROM " . $wpdb->prefix . "formmaker_gdrive_int WHERE form_id=" . $form->id );
                            if ( (int)$selectable_upload == 1 ) {
                              $temp_dir_dbox = explode( '\\', $fileTemp );
                              $temp_dir_dbox = implode( '%%', $temp_dir_dbox );
                              $value .= 'wdCloudAddon' . $temp_dir_dbox . '*@@url@@*' . $fileName . '*@@url@@*' . $files[ 'type' ][ $file_key ];
                            } else {
                              $dlink_dbox = '<a target="_blank" href="' . add_query_arg( array(
                                  'action' => 'WD_FM_GDRIVE_INT',
                                  'addon_task' => 'create_drive_link',
                                  'id' => $form->id,
                                ), admin_url( 'admin-ajax.php' ) ) . '&gdrive_file_name=' . $fileName . '&gdrive_folder_name=' . $form->title . '" >' . $fileName . '</a>';
                              $value .= $dlink_dbox;
                            }
                            $files[ 'tmp_name' ][ $file_key ] = $fileTemp;
                            $temp_file = array(
                              "name" => $files[ 'name' ][ $file_key ],
                              "type" => $files[ 'type' ][ $file_key ],
                              "tmp_name" => $files[ 'tmp_name' ][ $file_key ],
                              'field_key' => $i,
                            );
                          } else {
                            $check_both++;
                          }
                        } else {
                          $check_both++;
                        }
                      }
                      //
                      if ( $check_both != 0 ) {
                        $value .= '';
                        $files[ 'tmp_name' ][ $file_key ] = $fileTemp;
                        $temp_file = array(
                          "name" => $files[ 'name' ][ $file_key ],
                          "type" => $files[ 'type' ][ $file_key ],
                          "tmp_name" => $files[ 'tmp_name' ][ $file_key ],
                          'field_key' => $i,
                        );
                      }
                      if ( $form->save_uploads == 1 ) {
                        if ( !move_uploaded_file( $fileTemp, $upload_dir[ 'basedir' ] . '/' . $destination . '/' . $fileName ) ) {
                          return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( __( 'Error, file cannot be moved.', WDFMInstance(self::PLUGIN)->prefix ) ) );
                        }
                        $value .= $upload_dir[ 'baseurl' ] . '/' . $destination . '/' . $fileName . '*@@url@@*';
                        $files[ 'tmp_name' ][ $file_key ] = '/' . $destination . '/' . $fileName;
                        $temp_file = array(
                          "name" => $files[ 'name' ][ $file_key ],
                          "type" => $files[ 'type' ][ $file_key ],
                          "tmp_name" => $files[ 'tmp_name' ][ $file_key ],
                          'field_key' => $i,
                        );
                      }
                      array_push( $all_files, $temp_file );
                    }
                    if ( $required && !isset( $_FILES[ 'wdform_' . $i . '_file' . $id ] ) ) {
                      $missing_required_field = TRUE;
                    }
                  }
                }
              }
            }
            break;
          }
          case 'type_address': {
            $value = '*#*#*#';
            $element = isset( $_POST[ 'wdform_' . $i . "_street1" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_street1" . $id ] ) : NULL;
            if ( isset( $element ) ) {
              $value = $element;
              if ( $required && $value === '' ) {
                $missing_required_field = TRUE;
              }
              break;
            }
            $element = isset( $_POST[ 'wdform_' . $i . "_street2" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_street2" . $id ] ) : NULL;
            if ( isset( $element ) ) {
              $value = $element;
              break;
            }
            $element = isset( $_POST[ 'wdform_' . $i . "_city" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_city" . $id ] ) : NULL;
            if ( isset( $element ) ) {
              $value = $element;
              break;
            }
            $element = isset( $_POST[ 'wdform_' . $i . "_state" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_state" . $id ] ) : NULL;
            if ( isset( $element ) ) {
              $value = $element;
              break;
            }
            $element = isset( $_POST[ 'wdform_' . $i . "_postal" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_postal" . $id ] ) : NULL;
            if ( isset( $element ) ) {
              $value = $element;
              break;
            }
            $element = isset( $_POST[ 'wdform_' . $i . "_country" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_country" . $id ] ) : NULL;
            if ( isset( $element ) ) {
              $value = $element;
              break;
            }
            break;
          }
          case "type_hidden": {
            $value = isset( $_POST[ $label_label[ $key ] ] ) ? esc_html( $_POST[ $label_label[ $key ] ] ) : "";
            break;
          }
          case "type_radio": {
            $element = isset( $_POST[ 'wdform_' . $i . "_other_input" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_other_input" . $id ] ) : NULL;
            if ( isset( $element ) ) {
              $value = $element;
              break;
            }
            $value = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : "";

            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_checkbox": {
            $start = -1;
            $value = '';
            for ( $j = 0; $j < 100; $j++ ) {
              $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) : NULL;
              if ( isset( $element ) ) {
                $start = $j;
                break;
              }
            }
            $other_element_id = -1;
            $is_other = isset( $_POST[ 'wdform_' . $i . "_allow_other" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_allow_other" . $id ] ) : "";
            if ( $is_other == "yes" ) {
              $other_element_id = isset( $_POST[ 'wdform_' . $i . "_allow_other_num" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_allow_other_num" . $id ] ) : "";
            }
            if ( $start != -1 ) {
              for ( $j = $start; $j < 100; $j++ ) {
                $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) : NULL;
                if ( isset( $element ) ) {
                  if ( $j == $other_element_id ) {
                    $value = $value . (isset( $_POST[ 'wdform_' . $i . "_other_input" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_other_input" . $id ] ) : "") . '***br***';
                  } else {
                    $value = $value . (isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) : "") . '***br***';
                  }
                }
              }
            }

            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_paypal_price": {
            $value = isset( $_POST[ 'wdform_' . $i . "_element_dollars" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_dollars" . $id ] ) : 0;
            $value = (int)preg_replace( '/\D/', '', $value );
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element_dollars" . $id ] ) ) {
              $missing_required_field = TRUE;
              break;
            }
            if ( isset( $_POST[ 'wdform_' . $i . "_element_cents" . $id ] ) ) {
              $value = $value . '.' . (preg_replace( '/\D/', '', esc_html( $_POST[ 'wdform_' . $i . "_element_cents" . $id ] ) ));
            }
            $total += (float)($value);
            $paypal_option = array();
            if ( $value != 0 ) {
              $quantity = (isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) : 1);
              array_push( $paypal[ 'item_name' ], $label_label[ $key ] );
              array_push( $paypal[ 'quantity' ], $quantity );
              array_push( $paypal[ 'amount' ], $value );
              $is_amount = TRUE;
              array_push( $paypal[ 'on_os' ], $paypal_option );
            }
            $value = $value . $form_currency;
            break;
          }
          case "type_paypal_price_new": {
            $value = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) && $_POST[ 'wdform_' . $i . "_element" . $id ] ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : 0;
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
              break;
            }
            $total += (float)($value);
            $paypal_option = array();
            if ( $value != 0 ) {
              $quantity = (isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) : 1);
              array_push( $paypal[ 'item_name' ], $label_label[ $key ] );
              array_push( $paypal[ 'quantity' ], $quantity );
              array_push( $paypal[ 'amount' ], $value );
              $is_amount = TRUE;
              array_push( $paypal[ 'on_os' ], $paypal_option );
            }
            $value = $form_currency . $value;
            break;
          }
          case "type_paypal_select": {
            $value = '';
            if ( isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) && $_POST[ 'wdform_' . $i . "_element" . $id ] != '' ) {
              $value = esc_html( $_POST[ 'wdform_' . $i . "_element_label" . $id ] ) . ' : ' . $form_currency . (isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : "");
            }
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
              break;
            }
            $quantity = ( isset($_POST['wdform_' . $i . "_element_quantity" . $id]) ) ? intval( esc_html( $_POST['wdform_' . $i . "_element_quantity" . $id] ) ) : 1;
            $total += (float)(isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id ] : 0) * $quantity;
            array_push( $paypal[ 'item_name' ], $label_label[ $key ] . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_label" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_label" . $id ] : "") );
            array_push( $paypal[ 'quantity' ], $quantity );
            array_push( $paypal[ 'amount' ], (isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : "") );
            if ( isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) && $_POST[ 'wdform_' . $i . "_element" . $id ] != 0 ) {
              $is_amount = TRUE;
            }
            $element_quantity = isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) : NULL;
            if ( isset( $element_quantity ) && $value != '' ) {
              $value .= '***br***' . (isset( $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] ) : "") . ': ' . esc_html( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) . '***quantity***';
            }
            $paypal_option = array();
            $paypal_option[ 'on' ] = array();
            $paypal_option[ 'os' ] = array();
            for ( $k = 0; $k < 50; $k++ ) {
              $temp_val = isset( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) : NULL;
              if ( isset( $temp_val ) && $value != '' ) {
                array_push( $paypal_option[ 'on' ], (isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) : "") );
                array_push( $paypal_option[ 'os' ], (isset( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) : "") );
                $value .= '***br***' . (isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) : "") . ': ' . (isset( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) : "") . '***property***';
              }
            }
            array_push( $paypal[ 'on_os' ], $paypal_option );
            break;
          }
          case "type_paypal_radio": {
            $value = '';
            $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) && $_POST[ 'wdform_' . $i . "_element" . $id ] ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : '';
            if ( $element ) {
              $value = (isset( $_POST[ 'wdform_' . $i . "_element_label" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_label" . $id ] ) : '') . ' : ' . $form_currency . $element;
            }
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
              break;
            }
            $quantity = ( isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ]) ) ? intval( esc_html( $_POST['wdform_' . $i . "_element_quantity" . $id] ) ) : 1;
            $total += (float)(isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id ] : 0) * $quantity;
            array_push( $paypal[ 'item_name' ], $label_label[ $key ] . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_label" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_label" . $id ] ) : "") );
            array_push( $paypal[ 'quantity' ], $quantity );
            array_push( $paypal[ 'amount' ], (isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : 0) );
            if ( isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) && $_POST[ 'wdform_' . $i . "_element" . $id ] != 0 ) {
              $is_amount = TRUE;
            }
            $element_quantity = isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) : NULL;
            if ( isset( $element_quantity ) && $value != '' ) {
              $value .= '***br***' . (isset( $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] ) : "") . ': ' . esc_html( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) . '***quantity***';
            }
            $paypal_option = array();
            $paypal_option[ 'on' ] = array();
            $paypal_option[ 'os' ] = array();
            for ( $k = 0; $k < 50; $k++ ) {
              $temp_val = isset( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) : NULL;
              if ( isset( $temp_val ) && $value != '' ) {
                array_push( $paypal_option[ 'on' ], (isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) : "") );
                array_push( $paypal_option[ 'os' ], esc_html( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) );
                $value .= '***br***' . (isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) : "") . ': ' . esc_html( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) . '***property***';
              }
            }
            array_push( $paypal[ 'on_os' ], $paypal_option );
            break;
          }
          case "type_paypal_shipping": {
            $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) && $_POST[ 'wdform_' . $i . "_element" . $id ] ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : '';
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
              break;
            }
            if ( $element ) {
              $value = (isset( $_POST[ 'wdform_' . $i . "_element_label" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_label" . $id ] ) : '') . ' : ' . $form_currency . $element;
            } else {
              $value = '';
            }
            $paypal[ 'shipping' ] = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : "";
            break;
          }
          case "type_paypal_checkbox": {
            $start = -1;
            $value = '';
            for ( $j = 0; $j < 100; $j++ ) {
              $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) : NULL;
              if ( isset( $element ) ) {
                $start = $j;
                break;
              }
            }
            $other_element_id = -1;
            $is_other = isset( $_POST[ 'wdform_' . $i . "_allow_other" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_allow_other" . $id ] ) : "";
            if ( $is_other == "yes" ) {
              $other_element_id = isset( $_POST[ 'wdform_' . $i . "_allow_other_num" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_allow_other_num" . $id ] ) : "";
            }
            if ( $start != -1 ) {
              for ( $j = $start; $j < 100; $j++ ) {
                $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) : NULL;
                if ( isset( $element ) ) {
                  if ( $j == $other_element_id ) {
                    $value = $value . (isset( $_POST[ 'wdform_' . $i . "_other_input" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_other_input" . $id ] ) : "") . '***br***';
                  } else {
                    $element = (isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) && $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) : 0);
                    $value = $value . (isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j . "_label" ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . $j . "_label" ] ) : "") . ' - ' . $form_currency . $element . '***br***';
                    $quantity = ( isset($_POST[ 'wdform_' . $i . "_element_quantity" . $id]) ) ? intval( esc_html( $_POST['wdform_' . $i . "_element_quantity" . $id] ) ) : 1;
                    $total += (float)(isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . $j ] : 0) * (float)($quantity);
                    array_push( $paypal[ 'item_name' ], $label_label[ $key ] . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j . "_label" ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . $j . "_label" ] ) : "") );
                    array_push( $paypal[ 'quantity' ], $quantity );
                    array_push( $paypal[ 'amount' ], (isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? ($_POST[ 'wdform_' . $i . "_element" . $id . $j ] == '' ? '0' : esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] )) : "") );
                    if ( isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) && $_POST[ 'wdform_' . $i . "_element" . $id . $j ] != 0 ) {
                      $is_amount = TRUE;
                    }
                    $paypal_option = array();
                    $paypal_option[ 'on' ] = array();
                    $paypal_option[ 'os' ] = array();
                    for ( $k = 0; $k < 50; $k++ ) {
                      $temp_val = isset( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) : NULL;
                      if ( isset( $temp_val ) ) {
                        array_push( $paypal_option[ 'on' ], isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) : "" );
                        array_push( $paypal_option[ 'os' ], esc_html( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) );
                      }
                    }
                    array_push( $paypal[ 'on_os' ], $paypal_option );
                  }
                }
              }
              $element_quantity = isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) : NULL;
              if ( isset( $element_quantity ) ) {
                $value .= (isset( $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] ) : "") . ': ' . esc_html( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) . '***quantity***';
              }
              for ( $k = 0; $k < 50; $k++ ) {
                $temp_val = isset( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) : NULL;
                if ( isset( $temp_val ) ) {
                  $value .= '***br***' . (isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) : "") . ': ' . $_POST[ 'wdform_' . $i . "_property" . $id . $k ] . '***property***';
                }
              }
            }
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_star_rating": {
            $value0 = isset( $_POST[ 'wdform_' . $i . "_selected_star_amount" . $id ] ) ? (int) $_POST[ 'wdform_' . $i . "_selected_star_amount" . $id ] : 0;
            $value1 = (isset( $_POST[ 'wdform_' . $i . "_star_amount" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_star_amount" . $id ] ) : "");
            $value = $value0 . '/' . $value1;
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_selected_star_amount" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_scale_rating": {
            $value0 = (isset( $_POST[ 'wdform_' . $i . "_scale_radio" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_scale_radio" . $id ] ) : 0);
            $value1 = (isset( $_POST[ 'wdform_' . $i . "_scale_amount" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_scale_amount" . $id ] ) : "");
            $value = $value0 . '/' . $value1;
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_scale_radio" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_slider": {
            $value = isset( $_POST[ 'wdform_' . $i . "_slider_value" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_slider_value" . $id ] ) : "";
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_slider_value" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_range": {
            $value0 = isset( $_POST[ 'wdform_' . $i . "_element" . $id . '0' ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . '0' ] ) : "";
            $value1 = isset( $_POST[ 'wdform_' . $i . "_element" . $id . '1' ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . '1' ] ) : "";
            $value = $value0 . '-' . $value1;
            if ( $required && ( !isset( $_POST[ 'wdform_' . $i . "_element" . $id . '0' ] ) || !isset( $_POST[ 'wdform_' . $i . "_element" . $id . '1' ] ) ) ) {
              $missing_required_field = TRUE;
            }
            break;
          }
          case "type_grading": {
            $value = "";
            $items = explode( ":", isset( $_POST[ 'wdform_' . $i . "_hidden_item" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_hidden_item" . $id ] ) : "" );
            for ( $k = 0; $k < sizeof( $items ) - 1; $k++ ) {
              $element = (isset( $_POST[ 'wdform_' . $i . "_element" . $id . '_' . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element" . $id . '_' . $k ] ) : "");
              $value .= $element . ':';
            }
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            $value .= (isset( $_POST[ 'wdform_' . $i . "_hidden_item" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_hidden_item" . $id ] ) : "") . '***grading***';
            break;
          }
          case "type_matrix": {
            $rows_of_matrix = explode( "***", isset( $_POST[ 'wdform_' . $i . "_hidden_row" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_hidden_row" . $id ] ) : "" );
            $rows_count = sizeof( $rows_of_matrix ) - 1;
            $isset = FALSE;
            $column_of_matrix = explode( "***", isset( $_POST[ 'wdform_' . $i . "_hidden_column" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_hidden_column" . $id ] ) : "" );
            $columns_count = sizeof( $column_of_matrix ) - 1;
            if ( isset( $_POST[ 'wdform_' . $i . "_input_type" . $id ] ) && $_POST[ 'wdform_' . $i . "_input_type" . $id ] == "radio" ) {
              $input_value = "";
              for ( $k = 1; $k <= $rows_count; $k++ ) {
                $element = (isset( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k ] ) : 0);
                if ( $element ) {
                  $isset = TRUE;
                }
                $input_value .= $element . "***";
              }
            }
            if ( isset( $_POST[ 'wdform_' . $i . "_input_type" . $id ] ) && $_POST[ 'wdform_' . $i . "_input_type" . $id ] == "checkbox" ) {
              $input_value = "";
              for ( $k = 1; $k <= $rows_count; $k++ ) {
                for ( $j = 1; $j <= $columns_count; $j++ ) {
                  $element = (isset( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k . '_' . $j ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k . '_' . $j ] ) : 0);
                  if ( $element ) {
                    $isset = TRUE;
                  }
                  $input_value .= $element . "***";
                }
              }
            }
            if ( isset( $_POST[ 'wdform_' . $i . "_input_type" . $id ] ) && $_POST[ 'wdform_' . $i . "_input_type" . $id ] == "text" ) {
              $input_value = "";
              for ( $k = 1; $k <= $rows_count; $k++ ) {
                for ( $j = 1; $j <= $columns_count; $j++ ) {
                  $element = (isset( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k . '_' . $j ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k . '_' . $j ] ) : "");
                  if ( $element ) {
                    $isset = TRUE;
                  }
                  $input_value .= $element . "***";
                }
              }
            }
            if ( isset( $_POST[ 'wdform_' . $i . "_input_type" . $id ] ) && $_POST[ 'wdform_' . $i . "_input_type" . $id ] == "select" ) {
              $input_value = "";
              for ( $k = 1; $k <= $rows_count; $k++ ) {
                for ( $j = 1; $j <= $columns_count; $j++ ) {
                  $element = (isset( $_POST[ 'wdform_' . $i . "_select_yes_no" . $id . $k . '_' . $j ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_select_yes_no" . $id . $k . '_' . $j ] ) : "");
                  if ( $element ) {
                    $isset = TRUE;
                  }
                  $input_value .= $element . "***";
                }
              }
            }
            if ( $required && !isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
              $missing_required_field = TRUE;
            }
            $value = $rows_count . (isset( $_POST[ 'wdform_' . $i . "_hidden_row" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_hidden_row" . $id ] ) : "") . '***' . $columns_count . (isset( $_POST[ 'wdform_' . $i . "_hidden_column" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_hidden_column" . $id ] ) : "") . '***' . (isset( $_POST[ 'wdform_' . $i . "_input_type" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_input_type" . $id ] ) : "") . '***' . $input_value . '***matrix***';
            break;
          }
        }

        if ( $missing_required_field ) {
          return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( addslashes( sprintf( __( '%s field is required.', WDFMInstance(self::PLUGIN)->prefix ), $label_label[ $key ] ) ) ) );
        }
        if ( $invalid_email_address ) {
          return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( addslashes( __( 'Enter a valid email address.', WDFMInstance(self::PLUGIN)->prefix ) ) ) );
        }

        if ( $type == "type_address" ) {
          if ( $value == '*#*#*#' ) {
            continue;
          }
        }
        if ( $type == "type_send_copy" ) {
          // To prevent saving in Data base.
          continue;
        }
        if ( $type == "type_text" or $type == "type_textarea" or $type == "type_name" or $type == "type_submitter_mail" or $type == "type_number" or $type == "type_phone" or $type == "type_phone_new" ) {
          $untilupload = $form->form_fields;
          $untilupload = substr( $untilupload, strpos( $untilupload, $i . '*:*id*:*' . $type ), -1 );
          $untilupload = substr( $untilupload, 0, strpos( $untilupload, '*:*new_field*:' ) );

          $untilupload = explode( '*:*w_required*:*', $untilupload );

          $untilupload = $untilupload[ 1 ];

          $untilupload = explode( '*:*w_unique*:*', $untilupload );
          $unique_element = $untilupload[ 0 ];
          if ( strlen( $unique_element ) > 3 ) {
            $unique_element = substr( $unique_element, -3 );
          }
          if ( $unique_element == 'yes' ) {
            $unique = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . $wpdb->prefix . "formmaker_submits WHERE form_id= %d  and element_label= %s and element_value= %s", $id, $i, addslashes( $value ) ) );
            if ( $unique ) {
              return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( addslashes( sprintf( __( 'This field %s requires a unique entry.', WDFMInstance(self::PLUGIN)->prefix ), $label_label[ $key ] ) ) ) );
            }
          }
        }

        $save_or_no = TRUE;
        $fvals[ '{' . $i . '}' ] = str_replace( array(
          "***map***",
          "*@@url@@*",
          "@@@@@@@@@",
          "@@@",
          "***grading***",
          "***br***",
        ), array( " ", "", " ", " ", " ", ", " ), addslashes( $value ) );

        if ( $type == 'type_checkbox' ) {
          $fvals[ '{' . $i . '}' ] = rtrim( $fvals[ '{' . $i . '}' ], ', ' );
        }

        if ( $form->savedb ) {
          $submition_data = array(
            'form_id' => $id,
            'element_label' => $i,
            'element_value' => stripslashes( $value ),
            'group_id' => $group_id,
            'date' => date( 'Y-m-d H:i:s' ),
          );
          if ( $form->save_ip ) {
            $submition_data['ip'] = $_SERVER['REMOTE_ADDR'];
          }
          if ( $form->save_user_id ) {
            $submition_data['user_id_wd'] = $current_user->ID;
          }

          $save_or_no = $wpdb->insert( $wpdb->prefix . "formmaker_submits", $submition_data );
        }
        if ( !$save_or_no ) {
          return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( __( 'Database error occurred. Please try again.', WDFMInstance(self::PLUGIN)->prefix ) ) );
        }
        $chgnac = FALSE;
      } else {
        $fvals[ '{' . $i . '}' ] = '';
      }
    }

    $user_fields = array(
      "subid" => $group_id,
      "ip" => $ip,
      "userid" => $wp_userid,
      "username" => $wp_username,
      "useremail" => $wp_useremail,
    );
    $queries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "formmaker_query WHERE form_id=%d", (int)$id ) );
    if ( $queries ) {
      foreach ( $queries as $query ) {
        $temp = explode( '***wdfcon_typewdf***', $query->details );
        $con_type = $temp[ 0 ];
        $temp = explode( '***wdfcon_methodwdf***', $temp[ 1 ] );
        $con_method = $temp[ 0 ];
        $temp = explode( '***wdftablewdf***', $temp[ 1 ] );
        $table_cur = $temp[ 0 ];
        $temp = explode( '***wdfhostwdf***', $temp[ 1 ] );
        $host = $temp[ 0 ];
        $temp = explode( '***wdfportwdf***', $temp[ 1 ] );
        $port = $temp[0];
        if ($port) {
          $host .= ':' . $port;
        }
        $temp = explode( '***wdfusernamewdf***', $temp[ 1 ] );
        $username = $temp[ 0 ];
        $temp = explode( '***wdfpasswordwdf***', $temp[ 1 ] );
        $password = $temp[ 0 ];
        $temp = explode( '***wdfdatabasewdf***', $temp[ 1 ] );
        $database = $temp[ 0 ];
        $query = str_replace( array_keys( $fvals ), $fvals, $query->query );
        foreach ( $user_fields as $user_key => $user_field ) {
          $query = str_replace( '{' . $user_key . '}', $user_field, $query );
        }
        if ( $con_type == 'remote' ) {
          $wpdb_temp = new wpdb( $username, $password, $database, $host );
          $wpdb_temp->query( $query );
        } else {
          $wpdb->query( $query );
        }
      }
    }

    // Get stripe post value.
    $stripe_post_key = 'stripeToken' . $id;
    $stripeToken = WDW_FM_Library(self::PLUGIN)->get( $stripe_post_key, '' );
    if ( $is_amount && $stripeToken ) {
      $wdstripe_products_data = new stdClass();
      $tax = $form->tax;
      $total = $total + ($total * $tax) / 100;
      $shipping = isset( $paypal[ 'shipping' ] ) ? $paypal[ 'shipping' ] : 0;
      $total = $total + $shipping;
      $total = round( $total, 2 );
      $wdstripe_products_data->currency = $form->payment_currency;
      $wdstripe_products_data->amount = $total;
      $wdstripe_products_data->shipping = $shipping;
      $wdstripe_products_data = json_encode( $wdstripe_products_data );
      $frontend_parmas['wdstripe_stripeToken'] = $stripeToken;
      $frontend_parmas['wdstripe_products_data'] = $wdstripe_products_data;
    }

    $str = '';
    if ( $form->paypal_mode && $form->paypal_mode == 1 ) {
      if ( $paypal[ 'item_name' ] ) {
        if ( $is_amount ) {
          $tax = $form->tax;
          $currency = $form->payment_currency;
          $business = $form->paypal_email;
          $ip = $_SERVER[ 'REMOTE_ADDR' ];
          $total2 = round( $total, 2 );

          $submition_data = array();

          $submition_data['form_id'] = $id;
          $submition_data['element_label'] = 'item_total';
          $submition_data['element_value'] = $form_currency . $total2;
          $submition_data['group_id'] = $group_id;
          $submition_data['date'] = date( 'Y-m-d H:i:s' );
          if ( $form->save_ip ) {
            $submition_data['ip'] = $ip;
          }
          if ( $form->save_user_id ) {
            $submition_data['user_id_wd'] = $current_user->ID;
          }

          $save_or_no = $wpdb->insert( $wpdb->prefix . "formmaker_submits", $submition_data );

          if ( !$save_or_no ) {
            return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( __( 'Database error occurred. Please try again.', WDFMInstance(self::PLUGIN)->prefix ) ) );
          }
          $total = $total + ($total * $tax) / 100;
          if ( isset( $paypal[ 'shipping' ] ) ) {
            $total = $total + $paypal[ 'shipping' ];
          }
          $total = round( $total, 2 );

          $submition_data['element_label'] = 'total';
          $submition_data['element_value'] = $form_currency . $total;

          $save_or_no = $wpdb->insert( $wpdb->prefix . "formmaker_submits", $submition_data );

          if ( !$save_or_no ) {
            return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( __( 'Database error occurred. Please try again.', WDFMInstance(self::PLUGIN)->prefix ) ) );
          }

          $submition_data['element_label'] = '0';
          $submition_data['element_value'] = 'In progress';

          $save_or_no = $wpdb->insert( $wpdb->prefix . "formmaker_submits", $submition_data );

          if ( !$save_or_no ) {
            return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( __( 'Database error occurred. Please try again.', WDFMInstance(self::PLUGIN)->prefix ) ) );
          }
          $str = '';
          if ( $form->checkout_mode == 1 || $form->checkout_mode == "production" ) {
            $str .= "https://www.paypal.com/cgi-bin/webscr?";
          } else {
            $str .= "https://www.sandbox.paypal.com/cgi-bin/webscr?";
          }
          $str .= "currency_code=" . $currency;
          $str .= "&business=" . urlencode( $business );
          $str .= "&cmd=" . "_cart";
          $str .= "&notify_url=" . admin_url( 'admin-ajax.php?action=checkpaypal%26form_id=' . $id . '%26group_id=' . $group_id );
          $str .= "&upload=" . "1";
          $str .= "&charset=UTF-8";
          if ( isset( $paypal[ 'shipping' ] ) ) {
            $str = $str . "&shipping_1=" . $paypal[ 'shipping' ];
            //	$str=$str."&weight_cart=".$paypal['shipping'];
            //	$str=$str."&shipping2=3".$paypal['shipping'];
            $str = $str . "&no_shipping=2";
          }
          $i = 0;
          foreach ( $paypal[ 'item_name' ] as $pkey => $pitem_name ) {
            if ( $paypal[ 'amount' ][ $pkey ] ) {
              $i++;
              $str = $str . "&item_name_" . $i . "=" . urlencode( $pitem_name );
              $str = $str . "&amount_" . $i . "=" . $paypal[ 'amount' ][ $pkey ];
              $str = $str . "&quantity_" . $i . "=" . $paypal[ 'quantity' ][ $pkey ];
              if ( $tax ) {
                $str = $str . "&tax_rate_" . $i . "=" . $tax;
              }
              if ( $paypal[ 'on_os' ][ $pkey ] ) {
                foreach ( $paypal[ 'on_os' ][ $pkey ][ 'on' ] as $on_os_key => $on_item_name ) {
                  $str = $str . "&on" . $on_os_key . "_" . $i . "=" . $on_item_name;
                  $str = $str . "&os" . $on_os_key . "_" . $i . "=" . $paypal[ 'on_os' ][ $pkey ][ 'os' ][ $on_os_key ];
                }
              }
            }
          }
        }
      }
    }
    if ( $form->mail_verify ) {
      WDW_FM_Library(self::PLUGIN)->start_session();
      unset( $_SESSION[ 'hash' ] );
      unset( $_SESSION[ 'gid' ] );
      $ip = $_SERVER[ 'REMOTE_ADDR' ];
      $_SESSION[ 'gid' ] = $group_id;
      $send_tos = explode( '**', $form->send_to );
      if ( $send_tos ) {
        foreach ( $send_tos as $send_index => $send_to ) {
          $_SESSION[ 'hash' ][] = md5( $ip . time() . rand() );
          $send_to = str_replace( '*', '', $send_to );

          $submition_data = array(
            'form_id' => $id,
            'element_label' => 'verifyInfo@' . $send_to,
            'element_value' => $_SESSION[ 'hash' ][ $send_index ] . "**" . $form->mail_verify_expiretime . "**" . $send_to,
            'group_id' => $group_id,
            'date' => date( 'Y-m-d H:i:s' ),
          );
          if ( $form->save_ip ) {
            $submition_data['ip'] = $ip;
          }
          if ( $form->save_user_id ) {
            $submition_data['user_id_wd'] = $current_user->ID;
          }

          $save_or_no = $wpdb->insert( $wpdb->prefix . "formmaker_submits", $submition_data );

          if ( !$save_or_no ) {
            return array( 'error' => true, 'group_id' => $group_id, 'message' => addslashes( __( 'Database error occurred. Please try again.', WDFMInstance(self::PLUGIN)->prefix ) ) );
          }
        }
      }
    }
    if ( $chgnac ) {
      WDW_FM_Library(self::PLUGIN)->start_session();
      if ( $form->submit_text_type != 4 ) {
        $_SESSION[ 'massage_after_submit' . $id ] = addslashes( addslashes( __( 'Nothing was submitted.', WDFMInstance(self::PLUGIN)->prefix ) ) );
      }
      $_SESSION[ 'error_or_no' . $id ] = 1;
      $_SESSION[ 'form_submit_type' . $id ] = $form->submit_text_type . ',' . $form->id . ',' . $group_id;
      wp_redirect( $_SERVER[ "REQUEST_URI" ] );
      exit;
    }

    if ( WDFMInstance(self::PLUGIN)->is_free != 2 ) {
      $custom_fields = array(
        "ip" => $ip,
        "subid" => $group_id,
        'adminemail' => $adminemail,
        "useremail" => $wp_useremail,
        "username" => $wp_username,
        'pageurl' => $current_page_url,
        'formtitle' => $formtitle
      );
      $frontend_parmas['form_id'] = $id;
      $frontend_parmas['fvals'] = $fvals;
      $frontend_parmas['form_currency'] = $form_currency;
      $frontend_parmas['custom_fields'] = $custom_fields;
      $frontend_parmas['all_files'] = json_encode($all_files);

      // Send stripe receipt to logged in user email or first email address in user emails list.
      $user_email = $wp_useremail;
      if ( $form->send_to ) {
        $send_tos = explode( '**', $form->send_to );
        foreach ( $send_tos as $index => $send_to ) {
          $send_to = str_replace( '*', '', $send_to );

          $recipient = isset( $_POST[ 'wdform_' . str_replace( '*', '', $send_to ) . "_element" . $id ] ) ? $_POST[ 'wdform_' . $send_to . "_element" . $id ] : NULL;
          if ( $recipient ) {
            $user_email = $recipient;
            break;
          }
        }
      }
      $frontend_parmas['user_email'] = ( is_email($user_email) !== FALSE ) ? $user_email : '';
      do_action( 'fm_addon_frontend_init', $frontend_parmas );
    }
    $return_value = array( 'group_id' => $group_id, 'all_files' => $all_files, 'redirect_url' => $str );
    // Get output from add-ons.
    $return_value = $this->get_output_from_add_ons( $return_value );
    return $return_value;
  }

  /**
   * Get output from add-ons.
   *
   * @param array $params
   * @return array $$outputs
   */
  private function get_output_from_add_ons( $params = array() ) {
	  $data = array();
	  $outputsTmp = apply_filters( 'fm_output_error_from_add_ons', $data, $params );
	  $outputs =  array_merge($outputsTmp, $params);
	  if ( !empty($outputs['error']) ) {
		$outputs['error'] = 1;
	  }
	  return $outputs;
  }

  /**
   * @return int autoincrement value for group_id.
   */
  public function get_group_id() {
    global $wpdb;
    // Get max id from used group ids to prevent conflicts.
    $max_id = $wpdb->get_var( 'SELECT MAX( group_id ) FROM ' . $wpdb->prefix . 'formmaker_submits' );
    $last_id = $wpdb->insert( $wpdb->prefix . 'formmaker_groups', array( 'id' => 'NULL' ) );
    // If somehow maximum group id is greater than autoincrement number.
    if ($last_id && $wpdb->insert_id <= $max_id) {
      $last_id = $wpdb->insert( $wpdb->prefix . 'formmaker_groups', array( 'id' => $max_id + 1 ) );
    }
    if ($last_id) {
      // Get an autoincrement number for group_id.
      return $wpdb->insert_id;
    }
    else {
      // Get max id if somehow table does not exist.
      return $max_id + 1;
    }
  }

  /**
   * Remove.
   *
   * @param int $group_id
   */
  public function remove( $group_id = 0 ) {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'formmaker_submits WHERE group_id= %d', $group_id ) );
  }

  /**
   * Get after submission text.
   *
   * @param int $form_id
   * @param int $group_id
   *
   * @return mixed|null|string
   */
  public function get_after_submission_text( $form_id = 0, $group_id = 0 ) {
    global $wpdb;
    $submit_text = $wpdb->get_var( "SELECT submit_text FROM " . $wpdb->prefix . "formmaker WHERE id='" . $form_id . "'" );
    $current_user = wp_get_current_user();
    if ( $current_user->ID != 0 ) {
      $userid = $current_user->ID;
      $username = $current_user->display_name;
      $useremail = $current_user->user_email;
    } else {
      $userid = '';
      $username = '';
      $useremail = '';
    }
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "formmaker WHERE id=%d", $form_id ) );
    $ip = $_SERVER['REMOTE_ADDR'];
    $adminemail = get_option( 'admin_email' );
    $current_page_url = WDW_FM_Library(self::PLUGIN)->get_current_page_url();
    $formtitle = $row->title;
    $label_order_original = array();
    $label_order_ids = array();
    $submission_array = array();
    $label_all = explode( '#****#', $row->label_order_current );
    $label_all = array_slice( $label_all, 0, count( $label_all ) - 1 );
    foreach ( $label_all as $key => $label_each ) {
      $label_id_each = explode( '#**id**#', $label_each );
      $label_id = $label_id_each[ 0 ];
      array_push( $label_order_ids, $label_id );
      $label_order_each = explode( '#**label**#', $label_id_each[ 1 ] );
      $label_order_original[ $label_id ] = $label_order_each[ 0 ];
    }
    $submissions_row = $wpdb->get_results( $wpdb->prepare( "SELECT `element_label`, `element_value` FROM " . $wpdb->prefix . "formmaker_submits WHERE form_id=%d AND group_id=%d", $form_id, $group_id ) );
    foreach ( $submissions_row as $sub_row ) {
      $submission_array[ $sub_row->element_label ] = $sub_row->element_value;
    }
    foreach ( $label_order_original as $key => $label_each ) {
      if (isset($submission_array[ $key ])) {
        $submit_text = str_replace( array( '%' . $label_each . '%', '{' . $key . '}' ), $submission_array[ $key ], $submit_text );
      }
    }
    $custom_fields = array(
      "ip" => $ip,
      "subid" => $group_id,
      "userid" => $userid,
      'adminemail' => $adminemail,
      "useremail" => $useremail,
      "username" => $username,
      'pageurl' => $current_page_url,
      'formtitle' => $formtitle
    );
    foreach ( $custom_fields as $key => $custom_field ) {
      $key_replace = array( '%' . $key . '%', '{' . $key . '}' );
      $submit_text = str_replace( $key_replace, $custom_field, $submit_text );
    }
    $submit_text = str_replace( array(
      "***map***",
      "*@@url@@*",
      "@@@@@@@@@",
      "@@@",
      "***grading***",
      "***br***",
      "***star_rating***",
    ), array( " ", "", " ", " ", " ", ", ", " " ), $submit_text );

    return $submit_text;
  }

  /**
   * Increment views count.
   *
   * @param $id
   */
  public function increment_views_count( $id = 0 ) {
    global $wpdb;
    $vives_form = $wpdb->get_var( $wpdb->prepare( "SELECT views FROM " . $wpdb->prefix . "formmaker_views WHERE form_id=%d", $id ) );
    if ( isset( $vives_form ) ) {
      $vives_form = $vives_form + 1;
      $wpdb->update( $wpdb->prefix . "formmaker_views", array(
        'views' => $vives_form,
      ), array( 'form_id' => $id ), array(
        '%d',
      ), array( '%d' ) );
    } else {
      $wpdb->insert( $wpdb->prefix . 'formmaker_views', array(
        'form_id' => $id,
        'views' => 1,
      ), array(
        '%d',
        '%d',
      ) );
    }
  }

  /**
   * Gen mail.
   *
   * @param int $group_id
   * @param array $all_files
   * @param int $id
   * @param string $str
   * @return array
   */
  public function gen_mail( $group_id = 0, $all_files = array(), $id = 0, $str = '' ) {
    global $wpdb;
    WDW_FM_Library(self::PLUGIN)->start_session();
    // checking save uploads option
    $upload_dir = wp_upload_dir();
    $save_uploads = $wpdb->get_var( "SELECT save_uploads FROM " . $wpdb->prefix . "formmaker WHERE id=" . $id );
    if ( $save_uploads == 0 ) {
      $destination = $upload_dir[ 'basedir' ] . '/tmpAddon';
      if ( !file_exists( $destination ) ) {
        mkdir( $destination, 0777 );
      }
      foreach ( $all_files as &$all_file ) {
        $fileTemp = $all_file[ 'tmp_name' ];
        $fileName = $all_file[ 'name' ];
        if ( !move_uploaded_file( $fileTemp, $destination . '/' . $fileName ) ) {
          return array( 1, addslashes( __( 'Error, file cannot be moved.', WDFMInstance(self::PLUGIN)->prefix ) ) );
        }
        $all_file[ 'tmp_name' ] = $destination . "/" . $fileName;
      }
    }
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "formmaker WHERE id=%d", $id ) );
    if ( !$row->form_front ) {
      $id = '';
    }
    $ip = $_SERVER['REMOTE_ADDR'];
    $adminemail = get_option( 'admin_email' );
    $current_page_url = WDW_FM_Library(self::PLUGIN)->get_current_page_url();
    $formtitle = $row->title;
    $current_user = wp_get_current_user();
    $username = '';
    $useremail = '';
    if ( $current_user->ID != 0 ) {
      $username = $current_user->display_name;
      $useremail = $current_user->user_email;
    }
    $label_order_original = array();
    $label_order_ids = array();
    $label_label = array();
    $total = 0;
    $form_currency = '$';
    if ( $row->payment_currency ) {
      $form_currency = WDW_FM_Library(self::PLUGIN)->replace_currency_code( $row->payment_currency );
    }
    $form_currency = apply_filters('fm_form_currency', $form_currency, $id);

    $this->custom_fields['ip'] = $ip;
    $this->custom_fields['subid'] = $group_id;
    $this->custom_fields['adminemail'] = $adminemail;
    $this->custom_fields['useremail'] = $useremail;
    $this->custom_fields['username'] = $username;
    $this->custom_fields['pageurl'] = $current_page_url;
    $this->custom_fields['formtitle'] = $formtitle;

    $label_type = array();
    $label_all = explode( '#****#', $row->label_order_current );
    $label_all = array_slice( $label_all, 0, count( $label_all ) - 1 );
    foreach ( $label_all as $key => $label_each ) {
      $label_id_each = explode( '#**id**#', $label_each );
      $label_id = $label_id_each[ 0 ];
      array_push( $label_order_ids, $label_id );
      $label_order_each = explode( '#**label**#', $label_id_each[ 1 ] );
      $label_order_original[ $label_id ] = $label_order_each[ 0 ];
      $label_type[ $label_id ] = $label_order_each[ 1 ];
      array_push( $label_label, $label_order_each[ 0 ] );
      array_push( $label_type, $label_order_each[ 1 ] );
    }
    $disabled_fields = explode( ',', WDW_FM_Library(self::PLUGIN)->get('disabled_fields' . $id, '') );
    $disabled_fields = array_slice( $disabled_fields, 0, count( $disabled_fields ) - 1 );
    $list = '<table cellpadding="3" cellspacing="0" style="width: 600px; border-bottom: 1px solid #CCC; border-right: 1px solid #CCC;">';
    $list_text_mode = '';
    $td_style = ' style="border-top: 1px solid #CCC; border-left: 1px solid #CCC; padding: 10px; color: #3D3D3D;"';
    foreach ( $label_order_ids as $key => $label_order_id ) {
      $i = $label_order_id;
      $type = $label_type[ $i ];
      if ( $type != "type_map" and $type != "type_submit_reset" and $type != "type_editor" and $type != "type_captcha" and $type != "type_arithmetic_captcha" and $type != "type_recaptcha" and $type != "type_button" ) {
        $element_label = $label_order_original[ $i ];
        if ( !in_array( $i, $disabled_fields ) ) {
          switch ( $type ) {
            case 'type_text':
            case 'type_password':
            case "type_date":
            case "type_date_new":
            case "type_own_select":
            case "type_country":
            case "type_number":
            case "type_phone_new": {
              $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . '>' . $element_label . '</td><td ' . $td_style . '>' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case "type_date_range": {
              $element0 = isset( $_POST[ 'wdform_' . $i . "_element" . $id . "0" ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . "0" ] : NULL;
              $element1 = isset( $_POST[ 'wdform_' . $i . "_element" . $id . "1" ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . "1" ] : NULL;
              if ( isset( $element0 ) && $this->empty_field( $element0, $row->mail_emptyfields ) && $this->empty_field( $element1, $row->mail_emptyfields ) ) {
                $element = $element0 . ' - ' . $element1;
                $list = $list . '<tr valign="top"><td ' . $td_style . '>' . $element_label . '</td><td ' . $td_style . '>' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case 'type_textarea': {
              $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? wpautop( $_POST[ 'wdform_' . $i . "_element" . $id ] ) : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . '>' . $element_label . '</td><td ' . $td_style . '>' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case "type_hidden": {
              $element = isset( $_POST[ $element_label ] ) ? $_POST[ $element_label ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . '>' . $element_label . '</td><td ' . $td_style . '>' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case "type_mark_map": {
              $element = isset( $_POST[ 'wdform_' . $i . "_long" . $id ] ) ? $_POST[ 'wdform_' . $i . "_long" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . '>' . $element_label . '</td><td ' . $td_style . '>Longitude:' . $element . '<br/>Latitude:' . (isset( $_POST[ 'wdform_' . $i . "_lat" . $id ] ) ? $_POST[ 'wdform_' . $i . "_lat" . $id ] : "") . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - Longitude:' . $element . ' Latitude:' . (isset( $_POST[ 'wdform_' . $i . "_lat" . $id ] ) ? $_POST[ 'wdform_' . $i . "_lat" . $id ] : "") . "\r\n";
              }
              break;
            }
            case "type_submitter_mail": {
              $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case "type_time": {
              $hh = isset( $_POST[ 'wdform_' . $i . "_hh" . $id ] ) ? $_POST[ 'wdform_' . $i . "_hh" . $id ] : NULL;
              if ( isset( $hh ) && ($this->empty_field( $hh, $row->mail_emptyfields ) || $this->empty_field( $_POST[ 'wdform_' . $i . "_mm" . $id ], $row->mail_emptyfields ) || $this->empty_field( $_POST[ 'wdform_' . $i . "_ss" . $id ], $row->mail_emptyfields )) ) {
                $ss = isset( $_POST[ 'wdform_' . $i . "_ss" . $id ] ) ? $_POST[ 'wdform_' . $i . "_ss" . $id ] : NULL;
                if ( isset( $ss ) ) {
                  $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $hh . ':' . (isset( $_POST[ 'wdform_' . $i . "_mm" . $id ] ) ? $_POST[ 'wdform_' . $i . "_mm" . $id ] : "") . ':' . $ss;
                  $list_text_mode = $list_text_mode . $element_label . ' - ' . $hh . ':' . (isset( $_POST[ 'wdform_' . $i . "_mm" . $id ] ) ? $_POST[ 'wdform_' . $i . "_mm" . $id ] : "") . ':' . $ss;
                } else {
                  $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $hh . ':' . (isset( $_POST[ 'wdform_' . $i . "_mm" . $id ] ) ? $_POST[ 'wdform_' . $i . "_mm" . $id ] : "");
                  $list_text_mode = $list_text_mode . $element_label . ' - ' . $hh . ':' . (isset( $_POST[ 'wdform_' . $i . "_mm" . $id ] ) ? $_POST[ 'wdform_' . $i . "_mm" . $id ] : "");
                }
                $am_pm = isset( $_POST[ 'wdform_' . $i . "_am_pm" . $id ] ) ? $_POST[ 'wdform_' . $i . "_am_pm" . $id ] : NULL;
                if ( isset( $am_pm ) ) {
                  $list = $list . ' ' . $am_pm . '</td></tr>';
                  $list_text_mode = $list_text_mode . $am_pm . "\r\n";
                } else {
                  $list = $list . '</td></tr>';
                  $list_text_mode = $list_text_mode . "\r\n";
                }
              }
              break;
            }
            case "type_phone": {
              $element_first = isset( $_POST[ 'wdform_' . $i . "_element_first" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_first" . $id ] : NULL;
              if ( isset( $element_first ) && $this->empty_field( $element_first, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $element_first . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_last" . $id ] : "") . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element_first . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_last" . $id ] : "") . "\r\n";
              }
              break;
            }
            case "type_name": {
              $element_first = isset( $_POST[ 'wdform_' . $i . "_element_first" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_first" . $id ] : NULL;
              if ( isset( $element_first ) ) {
                $element_title = isset( $_POST[ 'wdform_' . $i . "_element_title" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_title" . $id ] : NULL;
                $element_middle = isset( $_POST[ 'wdform_' . $i . "_element_middle" . $id ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_element_middle" . $id ] ) : NULL;
                if ( (isset( $element_title ) || isset( $element_middle )) && ($this->empty_field( $element_title, $row->mail_emptyfields ) || $this->empty_field( $element_first, $row->mail_emptyfields ) || $this->empty_field( $_POST[ 'wdform_' . $i . "_element_last" . $id ], $row->mail_emptyfields ) || $this->empty_field( $_POST[ 'wdform_' . $i . "_element_middle" . $id ], $row->mail_emptyfields )) ) {
                  $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . (isset( $_POST[ 'wdform_' . $i . "_element_title" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_title" . $id ] : '') . ' ' . $element_first . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_last" . $id ] : "") . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_middle" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_middle" . $id ] : "") . '</td></tr>';
                  $list_text_mode = $list_text_mode . $element_label . ' - ' . (isset( $_POST[ 'wdform_' . $i . "_element_title" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_title" . $id ] : '') . ' ' . $element_first . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_last" . $id ] : "") . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_middle" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_middle" . $id ] : "") . "\r\n";
                } else {
                  if ( $this->empty_field( $element_first, $row->mail_emptyfields ) || $this->empty_field( $_POST[ 'wdform_' . $i . "_element_last" . $id ], $row->mail_emptyfields ) ) {
                    $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $element_first . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_last" . $id ] : "") . '</td></tr>';
                    $list_text_mode = $list_text_mode . $element_label . ' - ' . $element_first . ' ' . (isset( $_POST[ 'wdform_' . $i . "_element_last" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_last" . $id ] : "") . "\r\n";
                  }
                }
              }
              break;
            }
            case "type_address": {
              $element = isset( $_POST[ 'wdform_' . $i . "_street1" . $id ] ) ? $_POST[ 'wdform_' . $i . "_street1" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $label_order_original[ $i ] . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $label_order_original[ $i ] . ' - ' . $element . "\r\n";
                break;
              }
              $element = isset( $_POST[ 'wdform_' . $i . "_street2" . $id ] ) ? $_POST[ 'wdform_' . $i . "_street2" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $label_order_original[ $i ] . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $label_order_original[ $i ] . ' - ' . $element . "\r\n";
                break;
              }
              $element = isset( $_POST[ 'wdform_' . $i . "_city" . $id ] ) ? $_POST[ 'wdform_' . $i . "_city" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $label_order_original[ $i ] . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $label_order_original[ $i ] . ' - ' . $element . "\r\n";
                break;
              }
              $element = isset( $_POST[ 'wdform_' . $i . "_state" . $id ] ) ? $_POST[ 'wdform_' . $i . "_state" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $label_order_original[ $i ] . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $label_order_original[ $i ] . ' - ' . $element . "\r\n";
                break;
              }
              $element = isset( $_POST[ 'wdform_' . $i . "_postal" . $id ] ) ? $_POST[ 'wdform_' . $i . "_postal" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $label_order_original[ $i ] . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $label_order_original[ $i ] . ' - ' . $element . "\r\n";
                break;
              }
              $element = isset( $_POST[ 'wdform_' . $i . "_country" . $id ] ) ? $_POST[ 'wdform_' . $i . "_country" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $label_order_original[ $i ] . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $label_order_original[ $i ] . ' - ' . $element . "\r\n";
                break;
              }
              break;
            }
            case "type_date_fields": {
              $day = isset( $_POST[ 'wdform_' . $i . "_day" . $id ] ) ? $_POST[ 'wdform_' . $i . "_day" . $id ] : NULL;
              $month = isset( $_POST[ 'wdform_' . $i . "_month" . $id ] ) ? $_POST[ 'wdform_' . $i . "_month" . $id ] : "";
              $year = isset( $_POST[ 'wdform_' . $i . "_year" . $id ] ) ? $_POST[ 'wdform_' . $i . "_year" . $id ] : "";
              if ( isset( $day ) && $this->empty_field( $day, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . (($day || $month || $year) ? $day . '-' . $month . '-' . $year : '') . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . (($day || $month || $year) ? $day . '-' . $month . '-' . $year : '') . "\r\n";
              }
              break;
            }
            case "type_radio": {
              $element = isset( $_POST[ 'wdform_' . $i . "_other_input" . $id ] ) ? $_POST[ 'wdform_' . $i . "_other_input" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
                break;
              }
              $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case "type_checkbox": {
              $start = -1;
              for ( $j = 0; $j < 100; $j++ ) {
                $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . $j ] : NULL;
                if ( isset( $element ) ) {
                  $start = $j;
                  break;
                }
              }
              $other_element_id = -1;
              $is_other = isset( $_POST[ 'wdform_' . $i . "_allow_other" . $id ] ) ? $_POST[ 'wdform_' . $i . "_allow_other" . $id ] : "";
              if ( $is_other == "yes" ) {
                $other_element_id = isset( $_POST[ 'wdform_' . $i . "_allow_other_num" . $id ] ) ? $_POST[ 'wdform_' . $i . "_allow_other_num" . $id ] : "";
              }
              if ( $start != -1 || ($start == -1 && $row->mail_emptyfields) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >';
                $list_text_mode = $list_text_mode . $element_label . ' - ';
              }
              if ( $start != -1 ) {
                for ( $j = $start; $j < 100; $j++ ) {
                  $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . $j ] : NULL;
                  if ( isset( $element ) ) {
                    if ( $j == $other_element_id ) {
                      $list = $list . (isset( $_POST[ 'wdform_' . $i . "_other_input" . $id ] ) ? $_POST[ 'wdform_' . $i . "_other_input" . $id ] : "") . '<br>';
                      $list_text_mode = $list_text_mode . (isset( $_POST[ 'wdform_' . $i . "_other_input" . $id ] ) ? $_POST[ 'wdform_' . $i . "_other_input" . $id ] : "") . ', ';
                    } else {
                      $list = $list . (isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . $j ] : "") . '<br>';
                      $list_text_mode = $list_text_mode . (isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . $j ] : "") . ', ';
                    }
                  }
                }
              }
              if ( $start != -1 || ($start == -1 && $row->mail_emptyfields) ) {
                $list = $list . '</td></tr>';
                $list_text_mode = $list_text_mode . "\r\n";
              }
              break;
            }
            case "type_paypal_price": {
              $value = 0;
              if ( isset( $_POST[ 'wdform_' . $i . "_element_dollars" . $id ] ) ) {
                $value = $_POST[ 'wdform_' . $i . "_element_dollars" . $id ];
              }
              if ( isset( $_POST[ 'wdform_' . $i . "_element_cents" . $id ] ) && $_POST[ 'wdform_' . $i . "_element_cents" . $id ] ) {
                $value = $value . '.' . $_POST[ 'wdform_' . $i . "_element_cents" . $id ];
              }
              if ( $this->empty_field( $value, $row->mail_emptyfields ) && $value != '.' ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $value . $form_currency . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $value . $form_currency . "\r\n";
              }
              break;
            }
            case "type_paypal_price_new": {
              $value = 0;
              if ( isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
                $value = $_POST[ 'wdform_' . $i . "_element" . $id ];
              }
              if ( $this->empty_field( $value, $row->mail_emptyfields ) && $value != '.' ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . ($value == '' ? '' : $form_currency) . $value . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $value . $form_currency . "\r\n";
              }
              break;
            }
            case "type_paypal_select": {
              $value = '';
              if ( isset( $_POST[ 'wdform_' . $i . "_element_label" . $id ] ) && $_POST[ 'wdform_' . $i . "_element" . $id ] != '' ) {
                $value = $_POST[ 'wdform_' . $i . "_element_label" . $id ] . ' : ' . $form_currency . $_POST[ 'wdform_' . $i . "_element" . $id ];
              }
              $is_element_quantity = isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) ? TRUE : FALSE;
              $element_quantity_label = (isset( $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] ) && $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ]) ? $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] : NULL;
              $element_quantity = (isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) && $_POST[ 'wdform_' . $i . "_element_quantity" . $id ]) ? $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] : NULL;
              if ( $value != '' ) {
                if ( $is_element_quantity ) {
                  $value .= '<br/>' . $element_quantity_label . ': ' . (($element_quantity == NULL) ? 0 : $element_quantity);
                }
                for ( $k = 0; $k < 50; $k++ ) {
                  $temp_val = isset( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) ? $_POST[ 'wdform_' . $i . "_property" . $id . $k ] : NULL;
                  if ( isset( $temp_val ) ) {
                    $value .= '<br/>' . (isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] : "") . ': ' . $temp_val;
                  }
                }
              }
              if ( $this->empty_field( $value, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $value . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . str_replace( '<br/>', ', ', $value ) . "\r\n";
              }
              break;
            }
            case "type_paypal_radio": {
              $value = '';
              if ( isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
                $value = $_POST[ 'wdform_' . $i . "_element_label" . $id ] . ' : ' . $form_currency . (isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id ] : "");
                $is_element_quantity = isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) ? TRUE : FALSE;
                $element_quantity_label = isset( $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] : NULL;
                $element_quantity = (isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) && $_POST[ 'wdform_' . $i . "_element_quantity" . $id ]) ? $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] : NULL;
                if ( !empty($is_element_quantity) ) {
                  $value .= '<br/>' . $element_quantity_label . ': ' . (($element_quantity == NULL) ? 0 : $element_quantity);
                }
                for ( $k = 0; $k < 50; $k++ ) {
                  $temp_val = isset( $_POST[ 'wdform_' . $i . "_property" . $id . $k ] ) ? $_POST[ 'wdform_' . $i . "_property" . $id . $k ] : NULL;
                  if ( isset( $temp_val ) ) {
                    $value .= '<br/>' . (isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] : "") . ': ' . $temp_val;
                  }
                }
              }
              if ( $this->empty_field( $value, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $value . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . str_replace( '<br/>', ', ', $value ) . "\r\n";
              }
              break;
            }
            case "type_paypal_shipping": {
              if ( isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ) {
                $value = $_POST[ 'wdform_' . $i . "_element_label" . $id ] . ' : ' . $form_currency . (isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id ] : "");
                if ( $this->empty_field( $value, $row->mail_emptyfields ) ) {
                  $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $value . '</td></tr>';
                  $list_text_mode = $list_text_mode . $element_label . ' - ' . $value . "\r\n";
                }
              } else {
                $value = '';
              }
              break;
            }
            case "type_paypal_checkbox": {
              $start = -1;
              for ( $j = 0; $j < 300; $j++ ) {
                $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . $j ] : NULL;
                if ( isset( $element ) ) {
                  $start = $j;
                  break;
                }
              }
              if ( $start != -1 || ($start == -1 && $row->mail_emptyfields) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . '>' . $element_label . '</td><td ' . $td_style . '>';
                $list_text_mode = $list_text_mode . $element_label . ' - ';
              }
              if ( $start != -1 ) {
                for ( $j = $start; $j < 300; $j++ ) {
                  $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . $j ] : NULL;
                  if ( isset( $element ) ) {
                    $list = $list . (isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j . "_label" ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . $j . "_label" ] : "") . ' - ' . $form_currency . ($element == '' ? '0' : $element) . '<br>';
                    $list_text_mode = $list_text_mode . (isset( $_POST[ 'wdform_' . $i . "_element" . $id . $j . "_label" ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . $j . "_label" ] : "") . ' - ' . ($element == '' ? '0' . $form_currency : $element) . $form_currency . ', ';
                  }
                }
              }
              if ( $start != -1 ) {
                $is_element_quantity = isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) ? TRUE : FALSE;
                $element_quantity_label = isset( $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element_quantity_label" . $id ] : NULL;
                $element_quantity = (isset( $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] ) && $_POST[ 'wdform_' . $i . "_element_quantity" . $id ]) ? $_POST[ 'wdform_' . $i . "_element_quantity" . $id ] : NULL;
                if ( $is_element_quantity ) {
                  $list = $list . $element_quantity_label . ': ' . (($element_quantity == NULL) ? 0 : $element_quantity);
                  $list_text_mode = $list_text_mode . $element_quantity_label . ': ' . (($element_quantity == NULL) ? 0 : $element_quantity) . ', ';
                }
              }
              if ( $start != -1 ) {
                for ( $k = 0; $k < 50; $k++ ) {
                  $temp_val = isset( $_POST[ 'wdform_' . $i . "_element_property_value" . $id . $k ] ) ? $_POST[ 'wdform_' . $i . "_element_property_value" . $id . $k ] : NULL;
                  if ( isset( $temp_val ) ) {
                    $list = $list . '<br/>' . (isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] : "") . ': ' . $temp_val;
                    $list_text_mode = $list_text_mode . (isset( $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] ) ? $_POST[ 'wdform_' . $i . "_element_property_label" . $id . $k ] : "") . ': ' . $temp_val . ', ';
                  }
                }
              }
              if ( $start != -1 || ($start == -1 && $row->mail_emptyfields) ) {
                $list = $list . '</td></tr>';
                $list_text_mode = $list_text_mode . "\r\n";
              }
              break;
            }
            case "type_paypal_total": {
              $element = isset( $_POST[ 'wdform_' . $i . "_paypal_total" . $id ] ) ? $_POST[ 'wdform_' . $i . "_paypal_total" . $id ] : "";
              if ( $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case "type_star_rating": {
              $element = isset( $_POST[ 'wdform_' . $i . "_star_amount" . $id ] ) ? $_POST[ 'wdform_' . $i . "_star_amount" . $id ] : NULL;
              $selected = isset( $_POST[ 'wdform_' . $i . "_selected_star_amount" . $id ] ) ? $_POST[ 'wdform_' . $i . "_selected_star_amount" . $id ] : 0;
              if ( isset( $element ) && $this->empty_field( $selected, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $selected . '/' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $selected . '/' . $element . "\r\n";
              }
              break;
            }
            case "type_scale_rating": {
              $element = isset( $_POST[ 'wdform_' . $i . "_scale_amount" . $id ] ) ? $_POST[ 'wdform_' . $i . "_scale_amount" . $id ] : NULL;
              $selected = isset( $_POST[ 'wdform_' . $i . "_scale_radio" . $id ] ) ? $_POST[ 'wdform_' . $i . "_scale_radio" . $id ] : 0;
              if ( isset( $element ) && $this->empty_field( $selected, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $selected . '/' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $selected . '/' . $element . "\r\n";
              }
              break;
            }
            case "type_spinner": {
              $element = isset( $_POST[ 'wdform_' . $i . "_element" . $id ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case "type_slider": {
              $element = isset( $_POST[ 'wdform_' . $i . "_slider_value" . $id ] ) ? $_POST[ 'wdform_' . $i . "_slider_value" . $id ] : NULL;
              if ( isset( $element ) && $this->empty_field( $element, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case "type_range": {
              $element0 = isset( $_POST[ 'wdform_' . $i . "_element" . $id . '0' ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . '0' ] : NULL;
              $element1 = isset( $_POST[ 'wdform_' . $i . "_element" . $id . '1' ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . '1' ] : NULL;
              if ( (isset( $element0 ) && $this->empty_field( $element0, $row->mail_emptyfields )) || (isset( $element1 ) && $this->empty_field( $element1, $row->mail_emptyfields )) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >From: ' . $element0 . '<span style="margin-left:6px">  To </span>:' . $element1 . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - From: ' . $element0 . '   To: ' . $element1 . "\r\n";
              }
              break;
            }
            case "type_grading": {
              $element = isset( $_POST[ 'wdform_' . $i . "_hidden_item" . $id ] ) ? $_POST[ 'wdform_' . $i . "_hidden_item" . $id ] : "";
              $grading = explode( ":", $element );
              $items_count = sizeof( $grading ) - 1;
              $element = "";
              $total = 0;
              $form_empty_field = 0;
              for ( $k = 0; $k < $items_count; $k++ ) {
                $element .= $grading[ $k ] . ": " . (isset( $_POST[ 'wdform_' . $i . "_element" . $id . '_' . $k ] ) ? $_POST[ 'wdform_' . $i . "_element" . $id . '_' . $k ] : "") . "   ";
                $total += (isset( $_POST[ 'wdform_' . $i . "_element" . $id . '_' . $k ] ) ? (float) $_POST[ 'wdform_' . $i . "_element" . $id . '_' . $k ] : 0);
                if ( isset( $_POST[ 'wdform_' . $i . "_element" . $id . '_' . $k ] ) ) {
                  $form_empty_field = 1;
                }
              }
              $element .= "Total: " . $total;
              if ( isset( $element ) && $this->empty_field( $form_empty_field, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . '>' . $element_label . '</td><td ' . $td_style . ' >' . $element . '</td></tr>';
                $list_text_mode = $list_text_mode . $element_label . ' - ' . $element . "\r\n";
              }
              break;
            }
            case "type_matrix": {
              $form_empty_field = 0;
              $input_type = isset( $_POST[ 'wdform_' . $i . "_input_type" . $id ] ) ? $_POST[ 'wdform_' . $i . "_input_type" . $id ] : "";
              $mat_rows = explode( "***", isset( $_POST[ 'wdform_' . $i . "_hidden_row" . $id ] ) ? $_POST[ 'wdform_' . $i . "_hidden_row" . $id ] : "" );
              $rows_count = sizeof( $mat_rows ) - 1;
              $mat_columns = explode( "***", isset( $_POST[ 'wdform_' . $i . "_hidden_column" . $id ] ) ? $_POST[ 'wdform_' . $i . "_hidden_column" . $id ] : "" );
              $columns_count = sizeof( $mat_columns ) - 1;
              $matrix = '<table cellpadding="3" cellspacing="0" style="width: 600px; border-bottom: 1px solid #CCC; border-right: 1px solid #CCC;">';
              $matrix .= '<tr><td ' . $td_style . '></td>';
              for ( $k = 1; $k < count( $mat_columns ); $k++ ) {
                $matrix .= '<td style="border-top: 1px solid #CCC; border-left: 1px solid #CCC; padding: 10px; color: #3D3D3D; background-color: #EEEEEE; padding: 5px; ">' . $mat_columns[ $k ] . '</td>';
              }
              $matrix .= '</tr>';
              $aaa = Array();
              for ( $k = 1; $k <= $rows_count; $k++ ) {
                $matrix .= '<tr><td style="border-top: 1px solid #CCC; border-left: 1px solid #CCC; padding: 10px; color: #3D3D3D; background-color: #EEEEEE; padding: 5px;">' . $mat_rows[ $k ] . '</td>';
                if ( $input_type == "radio" ) {
                  $mat_radio = isset( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k ] ) ? $_POST[ 'wdform_' . $i . "_input_element" . $id . $k ] : 0;
                  if ( $mat_radio == 0 ) {
                    $checked = "";
                    $aaa[ 1 ] = "";
                  } else {
                    $aaa = explode( "_", $mat_radio );
                  }
                  for ( $j = 1; $j <= $columns_count; $j++ ) {
                    if ( $aaa[ 1 ] == $j ) {
                      $form_empty_field = 1;
                      $checked = "checked";
                    } else {
                      $checked = "";
                    }
                    $sign = $checked == 'checked' ? '&#10004;' : '';
                    $matrix .= '<td style="border-top: 1px solid #CCC; border-left: 1px solid #CCC; padding: 10px; color: #3D3D3D; text-align: center;">' . $sign . '</td>';
                  }
                } else {
                  if ( $input_type == "checkbox" ) {
                    for ( $j = 1; $j <= $columns_count; $j++ ) {
                      $checked = isset( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k . '_' . $j ] ) ? $_POST[ 'wdform_' . $i . "_input_element" . $id . $k . '_' . $j ] : "";
                      if ( $checked == 1 ) {
                        $form_empty_field = 1;
                        $checked = "checked";
                      } else {
                        $checked = "";
                      }
                      $sign = $checked == 'checked' ? '&#10004;' : '';
                      $matrix .= '<td style="border-top: 1px solid #CCC; border-left: 1px solid #CCC; padding: 10px; color: #3D3D3D; text-align: center;">' . $sign . '</td>';
                    }
                  } else {
                    if ( $input_type == "text" ) {
                      for ( $j = 1; $j <= $columns_count; $j++ ) {
                        $checked = isset( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k . '_' . $j ] ) ? esc_html( $_POST[ 'wdform_' . $i . "_input_element" . $id . $k . '_' . $j ] ) : "";
                        if ( $checked ) {
                          $form_empty_field = 1;
                        }
                        $matrix .= '<td style="border-top: 1px solid #CCC; border-left: 1px solid #CCC; padding: 10px; color: #3D3D3D; text-align: center;">' . $checked . '</td>';
                      }
                    } else {
                      for ( $j = 1; $j <= $columns_count; $j++ ) {
                        $checked = isset( $_POST[ 'wdform_' . $i . "_select_yes_no" . $id . $k . '_' . $j ] ) ? $_POST[ 'wdform_' . $i . "_select_yes_no" . $id . $k . '_' . $j ] : "";
                        if ( $checked ) {
                          $form_empty_field = 1;
                        }
                        $matrix .= '<td style="border-top: 1px solid #CCC; border-left: 1px solid #CCC; padding: 10px; color: #3D3D3D; text-align: center;">' . $checked . '</td>';
                      }
                    }
                  }
                }
                $matrix .= '</tr>';
              }
              $matrix .= '</table>';
              if ( isset( $matrix ) && $this->empty_field( $form_empty_field, $row->mail_emptyfields ) ) {
                $list = $list . '<tr valign="top"><td ' . $td_style . ' >' . $element_label . '</td><td ' . $td_style . ' >' . $matrix . '</td></tr>';
              }
              break;
            }
            default:
              break;
          }
        }
      }
    }
    $list = $list . '</table>';

    // User part.
    $fromname = $row->mail_from_name_user;
    $from_email = $row->mail_from_user;
    $subject = !empty( $row->mail_subject_user ) ? $row->mail_subject_user : $row->title;
    $attachment_user = array();
    if ( !WDFMInstance(self::PLUGIN)->is_demo ) {
      for ( $k = 0; $k < count( $all_files ); $k++ ) {
        if ( isset( $all_files[ $k ][ 'tmp_name' ] ) ) {
          if ( !isset( $attachment_user[ $all_files[ $k ][ 'field_key' ] ] ) ) {
            $attachment_user[ $all_files[ $k ][ 'field_key' ] ] = array();
          }
          $file_name = $all_files[$k]['tmp_name'];
          if ( $row->save_uploads == 1 ) {
            $basedir   = str_replace( site_url() .'/', '', $upload_dir['baseurl'] );
            $file = $basedir . $file_name;
          } else {
            $file = $file_name;
          }
          array_push($attachment_user[$all_files[$k]['field_key']], $file);
        }
      }
    }

    if ( $row->mail_mode_user ) {
      $content_type = "text/html";
      $list_user = wordwrap( $list, 100, "\n", TRUE );
      $new_script = wpautop( $row->script_mail_user );
    } else {
      $content_type = "text/plain";
      $list_user = wordwrap( $list_text_mode, 1000, "\n", TRUE );
      $new_script = str_replace( array( '<p>', '</p>' ), '', $row->script_mail_user );
    }
    foreach ( $label_order_original as $key => $label_each ) {
      $type = $label_type[ $key ];
      $key1 = $type == 'type_hidden' ? $label_each : $key;
      $label_each_decoded = htmlspecialchars_decode( $label_each );
      $new_value = $this->custom_fields_mail( $type, $key1, $id, $attachment_user, $form_currency );
      $key_replace = array( '%' . $label_each_decoded . '%', '{' . $key . '}' );

      $new_script = str_replace( $key_replace, $new_value, $new_script );

      if ( $type == "type_file_upload" ) {
        $new_value = $this->custom_fields_mail( $type, $key, $id, $attachment_user, $form_currency, 1 );
        $new_script = str_replace( array( '%' . $label_each_decoded . '(link)%', '{' . $key . '(link)}' ), $new_value, $new_script );
      }
      // Set from name value.
      if ( strpos( $fromname, '{' . $key . '}' ) > -1 || strpos( $fromname, '%' . $label_each . '%' ) > -1 ) {
        $new_value = str_replace( '<br>', ', ', $this->custom_fields_mail( $type, $key, $id, '', '' ) );
        if ( substr( $new_value, -2 ) == ', ' ) {
          $new_value = substr( $new_value, 0, -2 );
        }
        $fromname = str_replace( array( '%' . $label_each . '%', '{' . $key . '}' ), $new_value, $fromname );
      }
      // Set subject value.
      if ( (strpos( $subject, "{" . $key . "}" ) > -1) || (strpos( $subject, "%" . $label_each . "%" ) > -1) ) {
        $new_value = str_replace( '<br>', ', ', $this->custom_fields_mail( $type, $key1, $id, '', $form_currency ) );
        if ( substr( $new_value, -2 ) == ', ' ) {
          $new_value = substr( $new_value, 0, -2 );
        }

        $subject = str_replace( array( '%' . $label_each . '%', '{' . $key . '}' ), $new_value, $subject );
      }
    }
    $this->custom_fields['all'] = $list_user;

    foreach ( $this->custom_fields as $key => $custom_field ) {
      $new_script = str_replace( array( '%' . $key . '%', '{' . $key . '}' ), $custom_field, $new_script );
      $fromname = str_replace( array( '%' . $key . '%', '{' . $key . '}' ), $custom_field, $fromname );
      $from_email = str_replace( array( '%' . $key . '%', '{' . $key . '}' ), $custom_field, $from_email );
      $subject = str_replace( array( '%' . $key . '%', '{' . $key . '}' ), $custom_field, $subject );
    }
    if ( $fromname === '' ) {
      $fromname = get_bloginfo('name');
    }

    $header_arr = array();
    if ( $row->mail_from_user != '' ) {
      $header_arr[ 'from' ] = $from_email;
    }
    $header_arr['from_name'] = $fromname;
    $header_arr['content_type'] = $content_type;
    $header_arr['charset'] = 'UTF-8';
    $header_arr['reply_to'] = $row->reply_to_user;
    $header_arr['cc'] = $row->mail_cc_user;
    $header_arr['bcc'] = $row->mail_bcc_user;

    // PDF output for add-on.
    $pdf_data = array('attach_to_admin' => 0, 'attach_to_user' => 0, 'pdf_url' => '');
    if ( WDFMInstance(self::PLUGIN)->is_free != 2 ) {
      $pdf_data = apply_filters( 'fm_pdf_data_frontend', $pdf_data, array( 'custom_fields' => $this->custom_fields, 'form_id' => $id ) );
    }
    if ( $pdf_data['attach_to_user'] ) {
      array_push( $attachment_user, $pdf_data['pdf_url'] );
    }

	  if ( $row->sendemail && $row->send_to || (has_action('fm_set_params_frontend_init') && WDFMInstance(self::PLUGIN)->is_free != 2) ) {
      $body = $new_script;
      $send_tos = explode( '**', $row->send_to );
      $send_copy = isset( $_POST[ "wdform_send_copy_" . $id ] ) ? $_POST[ "wdform_send_copy_" . $id ] : NULL;
      if ( isset( $send_copy ) ) {
        $send = TRUE;
      }
      else {
        $mail_verification_post_id = (int)$wpdb->get_var( $wpdb->prepare( 'SELECT mail_verification_post_id FROM ' . $wpdb->prefix . 'formmaker WHERE id="%d"', $id ) );
        $verification_link = get_post( $mail_verification_post_id );

        // Replace pdf link in email body.
        $body = str_replace( '{PDF(link)}', site_url($pdf_data['pdf_url']), $body );

        foreach ( $send_tos as $index => $send_to ) {
          $send_to = str_replace( '*', '', $send_to );
          if ( $row->mail_verify && $verification_link !== NULL
            && (strpos( $new_script, "{verificationlink}" ) > -1 || strpos( $new_script, "%Verification link%" ) > -1) ) {
            $ver_link = $row->mail_mode_user ? "<a href =" . add_query_arg( array(
                'gid' => $_SESSION[ 'gid' ],
                'h' => $_SESSION[ 'hash' ][ $index ] . '@' . $send_to,
              ), get_post_permalink( $mail_verification_post_id ) ) . ">" . add_query_arg( array(
                'gid' => $_SESSION[ 'gid' ],
                'h' => $_SESSION[ 'hash' ][ $index ] . '@' . $send_to,
              ), get_post_permalink( $mail_verification_post_id ) ) . "</a><br/>" : add_query_arg( array(
              'gid' => $_SESSION[ 'gid' ],
              'h' => $_SESSION[ 'hash' ][ $index ] . '@' . $send_to,
            ), get_post_permalink( $mail_verification_post_id ) );
            $body = str_replace( array( '%Verification link%', '{verificationlink}' ), $ver_link, $new_script );
          }
          $recipient = isset( $_POST[ 'wdform_' . str_replace( '*', '', $send_to ) . "_element" . $id ] ) ? $_POST[ 'wdform_' . $send_to . "_element" . $id ] : NULL;
          if ( $recipient ) {
            if ( $row->mail_attachment_user ) {
              $remove_parrent_array_user = new RecursiveIteratorIterator( new RecursiveArrayIterator( $attachment_user ) );
              $attachment_user = iterator_to_array( $remove_parrent_array_user, FALSE );
            }
            else {
              $attachment_user = array();
            }
            if ( $row->sendemail && $row->send_to ) {
              WDW_FM_Library(self::PLUGIN)->mail($recipient, $subject, $body, $header_arr, $attachment_user);
            }
          }
        }
      }
    }

    // Admin part.
    if ( $row->sendemail || (has_action('fm_set_params_frontend_init') && WDFMInstance(self::PLUGIN)->is_free != 2) ) {
      $recipient = $row->mail ? $row->mail : '';
      $subject = !empty( $row->mail_subject ) ? $row->mail_subject : $row->title;

      $fromname = $row->from_name;
      $from_mail = $row->from_mail;
      $attachment = array();
      if ( !WDFMInstance(self::PLUGIN)->is_demo ) {
        for ( $k = 0; $k < count( $all_files ); $k++ ) {
          if ( isset( $all_files[ $k ][ 'tmp_name' ] ) ) {
            if ( !isset( $attachment[ $all_files[ $k ][ 'field_key' ] ] ) ) {
              $attachment[ $all_files[ $k ][ 'field_key' ] ] = array();
            }
            $file_name = $all_files[$k]['tmp_name'];
            if( $save_uploads == 1 ) {
                $basedir = str_replace(site_url() . '/', '', $upload_dir['baseurl']);
                $file = $basedir . $file_name;
            } else {
                $file = $file_name;
            }
            array_push( $attachment[ $all_files[ $k ][ 'field_key' ] ], $file );
          }
        }
      }
      if ( $pdf_data['attach_to_admin'] ) {
        array_push( $attachment, $pdf_data['pdf_url'] );
      }
      if ( $row->mail_mode ) {
        $content_type = "text/html";
        $list = wordwrap( $list, 100, "\n", TRUE );
        $new_script = wpautop( $row->script_mail );
      } else {
        $content_type = "text/plain";
        $list = $list_text_mode;
        $list = wordwrap( $list, 1000, "\n", TRUE );
        $new_script = str_replace( array( '<p>', '</p>' ), '', $row->script_mail );
      }

      $header_arr = array();
      foreach ( $label_order_original as $key => $label_each ) {
        $type = $label_type[ $key ];
        $key1 = $type == 'type_hidden' ? $label_each : $key;
        $label_each_decoded = htmlspecialchars_decode( $label_each );
        $key_replace = array( '%' . $label_each_decoded . '%', '{' . $key . '}' );

        $new_value = $this->custom_fields_mail( $type, $key1, $id, $attachment, $form_currency );
        $new_script = str_replace( $key_replace, $new_value, $new_script );

        if ( $type == "type_file_upload" ) {
          $new_value = $this->custom_fields_mail( $type, $key, $id, $attachment, $form_currency, 1 );
          $new_script = str_replace( array( '%' . $label_each_decoded . '(link)%', '{' . $key . '(link)}' ), $new_value, $new_script );
        }
        if ( strpos( $fromname, "{" . $key . "}" ) > -1 || strpos( $fromname, "%" . $label_each . "%" ) > -1 ) {
          $new_value = str_replace( '<br>', ', ', $this->custom_fields_mail( $type, $key, $id, '', $form_currency ) );
          if ( substr( $new_value, -2 ) == ', ' ) {
            $new_value = substr( $new_value, 0, -2 );
          }
          $fromname = str_replace( array( '%' . $label_each . '%', '{' . $key . '}' ), $new_value, $fromname );
        }
        if ( strpos( $fromname, "{" . $key . "}" ) > -1 || strpos( $fromname, "%username%" ) > -1 ) {
          $fromname = str_replace( array( '%' . $username . '%', '{' . $key . '}' ), $username, $fromname );
        }
        if ( strpos( $subject, "{" . $key . "}" ) > -1 || strpos( $subject, "%" . $label_each . "%" ) > -1 ) {
          $new_value = str_replace( '<br>', ', ', $this->custom_fields_mail( $type, $key1, $id, '', $form_currency ) );
          if ( substr( $new_value, -2 ) == ', ' ) {
            $new_value = substr( $new_value, 0, -2 );
          }
          $subject = str_replace( array( '%' . $label_each . '%', '{' . $key . '}' ), $new_value, $subject );
        }
      }
      $this->custom_fields['all'] = $list;

	    foreach ( $this->custom_fields as $key => $custom_field ) {
        $new_script = str_replace( array( '%' . $key . '%', '{' . $key . '}' ), $custom_field, $new_script );
        $recipient = str_replace( array( '%' . $key . '%', '{' . $key . '}' ), $custom_field, $recipient);
        $fromname = str_replace( array( '%' . $key . '%', '{' . $key . '}' ), $custom_field, $fromname );
        $from_mail = str_replace( array( '%' . $key . '%', '{' . $key . '}' ), $custom_field, $from_mail );
        $subject = str_replace( array( '%' . $key . '%', '{' . $key . '}' ), $custom_field, $subject );
      }
      if ( $fromname === '' ) {
	      $fromname = get_bloginfo('name');
      }
      if ( $row->from_mail ) {
        $header_arr[ 'from' ] = isset( $_POST['wdform_' . $row->from_mail . "_element" . $id] ) ? $_POST['wdform_' . $row->from_mail . "_element" . $id] : $from_mail;
      }
      $header_arr['from_name'] = $fromname;
      $header_arr['content_type'] = $content_type;
      $header_arr['charset'] = 'UTF-8';
      $header_arr['reply_to'] = isset( $_POST['wdform_' . $row->reply_to . "_element" . $id] ) ? $_POST['wdform_' . $row->reply_to . "_element" . $id] : $row->reply_to;
      $header_arr['cc'] = $row->mail_cc;
      $header_arr['bcc'] = $row->mail_bcc;

      $admin_body = $new_script;
      if ( $row->mail_attachment ) {
        $remove_parrent_array = new RecursiveIteratorIterator( new RecursiveArrayIterator( $attachment ) );
        $attachment = iterator_to_array( $remove_parrent_array, FALSE );
      } else {
        $attachment = array();
      }

      // Replace pdf link in email body.
      $admin_body = str_replace( '{PDF(link)}', site_url($pdf_data['pdf_url']), $admin_body );

      if ( $row->sendemail ) {
        $send = WDW_FM_Library(self::PLUGIN)->mail($recipient, $subject, $admin_body, $header_arr, $attachment);
      }
    }
    $_SESSION[ 'error_or_no' . $id ] = 0;
    $msg = addslashes( __( 'Your form was successfully submitted.', WDFMInstance(self::PLUGIN)->prefix ) );
    if ( $row->sendemail ) {
      if ( $row->mail || $row->send_to ) {
        if ( $send ) {
          if ( $send !== TRUE ) {
            $_SESSION[ 'error_or_no' . $id ] = 1;
            $msg = addslashes( __( 'Error, email was not sent.', WDFMInstance(self::PLUGIN)->prefix ) );
          }
          else {
            $_SESSION[ 'error_or_no' . $id ] = 0;
          }
        }
      }
    }

    // Add-on conditional email.
    if ( has_action('fm_set_params_frontend_init') && WDFMInstance(self::PLUGIN)->is_free != 2 ) {
      $fm_email_params = $row->sendemail ? array(
        'admin_body' => $admin_body,
        'body' => $body,
        'subject' => $subject,
        'headers' => $header_arr,
        'attachment' => $attachment,
        'attachment_user' => $attachment_user,
      ) : array();
      /* TODO. Need 'custom_fields_value' key. They work with it old conditional-emails.*/
      $custom_fields_value = array( $this->custom_fields['ip'], $this->custom_fields['useremail'], $this->custom_fields['username'], $this->custom_fields['subid'], $this->custom_fields['all'] );
      $params = array(
        'form_id' => $id,
        'fm_email_params' => $fm_email_params,
        'form_currency' => $form_currency,
        'custom_fields' => $this->custom_fields,
        'custom_fields_value' => $custom_fields_value
      );
      do_action( 'fm_set_params_frontend_init', $params );
    }

    // Delete files from uploads (save_upload = 0).
    if ( $row->save_uploads == 0 ) {
      foreach ( $all_files as &$all_file ) {
        if ( file_exists( $all_file[ 'tmp_name' ] ) ) {
          unlink( $all_file[ 'tmp_name' ] );
        }
      }
    }

    $_SESSION[ 'form_submit_type' . $id ] = $row->submit_text_type . ',' . $row->id . ',' . $group_id;

    $https = ((isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] == 'on') ? 'https://' : 'http://');
    $redirect_url = $https . $_SERVER[ "HTTP_HOST" ] . $_SERVER[ "REQUEST_URI" ];
    if ( $row->submit_text_type == 4 && $row->url ) {
      // Action after submission: URL.
      $redirect_url = $row->url;
    }
    elseif ( $row->article_id && ($row->submit_text_type == 2 || $row->submit_text_type == 5) ) {
      // Action after submission: Post/page.
      $redirect_url = $row->article_id;
    }

    if ( $row->submit_text_type != 4 || $row->url == '' ) {
      // This ensures that no message is enqueued by an add-on.
      if ( !$_SESSION[ 'massage_after_submit' . $id ] ) {
        $_SESSION[ 'massage_after_submit' . $id ] = $msg;
      }
      if ( $row->type == 'popover' || $row->type == 'topbar' || $row->type == 'scrollbox' ) {
        $_SESSION[ 'fm_hide_form_after_submit' . $id ] = 1;
      }
    }

    if ( !$str ) {
      wp_redirect( html_entity_decode( $redirect_url ) );
      exit;
    }
    else {
      $_SESSION[ 'redirect_paypal' . $id ] = 1;
      $str .= "&return=" . urlencode( add_query_arg( array( 'succes' => time() ), $redirect_url ) );
      wp_redirect( $str );
      exit;
    }
  }

  /**
   * Custom fields mail.
   *
   * @param string $type
   * @param string $key
   * @param int $id
   * @param array $attachment
   * @param string $form_currency
   * @param int $file_upload_link
   * @return null|string $new_value
   */
  public static function custom_fields_mail( $type = '', $key = '', $id = 0, $attachment = array(), $form_currency = '', $file_upload_link = 0 ) {
    $new_value = "";
    if ( $type != "type_submit_reset" or $type != "type_map" or $type != "type_editor" or $type != "type_captcha" or $type != "type_arithmetic_captcha" or $type != "type_recaptcha" or $type != "type_button" ) {
      switch ( $type ) {
        case 'type_text':
        case 'type_password':
        case 'type_textarea':
        case 'type_phone_new':
        case "type_date":
        case "type_date_new":
        case "type_own_select":
        case "type_country":
        case "type_number": {
          $element = isset( $_POST[ 'wdform_' . $key . "_element" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id ] : NULL;
          if ( isset( $element ) ) {
            $new_value = $element;
          }
          break;
        }
        case 'type_date_range' : {
          $element0 = isset( $_POST[ 'wdform_' . $key . "_element" . $id . "0" ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . "0" ] : NULL;
          $element1 = isset( $_POST[ 'wdform_' . $key . "_element" . $id . "1" ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . "1" ] : NULL;
          $element = $element0 . ' - ' . $element1;
          $new_value = $element;
        }
        case "type_file_upload": {
          if ( isset( $attachment[ $key ] ) ) {
            foreach ( $attachment[ $key ] as $attach ) {
              $uploadedFileNameParts = explode( '.', $attach );
              $uploadedFileExtension = array_pop( $uploadedFileNameParts );
              $file_name = explode( '/', $attach );
              $file_name = end( $file_name );
              if ( $file_upload_link == 1 ) {
                $new_value .= '<a href="' . site_url() . '/' . $attach . '"/>' . $file_name . '</a><br />';
              } else {
                $invalidFileExts = array(
                  'gif',
                  'jpg',
                  'jpeg',
                  'png',
                  'swf',
                  'psd',
                  'bmp',
                  'tiff',
                  'jpc',
                  'jp2',
                  'jpf',
                  'jb2',
                  'swc',
                  'aiff',
                  'wbmp',
                  'xbm',
                );
                $extOk = FALSE;
                foreach ( $invalidFileExts as $key => $valuee ) {
                  if ( is_numeric( strpos( strtolower( $valuee ), strtolower( $uploadedFileExtension ) ) ) ) {
                    $extOk = TRUE;
                  }
                }
                if ( $extOk == TRUE ) {
                  $new_value .= '<img src="' . site_url() . '/' . $attach . '" alt="' . $file_name . '"/>';
                }
              }
            }
          }
          break;
        }
        case "type_hidden": {
          $element = isset( $_POST[ $key ] ) ? $_POST[ $key ] : NULL;
          if ( isset( $element ) ) {
            $new_value = $element;
          }
          break;
        }
        case "type_mark_map": {
          $element = isset( $_POST[ 'wdform_' . $key . "_long" . $id ] ) ? $_POST[ 'wdform_' . $key . "_long" . $id ] : NULL;
          if ( isset( $element ) ) {
            $new_value = 'Longitude:' . $element . '<br/>Latitude:' . (isset( $_POST[ 'wdform_' . $key . "_lat" . $id ] ) ? $_POST[ 'wdform_' . $key . "_lat" . $id ] : "");
          }
          break;
        }
        case "type_submitter_mail": {
          $element = isset( $_POST[ 'wdform_' . $key . "_element" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id ] : NULL;
          if ( isset( $element ) ) {
            $new_value = $element;
          }
          break;
        }
        case "type_time": {
          $hh = isset( $_POST[ 'wdform_' . $key . "_hh" . $id ] ) ? $_POST[ 'wdform_' . $key . "_hh" . $id ] : NULL;
          if ( isset( $hh ) ) {
            $ss = isset( $_POST[ 'wdform_' . $key . "_ss" . $id ] ) ? $_POST[ 'wdform_' . $key . "_ss" . $id ] : NULL;
            if ( isset( $ss ) ) {
              $new_value = $hh . ':' . (isset( $_POST[ 'wdform_' . $key . "_mm" . $id ] ) ? $_POST[ 'wdform_' . $key . "_mm" . $id ] : "") . ':' . $ss;
            } else {
              $new_value = $hh . ':' . (isset( $_POST[ 'wdform_' . $key . "_mm" . $id ] ) ? $_POST[ 'wdform_' . $key . "_mm" . $id ] : "");
            }
            $am_pm = isset( $_POST[ 'wdform_' . $key . "_am_pm" . $id ] ) ? $_POST[ 'wdform_' . $key . "_am_pm" . $id ] : NULL;
            if ( isset( $am_pm ) ) {
              $new_value = $new_value . ' ' . $am_pm;
            }
          }
          break;
        }
        case "type_phone": {
          $element_first = isset( $_POST[ 'wdform_' . $key . "_element_first" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_first" . $id ] : NULL;
          if ( isset( $element_first ) ) {
            $new_value = $element_first . ' ' . (isset( $_POST[ 'wdform_' . $key . "_element_last" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_last" . $id ] : "");
          }
          break;
        }
        case "type_name": {
          $element_first = isset( $_POST[ 'wdform_' . $key . "_element_first" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_first" . $id ] : NULL;
          if ( isset( $element_first ) ) {
            $element_title = isset( $_POST[ 'wdform_' . $key . "_element_title" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_title" . $id ] : NULL;
            if ( isset( $element_title ) ) {
              $new_value = $element_title . ' ' . $element_first . ' ' . (isset( $_POST[ 'wdform_' . $key . "_element_last" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_last" . $id ] : "") . ' ' . (isset( $_POST[ 'wdform_' . $key . "_element_middle" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_middle" . $id ] : "");
            } else {
              $new_value = $element_first . ' ' . (isset( $_POST[ 'wdform_' . $key . "_element_last" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_last" . $id ] : "");
            }
          }
          break;
        }
        case "type_address": {
          $street1 = isset( $_POST[ 'wdform_' . $key . "_street1" . $id ] ) ? $_POST[ 'wdform_' . $key . "_street1" . $id ] : NULL;
          if ( isset( $street1 ) ) {
            $new_value = $street1;
            break;
          }
          $street2 = isset( $_POST[ 'wdform_' . $key . "_street2" . $id ] ) ? $_POST[ 'wdform_' . $key . "_street2" . $id ] : NULL;
          if ( isset( $street2 ) ) {
            $new_value = $street2;
            break;
          }
          $city = isset( $_POST[ 'wdform_' . $key . "_city" . $id ] ) ? $_POST[ 'wdform_' . $key . "_city" . $id ] : NULL;
          if ( isset( $city ) ) {
            $new_value = $city;
            break;
          }
          $state = isset( $_POST[ 'wdform_' . $key . "_state" . $id ] ) ? $_POST[ 'wdform_' . $key . "_state" . $id ] : NULL;
          if ( isset( $state ) ) {
            $new_value = $state;
            break;
          }
          $postal = isset( $_POST[ 'wdform_' . $key . "_postal" . $id ] ) ? $_POST[ 'wdform_' . $key . "_postal" . $id ] : NULL;
          if ( isset( $postal ) ) {
            $new_value = $postal;
            break;
          }
          $country = isset( $_POST[ 'wdform_' . $key . "_country" . $id ] ) ? $_POST[ 'wdform_' . $key . "_country" . $id ] : NULL;
          if ( isset( $country ) ) {
            $new_value = $country;
            break;
          }
          break;
        }
        case "type_date_fields": {
          $day = isset( $_POST[ 'wdform_' . $key . "_day" . $id ] ) ? $_POST[ 'wdform_' . $key . "_day" . $id ] : NULL;
          if ( isset( $day ) ) {
            $new_value = $day . '-' . (isset( $_POST[ 'wdform_' . $key . "_month" . $id ] ) ? $_POST[ 'wdform_' . $key . "_month" . $id ] : "") . '-' . (isset( $_POST[ 'wdform_' . $key . "_year" . $id ] ) ? $_POST[ 'wdform_' . $key . "_year" . $id ] : "");
          }
          break;
        }
        case "type_radio": {
          $element = isset( $_POST[ 'wdform_' . $key . "_other_input" . $id ] ) ? $_POST[ 'wdform_' . $key . "_other_input" . $id ] : NULL;
          if ( isset( $element ) ) {
            $new_value = $element;
            break;
          }
          $element = isset( $_POST[ 'wdform_' . $key . "_element" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id ] : NULL;
          if ( isset( $element ) ) {
            $new_value = $element;
          }
          break;
        }
        case "type_checkbox": {
          $start = -1;
          for ( $j = 0; $j < 100; $j++ ) {
            $element = isset( $_POST[ 'wdform_' . $key . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . $j ] : NULL;
            if ( isset( $element ) ) {
              $start = $j;
              break;
            }
          }
          $other_element_id = -1;
          $is_other = isset( $_POST[ 'wdform_' . $key . "_allow_other" . $id ] ) ? $_POST[ 'wdform_' . $key . "_allow_other" . $id ] : "";
          if ( $is_other == "yes" ) {
            $other_element_id = isset( $_POST[ 'wdform_' . $key . "_allow_other_num" . $id ] ) ? $_POST[ 'wdform_' . $key . "_allow_other_num" . $id ] : "";
          }
          if ( $start != -1 ) {
            for ( $j = $start; $j < 100; $j++ ) {
              $element = isset( $_POST[ 'wdform_' . $key . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . $j ] : NULL;
              if ( isset( $element ) ) {
                if ( $j == $other_element_id ) {
                  $new_value = $new_value . (isset( $_POST[ 'wdform_' . $key . "_other_input" . $id ] ) ? $_POST[ 'wdform_' . $key . "_other_input" . $id ] : "") . '<br>';
                } else {
                  $new_value = $new_value . $element . '<br>';
                }
              }
            }
          }
          break;
        }
        case "type_paypal_price": {
          $new_value = 0;
          if ( isset( $_POST[ 'wdform_' . $key . "_element_dollars" . $id ] ) ) {
            $new_value = $_POST[ 'wdform_' . $key . "_element_dollars" . $id ];
          }
          if ( isset( $_POST[ 'wdform_' . $key . "_element_cents" . $id ] ) ) {
            $new_value = $new_value . '.' . $_POST[ 'wdform_' . $key . "_element_cents" . $id ];
          }
          $new_value = $new_value . $form_currency;
          break;
        }
        case "type_paypal_price_new": {
          $new_value = '';
          if ( isset( $_POST[ 'wdform_' . $key . "_element" . $id ] ) && $_POST[ 'wdform_' . $key . "_element" . $id ] ) {
            $new_value = $form_currency . $_POST[ 'wdform_' . $key . "_element" . $id ];
          }
          $new_value = $new_value;
          break;
        }
        case "type_paypal_select": {
          $element = isset( $_POST[ 'wdform_' . $key . "_element" . $id ] ) && $_POST[ 'wdform_' . $key . "_element" . $id ] ? $_POST[ 'wdform_' . $key . "_element" . $id ] : '';
          if ( $element ) {
            $new_value = (isset( $_POST[ 'wdform_' . $key . "_element_label" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_label" . $id ] : "") . ' : ' . $form_currency . $element;
            $is_element_quantity = isset( $_POST[ 'wdform_' . $key . "_element_quantity" . $id ] ) ? TRUE : FALSE;
            $element_quantity_label = isset( $_POST[ 'wdform_' . $key . "_element_quantity_label" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_quantity_label" . $id ] : NULL;
            $element_quantity = (isset( $_POST[ 'wdform_' . $key . "_element_quantity" . $id ] ) && $_POST[ 'wdform_' . $key . "_element_quantity" . $id ]) ? $_POST[ 'wdform_' . $key . "_element_quantity" . $id ] : NULL;
            if ( $is_element_quantity ) {
              $new_value .= '<br/>' . $element_quantity_label . ': ' . ( !(empty($element_quantity)) ? $element_quantity : 0);
            }
			     for ( $k = 0; $k < 50; $k++ ) {
              $temp_val = isset( $_POST[ 'wdform_' . $key . "_property" . $id . $k ] ) ? $_POST[ 'wdform_' . $key . "_property" . $id . $k ] : NULL;
              if ( isset( $temp_val ) ) {
                $new_value .= '<br/>' . (isset( $_POST[ 'wdform_' . $key . "_element_property_label" . $id . $k ] ) ? $_POST[ 'wdform_' . $key . "_element_property_label" . $id . $k ] : "") . ': ' . $temp_val;
              }
            }
          }
          break;
        }
        case "type_paypal_radio": {
          $new_value = (isset( $_POST[ 'wdform_' . $key . "_element_label" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_label" . $id ] : "") . (isset( $_POST[ 'wdform_' . $key . "_element" . $id ] ) && $_POST[ 'wdform_' . $key . "_element" . $id ] ? ' - ' . $form_currency . $_POST[ 'wdform_' . $key . "_element" . $id ] : "");
          $is_element_quantity = isset( $_POST[ 'wdform_' . $key . "_element_quantity" . $id ] ) ? TRUE : FALSE;
          $element_quantity_label = isset( $_POST[ 'wdform_' . $key . "_element_quantity_label" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_quantity_label" . $id ] : NULL;
          $element_quantity = ( isset( $_POST[ 'wdform_' . $key . "_element_quantity" . $id ] ) && $_POST[ 'wdform_' . $key . "_element_quantity" . $id ]) ? $_POST[ 'wdform_' . $key . "_element_quantity" . $id ] : NULL;
          if ( $is_element_quantity ) {
           $new_value .= '<br/>'. $element_quantity_label . ': ' . ( (!empty($element_quantity)) ? $element_quantity : 0 );
          }
          for ( $k = 0; $k < 50; $k++ ) {
            $temp_val = isset( $_POST[ 'wdform_' . $key . "_property" . $id . $k ] ) ? $_POST[ 'wdform_' . $key . "_property" . $id . $k ] : NULL;
            if ( isset( $temp_val ) ) {
              $new_value .= '<br/>' . (isset( $_POST[ 'wdform_' . $key . "_element_property_label" . $id . $k ] ) ? $_POST[ 'wdform_' . $key . "_element_property_label" . $id . $k ] : "") . ': ' . $temp_val;
            }
          }
          break;
        }
        case "type_paypal_shipping": {
          $new_value = (isset( $_POST[ 'wdform_' . $key . "_element_label" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_label" . $id ] : "") . (isset( $_POST[ 'wdform_' . $key . "_element" . $id ] ) && $_POST[ 'wdform_' . $key . "_element" . $id ] ? ' : ' . $form_currency . $_POST[ 'wdform_' . $key . "_element" . $id ] : "");
          break;
        }
        case "type_paypal_checkbox": {
          $start = -1;
          for ( $j = 0; $j < 100; $j++ ) {
            $element = isset( $_POST[ 'wdform_' . $key . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . $j ] : NULL;
            if ( isset( $element ) ) {
              $start = $j;
              break;
            }
          }
          if ( $start != -1 ) {
            for ( $j = $start; $j < 100; $j++ ) {
              $element = isset( $_POST[ 'wdform_' . $key . "_element" . $id . $j ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . $j ] : NULL;
              if ( isset( $element ) ) {
                $new_value = $new_value . (isset( $_POST[ 'wdform_' . $key . "_element" . $id . $j . "_label" ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . $j . "_label" ] : "") . ' - ' . (isset( $element ) ? $form_currency . ($element == '' ? '0' : $element) : "") . '<br>';
              }
            }
          }
          $is_element_quantity = isset( $_POST[ 'wdform_' . $key . "_element_quantity" . $id ] ) ? TRUE : FALSE;
          $element_quantity_label = isset( $_POST[ 'wdform_' . $key . "_element_quantity_label" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element_quantity_label" . $id ] : NULL;
          $element_quantity = (isset( $_POST[ 'wdform_' . $key . "_element_quantity" . $id ] ) && $_POST[ 'wdform_' . $key . "_element_quantity" . $id ]) ? $_POST[ 'wdform_' . $key . "_element_quantity" . $id ] : NULL;
          if ( $is_element_quantity ) {
            $new_value .= $element_quantity_label . ': ' . ( (!empty($element_quantity)) ? $element_quantity : 0 );
          }
          for ( $k = 0; $k < 50; $k++ ) {
            $temp_val = isset( $_POST[ 'wdform_' . $key . "_property" . $id . $k ] ) ? $_POST[ 'wdform_' . $key . "_property" . $id . $k ] : NULL;
            if ( isset( $temp_val ) ) {
              $new_value .= '<br/>' . (isset( $_POST[ 'wdform_' . $key . "_element_property_label" . $id . $k ] ) ? $_POST[ 'wdform_' . $key . "_element_property_label" . $id . $k ] : "") . ': ' . $temp_val;
            }
          }
          break;
        }
        case "type_paypal_total": {
          $element = isset( $_POST[ 'wdform_' . $key . "_paypal_total" . $id ] ) ? $_POST[ 'wdform_' . $key . "_paypal_total" . $id ] : "";
          $new_value = $new_value . $element;
          break;
        }
        case "type_star_rating": {
          $element = isset( $_POST[ 'wdform_' . $key . "_star_amount" . $id ] ) ? $_POST[ 'wdform_' . $key . "_star_amount" . $id ] : NULL;
          $selected = isset( $_POST[ 'wdform_' . $key . "_selected_star_amount" . $id ] ) ? $_POST[ 'wdform_' . $key . "_selected_star_amount" . $id ] : 0;
          if ( isset( $element ) ) {
            $new_value = $new_value . $selected . '/' . $element;
          }
          break;
        }
        case "type_scale_rating": {
          $element = isset( $_POST[ 'wdform_' . $key . "_scale_amount" . $id ] ) ? $_POST[ 'wdform_' . $key . "_scale_amount" . $id ] : NULL;
          $selected = isset( $_POST[ 'wdform_' . $key . "_scale_radio" . $id ] ) ? $_POST[ 'wdform_' . $key . "_scale_radio" . $id ] : 0;
          if ( isset( $element ) ) {
            $new_value = $new_value . $selected . '/' . $element;
          }
          break;
        }
        case "type_spinner": {
          $element = isset( $_POST[ 'wdform_' . $key . "_element" . $id ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id ] : NULL;
          if ( isset( $element ) ) {
            $new_value = $new_value . $element;
          }
          break;
        }
        case "type_slider": {
          $element = isset( $_POST[ 'wdform_' . $key . "_slider_value" . $id ] ) ? $_POST[ 'wdform_' . $key . "_slider_value" . $id ] : NULL;
          if ( isset( $element ) ) {
            $new_value = $new_value . $element;
          }
          break;
        }
        case "type_range": {
          $element0 = isset( $_POST[ 'wdform_' . $key . "_element" . $id . '0' ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . '0' ] : NULL;
          $element1 = isset( $_POST[ 'wdform_' . $key . "_element" . $id . '1' ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . '1' ] : NULL;
          if ( isset( $element0 ) || isset( $element1 ) ) {
            $new_value = $new_value . $element0 . '-' . $element1;
          }
          break;
        }
        case "type_grading": {
          $element = isset( $_POST[ 'wdform_' . $key . "_hidden_item" . $id ] ) ? $_POST[ 'wdform_' . $key . "_hidden_item" . $id ] : "";
          $grading = explode( ":", $element );
          $items_count = sizeof( $grading ) - 1;
          $element = "";
          $total = 0;
          for ( $k = 0; $k < $items_count; $k++ ) {
            $element .= $grading[ $k ] . ":" . (isset( $_POST[ 'wdform_' . $key . "_element" . $id . '_' . $k ] ) ? $_POST[ 'wdform_' . $key . "_element" . $id . '_' . $k ] : "") . "   ";
            $total += (isset( $_POST[ 'wdform_' . $key . "_element" . $id . '_' . $k ] ) ? (float) $_POST[ 'wdform_' . $key . "_element" . $id . '_' . $k ] : 0);
          }
          $element .= "Total: " . $total;
          if ( isset( $element ) ) {
            $new_value = $new_value . $element;
          }
          break;
        }
        case "type_matrix": {
          $input_type = isset( $_POST[ 'wdform_' . $key . "_input_type" . $id ] ) ? $_POST[ 'wdform_' . $key . "_input_type" . $id ] : "";
          $mat_rows = explode( "***", isset( $_POST[ 'wdform_' . $key . "_hidden_row" . $id ] ) ? $_POST[ 'wdform_' . $key . "_hidden_row" . $id ] : "" );
          $rows_count = sizeof( $mat_rows ) - 1;
          $mat_columns = explode( "***", isset( $_POST[ 'wdform_' . $key . "_hidden_column" . $id ] ) ? $_POST[ 'wdform_' . $key . "_hidden_column" . $id ] : "" );
          $columns_count = sizeof( $mat_columns ) - 1;
          $matrix = "<table>";
          $matrix .= '<tr><td></td>';
          for ( $k = 1; $k < count( $mat_columns ); $k++ ) {
            $matrix .= '<td style="background-color:#BBBBBB; padding:5px; ">' . $mat_columns[ $k ] . '</td>';
          }
          $matrix .= '</tr>';
          $aaa = Array();
          for ( $k = 1; $k <= $rows_count; $k++ ) {
            $matrix .= '<tr><td style="background-color:#BBBBBB; padding:5px;">' . $mat_rows[ $k ] . '</td>';
            if ( $input_type == "radio" ) {
              $mat_radio = isset( $_POST[ 'wdform_' . $key . "_input_element" . $id . $k ] ) ? $_POST[ 'wdform_' . $key . "_input_element" . $id . $k ] : 0;
              if ( $mat_radio == 0 ) {
                $checked = "";
                $aaa[ 1 ] = "";
              } else {
                $aaa = explode( "_", $mat_radio );
              }
              for ( $j = 1; $j <= $columns_count; $j++ ) {
                if ( $aaa[ 1 ] == $j ) {
                  $checked = "&#10004;";
                } else {
                  $checked = "";
                }
                $matrix .= '<td style="text-align:center">' . $checked . '</td>';
              }
            } else {
              if ( $input_type == "checkbox" ) {
                for ( $j = 1; $j <= $columns_count; $j++ ) {
                  $checked = isset( $_POST[ 'wdform_' . $key . "_input_element" . $id . $k . '_' . $j ] ) ? $_POST[ 'wdform_' . $key . "_input_element" . $id . $k . '_' . $j ] : 0;
                  if ( $checked == 1 ) {
                    $checked = "&#10004;";
                  } else {
                    $checked = "";
                  }
                  $matrix .= '<td style="text-align:center">' . $checked . '</td>';
                }
              } else {
                if ( $input_type == "text" ) {
                  for ( $j = 1; $j <= $columns_count; $j++ ) {
                    $checked = isset( $_POST[ 'wdform_' . $key . "_input_element" . $id . $k . '_' . $j ] ) ? esc_html( $_POST[ 'wdform_' . $key . "_input_element" . $id . $k . '_' . $j ] ) : "";
                    $matrix .= '<td style="text-align:center">' . $checked . '</td>';
                  }
                } else {
                  for ( $j = 1; $j <= $columns_count; $j++ ) {
                    $checked = isset( $_POST[ 'wdform_' . $key . "_select_yes_no" . $id . $k . '_' . $j ] ) ? $_POST[ 'wdform_' . $key . "_select_yes_no" . $id . $k . '_' . $j ] : "";
                    $matrix .= '<td style="text-align:center">' . $checked . '</td>';
                  }
                }
              }
            }
            $matrix .= '</tr>';
          }
          $matrix .= '</table>';
          if ( isset( $matrix ) ) {
            $new_value = $new_value . $matrix;
          }
          break;
        }
        default:
          break;
      }
    }

    return $new_value;
  }

  /**
   * @param string $element
   * @param string $mail_emptyfields
   *
   * @return int
   */
  public function empty_field( $element = '', $mail_emptyfields = '' ) {
    if ( !$mail_emptyfields ) {
      if ( empty( $element ) ) {
        return 0;
      }
    }

    return 1;
  }

  /**
   * @param string $date
   * @param string $format
   *
   * @return bool
   */
  public function fm_validateDate( $date = '', $format = 'Y-m-d H:i:s' ) {
    $d = DateTime::createFromFormat( $format, $date );

    return $d && $d->format( $format ) == $date;
  }

  /**
   * Get all forms.
   *
   * @return array|null|object
   */
  public function all_forms() {
    global $wpdb;
    $q = 'SELECT * FROM ' . $wpdb->prefix . 'formmaker_display_options as display INNER JOIN ' . $wpdb->prefix . 'formmaker as forms ON display.form_id = forms.id WHERE display.type != "embedded" and forms.published=1' . (!WDFMInstance(self::PLUGIN)->is_free ? '' : ' AND forms.id' . (WDFMInstance(self::PLUGIN)->is_free == 1 ? ' NOT ' : ' ') . 'IN (' . (get_option( 'contact_form_forms', '' ) != '' ? get_option( 'contact_form_forms' ) : 0) . ')');
    $forms = $wpdb->get_results( $q );
    return $forms;
  }

  /**
   * Empty field validation.
   *
   * @param int $form_id
   *
   * @return bool
   */
  public function fm_empty_field_validation( $form_id ) {
    $hash  = md5( WDFMInstance(self::PLUGIN)->prefix .''. $form_id);
    $value = WDW_FM_Library(self::PLUGIN)->get('fm_empty_field_validation' . $form_id, '');
    if ( !empty($value) && $value === $hash ) {
      return TRUE;
    }
    return FALSE;
  }
}