<?php

namespace mundophpbb\seappearance\migrations;

class v110 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\\mundophpbb\\seappearance\\migrations\\v100'];
	}

	public function update_data()
	{
		return [
			['config_text.add', ['mundophpbb_seappearance_presets', '{}']],
		];
	}
}
