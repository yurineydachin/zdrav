<?php
require_once 'Model.php';

class Doctor extends Model {
    const URL = "%s/doctor_appointment/doctors_list?lpuCode=%s&specId=%d&days=14&scenery=%d";

    static public $modelName = "doctors";
    protected $idField = 'ID';
    protected $fields = array(
        "ID",
        "Family",
        "Name",
        "Patronymic",
        "Uchastok",
        "Room",
        "Post",
        "Sepatation",
        "lpuCode",
        "typeId",
    );
}
