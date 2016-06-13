<?php
require_once 'Model.php';

class DoctorType extends Model {
    const URL = "%s/doctor_appointment/lpu?lpuCode=%s&scenery=%d";

    static public $modelName = "lpu_doctor_types";
    protected $idField = 'ID';
    protected $fields = array(
        "ID",
        "Name",
        "Code",
    );
}
