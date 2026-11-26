<?php

namespace mundophpbb\seappearance\controller;

use mundophpbb\seappearance\service\font_catalog;
use Symfony\Component\HttpFoundation\Response;

class font
{
	private $fonts;

	public function __construct(font_catalog $fonts)
	{
		$this->fonts = $fonts;
	}

	public function download($font_id)
	{
		$data = $this->fonts->bytes($font_id);
		if ($data === false)
		{
			return new Response('', 404);
		}

		$response = new Response($data, 200, [
			'Content-Type' => 'font/woff2',
			'X-Content-Type-Options' => 'nosniff',
		]);
		$response->setPublic();
		$response->setMaxAge(3600);

		return $response;
	}
}
