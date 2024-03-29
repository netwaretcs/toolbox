<?php

/**
 * @brief       Sources Class
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Dev Toolbox
 * @since       4.0.1
 * @version     -storm_version-
 */


namespace IPS\toolbox\modules\front\devcenter;

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\Application;
use IPS\Dispatcher\Controller;
use IPS\Output;
use IPS\Request;
use IPS\Theme;
use IPS\toolbox\DevCenter\Sources;

use function _p;
use function array_search;
use function class_exists;
use function defined;
use function header;
use function interface_exists;
use function trait_exists;


if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * sources
 */
class _sources extends Controller
{
    use \IPS\toolbox\Shared\Sources;

    /**
     * @var Application
     */
    protected $application;

    /**
     * @var Sources
     */
    protected $elements;

    protected $front = true;

    public function execute()
    {
        $app = (string)Request::i()->appKey;
        if (!$app) {
            $app = 'core';
        }
        $this->application = Application::load($app);
        $this->elements = new Sources($this->application);
        parent::execute();
    }


}
