<?php

namespace mundophpbb\seappearance\service;

use phpbb\config\db_text;

class settings
{
	const CONFIG_KEY = 'mundophpbb_seappearance_settings';
	const STYLE_PATH = 'prosilver_se';
	const STYLE_NAME = 'prosilver Special Edition';

	// prosilver Special Edition colors. The pickers are refreshed from the installed theme after the preview loads.
	const COLORS = [
		'page' => '#ACA8A1',
		'frame' => '#FFFFFF',
		'shadow' => '#9D9A93',
		'surface' => '#FFFFFF',
		'header' => '#7C8891',
		'header_text' => '#FFFFFF',
		'category' => '#505C65',
		'category_text' => '#FFFFFF',
		'nav' => '#DCDEE2',
		'row' => '#E8ECEE',
		'text' => '#333333',
		'link' => '#105289',
	];

	const FONTS = [
		'montserrat' => ['Montserrat', '"SE Appearance Montserrat", Verdana, Arial, sans-serif'],
		'system' => ['System UI', 'system-ui, -apple-system, "Segoe UI", sans-serif'],
		'arial' => ['Arial', 'Arial, Helvetica, sans-serif'],
		'verdana' => ['Verdana', 'Verdana, Geneva, sans-serif'],
		'trebuchet' => ['Trebuchet MS', '"Trebuchet MS", Arial, sans-serif'],
		'georgia' => ['Georgia', 'Georgia, serif'],
	];

	const SIZES = [
		'base_size' => [10, 16, 10],
		'post_size' => [11, 24, 13],
	];

	private $config_text;
	private $fonts;

	public function __construct(db_text $config_text, font_catalog $fonts)
	{
		$this->config_text = $config_text;
		$this->fonts = $fonts;
	}

	public function read()
	{
		$stored = json_decode((string) $this->config_text->get(self::CONFIG_KEY), true);
		return $this->validate(is_array($stored) ? $stored : [])[0];
	}

	public function save(array $values)
	{
		$this->config_text->set(self::CONFIG_KEY, json_encode($values));
	}

	public function validate(array $input)
	{
		$settings = ['enabled' => !empty($input['enabled'])];
		$errors = [];

		foreach (self::COLORS as $name => $default)
		{
			$value = $this->read_value($input, $name);
			if ($value !== '' && !preg_match('/^#[a-fA-F0-9]{6}$/D', $value))
			{
				$errors[] = $name;
				$value = '';
			}
			$settings[$name] = strtoupper($value);
		}

		foreach (['body_font', 'heading_font'] as $name)
		{
			$value = $this->read_value($input, $name);
			if ($value !== '' && !isset(self::FONTS[$value]) && !$this->fonts->has($value))
			{
				$errors[] = $name;
				$value = '';
			}
			$settings[$name] = $value;
		}

		foreach (self::SIZES as $name => list($min, $max))
		{
			$value = $this->read_value($input, $name);
			if ($value !== '' && (!ctype_digit($value) || (int) $value < $min || (int) $value > $max))
			{
				$errors[] = $name;
				$value = '';
			}
			$settings[$name] = $value === '' ? '' : (string) (int) $value;
		}

		return [$settings, $errors];
	}

	/**
	 * prosilver SE may be installed in another folder or used as the parent of a custom style,
	 * so match the folder, the parent tree or the name from style.cfg.
	 */
	public function is_target_style(array $style)
	{
		$paths = array_merge([(string) ($style['style_path'] ?? '')], explode('/', (string) ($style['style_parent_tree'] ?? '')));
		return in_array(self::STYLE_PATH, $paths, true)
			|| strcasecmp(trim((string) ($style['style_name'] ?? '')), self::STYLE_NAME) === 0;
	}

	public function uses_montserrat(array $values)
	{
		return $values['body_font'] === 'montserrat' || $values['heading_font'] === 'montserrat';
	}

	public function font_options()
	{
		$options = self::FONTS;
		foreach ($this->fonts->names() as $id => $name)
		{
			$options[$id] = [$name, $this->fonts->stack($id)];
		}
		return $options;
	}

