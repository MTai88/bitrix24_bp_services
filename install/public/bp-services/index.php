<?php
/**
 * Публичная страница «Сервисы бизнес-процессов»: /bp-services/
 * Плитка сервисов для авторизованных сотрудников; гости перенаправляются
 * на страницу входа. Создаётся установщиком модуля mtai.bpservices
 * (install/public → корень сайта).
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

global $APPLICATION, $USER;

if (!$USER->IsAuthorized())
{
	LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam('', ['backurl'])));
}

$APPLICATION->SetTitle('Сервисы бизнес-процессов');
$APPLICATION->IncludeComponent(
	'mtai:bpservices.grid',
	'',
	[
		'CACHE_TIME' => 36000,
	],
	false
);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
