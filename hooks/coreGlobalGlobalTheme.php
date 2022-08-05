//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\Data\Store;
use IPS\Output;
use IPS\Request;
use IPS\Settings;
use IPS\Theme;
use IPS\toolbox\Application;

use const IPS\CACHING_LOG;

if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    exit;
}

class toolbox_hook_coreGlobalGlobalTheme extends _HOOK_CLASS_
{
    /* !Hook Data - DO NOT REMOVE */
    public static function hookData()
    {
        if (\is_callable('parent::hookData')) {
            return parent::hookData();
        }
        return [];
    }

    /* End Hook Data */

    public function includeCSS()
    {
            $css = Output::i()->cssFiles;
            $caching = Theme::i()->css('styles/caching_log.css', 'core', 'front');
            $cachingCss = array_pop($caching);
            if (CACHING_LOG && $key = array_search($cachingCss, $css)) {
                unset(Output::i()->cssFiles[$key]);
            }
            if (\IPS\QUERY_LOG && !Request::i()->isAjax()) {
                Output::i()->cssFiles = array_merge(
                    Output::i()->cssFiles,
                    Theme::i()->css('profiler.css', 'toolbox', 'front')
                );
                $query = Theme::i()->css('styles/query_log.css', 'core', 'front');
                $queryCss = array_pop($query);
                if ($key = array_search($queryCss, $css)) {
                    unset(Output::i()->cssFiles[$key]);
                }

                if (Settings::i()->dtprofiler_enabled_css) {
                    Store::i()->dtprofiler_css = Output::i()->cssFiles;
                }
            }
        if ( \is_callable('parent::includeCSS') )
        {
            return \call_user_func_array( 'parent::' . __FUNCTION__, \func_get_args() );
        }

    }

    public function includeJS()
    {
        $data = '';
        if (!Request::i()->isAjax()) {
            $debugjs = Output::i()->js('global_debug.js', 'toolbox', 'global');
            $js = '';
            foreach($debugjs as $j){
                $js .= '<script type="text/javascript" src="'.$j.'?v='.\IPS\Output\Javascript::javascriptCacheBustKey().'" data-ips></script>';
            }
            $vals = json_decode(Settings::i()->dtprofiler_console_replacements,true);
            $replacements = json_encode(array_combine(array_values($vals),array_values($vals)));
            $canUse = Settings::i()->dtprofiler_use_console ? 1 : 0;
            $canUse = \IPS\QUERY_LOG ? $canUse : 0;
            $canReplace = Settings::i()->dtprofiler_use_console ? 1 : 0;
            $cjEditor = \IPS\DEV_WHOOPS_EDITOR;
            $cjBaseUrl = \IPS\Settings::i()->base_url;
            $cjAppPath = Application::getRootPath('toolbox');
            $cjDebug = \IPS\IN_DEV === true || \IPS\DEBUG_JS === true ? 1 : 0;

            $data = <<<EOF
<script type="text/javascript">
    var dtProfilerUseConsole = {$canUse};
    var dtProfilerEditor = '{$cjEditor}';
    var dtProfilerReplaceConsole = {$canReplace};
    var dtProfilerBaseUrl = '{$cjBaseUrl}';
    var dtProfilerAppPath = '{$cjAppPath}';
    var dtProfilerDebug = '{$cjDebug}'; 
    var dtProfilerReplacements = {$replacements};
    
</script>
{$js}
EOF;

            if (defined('DT_NODE') && DT_NODE) {
                Application::addJs(['front_socket']);
            }

            Application::addJs(['global_main'],'global');
            Application::addJs(['global_proxy'], 'global');

            if (\IPS\QUERY_LOG && !Request::i()->isAjax()) {
                Application::addJs(['front_profiler']);
                if (Settings::i()->dtprofiler_enabled_js) {
                    Store::i()->dtprofiler_js = Output::i()->jsFiles;
                }

                if (Settings::i()->dtprofiler_enabled_jsvars) {
                    Store::i()->dtprofiler_js_vars = Output::i()->jsVars;
                }
            }
        }

        if ( \is_callable('parent::includeJS') )
        {
            $data .= \call_user_func_array( 'parent::' . __FUNCTION__, \func_get_args() );
            return $data;
        }
    }
}
