<?php

namespace mundophpbb\seappearance\event;

use mundophpbb\seappearance\service\settings;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private $settings;
	private $template;
	private $user;

	public function __construct(settings $settings, template $template, user $user)
	{
		$this->settings = $settings;
		$this->template = $template;
		$this->user = $user;
	}

	public static function getSubscribedEvents()
	{
		return ['core.page_header_after' => 'apply_appearance'];
	}

	public function apply_appearance()
	{
		if (!$this->settings->is_target_style((array) $this->user->style))
		{
			return;
		}

		$values = $this->settings->read();
		if ($values['enabled'])
		{
			$this->template->assign_vars([
				'SEA_FORUM_CSS' => $this->settings->stylesheet($values),
				'S_SEA_MONTSERRAT' => $this->settings->uses_montserrat($values),
			]);
		}
	}
}