	public function stylesheet(array $values)
	{
		$rules = [];
		$colors = [
			'page' => ['html, #phpbb', 'background-color: %s;'],
			'frame' => ['#phpbb #wrap', 'border-color: %s;'],
			'surface' => ['#wrap', 'background-color: %s;'],
			'header' => ['#phpbb .headerbar', 'background: %s;'],
			'header_text' => ['#phpbb .site-description, #phpbb .site-description h1', 'color: %s;'],
			// prosilver SE also paints the jumpbox category links with the category gradient.
			'category' => ['#phpbb .forabg, #phpbb .forumbg, #phpbb .jumpbox-cat-link', 'background: %s;'],
			// Sortable table headers (member list, team, search results) sit on the same category background.
			'category_text' => ['#phpbb li.header dt, #phpbb li.header dd, #phpbb li.header a, #phpbb li.header a:hover, #phpbb .jumpbox-cat-link, #phpbb table.table1 thead th, #phpbb table.table1 thead th a, #phpbb table.table1 thead th a:hover', 'color: %s;'],
			'nav' => ['#phpbb .navbar', 'background-color: %s;'],
			'row' => ['#phpbb li.row, #phpbb .jumpbox-sub-link', 'background-color: %s;'],
			'text' => ['#phpbb #wrap, #phpbb .postbody .content', 'color: %s;'],
			'link' => ['#phpbb #wrap a:where(:link, :visited):not(.username):not(.username-coloured):not(.button):not(.button1):not(.button2):not(.button3):not(li.header a):not(thead a):not(.site-description a)', 'color: %s;'],
		];

		foreach ($colors as $key => list($selector, $declaration))
		{
			if ($values[$key] !== '')
			{
				$rules[] = $selector . ' { ' . sprintf($declaration, $values[$key]) . ' }';
			}
		}

		if ($values['page'] !== '')
		{
			$rules[] = $this->footer_stylesheet($values['page']);
		}
		if ($values['shadow'] !== '')
		{
			// prosilver SE removes the frame and its shadow at 920px and below; keep that layout on narrow screens.
			$rules[] = '@media (min-width: 921px) and (min-device-width: 921px) { #phpbb #wrap { box-shadow: 0 0 5px 2px ' . $values['shadow'] . ', inset 0 3px 18px rgba(100, 100, 100, .25); } }';
		}
		if ($values['row'] !== '')
		{
			// Keep prosilver SE's lighter tone for bg3 rows and the hover highlights.
			$rules[] = '#phpbb li.row:hover { background-color: color-mix(in srgb, ' . $values['row'] . ' 94%, white); }';
			$rules[] = '#phpbb li.row.bg3 { background-color: color-mix(in srgb, ' . $values['row'] . ' 60%, white); }';
			$rules[] = '#phpbb li.row.bg3:hover { background-color: color-mix(in srgb, ' . $values['row'] . ' 45%, white); }';
		}
		if ($values['body_font'] !== '')
		{
			// Prosilver sets a separate font on the post content container.
			$rules[] = '#phpbb, #phpbb .postbody .content, #phpbb .content p, #phpbb input, #phpbb select, #phpbb textarea, #phpbb button'
				. ' { font-family: ' . $this->font_stack($values['body_font']) . '; }';
		}
		if ($values['heading_font'] !== '')
		{
			$rules[] = '#phpbb h1, #phpbb h2, #phpbb h3, #phpbb h4, #phpbb h5, #phpbb h6, #phpbb a.forumtitle, #phpbb a.topictitle { font-family: ' . $this->font_stack($values['heading_font']) . '; }';
		}
		if ($values['base_size'] !== '')
		{
			$rules[] = '#phpbb { font-size: ' . $values['base_size'] . 'px; }';
		}
		if ($values['post_size'] !== '')
		{
			$rules[] = '#phpbb .postbody .content { font-size: ' . $values['post_size'] . 'px; }';
		}

		$font_css = $this->fonts->stylesheet([$values['body_font'], $values['heading_font']]);
		return ($font_css === '' ? '' : $font_css . "\n") . implode("\n", $rules);
	}

	private function footer_stylesheet($background)
	{
		$luminance = $this->relative_luminance($background);
		$black_contrast = ($luminance + 0.05) / 0.05;
		$white_contrast = 1.05 / ($luminance + 0.05);
		$dark_text = $black_contrast >= $white_contrast;
		$text = $dark_text ? '#000000' : '#FFFFFF';
		$link = $dark_text ? '#105289' : '#A9D7FF';
		$link_luminance = $this->relative_luminance($link);
		$link_contrast = (max($luminance, $link_luminance) + 0.05) / (min($luminance, $link_luminance) + 0.05);
		if ($link_contrast < 4.5)
		{
			$link = $text;
		}

		// prosilver SE displays its copyright block outside the forum wrapper and forces
		// black footer links with !important, so the override must be important too.
		$footer = '#phpbb > .copyright';
		return $footer . ', ' . $footer . ' .footer-row, ' . $footer . ' .footer-copyright, ' . $footer . ' .footer-info { color: ' . $text . "; }\n"
			. $footer . ' a { color: ' . $link . " !important; text-decoration: underline; }\n"
			. $footer . ' a .footer-link-text { color: inherit; }';
	}

	private function relative_luminance($color)
	{
		$channels = str_split(substr($color, 1), 2);
		list($red, $green, $blue) = array_map(static function ($channel)
		{
			$value = hexdec($channel) / 255;
			return $value <= 0.04045 ? $value / 12.92 : pow(($value + 0.055) / 1.055, 2.4);
		}, $channels);

		return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
	}

	private function font_stack($id)
	{
		return self::FONTS[$id][1] ?? $this->fonts->stack($id);
	}

	private function read_value(array $input, $key)
	{
		return isset($input[$key]) && is_scalar($input[$key]) ? trim((string) $input[$key]) : '';
	}
}
