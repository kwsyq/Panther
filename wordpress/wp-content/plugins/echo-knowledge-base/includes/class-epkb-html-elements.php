<?php

/**
 * Elements of form UI and others
 *
 * @copyright   Copyright (C) 2018, Echo Plugins
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class EPKB_HTML_Elements {

	// Form Elements------------------------------------------------------------------------------------------/

	/**
	 * Add Default Fields
	 *
	 * @param array $input_array
	 * @param array $custom_defaults
	 *
	 * @return array
	 */
	public static function add_defaults( array $input_array, array $custom_defaults=array() ) {

		$defaults = array(
			'id'                => '',
			'name'              => 'text',
			'value'             => '',
			'label'             => '',
			'title'             => '',
			'class'             => '',
			'main_label_class'  => '',
			'label_class'       => '',
			'input_class'       => '',
			'input_group_class' => '',
			'radio_class'       => '',
			'action_class'      => '',
			'container_class'   => '',
			'desc'              => '',
			'info'              => '',
			'placeholder'       => '',
			'readonly'          => false,  // will not be submitted
			'required'          => '',
			'autocomplete'      => false,
			'data'              => false,
			'disabled'          => false,
			'size'              => 3,
			'max'               => 50,
			'options'           => array(),
			'label_wrapper'     => '',
			'input_wrapper'     => '',
			'icon_color'        => '',
			'return_html'       => false,
			'unique'            => true,
			'text_class'        => '',
			'icon'              => '',
			'list'              => array(),
			'btn_text'          => '',
			'btn_url'           => '',
			'more_info_text'    => '',
			'more_info_url'     => '',
			'tooltip_title'     => '',
			'tooltip_body'      => '',
			'is_pro'            => '',
            'input_size'        => 'medium'
		);
		$defaults = array_merge( $defaults, $custom_defaults );
		return array_merge( $defaults, $input_array );
	}

	/**
	 * Renders an HTML Text field
	 *
	 * @param array $args Arguments for the text field
	 * @param bool $return_html
	 * @return false|string
	 */
	public static function text( $args = array(), $return_html=false ) {

		if ( $return_html ) {
			ob_start();
		}

		$args = self::add_defaults( $args );
		$args = self::get_specs_info( $args );

		$readonly = $args['readonly'] ? ' readonly' : '';

		$data_escaped = '';
		if ( ! empty( $args['data'] ) ) {
			foreach ( $args['data'] as $key => $value ) {
				$data_escaped .= 'data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
			}
		}   ?>

		<div class="epkb-input-group epkb-admin__text-field <?php echo esc_html( $args['input_group_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>_group" >

			<label class="<?php echo esc_attr( $args['label_class'] ); ?>" for="<?php echo esc_attr( $args['name'] ); ?>">  <?php
			    echo esc_html( $args['label'] );
			    if ( ! empty( $args['tooltip_body'] ) ) {
			        self::display_tooltip( $args['tooltip_title'], $args['tooltip_body'] );
			    }
				if ( $args['is_pro'] ) {
					self::display_pro_setting_tag( $args['label'] );
				}        ?>
			</label>

			<div class="input_container <?php echo esc_attr( $args['input_class'] ); ?>">
			    <input type="text"
			           class="epkb-input--<?php echo esc_attr( $args['input_size'] ); ?>"
			           name="<?php echo  esc_attr( $args['name'] ); ?>"
			           id="<?php echo  esc_attr( $args['name'] ); ?>"
			           autocomplete="<?php echo ( $args[ 'autocomplete' ] ? 'on' : 'off' ); ?>"
			           value="<?php echo esc_attr( $args['value'] ); ?>"
			           placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"						<?php
			           echo $data_escaped . $readonly;						?>
			           maxlength="<?php echo esc_attr( $args['max'] ); ?>"
			    />
			</div>

		</div>		<?php

		if ( $return_html ) {
			return ob_get_clean();
		}

		return '';
	}

	/**
	 * Renders several HTML input text horizontally
	 *
	 * @param array $args
	 * @param bool $return_html
	 *
	 * @return false|string
	 */
	public static function horizontal_text_inputs( $args = array(), $return_html=false ) {

		if ( $return_html ) {
			ob_start();
		}

		$defaults = array(
			'inputs' => array()
		);

		$args = self::add_defaults( $args, $defaults ); ?>

        <div class="epkb-input-group epkb-admin__text-field <?php echo esc_attr( $args['input_group_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>_group" >

            <label class="<?php echo esc_attr( $args['label_class'] ); ?>">  <?php
		        echo esc_html( $args['label'] );
		        if ( ! empty( $args['tooltip_body'] ) ) {
			        self::display_tooltip( $args['tooltip_title'], $args['tooltip_body'] );
		        }
		        if ( $args['is_pro'] ) {
			        self::display_pro_setting_tag( $args['label'] );
		        }        ?>
            </label>

            <div class="epkb-text-inputs-container">    <?php

				foreach( $args['inputs'] as $input ) {

					$input = self::add_defaults( $input, $defaults );
					$input = self::get_specs_info( $input );  ?>

                    <div class="input_container <?php echo esc_attr( $args['input_class'] ); ?>">

                        <input type="text"
                               class="epkb-input--<?php echo esc_attr( $args['input_size'] ); ?>"
                               name="<?php echo  esc_attr( $input['name'] ); ?>"
                               id="<?php echo  esc_attr( $input['name'] ); ?>"
                               autocomplete="<?php echo ( $args[ 'autocomplete' ] ? 'on' : 'off' ); ?>"
                               value="<?php echo esc_attr( $input['value'] ); ?>"
                               placeholder="<?php echo esc_attr( $input['placeholder'] ); ?>"
                               maxlength="<?php echo esc_attr( $input['max'] ); ?>"
                        />

                        <label class="epkb-label" for="<?php echo esc_attr( $input['name'] ); ?>">
                            <span class="epkb-label__text"><?php echo esc_html( $input['label'] ); ?></span>
                        </label>

                    </div>  <?php
				}   ?>
            </div>

        </div>	<?php

		if ( $return_html ) {
			return ob_get_clean();
		}

		return '';
	}

	/**
	 * Renders an HTML textarea
	 *
	 * @param array $args Arguments for the text field
	 * @param bool $return_html
	 *
	 * @return false|string
	 */
	public static function textarea( $args = array(), $return_html=false ) {

		if ( $return_html ) {
			ob_start();
		}

		$defaults = array(
			'name'        => 'textarea',
			'class'       => 'large-text',
			'rows'        => 4,
		);
		$args = self::add_defaults( $args, $defaults );
		$args = self::get_specs_info( $args );

		if ( empty( $args['main_tag'] ) ) {
			$main_tag = 'li';
		} else {
			$main_tag = $args['main_tag'];
		}		?>

		<<?php echo ( $main_tag == 'li' ? $main_tag : 'div' ); ?> class="epkb-input-group <?php echo esc_attr( $args['input_group_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>_group" >

		<label class="<?php echo esc_attr( $args['label_class'] ); ?>" for="<?php echo esc_attr( $args['name'] ); ?>">
			<?php echo esc_html( $args['label'] ); ?>
		</label>
		<div class="input_container <?php echo esc_attr( $args['input_class'] ); ?>">
			<textarea
					class="epkb-input--<?php echo esc_attr( $args['input_size'] ); ?>"
					rows="<?php echo esc_attr( $args['rows'] ); ?>"
					name="<?php echo esc_attr( $args['name'] ); ?>"
					id="<?php echo esc_attr( $args['name'] ); ?>"
					value="<?php echo esc_attr( $args['value'] ); ?>"
					placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
					<?php echo ( $args['disabled'] ? ' disabled="disabled"' : '' ); ?> >
					<?php echo esc_html( $args['value'] ); ?>
				</textarea>
		</div>

		</<?php echo ( $main_tag == 'li' ? $main_tag : 'div' ); ?>>		<?php

		if ( ! empty( $args['info'] ) ) { ?>
			<span class="epkb-info-icon"><p class="hidden"><?php echo esc_html( $args['info'] ); ?></p></span>		<?php
		}

		if ( $return_html ) {
			return ob_get_clean();
		}

		return '';
	}

	/**
	 * Renders an HTML Checkbox
	 *
	 * @param array $args
	 * @param bool $return_html
	 *
	 * @return string
	 */
	public static function checkbox( $args = array(), $return_html=false ) {

		if ( $return_html ) {
			ob_start();
		}

		$defaults = array(
			'name'         => 'checkbox',
		);
		$args = self::add_defaults( $args, $defaults );	?>

		<div class="config-input-group <?php echo esc_attr( $args['input_group_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>_group">

			<label class="<?php echo esc_attr( $args['label_class'] ); ?>" for="<?php echo esc_attr( $args['name'] ); ?>">
				<?php echo esc_html( $args['label'] ); ?>
			</label>

			<div class="input_container <?php echo esc_attr( $args['container_class'] ); ?>">
				<input type="checkbox"
				       name="<?php echo esc_attr( $args['name'] ); ?>"
				       id="<?php echo esc_attr( $args['name'] ); ?>"
				       value="on"
				       class="<?php echo esc_attr( $args['input_class'] ); ?>"
					<?php echo checked( "on", $args['value'], false ); ?> />
			</div>
		</div>			<?php

		if ( $return_html ) {
			return ob_get_clean();
		}

		return '';
	}

	/**
	 * Renders an HTML Toggle ( checkbox )
	 *
	 * @param array $args
	 * textLoc - left, right
	 * @return false|string
	 */
	public static function checkbox_toggle( $args = array() ) {
		$defaults = array(
			'name'          => '',
			'text'          => '',
			'data'          => '',
			'topDesc'       => '',
			'bottomDesc'    => '',
			'textLoc'       => 'left',
			'checked'       => false,
			'toggleOnText'  => __( 'yes', 'echo-knowledge-base' ),
			'toggleOffext'  => __( 'no', 'echo-knowledge-base' ),
			'return_html'   => false,
		);
		$args       = self::add_defaults( $args, $defaults );
		$text       = $args['text'];
		$topDesc    = $args['topDesc'];
		$bottomDesc = $args['bottomDesc'];

		if ( $args['return_html'] ) {
			ob_start();
		}   ?>

		<div id="<?php echo esc_attr( $args['id'] ); ?>" class="epkb-settings-control-container epkb-settings-control-type-toggle <?php echo 'epkb-settings-control-type-toggle--' . esc_attr( $args['textLoc'] ); ?>" data-field="<?php echo esc_attr( $args['data'] ); ?>">     <?php

			if ( ! empty( $topDesc ) ) {    ?>
				<div class="epkb-settings-control__description"><?php echo wp_kses_post( $topDesc ); ?></div>  <?php
			}   ?>

			<div class="epkb-settings-control__field">
				<label class="epkb-settings-control__title"><?php echo esc_html( $text ); ?></label>
				<div class="epkb-settings-control__input">
					<label class="epkb-settings-control-toggle">
						<input type="checkbox" class="epkb-settings-control__input__toggle" value="on" name="<?php echo esc_attr( $args['name'] ); ?>" <?php checked( true, $args['checked'] ); ?>/>
						<span class="epkb-settings-control__input__label" data-on="<?php echo esc_attr( $args['toggleOnText'] ); ?>" data-off="<?php echo esc_attr( $args['toggleOffext'] ); ?>"></span>
						<span class="epkb-settings-control__input__handle"></span>
					</label>
				</div>
			</div>			<?php

			if ( ! empty( $bottomDesc ) ) {     ?>
				<div class="epkb-settings-control__description"><?php echo wp_kses_post( $bottomDesc ); ?></div>  <?php
			}   ?>

		</div>		<?php

		if ( $args['return_html'] ) {
			return ob_get_clean();
		}
	}

	/**
	 * Renders an HTML drop-down box
	 *
	 * @param array $args
	 */
	public static function dropdown( $args = array() ) {

		$args = self::add_defaults( $args );
		$args = self::get_specs_info( $args );  ?>

		<div class="epkb-input-group <?php echo esc_attr( $args['input_group_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>_group">
			<label class="<?php echo esc_attr( $args['label_class'] ); ?>" for="<?php echo esc_attr( $args['name'] ); ?>">  <?php
				echo esc_html( $args['label'] );
				if ( ! empty( $args['tooltip_body'] ) ) {
					self::display_tooltip( $args['tooltip_title'], $args['tooltip_body'] );
				}
				if ( $args['is_pro'] ) {
					self::display_pro_setting_tag( $args['label'] );
				}                ?>
			</label>

			<div class="input_container <?php echo esc_attr( $args['input_class'] ); ?>" id="">

				<select name="<?php echo esc_attr( $args['name'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>">     <?php
					foreach( $args['options'] as $key => $label ) {
						$selected = selected( $key, $args['value'], false );
						echo '<option value="' . esc_attr( $key ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
					}  ?>
				</select>
			</div>		<?php

			if ( ! empty( $args['info'] ) ) { ?>
				<span class='epkb-info-icon'><p class='hidden'><?php echo esc_html( $args['info'] ); ?></p></span>			<?php
			}	?>

		</div>		<?php
	}

	/**
	 * Renders several HTML radio buttons in a Row
	 * Type of Radio buttons: use the input_group_class
	 *          epkb-radio-vertical-group-container             Regular Radio Horizontal Group
	 *          epkb-radio-vertical-button-group-container      Button Style Radio Horizontal Group
	 *
	 * @param array $args
	 * @return false|string
	 */
	public static function radio_buttons_horizontal($args = array() ) {

		$defaults = array(
			'id'                => 'radio',
			'name'              => 'radio-buttons',
            'desc_condition'    => '',
		);
		$args = self::add_defaults( $args, $defaults );
		$args = self::get_specs_info( $args );

		$ix = 0;

		if ( $args['return_html'] ) {
			ob_start();
		}   ?>

        <div class="epkb-input-group <?php echo esc_attr( $args['input_group_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>_group" >

			<span class="epkb-main_label <?php echo esc_attr( $args['main_label_class'] ); ?>">
                <?php echo esc_html( $args['label'] );
                if ( ! empty( $args['tooltip_body'] ) ) {
	                self::display_tooltip( $args['tooltip_title'], $args['tooltip_body'] );
                }

                if ( ! empty( $args['info'] ) ) { ?>
                    <span class="epkb-info-icon"><p class="hidden"><?php echo esc_html( $args['info'] ); ?></p></span> <?php
                }
                if ( $args['is_pro'] ) {
	                self::display_pro_setting_tag( $args['label'] );
                } ?>
            </span>

            <div class="epkb-radio-buttons-container <?php echo esc_attr( $args['input_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>">              <?php

				foreach( $args['options'] as $key => $label ) { ?>
                    <div class="epkb-input-container">

                        <input class="epkb-input" type="radio"
                               name="<?php echo esc_attr( $args['name'] ); ?>"
                               id="<?php echo esc_attr( $args['name'] . $ix ); ?>"
                               value="<?php echo esc_attr( $key ); ?>"
                               autocomplete="<?php echo ( $args['autocomplete'] ? 'on' : 'off' ); ?>"<?php
						checked( $key, $args['value'] )	?>
                        />
                        <label class="epkb-label" for="<?php echo esc_attr( $args['name'] ).$ix ?>">
                            <span class="epkb-label__text"><?php echo esc_html( $label ); ?></span>
                        </label>


                    </div> <?php

					$ix++;
				} //foreach

				?>
            </div> <?php

			if ( $args['desc'] ) {

                $showDesc = '';

                // If there is a condition check for which option is checked.
				if ( isset( $args['desc_condition'] ) ) {
					if ( (string) esc_attr( $args['desc_condition'] ) === (string) esc_attr( $args['value'] ) ) {
						$showDesc = 'radio-buttons-horizontal-desc--show';
					}
				} else {  // If no Condition show desc all the time.
					$showDesc = 'radio-buttons-horizontal-desc--show';
				}
				echo '<span class="radio-buttons-horizontal-desc '.$showDesc.'">'.$args['desc'].'</span>';

			} ?>

        </div>	<?php

		if ( $args['return_html'] ) {
			return ob_get_clean();
		}
	}

	/**
	 * Renders several HTML radio buttons in a row but as Icons.
	 *
	 * @param array $args
	 *  options key     = icon CSS name
	 *  option value    = text ( Hidden )*
	 */
	public static function radio_buttons_icon_selection( $args = array() ) {

		$defaults = array(
			'id'                => 'radio',
			'name'              => 'radio-buttons',
		);
		$args = self::add_defaults( $args, $defaults );
		$args = self::get_specs_info( $args );

		$ix = 0;   		?>

		<div class="epkb-input-group epkb-admin__radio-icons <?php echo esc_attr( $args['input_group_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>_group" >

			<span class="epkb-main_label <?php echo esc_attr( $args['main_label_class'] ); ?>"><?php echo esc_html( $args['label'] );
            if ( $args['is_pro'] ) {
					self::display_pro_setting_tag( $args['label'] );
				} ?>
            </span>

			<div class="epkb-radio-buttons-container <?php echo esc_attr( $args['input_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>">              <?php 
			
				foreach( $args['options'] as $key => $label ) {	?>

					<div class="epkb-input-container">
						<label class="epkb-label" for="<?php echo esc_attr( $args['name'] ).$ix ?>">
							<span class="epkb-label__text"><?php echo esc_html( $label ); ?></span>
							<input class="epkb-input" type="radio"
								name="<?php echo esc_attr( $args['name'] ); ?>"
								id="<?php echo esc_attr( $args['name'] . $ix ); ?>"
								value="<?php echo esc_attr( $key ); ?>"
								autocomplete="<?php echo ( $args['autocomplete'] ? 'on' : 'off' ); ?>"<?php
								checked( $key, $args['value'] )	?>
							/>                            <?php

                            switch ($key) {
	                            case 'ep_font_icon_help_dialog':
		                            echo '<span class="'.esc_attr( $key ).' epkbfa-input-icon"></span>';
		                            break;
	                            default:
		                            echo '<span class="epkbfa epkbfa-font epkbfa-'.esc_attr( $key ).' epkbfa-input-icon"></span>';

                            } ?>

						</label>
					</div> <?php

					$ix++;
				} //foreach

				if ( ! empty( $args['tooltip_body'] ) ) {
					self::display_tooltip( $args['tooltip_title'], $args['tooltip_body'] );
				}

				if ( ! empty( $args['info'] ) ) { ?>
					<span class="epkb-info-icon"><p class="hidden"><?php echo esc_html( $args['info'] ); ?></p></span> <?php
				} ?>
			</div> <?php

			if ( $args['desc'] ) {
				echo $args['desc'];
			} ?>

		</div>	<?php
	}

	/**
	 * Renders several HTML radio buttons in a column
	 * Type of Radio buttons: use the input_group_class
	 *          epkb-radio-vertical-group-container           Regular Radio Group
	 *          epkb-radio-vertical-button-group-container    Button Style Radio Group
	 *
	 * @param array $args
	 * @return false|string
	 */
	public static function radio_buttons_vertical($args = array() ) {

		$defaults = array(
			'id'                => 'radio',
			'name'              => 'radio-buttons',
		);
		$args = self::add_defaults( $args, $defaults );
		$args = self::get_specs_info( $args );

		$ix = 0;

		if ( $args['return_html'] ) {
			ob_start();
		}   ?>
        <div class="epkb-input-group <?php echo esc_attr( $args['input_group_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>_group" >

			<span class="epkb-main_label <?php echo esc_attr( $args['main_label_class'] ); ?>">
                <?php echo esc_html( $args['label'] );
                if ( ! empty( $args['tooltip_body'] ) ) {
	                self::display_tooltip( $args['tooltip_title'], $args['tooltip_body'] );
                }

                if ( ! empty( $args['info'] ) ) { ?>
                    <span class="epkb-info-icon"><p class="hidden"><?php echo esc_html( $args['info'] ); ?></p></span> <?php
                }
                if ( $args['is_pro'] ) {
	                self::display_pro_setting_tag( $args['label'] );
                } ?>
            </span>

            <div class="epkb-radio-buttons-container <?php echo esc_attr( $args['input_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>">              <?php

				foreach( $args['options'] as $key => $label ) { ?>
                    <div class="epkb-input-container">

                        <input class="epkb-input" type="radio"
                               name="<?php echo esc_attr( $args['name'] ); ?>"
                               id="<?php echo esc_attr( $args['name'] . $ix ); ?>"
                               value="<?php echo esc_attr( $key ); ?>"
                               autocomplete="<?php echo ( $args['autocomplete'] ? 'on' : 'off' ); ?>"<?php
						checked( $key, $args['value'] )	?>
                        />
                        <label class="epkb-label" for="<?php echo esc_attr( $args['name'] ).$ix ?>">
                            <span class="epkb-label__text"><?php echo esc_html( $label ); ?></span>
                        </label>


                    </div> <?php

					$ix++;
				} //foreach

				?>
            </div> <?php

			if ( $args['desc'] ) {
				echo $args['desc'];
			} ?>

        </div>	<?php

		if ( $args['return_html'] ) {
			return ob_get_clean();
		}
	}

	/**
	 * Single Inputs for text_fields_horizontal function
	 * @param array $args
	 */
	public static function horizontal_text_input( $args = array() ) {

		$args = self::add_defaults( $args );

		$data_escaped = '';
		if ( ! empty( $args['data'] ) ) {
			foreach ( $args['data'] as $key => $value ) {
				$data_escaped .= 'data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
			}
		}		?>

		<div class="<?php echo esc_attr( $args['text_class'] ); ?>">     <?php

			if ( ! empty( $args['label'] ) ) {    ?>
				<label class="<?php echo esc_attr( $args['label_class'] ); ?>" for="<?php echo esc_attr( $args['name'] ); ?>">
					<?php echo esc_html( $args['label'] ); ?>
				</label>    <?php
			}   ?>

			<div class="input_container">
				<input type="text"
				       name="<?php echo esc_attr( $args['name'] ); ?>"
				       <?php echo empty( $args['id'] ) ? '' : ' id="' . esc_attr( $args['id'] ) . '"'; ?>
				       autocomplete="<?php echo ( $args['autocomplete'] ? 'on' : 'off' ); ?>"
				       value="<?php echo esc_attr( $args['value'] ); ?>"
				       placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
				       maxlength="<?php echo esc_attr( $args['max'] ); ?>"					<?php
				echo $data_escaped . ( $args[ 'disabled' ] ? ' disabled="disabled"' : '' );	?> />
			</div>

		</div>	<?php
	}

	/**
	 * Renders several HTML checkboxes in several columns
	 *
	 * @param array $args
	 */
	public static function checkboxes_multi_select( $args = array() ) {

		$defaults = array(
			'id'           => 'checkbox',
			'name'         => 'checkbox',
			'value'        => array(),
			'main_class'   => '',
			'main_tag'     => 'li'
		);
		$args = self::add_defaults( $args, $defaults );
		$ix = 0;    	?>

		<<?php echo esc_html( $args['main_tag'] ); ?> class=" <?php echo esc_attr( $args['input_group_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>_group" >   <?php

		if ( $args['label'] != '' ) {   ?>
			<div class="main_label <?php echo esc_attr( $args['main_label_class'] ); ?>"><?php echo esc_html( $args['label'] ); ?></div>  <?php
		}   ?>

		<div class="epkb-checkboxes-horizontal <?php echo esc_attr( $args['input_class'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>">
			 		<?php

				foreach( $args['options'] as $key => $label ) {

					$tmp_value = is_array( $args['value'] ) ? $args['value'] : array();
					$checked = in_array( $key, $tmp_value );
					$label = str_replace( ',', '', $label );
					$input_id = $args['name'] . '-' . $ix;  ?>

					<div class="epkb-input-group">
						<label class="<?php echo esc_attr( $args['label_class'] ); ?>" for="<?php echo esc_attr( $input_id ); ?>">
							<?php echo esc_html( $label ); ?>
						</label>
						<div class="input_container <?php echo esc_html( $args['input_class'] ); ?>">
							<input type="checkbox"
							       name="<?php echo esc_attr( $args['name'] ); ?>"
							       id="<?php echo esc_attr( $input_id ); ?>"
							       autocomplete="<?php echo ( $args['autocomplete'] ? 'on' : 'off' ); ?>"
							       value="<?php echo esc_attr( $key ); ?>"
								<?php checked( true, $checked ); ?>
							/>
						</div>
					</div>   	<?php

					$ix++;
				} //foreach   	?>

		</div>
		</<?php echo esc_html( $args['main_tag'] ); ?>>   <?php
	}

	/**
	 * Output submit button
	 *
	 * @param string $button_label
	 * @param string $action
	 * @param string $main_class
	 * @param string $html - any additional hidden fields
	 * @param bool $unique_button - is this unique button or a group of buttons - use 'ID' for the first and 'class' for the other
	 * @param bool $return_html
	 * @param string $inputClass
	 * @return string
	 */
	public static function submit_button_v2( $button_label, $action, $main_class='', $html='', $unique_button=true, $return_html=false, $inputClass='' ) {

		if ( $return_html ) {
			ob_start();
		}		?>

		<div class="epkb-submit <?php echo esc_attr( $main_class ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>"/>     <?php

			if ( $unique_button ) {  ?>
				<input type="hidden" name="_wpnonce_epkb_ajax_action" value="<?php echo wp_create_nonce( "_wpnonce_epkb_ajax_action" ); ?>"/>
				<input type="submit" id="<?php echo esc_attr( $action ); ?>" class="<?php echo esc_attr( $inputClass ); ?>" value="<?php echo esc_attr( $button_label ); ?>" />  <?php
			} else {    ?>
				<input type="submit" class="<?php echo esc_attr( $action ) . ' ' . esc_attr( $inputClass ); ?>" value="<?php echo esc_attr( $button_label ); ?>" />  <?php
			}

			echo wp_kses_post( $html );  ?>
		</div>  <?php

		if ( $return_html ) {
			return ob_get_clean();
		}

		return '';
	}

	/**
	 * Renders an HTML Text field
	 * This has Wrappers because you need to be able to wrap both elements ( Label , Input )
	 *
	 * @param array $args Arguments for the text field
	 * @return string Text field
	 */
	public static function text_basic( $args = array() ) {

		$args = self::add_defaults( $args );
		$id             = $args['name'];
		$autocomplete   = $args['autocomplete'] ? 'on' : 'off';
		$readonly       = $args['readonly'] ? ' readonly' : '';
		$data_escaped = '';
		$label_wrap_open  = '';
		$label_wrap_close = '';
		$input_wrap_open  = '';
		$input_wrap_close = '';

		if ( ! empty( $args['data'] ) ) {
			foreach ( $args['data'] as $key => $value ) {
				$data_escaped .= 'data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
			}
		}
		if ( ! empty( $args['label_wrapper']) ) {
			$label_wrap_open   = '<' . esc_html( $args['label_wrapper'] ) . ' class="' . esc_attr( $args['main_label_class'] ) . '" >';
			$label_wrap_close  = '</' . esc_html( $args['label_wrapper'] ) . '>';
		}
		if ( ! empty( $args['input_wrapper']) ) {
			$label_wrap_open   = '<' . esc_html( $args['input_wrapper'] ) . ' class="' . esc_attr( $args['input_group_class'] ) . '" >';
			$label_wrap_close  = '<' . esc_html( $args['input_wrapper'] ) . '>';
		}

		if ( ! empty( $args['return_html'] ) ) {
			ob_start();
		}

		echo $label_wrap_open;  ?>
		<label class="<?php echo esc_attr( $args['label_class'] ); ?>" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $args['label'] ); ?></label>		<?php
		echo $label_wrap_close;

		echo  $input_wrap_open; ?>
		<input type="text" name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $args['input_class'] ); ?>"
		       autocomplete="<?php echo $autocomplete; ?>" value="<?php echo esc_attr( $args['value'] ); ?>"
		       placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" maxlength="<?php echo esc_attr( $args['max'] ); ?>" <?php echo $data_escaped . $readonly; ?> />		<?php
		echo  $input_wrap_close;

		if ( ! empty( $args['return_html'] ) ) {
			return ob_get_clean();
		}
		return '';
	}

	/**
     * Copy text to clipboard
     *
	 * @param $copy_text
	 * @param $label
	 * @param $return_html
	 *
	 * @return false|string
	 */
	public static function get_copy_to_clipboard_box( $copy_text, $label='', $return_html=true ) {

		if ( ! empty( $return_html ) ) {
			ob_start();
		}
        if ( ! empty( $label ) ) {  ?>
            <span class=""><?php echo esc_html( $label ); ?></span> <?php
		}   ?>
        <div class="epkb-copy-to-clipboard-box-container">
            <div class="epkb-ctc__embed-content">
                <span class="epkb-ctc__embed-notification"><?php echo esc_html__( 'Copied to clipboard', 'echo-knowledge-base' ); ?></span>
                <span class="epkb-ctc__embed-code"><?php echo esc_html( $copy_text ); ?></span>
            </div>
            <a class="epkb-ctc__copy-button" href="#">
                <span><?php echo esc_html__( 'Copy', 'echo-knowledge-base' ); ?></span>
            </a>
        </div>  <?php
		if ( ! empty( $return_html ) ) {
			return ob_get_clean();
		}
        return '';
	}

	private static function get_specs_info( $args ) {

		if ( empty( $args['specs'] ) ) {
			return $args;
		}

		$specs_name = $args['specs'];
		$field_specs = EPKB_KB_Config_Specs::get_fields_specification( EPKB_KB_Config_DB::DEFAULT_KB_ID );

		if ( empty( $field_specs[$specs_name] ) ) {
			return $args;
		}

		$field_spec = $field_specs[$specs_name];
		$field_spec = wp_parse_args( $field_spec, EPKB_KB_Config_Specs::get_defaults() );

		$args_specs = array(
			'name'              => $field_spec['name'],
			'label'             => $field_spec['label'],
			'type'              => $field_spec['type'],
			'input_group_class' => 'epkb-admin__input-field epkb-admin__' . $field_spec['type'] . '-field ',
			'input_class'       => $field_spec['is_pro'] && ! EPKB_Utilities::is_elegant_layouts_enabled() ? 'epkb-admin__input-disabled' : '',
			'is_pro'            => $field_spec['is_pro']
		);

		if ( $args_specs['type'] == 'select' ) {
			$args['options'] = $field_spec['options'];
		}

		return array_merge( $args, $args_specs );
	}

	/**
	 * Display a tooltip for admin form fields
	 *
	 * @param $title
	 * @param $body
	 * @param $args
	 *
	 */
	public static function display_tooltip( $title, $body, $args = array() ) {
		if ( empty( $body ) ) {
			return;
		}

		$defaults = array(
			'class'       => '',
			'open-icon'   => 'info-circle',
			'open-text'   => '',
			'link_text'   => __( 'Learn More', 'echo-knowledge-base' ),
			'link_url'    => '',
			'link_target' => '_blank'
		);
		$args = array_merge( $defaults, $args );  ?>

		<div class="epkb__option-tooltip <?php echo esc_attr( $args['class'] ); ?>">
			<span class="epkb__option-tooltip__button <?php echo $args['open-icon'] ? 'epkbfa epkbfa-' . esc_attr( $args['open-icon'] ) : ''; ?>">  <?php
				echo esc_html( $args['open-text'] );  ?>
			</span>
			<div class="epkb__option-tooltip__contents">    <?php
				if ( ! empty( $title ) ) {   ?>
					<div class="epkb__option-tooltip__header">
						<?php echo esc_html( $title ); ?>
					</div>  <?php
				}   ?>
				<div class="epkb__option-tooltip__body">
					<?php echo wp_kses_post( $body );    ?>
				</div>  <?php
				if ( ! empty( $args['link_url'] ) ) { ?>
					<div class="epkb__option-tooltip__footer">
						<a href="<?php echo esc_url( $args['link_url'] ); ?>" class="epkb__option-tooltip__button" target="<?php echo esc_attr( $args['link_target'] ); ?>">  <?php
							echo esc_html( $args['link_text'] ); ?>
						</a>
					</div>  <?php
				}  ?>
			</div>
		</div>  <?php
	}

	/**
	 *  Display a PRO Tag for settings and a Tool tip if user clicks on the settings.
	 *
	 * @param $title
	 */
	public static function display_pro_setting_tag( $title ) {

		if( EPKB_Utilities::is_help_dialog_pro_enabled() ) {
			return;
		}        ?>

		<span class="epkb__option-pro-tag">PRO</span>
		<div class="epkb__option-pro-tooltip">

			<div class="epkb__option-pro-tooltip__contents">
				<div class="epkb__option-pro-tooltip__header"><?php echo esc_html( $title ); ?></div>
				<div class="epkb__option-pro-tooltip__body">
					You need to upgrade to the PRO version to use this feature.
				</div>
				<div class="epkb__option-pro-tooltip__footer">
					<a class="epkb__option-pro-tooltip__button epkb-success-btn" href="https://www.echoknowledgebase.com/bundle-pricing/" target="_blank" rel="nofollow">Get PRO</a>
				</div>
			</div>
		</div>    <?php
	}
}