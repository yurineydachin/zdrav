<?php
require_once 'Model.php';

class Lpu extends Model {
    const URL = "%s/doctor_appointment/lpu_list/%s";

    static public $modelName = "lpu_doctor_types";
    protected $idField = 'LPUCODE';
    protected $fields = array(
        "ID",
        "LPUCODE",
        "EMAIL",
        "SITEURL",
        "NAME",
        "IP",
        "ADDRESS",
        "PHONE",
        "ACCESSIBILITY",
        "CHILDREN",
        "CITY",
        "isWaitingList",
        "isCallDocHome",
        "latitude",
        "longitude",
    );
}
