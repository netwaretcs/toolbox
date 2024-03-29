//<?php namespace toolbox_IPS_Application_BuilderFilter_a2742a9970230ee38a54a91c24e23362b;

use Exception;
use IPS\Applicaiton\BuildFilter;
use IPS\Application;
use IPS\Request;
use IPS\Settings;

use IPS\toolbox\DevCenter\extensions\toolbox\DevCenter\Headerdoc\Headerdoc;

use RecursiveFilterIterator;
use SplFileInfo;

use function array_merge;
use function count;
use function in_array;
use function is_array;
use function json_decode;
use function method_exists;

if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * @mixin RecursiveFilterIterator
 * @mixin _HOOK_CLASS_
 * @mixin BuildFilter
 * @mixin SplFileInfo
 */
class toolbox_hook_BuilderFilter extends _HOOK_CLASS_
{

    public function accept()
    {
        if ($this->isFile()) {
            $skip = [];
            $toSKip = json_decode(Settings::i()->dtdevplus_skip_files, true);

            if (is_array($toSKip) && count($toSKip)) {
                $skip = $toSKip;
            }
            $skip[] = '.gitignore';
            $skip[] = 'composer.lock';
            $skip[] = 'README.md';
            $skip[] = 'composer.json';
            try {
                $appKey = Request::i()->appKey;
                $app = Application::load($appKey);

                foreach ($app->extensions('toolbox', 'Headerdoc', true) as $class) {
                    if (method_exists($class, 'filesSkip')) {
                        $class->filesSkip($skip);
                    }
                }
            } catch (Exception $e) {
            }

            return !in_array($this->getFilename(), $skip, true);
        }

        return parent::accept();
    }

    protected function getDirectoriesToIgnore()
    {
        $skip = parent::getDirectoriesToIgnore();
        $appKey = Request::i()->appKey;

        if (in_array($appKey, \IPS\toolbox\Application::$toolBoxApps, true)) {
            foreach ($skip as $key => $val) {
                if ($val === 'dev') {
                    unset($skip[$key]);
                }
            }
        }
        $toSKip = json_decode(Settings::i()->dtdevplus_skip_dirs, true);

        if (is_array($toSKip) && count($toSKip)) {
            $skip = array_merge($skip, $toSKip);
        }

        try {
            $app = Application::load($appKey);

            /* @var Headerdoc $class */
            foreach ($app->extensions('toolbox', 'Headerdoc', true) as $class) {
                if (method_exists($class, 'dirSkip')) {
                    $class->dirSkip($skip);
                }
            }
        } catch (Exception $e) {
        }

        return $skip;
    }
}
