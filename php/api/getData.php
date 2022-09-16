<?php

namespace CFR;

// header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require '../../vendor/autoload.php';

$type = trim(htmlspecialchars($_GET["type"]), ' ');
$id = trim(htmlspecialchars($_GET["id"]), ' ');

const accepted_types = ['idro', 'pluvio', 'termo', 'anemo', 'igro', 'radio'];

if (!in_array(strtolower($type), accepted_types)) {
    echo ('Station type does not exists!');
    http_response_code(400);
    die;
}

$slt = new SLT\StationList();
$info = $slt->getStationInfo($type, $id);

if ($info === NULL) {
    echo ('Station ID does not match!');
    http_response_code(404);
    die;
}

$data = Null;
switch ($type) {
    case 'idro':
        $cfr = new SDT\HYDRO($id);
        $data = $cfr->getStationData();
        break;

    case 'pluvio':
        $cfr = new SDT\PLUVIO($id);
        $data = $cfr->getStationData();
        break;

    case 'termo':
        $cfr = new SDT\THERMO($id);
        $data = $cfr->getStationData();
        break;

    case 'anemo':
        $cfr = new SDT\ANEMO($id);
        $data = $cfr->getStationData();
        break;
    
    case 'igro':
        $cfr = new SDT\HYGRO($id);
        $data = $cfr->getStationData();
        break;

    case 'radio':
        $cfr = new SDT\RADIO($id);
        $data = $cfr->getStationData();
        break;

    default:
        http_response_code(404);
        die;
}

$json = [
    'id'=> $info['id'],
    'type'=> $cfr->type,
    'name'=> $info['name'],
    'province'=> $info['province'],
    'data'=>$data
];

http_response_code(200);

echo (json_encode($json, JSON_PRETTY_PRINT));
