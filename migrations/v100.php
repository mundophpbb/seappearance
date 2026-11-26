<?php

namespace mundophpbb\seappearance\migrations;

class v100 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\\phpbb\\db\\migration\\data\\v330\\v330'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'seappearance_fonts' => [
					'COLUMNS' => [
						'font_id' => ['VCHAR:31', ''],
						'font_name' => ['VCHAR_UNI:60', ''],
						'font_data' => ['MTEXT', ''],
					],
					'PRIMARY_KEY' => 'font_id',
				],
			],
		];
	}

	public function revert_schema()
	{
		return ['drop_tables' => [$this->table_prefix . 'seappearance_fonts']];
	}

	public function update_data()
	{
		return [
			['config_text.add', ['mundophpbb_seappearance_settings', '{}']],
			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_SE_APPEARANCE']],
			['module.add', ['acp', 'ACP_SE_APPEARANCE', [
				'module_basename' => '\\mundophpbb\\seappearance\\acp\\settings_module',
				'modes' => ['settings'],
			]]],
		];
	}
}
