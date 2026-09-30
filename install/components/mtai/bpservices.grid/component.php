<?php
/**
 * mtai:bpservices.grid — плитка сервисов бизнес-процессов.
 *
 * Выводит активные элементы инфоблока «Сервисы бизнес-процессов»:
 * иконка + название + описание; клик ведёт на форму создания элемента
 * привязанного универсального списка. Элементы с удалённым или
 * неактивным процессом пропускаются.
 */

use Bitrix\Main\Loader;
use Mtai\Bpservices\IblockManager;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

/** @var CBitrixComponent $this */
/** @var array $arParams */
/** @var array $arResult */
global $USER;

$arParams['CACHE_TIME'] = isset($arParams['CACHE_TIME']) ? (int)$arParams['CACHE_TIME'] : 36000;

$arResult = [
	'IBLOCK_ID' => 0,
	'ITEMS' => [],
	'ERROR' => '',
	'IS_ADMIN' => is_object($USER) && $USER->IsAdmin(),
	'ADD_LINK' => '',
];

if (!Loader::includeModule('iblock'))
{
	$arResult['ERROR'] = 'Модуль «Информационные блоки» не установлен.';
	$this->includeComponentTemplate();

	return;
}

if (!Loader::includeModule('mtai.bpservices'))
{
	$arResult['ERROR'] = 'Модуль mtai.bpservices не установлен.';
	$this->includeComponentTemplate();

	return;
}

$iblockId = IblockManager::getServicesIblockId();
if ($iblockId <= 0)
{
	$arResult['ERROR'] = 'Инфоблок «Сервисы бизнес-процессов» не найден — переустановите модуль mtai.bpservices.';
	$this->includeComponentTemplate();

	return;
}

$arResult['IBLOCK_ID'] = $iblockId;

if ($this->startResultCache())
{
	// enum id → id привязанного инфоблока списка (XML_ID значения)
	$enumMap = [];
	$rsEnum = CIBlockPropertyEnum::GetList(
		['SORT' => 'ASC', 'ID' => 'ASC'],
		['IBLOCK_ID' => $iblockId, 'CODE' => IblockManager::PROPERTY_PROCESS]
	);
	while ($enum = $rsEnum->Fetch())
	{
		$enumMap[(int)$enum['ID']] = (int)$enum['XML_ID'];
	}

	$processIblocks = IblockManager::getProcessIblocks();

	$rsElements = CIBlockElement::GetList(
		['SORT' => 'ASC', 'ID' => 'ASC'],
		[
			'IBLOCK_ID' => $iblockId,
			'ACTIVE' => 'Y',
			'CHECK_PERMISSIONS' => 'Y',
		],
		false,
		false,
		[
			'ID',
			'NAME',
			'PREVIEW_TEXT',
			'PROPERTY_' . IblockManager::PROPERTY_PROCESS,
			'PROPERTY_' . IblockManager::PROPERTY_ICON,
		]
	);
	while ($element = $rsElements->Fetch())
	{
		$processId = $enumMap[(int)$element['PROPERTY_' . IblockManager::PROPERTY_PROCESS . '_ENUM_ID']] ?? 0;
		if ($processId <= 0 || !isset($processIblocks[$processId]))
		{
			continue;
		}

		$iconFileId = (int)$element['PROPERTY_' . IblockManager::PROPERTY_ICON . '_VALUE'];

		$arResult['ITEMS'][] = [
			'ID' => (int)$element['ID'],
			'NAME' => (string)$element['NAME'],
			'DESCRIPTION' => (string)$element['PREVIEW_TEXT'],
			'PROCESS_ID' => $processId,
			'PROCESS_NAME' => $processIblocks[$processId]['NAME'],
			'ICON' => $iconFileId > 0 ? CFile::GetFileArray($iconFileId) : null,
			'URL' => IblockManager::buildProcessFormUrl($processId),
		];
	}

	if (defined('BX_COMP_MANAGED_CACHE'))
	{
		global $CACHE_MANAGER;
		$CACHE_MANAGER->RegisterTag('iblock_id_' . $iblockId);
	}

	$this->includeComponentTemplate();
	$this->endResultCache();
}

// служебные ссылки администратору — вне кеша
if ($arResult['IS_ADMIN'])
{
	$arResult['ADD_LINK'] = '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=' . $iblockId
		. '&type=' . IblockManager::IBLOCK_TYPE . '&lang=' . LANGUAGE_ID . '&ID=0';
}
