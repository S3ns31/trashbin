<?php
/*
 * This file is part of trashbin.
 *
 * (c) 2010 Igor Wiedler
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require dirname(__FILE__) . '/bootstrap.php';
require dirname(__FILE__) . '/lib/support.php';
require dirname(__FILE__) . '/lib/FileNotFoundException.php';
require dirname(__FILE__) . '/lib/RedirectException.php';

// set up twig
$loader = new Twig_Loader_Filesystem(dirname(__FILE__) . '/views');
$twig = new Twig_Environment($loader, array(
	'cache' => dirname(__FILE__) . '/cache/twig',
	'debug' => $config['twig']['debug'],
));

try
{
	$index_url = '.';
	$create_url = '?q=create';
	$view_url = '%s';

	$languages = get_languages();
	
	$action = isset($_GET['q']) ? (string) $_GET['q'] : 'index';
	if (!in_array($action, array('index', 'create', 'view')))
	{
		throw new FileNotFoundException('page not found');
	}

	switch ($action)
	{
		case 'index':
			$template = $twig->loadTemplate('index.html');

			$template->display(array(
				'create_url'	=> $create_url,
				'languages'	=> $languages,
				'footer'	=> $config['global']['footer'],
			));
		break;

		case 'create':
			if (!isset($_POST['submit']))
			{
				throw new RedirectException($index_url);
			}

			$paste = new Paste();
			$paste->content = isset($_POST['content']) ? preg_replace('#\\r?\\n#', "\n", (string) $_POST['content']) : '';

			if (trim($paste->content) === '')
			{
				$error_msg = 'you must enter some content';

				$template = $twig->loadTemplate('index.html');

				$template->display(array(
					'create_url'	=> $create_url,
					'languages'	=> $languages,
					'error_msg'	=> $error_msg,
					'paste'		=> $paste,
					'footer'	=> $config['global']['footer'],
				));

				return;
			}

			$language = isset($_POST['language']) ? (string) $_POST['language'] : '';
			if (language_exists($language))
			{
				$paste->language = $language;
			}

			$paste->save();

			throw new RedirectException(sprintf($view_url, $paste->hash_id));
		break;

		case 'view':
			$hash_id = isset($_GET['id']) ? (string) $_GET['id'] : '';
			$paste = Doctrine_Core::getTable('Paste')->findOneByHashId($hash_id);

			if (!$paste)
			{
				throw new FileNotFoundException('paste not found');
			}

			$template = $twig->loadTemplate('view.html');

			$template->display(array(
				'paste'		=> $paste,
				'index_url'	=> $index_url,
				'footer'	=> $config['global']['footer'],
			));
		break;
	}
}
catch (RedirectException $e)
{
	redirect($e->getMessage());
}
catch (FileNotFoundException $e)
{
	file_not_found();

	$template = $twig->loadTemplate('error.html');

	$template->display(array(
		'index_url'	=> $index_url,
		'message'	=> $e->getMessage(),
		'footer'	=> $config['global']['footer'],
	));
}
catch (Exception $e)
{
	$template = $twig->loadTemplate('error.html');

	$template->display(array(
		'index_url'	=> $index_url,
		'message'	=> $e->getMessage(),
		'footer'	=> $config['global']['footer'],
	));
}
