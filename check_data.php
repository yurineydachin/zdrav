<?php
require_once 'model/Doctor.php';
require_once 'model/DoctorType.php';
require_once 'model/Lpu.php';
require_once 'model/City.php';

$modelCity       = new City();
$modelLpu        = new Lpu();
$modelDoctorType = new DoctorType();
$modelDoctor     = new Doctor();

echo "\nResult City: "; print_r(count($modelCity->loadFromFile()->getAllItems()));
echo "\nResult Lpu: "; print_r(count($modelLpu->loadFromFile()->getAllItems()));
echo "\nResult DoctorType: "; print_r(count($modelDoctorType->loadFromFile()->getAllItems()));
echo "\nResult Doctor: "; print_r(count($modelDoctor->loadFromFile()->getAllItems()));

echo "\nErrors City: "; print_r($modelCity->getErrors());
echo "\nErrors Lpu: "; print_r($modelLpu->getErrors());
echo "\nErrors DoctorType: "; print_r($modelDoctorType->getErrors());
echo "\nErrorsResult Doctor: "; print_r($modelDoctor->getErrors());

echo "\n\n";
