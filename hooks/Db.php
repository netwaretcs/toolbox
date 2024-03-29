//<?php namespace toolbox_IPS_Db_af8fb66cb531fdc3f6d8c6bc4f4048dd7;


use IPS\Db\Exception;
use IPS\toolbox\Profiler\Memory;
use IPS\toolbox\Profiler\Time;
use IPS\toolbox\Proxy\Generator\Db;
use IPS\toolbox\Proxy\Generator\Proxy;
use IPS\toolbox\Proxy\Proxyclass;
use Throwable;

use function class_exists;

if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

class toolbox_hook_Db extends _HOOK_CLASS_
{
    protected $dtkey;

    /**
     * @inheritdoc
     */
    public function addColumn($table, $definition)
    {
        parent::addColumn($table, $definition);
        if (class_exists(Proxy::class, true)) {
            Proxy::adjustModel($table);
        }
    }

    /**
     * @inheritdoc
     */
    public function createTable($data)
    {
        $return = parent::createTable($data);

        if (class_exists(Proxyclass::class, true)) {
            Db::i()->create();
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    protected function log($logQuery, $server = null)
    {
        $this->dtkey++;
        parent::log($logQuery, $server);
//        $this->log[] = array(
//            'query' => $logQuery,
//            'server' => $server,
//            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
//            'extra' => null,
//        );
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function preparedQuery($query, array $_binds, $read = false)
    {
        if (\IPS\QUERY_LOG && class_exists(Memory::class, true)) {
            $memory = new Memory();
            $time = new Time();
        }

        $parent = parent::preparedQuery($query, $_binds, $read);

        if (\IPS\QUERY_LOG && class_exists(Memory::class, true)) {
            $final = $time->end();
            $mem = $memory->end();
            $this->finalizeLog($final, $mem);
        }

        return $parent;
    }

    /**
     * @inheritdoc
     */
    public function query($query, $log = true, $read = false)
    {
        if (\IPS\QUERY_LOG && class_exists(Memory::class, true)) {
            $memory = new Memory();
            $time = new Time();
        }

        try {
            $parent = parent::query($query, $log, $read);
        }
        catch(\Exception | Throwable $e){
            throw new \IPS\Db\Exception( $this->error, $this->errno );
        }
        if (\IPS\QUERY_LOG && class_exists(Memory::class, true)) {
            $final = $time->end();
            $mem = $memory->end();
            $this->finalizeLog($final, $mem);
        }

        return $parent;
    }

    /**
     * @param $time
     * @param $mem
     */
    protected function finalizeLog($time, $mem)
    {
        $id = $this->dtkey - 1;
        $this->log[$id]['time'] = $time;
        $this->log[$id]['mem'] = $mem;
    }
}
