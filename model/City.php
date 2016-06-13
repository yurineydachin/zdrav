<?php
require_once 'Model.php';

class City extends Model {
    const URL = "%s/doctor_appointment/city_list";

    static public $modelName = "city_list";
    protected $idField = 'ID';
    protected $fields = array(
        "ID",
        "NAME",
        "OKATO",
        "count",
    );
}
