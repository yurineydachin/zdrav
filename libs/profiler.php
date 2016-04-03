<?php
class TimeProfiler
{
    const total   = 'total';
    const curl    = 'curl';
    const parsing = 'parsing';
    const mapping = 'mapping';

    private $metrics = array(
        self::total,
        self::curl,
        self::parsing,
        self::mapping,
    );

    private $event = array();
    private $_enabled = true;

    private static $single = null;
    private static $inc = 0;

    static public function instance()
    {
        if (is_null(self::$single)) {
            self::$single = new self();
        }
        return self::$single;
    }

    public function clear()
    {
        self::$single = null;
        self::$inc = 0;
        unset($this->event);
    }

    public function start($metric)
    {
        if (! $this->_enabled) {
            return false;
        }
        if (! isset($this->event[$metric])) {
            $this->event[$metric] = array();
        }

        $key = self::$inc++;
        $this->event[$metric][$key] = array(
            'ts' => microtime(true),
            'te' => null,
        );
        return $key;
    }

    public function stop($metric, $key, $timeStart = null)
    {
        if (! $this->_enabled) {
            return false;
        }
        if (! isset($this->event[$metric])) {
            $this->event[$metric] = array();
        }
        if (! isset($this->event[$metric][$key]))
        {
            $this->event[$metric][$key] = array(
                'ts' => $timeStart,
                'te' => microtime(true),
            );
        }
        else
        {
            $this->event[$metric][$key]['te'] = microtime(true);
        }
        return $this;
    }

    public function getMetricStat($metric)
    {
        if (! isset($this->event[$metric]))
        {
            return array(
                'count'  => 0,
                'time'   => 0,
            );
        }

        $time = 0;
        $lastTime = 0;
        foreach ($this->event[$metric] as $event)
        {
            if (! is_null($event['ts']) && ! is_null($event['te']) && $lastTime < $event['te']) {
                $time += $event['te'] - $event['ts'];
                $lastTime = $event['te']; // не учитываю в суммированном времени под запросы
            }
        }
        return array(
            'count'  => count($this->event[$metric]),
            'time'   => $time,
        );
    }

    public function getLogString()
    {
        $res = '';
        foreach ($this->metrics as $metric)
        {
            $data = $this->getMetricStat($metric);
            $res .= sprintf("%d %01.3f ", $data['count'], $data['time']);
        }
        return $res;
    }

    public function getLogTimesString()
    {
        $res = '';
        foreach ($this->metrics as $metric)
        {
            $data = $this->getMetricStat($metric);
            $res .= sprintf("%s: %01.3f  ", $metric, $data['time']);
        }
        return $res;
    }

    public function getStat()
    {
        $res = '';
        foreach (array_keys($this->event) as $metric)
        {
            $data = $this->getMetricStat($metric);
            $res .= sprintf("%20.20s %4d %6.3f\n", $metric, $data['count'], $data['time']);
        }
        return $res;
    }

    public function setEnabled($enabled = true)
    {
        $this->_enabled = (bool) $enabled;
    }
}
