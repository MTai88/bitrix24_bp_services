<?php

namespace Mtai\Bpservices;

/**
 * Keeps the PROCESS_IBLOCK list property of the services infoblock in sync
 * with infoblocks of the bitrix_processes type (universal lists), and tunes
 * the bizproc UI to the single entry point /bp-services/.
 *
 * Registered for the old-style iblock events OnAfterIBlockAdd / Update /
 * Delete, therefore the handlers receive the iblock fields by reference.
 */
class EventHandler
{
	/** «Запущенные процессы»: единственный путь к процессам — /bp-services/. */
	public const USER_PROCESSES_PATH = '/bizproc/userprocesses/';

	/**
	 * Toolbar button "Запустить" on the started processes page; the id is
	 * built server-side by bitrix:bizproc.user.processes (GRID_ID constant),
	 * the button itself is rendered client-side.
	 */
	public const USER_PROCESSES_START_BUTTON_ID = 'bizproc_user_processes_v2-filter-start-workflow-button';
	/**
	 * @param array &$fields iblock fields, IBLOCK_TYPE_ID included
	 */
	public static function onAfterIblockAdd(&$fields): void
	{
		if (($fields['IBLOCK_TYPE_ID'] ?? '') === IblockManager::PROCESS_TYPE)
		{
			IblockManager::syncProcessEnum();
		}
	}

	/**
	 * @param array &$fields iblock fields; on update NAME may be missing,
	 *   so the type check is skipped when the type is unknown
	 */
	public static function onAfterIblockUpdate(&$fields): void
	{
		if (!is_array($fields))
		{
			return;
		}

		$type = $fields['IBLOCK_TYPE_ID'] ?? '';
		if ($type === IblockManager::PROCESS_TYPE || $type === '')
		{
			IblockManager::syncProcessEnum();
		}
	}

	/**
	 * The old event passes only the iblock ID (not the fields), so the type of
	 * the deleted iblock is unknown — a full resync is cheap and removes dead
	 * enum values anyway.
	 *
	 * @param int|array &$fields iblock ID or iblock fields
	 */
	public static function onAfterIblockDelete(&$fields): void
	{
		if (is_array($fields) && ($fields['IBLOCK_TYPE_ID'] ?? '') !== IblockManager::PROCESS_TYPE)
		{
			return;
		}

		IblockManager::syncProcessEnum();
	}

	/**
	 * Hides the "Запустить" toolbar button on the started processes page:
	 * employees launch processes from /bp-services/ only. The style goes to
	 * <head> so the client-rendered button never flashes. AJAX responses are
	 * skipped by the page check.
	 *
	 * @param string &$content page buffer
	 */
	public static function onEndBufferContent(&$content): void
	{
		if (!is_string($content) || $content === '')
		{
			return;
		}

		global $APPLICATION;
		if (mb_strpos($APPLICATION->GetCurPage(), self::USER_PROCESSES_PATH) !== 0)
		{
			return;
		}

		$style = '<style>#' . self::USER_PROCESSES_START_BUTTON_ID . '{display:none !important;}</style>';

		$pos = mb_strripos($content, '</head>');
		if ($pos !== false)
		{
			$content = mb_substr($content, 0, $pos) . $style . mb_substr($content, $pos);
		}
	}
}
