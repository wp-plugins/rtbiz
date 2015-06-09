<?php

// autoload.php generated by Composer

//require_once __DIR__ . '/composer' . '/autoload_real.php';
//
//return ComposerAutoloaderInitf38d4ccc54cb1522431206e101b4c1ea::getLoader();

namespace Rt_Helpdesk_Zend;
class MyAutoloader
{
	public static function load($className)
	{
		$className= str_replace("\\" ,"/" , $className);
		if (file_exists(__DIR__ .'/'. $className . '.php'))
			require __DIR__ .'/'. $className . '.php';
	}
}
spl_autoload_register(__NAMESPACE__ . "\\MyAutoloader::load");
