<?php

namespace mundophpbb\seappearance\service;

use phpbb\config\db_text;

/**
 * Built-in themes and themes saved by administrators.
 *
 * A theme holds every appearance field (colors, fonts and sizes) but never the
 * "apply custom appearance" switch. Applying a theme only fills the ACP form;
 * the forum changes when the administrator submits it.
 */
class presets
{
	const CONFIG_KEY = 'mundophpbb_seappearance_presets';
	const MAX_SAVED = 20;
	const MAX_NAME = 60;
	const EXPORT_FORMAT = 'mundophpbb/seappearance-theme';

	// Every theme keeps light inner surfaces: the extension does not recolor post
	// panels, forms or tables, so dark text has to stay readable on them.
	const BUILTIN = [
		'ocean' => [
			'page' => '#2F5D7C', 'frame' => '#FFFFFF', 'shadow' => '#1E3E54', 'surface' => '#FFFFFF',
			'header' => '#1F5F8B', 'header_text' => '#FFFFFF', 'category' => '#174A6E', 'category_text' => '#FFFFFF',
			'nav' => '#DCEAF4', 'row' => '#EEF4F9', 'text' => '#26323A', 'link' => '#0B5C99',
			'body_font' => 'system', 'heading_font' => 'system',
		],
		'forest' => [
			'page' => '#3E5A47', 'frame' => '#FFFFFF', 'shadow' => '#2B3F31', 'surface' => '#FFFFFF',
			'header' => '#2E6B47', 'header_text' => '#FFFFFF', 'category' => '#24553A', 'category_text' => '#FFFFFF',
			'nav' => '#DDEBE1', 'row' => '#EDF4EF', 'text' => '#26302A', 'link' => '#1D6B3E',
			'body_font' => 'verdana', 'heading_font' => 'trebuchet',
		],
		'sepia' => [
			'page' => '#CDBFA6', 'frame' => '#E2D6C0', 'shadow' => '#A8977A', 'surface' => '#FFFDF7',
			'header' => '#8A6A45', 'header_text' => '#FFFFFF', 'category' => '#6E5233', 'category_text' => '#FFFFFF',
			'nav' => '#EFE6D6', 'row' => '#F6F0E4', 'text' => '#3A3027', 'link' => '#8A4B12',
			'body_font' => 'georgia', 'heading_font' => 'georgia', 'post_size' => '14',
		],
		'graphite' => [
			'page' => '#2B2E33', 'frame' => '#3A3F46', 'shadow' => '#16181B', 'surface' => '#FFFFFF',
			'header' => '#3A3F46', 'header_text' => '#FFFFFF', 'category' => '#2F3439', 'category_text' => '#FFFFFF',
			'nav' => '#E3E5E8', 'row' => '#F0F1F3', 'text' => '#24272B', 'link' => '#2F5F8F',
			'body_font' => 'montserrat', 'heading_font' => 'montserrat',
		],
		'wine' => [
			'page' => '#5A2A35', 'frame' => '#FFFFFF', 'shadow' => '#3C1B23', 'surface' => '#FFFFFF',
			'header' => '#7A2E3F', 'header_text' => '#FFFFFF', 'category' => '#5E2230', 'category_text' => '#FFFFFF',
			'nav' => '#F1E3E6', 'row' => '#F8EEF0', 'text' => '#2E2427', 'link' => '#8C2338',
			'body_font' => 'trebuchet', 'heading_font' => 'georgia',
		],
		'contrast' => [
			'page' => '#000000', 'frame' => '#000000', 'shadow' => '#000000', 'surface' => '#FFFFFF',
			'header' => '#000000', 'header_text' => '#FFFFFF', 'category' => '#000000', 'category_text' => '#FFFFFF',
			'nav' => '#FFFFFF', 'row' => '#FFFFFF', 'text' => '#000000', 'link' => '#0000CC',
			'body_font' => 'verdana', 'heading_font' => 'verdana', 'base_size' => '11', 'post_size' => '15',
		],
	];

	private $config_text;
	private $settings;

