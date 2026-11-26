<?php

namespace mundophpbb\seappearance\acp;

use mundophpbb\seappearance\service\font_catalog;
use mundophpbb\seappearance\service\presets;
use mundophpbb\seappearance\service\settings;
use phpbb\request\request_interface;

class settings_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	public function main($id, $mode)
	{
		global $phpbb_container, $phpbb_root_path, $request, $template;

		$language = $phpbb_container->get('language');
		$language->add_lang('appearance', 'mundophpbb/seappearance');
		$language->add_lang('info_acp_seappearance', 'mundophpbb/seappearance');
		$settings = $phpbb_container->get('mundophpbb.seappearance.settings');
		$fonts = $phpbb_container->get('mundophpbb.seappearance.font_catalog');
		$presets = $phpbb_container->get('mundophpbb.seappearance.presets');

		$this->tpl_name = 'acp_seappearance';
		$this->page_title = 'ACP_SE_APPEARANCE_SETTINGS';

		add_form_key(settings::CONFIG_KEY);
		$values = $settings->read();
		$errors = [];
		$notice = '';
		$font_name = '';
		$font_error = null;
		$preset_name = '';
		$preset_error = null;
		$selected_fonts = [$values['body_font'], $values['heading_font']];

		$upload_font = $request->is_set_post('upload_font');
		$remove_font = $request->is_set_post('remove_font') && !$request->is_set_post('cancel');
		$save = $request->is_set_post('save');
		$restore = $request->is_set_post('restore');
		$save_preset = $request->is_set_post('save_preset');
		$remove_preset = $request->is_set_post('remove_preset') && !$request->is_set_post('cancel');
		if ($upload_font || $remove_font || $save || $restore || $save_preset || $remove_preset)
		{
			if (!check_form_key(settings::CONFIG_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if ($upload_font)
			{
				$font_name = $request->variable('sea_font_name', '', true, request_interface::POST);
				$font_error = $fonts->upload($font_name, $request->file('sea_font_file'));
				if (!$font_error)
				{
					redirect($this->u_action . '&amp;sea_font_added=1#sea-custom-fonts');
				}
			}
			else if ($remove_font)
			{
				$font_error = $this->remove_font($fonts, $selected_fonts);
			}
			else if ($remove_preset)
			{
				$preset_error = $this->remove_preset($presets);
			}
			else if ($save_preset)
			{
				// Save the form as a theme without changing what the forum uses.
				$preset_name = trim($request->variable('sea_preset_name', '', true, request_interface::POST));
				list($values, $errors) = $settings->validate($this->read_form($request));
				if (!$errors)
				{
					list($preset_error, $replaced) = $presets->save($preset_name, $values);
					if (!$preset_error)
					{
						$notice = $language->lang($replaced ? 'SEA_PRESET_REPLACED' : 'SEA_PRESET_SAVED', $preset_name);
						$preset_name = '';
					}
				}
			}
			else if ($restore)
			{
				$values = $settings->validate(['enabled' => $values['enabled']])[0];
				$notice = $language->lang('SEA_RESTORED_DRAFT');
			}
			else
			{
				list($values, $errors) = $settings->validate($this->read_form($request));
				if (!$errors)
				{
					$settings->save($values);
					trigger_error($language->lang('SEA_SAVED') . adm_back_link($this->u_action));
				}
			}
		}

		$this->assign_status($settings, $values, $language, $template);

		$extension_path = $phpbb_root_path . 'ext/mundophpbb/seappearance/';
		$theme_path = $phpbb_root_path . 'styles/' . settings::STYLE_PATH . '/theme';
		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'SEA_ASSETS' => $extension_path . 'assets/',
			'SEA_MONTSERRAT_CSS' => $extension_path . 'styles/all/theme/montserrat.css',
			'SEA_PREVIEW_PARENT' => $phpbb_root_path . 'styles/prosilver/theme',
			'SEA_PREVIEW_STYLE' => is_file($theme_path . '/stylesheet.css') ? $theme_path : '',
			'S_SEA_ENABLED' => $values['enabled'],
			'SEA_NOTICE' => $notice,
			'S_SEA_ERRORS' => !empty($errors),
			'SEA_FONT_NAME' => $font_name,
			'SEA_FONT_MAX_NAME' => font_catalog::MAX_FONT_NAME,
			'SEA_FONT_MAX_SIZE' => font_catalog::MAX_UPLOAD_SIZE,
			'S_SEA_FONT_ADDED' => !$upload_font && $request->variable('sea_font_added', false, false, request_interface::GET),
			'SEA_FONT_ERROR' => $font_error ? $language->lang($font_error) : '',
			'SEA_CUSTOM_FONT_CSS' => $fonts->stylesheet(array_keys($fonts->names())),
			'SEA_PRESET_NAME' => $preset_name,
			'SEA_PRESET_MAX_NAME' => presets::MAX_NAME,
			'SEA_PRESET_ERROR' => $preset_error ? $language->lang($preset_error, presets::MAX_SAVED) : '',
			'SEA_PRESET_FORMAT' => presets::EXPORT_FORMAT,
			'SEA_PRESET_FIELDS' => json_encode(presets::fields()),
		]);

		foreach ($presets->builtin() as $key => $preset_values)
		{
			$template->assign_block_vars('sea_builtin_presets', [
				'LABEL' => $language->lang('SEA_PRESET_' . strtoupper($key)),
				'VALUES' => json_encode($preset_values),
			]);
		}
		foreach ($presets->saved() as $key => $preset)
		{
			$template->assign_block_vars('sea_saved_presets', [
				'KEY' => $key,
				'LABEL' => $preset['name'],
				'VALUES' => json_encode($preset['values']),
				'S_MISSING_FONT' => $preset['missing_font'],
			]);
		}

		foreach ($fonts->names() as $key => $name)
		{
			$template->assign_block_vars('sea_custom_fonts', [
				'KEY' => $key,
				'LABEL' => $name,
				'S_SELECTED' => in_array($key, $selected_fonts, true),
			]);
		}

		foreach (settings::COLORS as $key => $default)
		{
			$template->assign_block_vars('sea_colors', [
				'KEY' => $key,
				'LABEL' => $language->lang('SEA_' . strtoupper($key)),
				'VALUE' => $values[$key],
				'DEFAULT' => $default,
				'S_ERROR' => in_array($key, $errors, true),
			]);
		}

		$font_options = $settings->font_options();
		foreach (['body_font', 'heading_font'] as $key)
		{
			$template->assign_block_vars('sea_fonts', [
				'KEY' => $key,
				'LABEL' => $language->lang('SEA_' . strtoupper($key)),
				'S_ERROR' => in_array($key, $errors, true),
			]);
			foreach ($font_options as $font => list($label, $stack))
			{
				$template->assign_block_vars('sea_fonts.options', [
					'KEY' => $font,
					'LABEL' => $font === 'system' ? $language->lang('SEA_SYSTEM_FONT') : $label,
					'STACK' => $stack,
					'S_SELECTED' => $values[$key] === $font,
				]);
			}
		}

		foreach (settings::SIZES as $key => list($min, $max, $default))
		{
			$template->assign_block_vars('sea_sizes', [
				'KEY' => $key,
				'LABEL' => $language->lang('SEA_' . strtoupper($key)),
				'VALUE' => $values[$key],
				'MIN' => $min,
				'MAX' => $max,
				'DEFAULT' => $default,
				'S_ERROR' => in_array($key, $errors, true),
			]);
		}
	}

	/**
	 * Explain why saved choices might not reach the public pages.
	 */
	private function assign_status(settings $settings, array $values, $language, $template)
	{
		global $config, $db, $user;

		$default_matches = false;
		$own_name = '';
		$own_matches = false;
		$targets = [];
		$active = [];
		$default_name = '';
		$result = $db->sql_query('SELECT * FROM ' . STYLES_TABLE . ' ORDER BY style_name');
		while ($row = $db->sql_fetchrow($result))
		{
			if ((int) $row['style_id'] === (int) $config['default_style'])
			{
				$default_name = $row['style_name'];
				$default_matches = $settings->is_target_style($row);
			}
			if ((int) $row['style_id'] === (int) $user->data['user_style'])
			{
				$own_name = $row['style_name'];
				$own_matches = $settings->is_target_style($row);
			}
			if ($settings->is_target_style($row))
			{
				$targets[] = $row['style_name'] . ' (styles/' . $row['style_path'] . '/)';
				if (!empty($row['style_active']))
				{
					$active[] = $row['style_name'];
				}
			}
		}
		$db->sql_freeresult($result);

		$messages = [];
		if (!$values['enabled'])
		{
			$messages[] = ['error', $language->lang('SEA_STATUS_DISABLED')];
		}
		if (!$targets)
		{
			$messages[] = ['error', $language->lang('SEA_STATUS_NO_STYLE')];
		}
		else if (!$active)
		{
			$messages[] = ['error', $language->lang('SEA_STATUS_INACTIVE', implode(', ', $targets))];
		}
		else
		{
			if (!$default_matches)
			{
				$messages[] = ['notice', $language->lang('SEA_STATUS_NOT_DEFAULT', $default_name, implode(', ', $active))];
			}
			// Changing the default style does not change the style already saved on existing accounts.
			if (empty($config['override_user_style']) && $own_name !== '' && !$own_matches)
			{
				$messages[] = ['error', $language->lang('SEA_STATUS_OWN_STYLE', $own_name)];
			}
		}

		$template->assign_var('SEA_STATUS_TARGETS', implode(', ', $targets));
		foreach ($messages as list($type, $text))
		{
			$template->assign_block_vars('sea_status', ['S_ERROR' => $type === 'error', 'TEXT' => $text]);
		}
	}

	private function read_form($request)
	{
		$input = ['enabled' => $request->variable('sea_enabled', 0, false, request_interface::POST) === 1];
		foreach (presets::fields() as $field)
		{
			$input[$field] = $request->variable('sea_' . $field, '', false, request_interface::POST);
		}
		return $input;
	}

	private function remove_preset(presets $presets)
	{
		global $phpbb_container, $request;

		$id = $request->variable('remove_preset', '', false, request_interface::POST);
		if (!$presets->has($id))
		{
			return 'SEA_PRESET_ERROR_MISSING';
		}
		$language = $phpbb_container->get('language');
		if (confirm_box(true))
		{
			$presets->remove($id);
			trigger_error($language->lang('SEA_PRESET_REMOVED') . adm_back_link($this->u_action . '#sea-themes'));
		}

		confirm_box(false, $language->lang('SEA_PRESET_REMOVE_CONFIRM', $presets->name($id)), build_hidden_fields([
			'remove_preset' => $id,
			'creation_time' => $request->variable('creation_time', 0, false, request_interface::POST),
			'form_token' => $request->variable('form_token', '', false, request_interface::POST),
		]));

		return 'FORM_INVALID';
	}

	private function remove_font(font_catalog $fonts, array $selected)
	{
		global $phpbb_container, $request;

		$id = $request->variable('remove_font', '', false, request_interface::POST);
		if (!$fonts->has($id))
		{
			return 'SEA_FONT_ERROR_MISSING';
		}
		if (in_array($id, $selected, true))
		{
			return 'SEA_FONT_ERROR_SELECTED';
		}
		if (confirm_box(true))
		{
			$fonts->remove($id);
			trigger_error($phpbb_container->get('language')->lang('SEA_FONT_REMOVED') . adm_back_link($this->u_action . '#sea-custom-fonts'));
		}

		confirm_box(false, 'SEA_FONT_REMOVE', build_hidden_fields([
			'remove_font' => $id,
			'creation_time' => $request->variable('creation_time', 0, false, request_interface::POST),
			'form_token' => $request->variable('form_token', '', false, request_interface::POST),
		]));

		return 'FORM_INVALID';
	}
}
