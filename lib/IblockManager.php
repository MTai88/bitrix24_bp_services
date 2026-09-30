<?php

namespace Mtai\Bpservices;

use Bitrix\Main\SiteTable;
use CIBlock;
use CIBlockElement;
use CIBlockProperty;
use CIBlockPropertyEnum;
use CIBlockType;
use RuntimeException;

/**
 * Service registry of the module: the "Business process services" infoblock
 * and its PROCESS_IBLOCK list property kept in sync with infoblocks of the
 * bitrix_processes type (universal lists).
 *
 * The XML_ID of every enumeration value stores the ID of the bound list
 * infoblock, so the public page can build a link to the element creation
 * form of that list without extra queries.
 */
class IblockManager
{
	/** Own infoblock type of the module. */
	public const IBLOCK_TYPE = 'mtai_bpservices';

	/** Symbol code of the services infoblock. */
	public const IBLOCK_CODE = 'mtai_bpservices';

	/** Property: bound universal list (select), enum XML_ID = list infoblock ID. */
	public const PROPERTY_PROCESS = 'PROCESS_IBLOCK';

	/** Property: service icon (file). */
	public const PROPERTY_ICON = 'ICON';

	/** Infoblock type of universal lists (module "Lists"). */
	public const PROCESS_TYPE = 'bitrix_processes';

	/** Where to return after an element of a list is added/updated (back_url param). */
	public const PROCESS_AFTER_SAVE_URL = '/bizproc/userprocesses/';

	/**
	 * Element creation form URL template of a universal list; back_url makes
	 * the form return to PROCESS_AFTER_SAVE_URL after saving.
	 */
	public const PROCESS_FORM_URL_TEMPLATE = '/bizproc/processes/#PROCESS_ID#/element/0/0/?list_section_id=&back_url=' . self::PROCESS_AFTER_SAVE_URL;

	/**
	 * @return int ID of the services infoblock, 0 when not created yet
	 */
	public static function getServicesIblockId(): int
	{
		$row = CIBlock::GetList(
			[],
			[
				'TYPE' => self::IBLOCK_TYPE,
				'CODE' => self::IBLOCK_CODE,
				'CHECK_PERMISSIONS' => 'N',
			]
		)->Fetch();

		return $row ? (int)$row['ID'] : 0;
	}

	/**
	 * All infoblocks of the universal lists type, no matter active or not.
	 *
	 * @return array<int, array{NAME: string, SORT: int, ACTIVE: string}>
	 */
	public static function getProcessIblocks(): array
	{
		$result = [];

		$rsIblocks = CIBlock::GetList(
			['SORT' => 'ASC', 'NAME' => 'ASC'],
			['TYPE' => self::PROCESS_TYPE, 'CHECK_PERMISSIONS' => 'N']
		);
		while ($iblock = $rsIblocks->Fetch())
		{
			$result[(int)$iblock['ID']] = [
				'NAME' => (string)$iblock['NAME'],
				'SORT' => (int)$iblock['SORT'],
				'ACTIVE' => (string)$iblock['ACTIVE'],
			];
		}

		return $result;
	}

	/**
	 * Creates the infoblock type, the services infoblock and its properties.
	 * Safe to call repeatedly: existing entities are reused.
	 *
	 * @return int ID of the services infoblock
	 * @throws RuntimeException
	 */
	public static function installInfoblock(): int
	{
		if (self::getServicesIblockId() > 0)
		{
			self::syncProcessEnum();

			return self::getServicesIblockId();
		}

		$rsType = CIBlockType::GetList([], ['=ID' => self::IBLOCK_TYPE]);
		if (!$rsType->Fetch())
		{
			$obType = new CIBlockType();
			$ok = $obType->Add([
				'ID' => self::IBLOCK_TYPE,
				'SECTIONS' => 'N',
				'SORT' => 95,
				'LANG' => [
					'ru' => [
						'NAME' => 'Сервисы бизнес-процессов',
						'SECTION_NAME' => 'Разделы',
						'ELEMENT_NAME' => 'Сервис',
					],
					'en' => [
						'NAME' => 'Business process services',
						'SECTION_NAME' => 'Sections',
						'ELEMENT_NAME' => 'Service',
					],
				],
			]);
			if (!$ok)
			{
				throw new RuntimeException('iblock type: ' . $obType->LAST_ERROR);
			}
		}

		$siteIds = [];
		$rsSites = SiteTable::getList(['select' => ['LID'], 'filter' => ['=ACTIVE' => 'Y']]);
		while ($site = $rsSites->fetch())
		{
			$siteIds[] = $site['LID'];
		}
		if (!$siteIds)
		{
			$siteIds = ['s1'];
		}

		$obIblock = new CIBlock();
		$iblockId = (int)$obIblock->Add([
			'ACTIVE' => 'Y',
			'NAME' => 'Сервисы бизнес-процессов',
			'CODE' => self::IBLOCK_CODE,
			'IBLOCK_TYPE_ID' => self::IBLOCK_TYPE,
			'SITE_ID' => $siteIds,
			'SORT' => 100,
			// 1 = portal administrators, 2 = all users (the page is read-only for employees)
			'GROUP_ID' => [1 => 'X', 2 => 'R'],
			'WORKFLOW' => 'N',
			'LIST_PAGE_URL' => '#SITE_DIR#bp-services/',
			'DETAIL_PAGE_URL' => '#SITE_DIR#bp-services/',
			'INDEX_ELEMENT' => 'Y',
			'INDEX_SECTION' => 'N',
			'FIELDS' => [
				// short description shown on the service card
				'PREVIEW_TEXT' => ['IS_REQUIRED' => 'N', 'DEFAULT_VALUE' => '', 'USE_EDITOR' => 'N'],
				'PREVIEW_TEXT_TYPE' => ['DEFAULT_VALUE' => 'text'],
			],
		]);
		if ($iblockId <= 0)
		{
			throw new RuntimeException('iblock: ' . $obIblock->LAST_ERROR);
		}

		self::ensureProperties($iblockId);
		self::syncProcessEnum($iblockId);

		return $iblockId;
	}

