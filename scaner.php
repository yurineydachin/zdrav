<?php
require_once 'libs/curl.php';
require_once 'libs/profiler.php';
//require_once 'libs/proxies.php';

class ZdradScanner {

    const NO_DATA_RECIVED          = -1;
    const STATUS_UNKNOWN           = "UNKNOWN";
    const STATUS_COMPLETE          = "COMPLETE";
    const STATUS_ALREADY           = "ALREADY APPOINTMENT";
    const STATUS_BUSY              = 'TIME IS BUSY';
    const STATUS_NO_TIME           = 'NO TIME';
    const STATUS_TIME_NOT_OPENED   = 'TIME NOT OPENED';
    const STATUS_NO_AVAILABLE_TIME = 'NO AVAILABLE TIME';
    const STATUS_ERROR_LOADING     = 'ERROR LOADING';
    const STATUS_LOADING_OK        = 'LOADING OK';

    const COOKIE_FILE            = "/tmp/%s_cookie.txt";
    const DOMAIN_URL             = "https://uslugi.mosreg.ru/zdrav";
    const DOCTOR_APPOINTMENT_URL = "%s/doctor_appointment/doctor/%s/%s/%s?scenery=%d";
    const LOG_IN_URL             = "%s/doctor_appointment/submit";
    const SAVE_EMAIL_URL         = "%s/doctor_appointment/save_email";
    const SET_LAST_STEP_URL      = "%s/doctor_appointment/set_last_step";
    const CREATE_VISIT_URL       = "%s/doctor_appointment/create_visit";
    const CITY_LIST_URL          = "%s/doctor_appointment/city_list";
    const LPU_LIST_URL           = "%s/doctor_appointment/lpu_list/%s";
    const LPU_DOCTOR_TYPES_URL   = "%s/doctor_appointment/lpu?lpuCode=%s&scenery=%d";
    const DOCTORS_LIST_URL       = "%s/doctor_appointment/doctors_list?lpuCode=%s&specId=%d&days=14&scenery=%d";

    const AUTHORITION_TIMEOUT = 600;
    const MODEL_DELIMITER     = "|";
    const MODEL_BY_ID_FIELD   = "ByID";

    public $timePriority = array(
        '23:59' => 1,
    );
    public $email    = "yurineydachin@mail.ru";
    public $polis    = "141712440";
    public $birthday = "01.03.2016";
    public $lpuCode  = "0901052";
    public $doctor   = "1332";
    public $date     = "2016-04-11";
    public $scenery  = 1;
    public $debug    = false;

    protected $IsAuthorized = false;
    protected $LastAuthorized = null;

    protected $cookieFile;
    protected $curl;
    protected $proxy;
    protected $number = "";
    protected $res = array(
        'times' => array(),
        'create_visit' => array(
            'STATUS' => 'UNKNOWN'
        ),
    );
    protected $errors = array();

