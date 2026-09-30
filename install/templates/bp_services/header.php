<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}
/**
 * Лёгкий публичный шаблон для раздела /bp-services/ (модуль mtai.bpservices).
 * Назначается правилом CSite::InDir('/bp-services/') в b_site_template,
 * чтобы страница не рендерилась внутри тяжёлой CRM-оболочки Air.
 *
 * @global CMain $APPLICATION
 * @global CUser $USER
 */
?><!DOCTYPE html>
<html lang="<?= LANGUAGE_ID ?>">
<head>
	<meta charset="<?= LANG_CHARSET ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><? $APPLICATION->ShowTitle() ?></title>
	<? $APPLICATION->ShowHead(); ?>
	<link rel="stylesheet" href="<?= SITE_TEMPLATE_PATH ?>/template_styles.css">
</head>
<body>
<? $APPLICATION->ShowPanel(); ?>
<header class="bpsp-header">
	<div class="bpsp-container bpsp-header__inner">
		<a class="bpsp-logo" href="/bp-services/">
			<span class="bpsp-logo__badge">БП</span>
			<span class="bpsp-logo__text">Сервисы бизнес-процессов</span>
		</a>
		<nav class="bpsp-nav">
			<a href="/news/">Новости</a>
			<a href="/">Портал</a>
		</nav>
	</div>
</header>
<main class="bpsp-main">
	<div class="bpsp-container">
		<h1 class="bpsp-title"><? $APPLICATION->ShowTitle() ?></h1>