	public function __construct(db_text $config_text, settings $settings)
	{
		$this->config_text = $config_text;
		$this->settings = $settings;
	}

	/**
	 * Names of the appearance fields a theme stores, in form order.
	 */
	public static function fields()
	{
		return array_merge(array_keys(settings::COLORS), ['body_font', 'heading_font'], array_keys(settings::SIZES));
	}

	/**
	 * @return array id => complete, validated values
	 */
	public function builtin()
	{
		$themes = [];
		foreach (self::BUILTIN as $id => $values)
		{
			$themes[$id] = $this->clean($values)[0];
		}
		return $themes;
	}

	/**
	 * @return array id => ['name' => string, 'values' => array, 'missing_font' => bool]
	 */
	public function saved()
	{
		$themes = [];
		foreach ($this->stored() as $id => $theme)
		{
			list($values, $errors) = $this->clean($theme['values']);
			$themes[$id] = [
				'name' => $theme['name'],
				'values' => $values,
				// A custom font used by the theme was removed after the theme was saved.
				'missing_font' => (bool) array_intersect($errors, ['body_font', 'heading_font']),
			];
		}

		uasort($themes, static function ($a, $b)
		{
			return strnatcasecmp($a['name'], $b['name']);
		});
		return $themes;
	}

	public function has($id)
	{
		return is_string($id) && isset($this->stored()[$id]);
	}

	public function name($id)
	{
		return $this->stored()[$id]['name'] ?? '';
	}

	/**
	 * Save values under a name. A theme with the same name (ignoring case) is replaced.
	 *
	 * @return array [error language key or null, bool replaced an existing theme]
	 */
	public function save($name, array $values)
	{
		$name = trim($name);
		if (!$this->valid_name($name))
		{
			return ['SEA_PRESET_ERROR_NAME', false];
		}

		$themes = $this->stored();
		$id = null;
		foreach ($themes as $key => $theme)
		{
			if ($this->same_name($theme['name'], $name))
			{
				$id = $key;
				break;
			}
		}
		if ($id === null && count($themes) >= self::MAX_SAVED)
		{
			return ['SEA_PRESET_ERROR_LIMIT', false];
		}

		$replaced = $id !== null;
		$id = $id ?? 'saved_' . bin2hex(random_bytes(6));
		$themes[$id] = ['name' => $name, 'values' => $this->clean($values)[0]];
		$this->store($themes);

		return [null, $replaced];
	}

	public function remove($id)
	{
		$themes = $this->stored();
		if (isset($themes[$id]))
		{
			unset($themes[$id]);
			$this->store($themes);
		}
	}

	public function valid_name($name)
	{
		return is_string($name)
			&& preg_match('/^[\p{L}\p{N}][\p{L}\p{M}\p{N} ._-]*$/uD', $name)
			&& mb_strlen($name, 'UTF-8') <= self::MAX_NAME;
	}

	/**
	 * Keep only the appearance fields and validate them. Invalid values become
	 * empty, which means "use the style's original".
	 *
	 * @return array [values, invalid field names]
	 */
	private function clean(array $values)
	{
		list($clean, $errors) = $this->settings->validate($values);
		return [array_intersect_key($clean, array_flip(self::fields())), $errors];
	}

	private function stored()
	{
		$stored = json_decode((string) $this->config_text->get(self::CONFIG_KEY), true);
		$themes = [];
		foreach (is_array($stored) ? $stored : [] as $id => $theme)
		{
			if (is_string($id) && preg_match('/^saved_[a-f0-9]{12}$/D', $id) && is_array($theme)
				&& isset($theme['name']) && $this->valid_name($theme['name']) && isset($theme['values']) && is_array($theme['values']))
			{
				$themes[$id] = ['name' => $theme['name'], 'values' => $theme['values']];
			}
		}
		return $themes;
	}

	private function store(array $themes)
	{
		$this->config_text->set(self::CONFIG_KEY, json_encode($themes));
	}

	private function same_name($a, $b)
	{
		return mb_strtolower($a, 'UTF-8') === mb_strtolower($b, 'UTF-8');
	}
}
