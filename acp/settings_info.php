<?php

namespace mundophpbb\seappearance\acp;

class settings_info
{
	public function module()
	{
		return [
			'filename' => '\\mundophpbb\\seappearance\\acp\\settings_module',
			'title' => 'ACP_SE_APPEARANCE',
			'modes' => [
				'settings' => [
					'title' => 'ACP_SE_APPEARANCE_SETTINGS',
					'auth' => 'ext_mundophpbb/seappearance && acl_a_board',
					'cat' => ['ACP_SE_APPEARANCE'],
				],
			],
		];
	}
}
