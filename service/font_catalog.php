<?php

namespace mundophpbb\seappearance\service;

use phpbb\db\driver\driver_interface;
use phpbb\routing\helper;

class font_catalog
{
	const MAX_UPLOAD_SIZE = 1048576;
	const MAX_FONT_NAME = 60;

	private $db;
	private $routing;
	private $table;
	private $names;

	public function __construct(driver_interface $db, helper $routing, $table)
	{
		$this->db = $db;
		$this->routing = $routing;
		$this->table = $table;
	}

	public function names()
	{
		if ($this->names === null)
		{
			$this->names = [];
			$result = $this->db->sql_query('SELECT font_id, font_name FROM ' . $this->table . ' ORDER BY font_name, font_id');
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->names[$row['font_id']] = $row['font_name'];
			}
			$this->db->sql_freeresult($result);
		}

		return $this->names;
	}

	public function has($id)
	{
		return $this->valid_id($id) && isset($this->names()[$id]);
	}

	public function stack($id)
	{
		return '"SE Appearance ' . $id . '", Verdana, Arial, sans-serif';
	}

	public function upload($name, array $file)
	{
		if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{M}\p{N} ._-]*$/uD', $name) || utf8_strlen($name) > self::MAX_FONT_NAME)
		{
			return 'SEA_FONT_ERROR_NAME';
		}

		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true))
		{
			return 'SEA_FONT_ERROR_SIZE';
		}
		$path = $file['tmp_name'] ?? '';
		if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path))
		{
			return 'SEA_FONT_ERROR_UPLOAD';
		}

		$data = file_get_contents($path, false, null, 0, self::MAX_UPLOAD_SIZE + 1);
		if ($data === false)
		{
			return 'SEA_FONT_ERROR_UPLOAD';
		}
		if (strlen($data) > self::MAX_UPLOAD_SIZE)
		{
			return 'SEA_FONT_ERROR_SIZE';
		}
		$filename = $file['name'] ?? '';
		if (!is_string($filename) || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'woff2' ||
			!$this->valid_woff2($data))
		{
			return 'SEA_FONT_ERROR_FORMAT';
		}

		$sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', [
			'font_id' => 'upload_' . bin2hex(random_bytes(12)),
			'font_name' => $name,
			'font_data' => base64_encode($data),
		]);
		$this->db->sql_query($sql);
		$this->names = null;

		return null;
	}

	public function remove($id)
	{
		if ($this->valid_id($id))
		{
			$sql = 'DELETE FROM ' . $this->table . "
				WHERE font_id = '" . $this->db->sql_escape($id) . "'";
			$this->db->sql_query($sql);
			$this->names = null;
		}
	}

	public function bytes($id)
	{
		if (!$this->valid_id($id))
		{
			return false;
		}

		$sql = 'SELECT font_data FROM ' . $this->table . "
			WHERE font_id = '" . $this->db->sql_escape($id) . "'";
		$result = $this->db->sql_query($sql);
		$data = $this->db->sql_fetchfield('font_data', false, $result);
		$this->db->sql_freeresult($result);

		return is_string($data) ? base64_decode($data, true) : false;
	}

	public function stylesheet(array $ids)
	{
		$rules = [];
		foreach (array_unique($ids) as $id)
		{
			if (!$this->has($id))
			{
				continue;
			}

			$url = $this->routing->route('mundophpbb_seappearance_font', ['font_id' => $id], false, '');
			// The asset does not use phpBB's session or style query parameters.
			$url = explode('?', $url, 2)[0];
			// Escape the URL as a CSS string inside the inline style element.
			$url = strtr($url, ['\\' => '\\5c ', '"' => '\\22 ', '<' => '\\3c ', "\n" => '\\a ', "\r" => '\\d ', "\f" => '\\c ']);
			$rules[] = '@font-face { font-family: "SE Appearance ' . $id . '"; src: url("' . $url . '") format("woff2"); font-style: normal; font-weight: 400; font-display: swap; }';
		}
		return implode("\n", $rules);
	}

	private function valid_id($id)
	{
		return preg_match('/^upload_[a-f0-9]{24}$/D', $id);
	}

	private function valid_woff2($data)
	{
		$length = strlen($data);
		if ($length <= 48 || substr($data, 0, 4) !== 'wOF2' ||
			!in_array(substr($data, 4, 4), ["\x00\x01\x00\x00", 'OTTO'], true))
		{
			return false;
		}

		$header = unpack('Nlength/ntables/nreserved/Nsfnt/Ncompressed', substr($data, 8, 16));
		if ($header['length'] !== $length || $header['reserved'] !== 0 || $header['tables'] < 1 ||
			$header['tables'] > 256 || $header['sfnt'] < 12 + 16 * $header['tables'] ||
			$header['sfnt'] > 16 * self::MAX_UPLOAD_SIZE || $header['compressed'] < 1 ||
			$header['compressed'] > $length - 48 - 2 * $header['tables'])
		{
			return false;
		}

		$blocks = unpack('Nmeta_offset/Nmeta_length/Nmeta_original/Nprivate_offset/Nprivate_length', substr($data, 28, 20));
		$min_offset = 48 + 2 * $header['tables'] + $header['compressed'];
		foreach (['meta', 'private'] as $key)
		{
			$offset = $blocks[$key . '_offset'];
			$size = $blocks[$key . '_length'];
			if ($offset === 0 && $size === 0)
			{
				continue;
			}
			if ($offset < $min_offset || $offset > $length || $size < 1 || $size > $length - $offset)
			{
				return false;
			}
			$min_offset = $offset + $size;
		}
		return ($blocks['meta_offset'] === 0) === ($blocks['meta_original'] === 0);
	}
}