	/**
	 * @param int $iblockId services infoblock ID
	 * @throws RuntimeException
	 */
	public static function ensureProperties(int $iblockId): void
	{
		$properties = [
			[
				'NAME' => 'Процесс (универсальный список)',
				'CODE' => self::PROPERTY_PROCESS,
				'PROPERTY_TYPE' => 'L',
				'LIST_TYPE' => 'L',
				'MULTIPLE' => 'N',
				'IS_REQUIRED' => 'Y',
				'SORT' => 20,
				'HINT' => 'Список, на форму создания элемента которого будет вести сервис. Варианты заполняются автоматически из универсальных списков.',
			],
			[
				'NAME' => 'Иконка сервиса',
				'CODE' => self::PROPERTY_ICON,
				'PROPERTY_TYPE' => 'F',
				'FILE_TYPE' => 'svg, png, jpg, jpeg, webp',
				'MULTIPLE' => 'N',
				'IS_REQUIRED' => 'N',
				'SORT' => 30,
				'HINT' => 'Отображается на плитке сервиса на странице /bp-services/.',
			],
		];

		foreach ($properties as $fields)
		{
			$exists = CIBlockProperty::GetList(
				[],
				['IBLOCK_ID' => $iblockId, 'CODE' => $fields['CODE']]
			)->Fetch();
			if ($exists)
			{
				continue;
			}

			$fields['IBLOCK_ID'] = $iblockId;
			$obProperty = new CIBlockProperty();
			$propertyId = (int)$obProperty->Add($fields);
			if ($propertyId <= 0)
			{
				throw new RuntimeException('property ' . $fields['CODE'] . ': ' . $obProperty->LAST_ERROR);
			}
		}
	}

	/**
	 * Aligns enumeration values of the PROCESS_IBLOCK property with the actual
	 * infoblocks of the bitrix_processes type: adds new, updates names and
	 * sorts, removes dead ones. Called on module install and by event handlers
	 * after a list infoblock is added, updated or deleted.
	 *
	 * @param int|null $iblockId services infoblock ID (found automatically when null)
	 * @return array report: added / updated / deleted enum value IDs
	 */
	public static function syncProcessEnum(?int $iblockId = null): array
	{
		$report = ['added' => [], 'updated' => [], 'deleted' => []];

		if ($iblockId === null)
		{
			$iblockId = self::getServicesIblockId();
		}
		if ($iblockId <= 0)
		{
			return $report;
		}

		$property = CIBlockProperty::GetList(
			[],
			['IBLOCK_ID' => $iblockId, 'CODE' => self::PROPERTY_PROCESS]
		)->Fetch();
		if (!$property)
		{
			return $report;
		}
		$propertyId = (int)$property['ID'];

		$existing = [];
		$rsEnum = CIBlockPropertyEnum::GetList(
			[],
			['IBLOCK_ID' => $iblockId, 'CODE' => self::PROPERTY_PROCESS]
		);
		while ($enum = $rsEnum->Fetch())
		{
			$existing[(string)$enum['XML_ID']] = $enum;
		}

		$obEnum = new CIBlockPropertyEnum();

		foreach (self::getProcessIblocks() as $processId => $process)
		{
			$xmlId = (string)$processId;
			if (isset($existing[$xmlId]))
			{
				$enum = $existing[$xmlId];
				if ($enum['VALUE'] !== $process['NAME'] || (int)$enum['SORT'] !== $process['SORT'])
				{
					if ($obEnum->Update($enum['ID'], ['VALUE' => $process['NAME'], 'SORT' => $process['SORT']]))
					{
						$report['updated'][] = (int)$enum['ID'];
					}
				}
				unset($existing[$xmlId]);
				continue;
			}

			$enumId = (int)$obEnum->Add([
				'PROPERTY_ID' => $propertyId,
				'VALUE' => $process['NAME'],
				'XML_ID' => $xmlId,
				'SORT' => $process['SORT'],
			]);
			if ($enumId > 0)
			{
				$report['added'][] = $enumId;
			}
		}

		// what is left in $existing refers to deleted list infoblocks
		foreach ($existing as $enum)
		{
			if ($obEnum->Delete($enum['ID']))
			{
				$report['deleted'][] = (int)$enum['ID'];
			}
		}

		if ($report['added'] || $report['updated'] || $report['deleted'])
		{
			CIBlock::clearIblockTagCache($iblockId);
		}

		return $report;
	}

	/**
	 * URL of the element creation form of a universal list.
	 */
	public static function buildProcessFormUrl(int $processIblockId): string
	{
		$siteDir = defined('SITE_DIR') ? SITE_DIR : '/';

		return $siteDir . ltrim(
			str_replace('#PROCESS_ID#', (string)$processIblockId, self::PROCESS_FORM_URL_TEMPLATE),
			'/'
		);
	}

	/**
	 * Deletes the services infoblock with all elements and the module infoblock type.
	 */
	public static function uninstallInfoblock(): void
	{
		$iblockId = self::getServicesIblockId();
		if ($iblockId > 0)
		{
			CIBlockElement::DeleteAll($iblockId);
			CIBlock::Delete($iblockId);
		}

		$rsType = CIBlockType::GetList([], ['=ID' => self::IBLOCK_TYPE]);
		if ($rsType->Fetch())
		{
			CIBlockType::Delete(self::IBLOCK_TYPE);
		}
	}
}
