<?php

/**
 * Конфигурация ajax-контроллеров модуля.
 *
 * Экшены доступны как
 *   /bitrix/services/main/ajax.php?action=mtai:bpservices.file.upload
 *   /bitrix/services/main/ajax.php?action=mtai:bpservices.file.revert
 * и разрешаются в класс \Mtai\Bpservices\Controller\File.
 */

return [
	'controllers' => [
		'value' => [
			'defaultNamespace' => '\\Mtai\\Bpservices\\Controller',
			'namespaces' => [
				'\\Mtai\\Bpservices\\Controller' => 'file',
			],
		],
		'readonly' => true,
	],
];