    public function getResult()
    {
        return $this->res;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function __construct($number = null)
    {
        $this->number = $number;
        $this->name = __CLASS__ . $this->number;
        $this->cookieFile = sprintf(self::COOKIE_FILE, $this->name);

        //$this->proxy = SCANNER_PROXY11;
        $this->curlInit();
    }

    protected function curlInit()
    {
        $this->curl = new Curl_Persistent();
        $this->proxy && $this->curl->setProxy($this->proxy);
        $this->curl->setTimeout(30, 30);
        $this->curl->setMaxReq(30);
        $this->curl->setHeaders(array(
            'User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64; rv:25.0) Gecko/20100101 Firefox/25.0',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
            'Accept-Encoding: identity',
        ));
        $this->curl->setOpt(array(
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR  => $this->cookieFile,
        ));
        $this->curl->DEBUG_CACHE = $this->debug;
        $this->curl->DEBUG_TMP .= $this->name . '_';
    }

    public function __destruct()
    {
        unset($this->curl);
    }

    public function runExample()
    {
        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start(TimeProfiler::total);

        $this->loadTimes();

        list($timeId, $timeRanges) = $this->getTheBestTime();
        if (is_numeric($timeId))
        {
            //$this->saveEmail();
            //$this->setLastStep();
            $this->login();
            $this->createVisit($timeId);
        }
        echo "\nResult: "; print_r($this->res);
        echo "\nErrors: "; print_r($this->errors);
        echo "\nSTATUS: "; print_r($this->res['create_visit']['STATUS']);

        $profiler->stop(TimeProfiler::total, $pKey);
        echo $profiler->getStat() . "\n";
        $profiler->clear();
    }

    public function loadTimes()
    {
        $url = sprintf(self::DOCTOR_APPOINTMENT_URL, self::DOMAIN_URL, $this->lpuCode, $this->doctor, $this->date, $this->scenery);

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start(TimeProfiler::curl);
        $page = $this->curl->get($url);
        $profiler->stop(TimeProfiler::curl, $pKey);

        return $this->parseTimes($page, array('id' => 'times'));
    }

    public function parseTimes($page, $info)
    {
        $id = $info['id'];

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start('parseTimes');

        if (! $page || ! ($json = json_decode($page, true)) || !isset($json['timeItems']) || !isset($json['date'])) {
            $this->errors[$id] = $page;
            return self::STATUS_NO_TIME;
        } elseif ($json['date'] != $this->date) {
            return self::STATUS_TIME_NOT_OPENED;
        }

        $availableTimes = array();
        foreach ($json['timeItems'] as $timeData)
        {
            if (! empty($timeData['attrs']['PosID']) && empty($timeData['attrs']['BusyFlag']) && !empty($timeData['time']) && !empty($timeData['attrs']['FlagAccess'])) {
                $availableTimes[$timeData['attrs']['PosID']] = $timeData['time'];
            }
        }
        if (count($json['timeItems']) > 0 && count($availableTimes) == 0) {
            return self::STATUS_NO_AVAILABLE_TIME;
        }
        $this->res[$id] = $availableTimes;

        $profiler->stop('parseTimes', $pKey);
        return self::STATUS_LOADING_OK;
    }

    public function login()
    {
        if ($this->IsAuthorized && $this->LastAuthorized && (time() - $this->LastAuthorized < self::AUTHORITION_TIMEOUT)) {
            return;
        }

        $url = sprintf(self::LOG_IN_URL, self::DOMAIN_URL);
        $params = array(
            "sPol"     => null,
            "nPol"     => $this->polis,
            "birthday" => $this->birthday,
            "scenery"  => $this->scenery,
        );

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start(TimeProfiler::curl);
        $page = $this->curl->get($url, $params);
        $profiler->stop(TimeProfiler::curl, $pKey);

        return $this->parseLogin($page, array('id' => 'login'));
    }

    public function parseLogin($page, $info)
    {
        $id = $info['id'];

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start('parseLogin');

        if (! $page || ! ($json = json_decode($page, true)) || ! isset($json['items']) || count($json['items']) == 0) {
            $this->errors[$id] = $page;
            return self::NO_DATA_RECIVED;
        }

        $this->IsAuthorized = true;
        $this->LastAuthorized = time();
        $this->res[$id] = array(
            'STATUS'          => 'AUTHORIZED',
            'last_authorized' => $this->LastAuthorized,
            'success'         => $json['success'],
            'count_items'     => count($json['items']),
        );

        $profiler->stop('parseLogin', $pKey);
        return self::STATUS_LOADING_OK;
    }

    public function getTheBestTime($times = null)
    {
        if (is_null($times) || count($times) === 0) {
            if (empty($this->res['times']) || count($this->res['times']) == 0) {
                return array(null, array());
            }
            $times = $this->res['times'];
        }

        $groups = array();
        foreach ($this->timePriority as $groupId)
        {
            $groups[$groupId] = array();
        }
        ksort($groups);

        foreach ($times as $timeId => $time) {
            foreach ($this->timePriority as $timeGroup => $groupId)
            {
                if ($time <= $timeGroup) {
                    $groups[$groupId][$timeId] = $time;
                    break;
                }
            }
        }
        foreach ($groups as $groupTimes) {
            if (count($times) > 0) {
                return array($this->getRandomTimeId($groupTimes), $groups);
            }
        }
        return array(null, $groups);
    }

    private function getRandomTimeId($times)
    {
        return array_rand($times);
    }

    public function createVisit($timeId)
    {
        $id = 'create_visit';
        $this->res[$id] = array('STATUS' => self::STATUS_UNKNOWN);

        if (empty($timeId)) {
            $this->errors[$id] = "No timeId: create_visit not sended";
            $this->res[$id]['STATUS'] = self::STATUS_NO_TIME;
            return $this->res[$id]['STATUS'];
        }
        $url = sprintf(self::CREATE_VISIT_URL, self::DOMAIN_URL);
        $params = array(
            "lpuCode" => $this->lpuCode,
            "DTTID"   => $timeId,
        );

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start(TimeProfiler::curl);
        $page = $this->curl->get($url, $params);
        $profiler->stop(TimeProfiler::curl, $pKey);

        return $this->parseCreateVisit($page, array('id' => $id));
    }

    public function parseCreateVisit($page, $info)
    {
        $id = $info['id'];

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start('parseCreateVisit');

        if (! $page || ! ($json = json_decode($page, true)) || empty($json['success'])) {
            $this->errors[$id] = $page;
            $this->res[$id]['STATUS'] = self::STATUS_ERROR_LOADING;
            return $this->res[$id]['STATUS'];
        }
        if (empty($json['items']) || empty($json['items']['CreateVisitResult'])) {
            $this->errors[$id] = $page;
            $this->res[$id]['STATUS'] = self::STATUS_ERROR_LOADING;
            return $this->res[$id]['STATUS'];
        }

        $this->res[$id] = $json;
        $this->res[$id]['STATUS'] = self::STATUS_UNKNOWN;

        if (strpos($json['items']['CreateVisitResult'], '<ErrorDescription>OK') !== false) {
            $this->res[$id]['STATUS'] = self::STATUS_COMPLETE;
        } elseif (strpos($json['items']['CreateVisitResult'], '<ErrorDescription>Данный пациент уже записан') !== false) {
            $this->res[$id]['STATUS'] = self::STATUS_ALREADY;
        } elseif (strpos($json['items']['CreateVisitResult'], '<ErrorDescription>У Вас уже есть запись') !== false) {
            $this->res[$id]['STATUS'] = self::STATUS_ALREADY;
        } elseif (strpos($json['items']['CreateVisitResult'], '<ErrorDescription>Выбранное время уже занято') !== false) {
            $this->res[$id]['STATUS'] = self::STATUS_BUSY;
        }

        $profiler->stop('parseCreateVisit', $pKey);
        return $this->res[$id]['STATUS'];
    }

    //-------------------- MODELS ---------------------

    public function loadCities()
    {
        $this->res = array(
            'city_list'        => array(),
            'lpu_list'         => array(),
            'lpu_doctor_types' => array(),
            'doctors'          => array(),
        );

        $url = sprintf(self::CITY_LIST_URL, self::DOMAIN_URL);

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start(TimeProfiler::curl);
        $page = $this->curl->get($url);
        $profiler->stop(TimeProfiler::curl, $pKey);

        return $this->parseCityList($page, array('id' => 'city_list'));
    }

    public function parseCityList($page, $info)
    {
        $id = $info['id'];

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start('parseCityList');

        if (! $page || ! ($json = json_decode($page, true)) || empty($json['items'])) {
            $this->errors[$id] = $page;
            return self::NO_DATA_RECIVED;
        }

        foreach ($json['items'] as $item)
        {
            $this->res[$id][$item['ID']] = $item;
        }

        $profiler->stop('parseCityList', $pKey);
        return self::STATUS_LOADING_OK;
    }

    public function loadHospitals($cityId)
    {
        $url = sprintf(self::LPU_LIST_URL, self::DOMAIN_URL, $cityId);

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start(TimeProfiler::curl);
        $page = $this->curl->get($url);
        $profiler->stop(TimeProfiler::curl, $pKey);

        return $this->parseLpuList($page, array('id' => 'lpu_list'));
    }

    public function parseLpuList($page, $info)
    {
        $id = $info['id'];

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start('parseLpuList');

        if (! $page || ! ($json = json_decode($page, true)) || empty($json['items'])) {
            $this->errors[$id] = $page;
            return self::NO_DATA_RECIVED;
        }

        foreach ($json['items'] as $item)
        {
            $this->res[$id][$item['LPUCODE']] = $item;
        }

        $profiler->stop('parseLpuList', $pKey);
        return self::STATUS_LOADING_OK;
    }

    public function loadDostorTypes($lpuCodes)
    {
        foreach ((array)$lpuCodes as $lpuCode)
        {
            $url = sprintf(self::LPU_DOCTOR_TYPES_URL, self::DOMAIN_URL, $lpuCode, $this->scenery);

            $profiler = TimeProfiler::instance();
            $pKey = $profiler->start(TimeProfiler::curl);
            $page = $this->curl->get($url);
            $profiler->stop(TimeProfiler::curl, $pKey);

            $this->parseDostorTypes($page, array('id' => 'lpu_doctor_types', 'lpuCode' => $lpuCode));
        }
    }

    public function parseDostorTypes($page, $info)
    {
        $id = $info['id'];

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start('parseDostorTypes');

        if (! $page || ! ($json = json_decode($page, true)) || empty($json['items'])) {
            $this->errors[$id] = $page;
            return self::NO_DATA_RECIVED;
        }

        $typeIds = array();
        foreach ($json['items'] as $item)
        {
            $this->res[$id][$item['Specialty']['ID']] = $item['Specialty'];
            $typeIds[] = $item['Specialty']['ID'];
        }

        $profiler->stop('parseDostorTypes', $pKey);
        $this->loadDoctors($info['lpuCode'], $typeIds);
        return self::STATUS_LOADING_OK;
    }

    public function loadDoctors($lpuCode, $typesIds)
    {
        foreach ((array)$typesIds as $typeId)
        {
            $url = sprintf(self::DOCTORS_LIST_URL, self::DOMAIN_URL, $lpuCode, $typeId, $this->scenery);

            $profiler = TimeProfiler::instance();
            $pKey = $profiler->start(TimeProfiler::curl);
            $page = $this->curl->get($url);
            $profiler->stop(TimeProfiler::curl, $pKey);

            $this->parseDoctors($page, array('id' => 'doctors', 'lpuCode' => $lpuCode, 'typeId' => $typeId));
        }
    }

    public function parseDoctors($page, $info)
    {
        $id = $info['id'];

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start('parseDoctors');

        if (! $page || ! ($json = json_decode($page, true)) || empty($json['items'])) {
            $this->errors[$id] = $page;
            return self::NO_DATA_RECIVED;
        }

        foreach ($json['items'] as $item)
        {
            $this->res[$id][$item['DocPost']['ID']] = array(
                "ID" =>         $item['DocPost']['ID'],
                "Family" =>     $item['DocPost']['Doctor']['Family'],
                "Name" =>       $item['DocPost']['Doctor']['Name'],
                "Patronymic" => $item['DocPost']['Doctor']['Patronymic'],
                "Uchastok" =>   $item['DocPost']['Uchastok'],
                "Room" =>       $item['DocPost']['Room'],
                "Post" =>       $item['DocPost']['Post'],
                "Sepatation" => $item['DocPost']['Sepatation'],
                "lpuCode" =>    $info['lpuCode'],
                "typeId" =>     $info['typeId'],
            );
        }

        $profiler->stop('parseDoctors', $pKey);
        return self::STATUS_LOADING_OK;
    }

    public function saveModesToFile($items, $filename)
    {
        $rows = array();
        if (count($items) > 0)
        {
            $item = reset($items);
            $rows[] = implode(self::MODEL_DELIMITER, array_keys($item)) . self::MODEL_DELIMITER . self::MODEL_BY_ID_FIELD;
            foreach ($items as $key => $item)
            {
                $item[self::MODEL_BY_ID_FIELD] = $key;
                $rows[] = implode(self::MODEL_DELIMITER, $item);
            }
            file_put_contents($filename, implode("\n", $rows));
            return true;
        }
        return false;
    }

    public function loadModesFromFile($modelName, $filename)
    {
        $this->res[$modelName] = array();
        if (file_exists($filename)) {
            $rows = file($filename);
            if (count($rows) > 1) {
                foreach ($rows as $i => $row)
                {
                    if ($i == 0)
                    {
                        $keys = explode(self::MODEL_DELIMITER, $rows[0]);
                        $byIdField = array_pop($keys);
                    }
                    else
                    {
                        $values = explode(self::MODEL_DELIMITER, $rows[$i]);
                        $byId = array_pop($values);
                        $model = array();
                        foreach ($keys as $j => $key)
                        {
                            $model[$key] = $values[$j];
                        }
                        $this->res[$modelName][$byId] = $model;
                    }
                }
            } else {
                $this->errors[$modelName] = "No rows: ".count($rows);
            }
        } else {
            $this->errors[$modelName] = "No file: ".$filename;
        }
    }

    //-------------------- NOT USED--------------------

    public function saveEmail()
    {
        $url = sprintf(self::SAVE_EMAIL_URL, self::DOMAIN_URL);
        $params = array(
            "email" => $this->email,
        );

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start(TimeProfiler::curl);
        $page = $this->curl->get($url, $params);
        $profiler->stop(TimeProfiler::curl, $pKey);

        return $this->parseSaveEmail($page, array('id' => 'save_email'));
    }

    public function parseSaveEmail($page, $info)
    {
        $id = $info['id'];

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start('parseSaveEmail');

        if (! $page || ! ($json = json_decode($page, true)) || empty($json['success'])) {
            $this->errors[$id] = $page;
            return self::NO_DATA_RECIVED;
        }

        $this->res[$id] = $json;

        $profiler->stop('parseSaveEmail', $pKey);
        return $this->res;
    }

    public function setLastStep()
    {
        if (empty($this->res['times']) || count($this->res['times']) == 0) {
            return self::STATUS_NO_TIME;
        }

        list($timeId, $timeRanges) = $this->getTheBestTime();
        if (empty($timeId)) { 
            $this->errors['set_last_step'] = "No timeId: set_last_step not sended";
            return self::STATUS_NO_TIME;
        }
        $url = sprintf(self::SET_LAST_STEP_URL, self::DOMAIN_URL);
        $params = array(
            "lpuCode" => $this->lpuCode,
            "DTTID"   => $timeId,
        );

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start(TimeProfiler::curl);
        $page = $this->curl->get($url, $params);
        $profiler->stop(TimeProfiler::curl, $pKey);

        return $this->parseSetLastStep($page, array('id' => 'set_last_step'));
    }

    public function parseSetLastStep($page, $info)
    {
        $id = $info['id'];

        $profiler = TimeProfiler::instance();
        $pKey = $profiler->start('parseSetLastStep');

        if (! $page || ! ($json = json_decode($page, true)) || empty($json['success'])) {
            $this->errors[$id] = $page;
            return self::NO_DATA_RECIVED;
        }

        $this->res[$id] = $json;

        $profiler->stop('parseSetLastStep', $pKey);
        return $this->res;
    }
}
