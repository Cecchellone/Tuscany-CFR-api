<?php

namespace CFR;

// header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require '../../vendor/autoload.php';

$type = trim(htmlspecialchars($_GET["type"]), ' ');
$ids = array_values(array_map(fn ($x) => trim(htmlspecialchars($x), ' '), $_GET["id"]));

const accepted_types = ['idro', 'pluvio', 'termo', 'anemo', 'igro', 'radio'];

if (!in_array(strtolower($type), accepted_types)) {
    echo ('Station type does not exists!');
    http_response_code(400);
    die;
}

$slt = new SLT\StationList();
$infos = $slt->getStationInfo($type, $ids);


if (count($infos) < count($ids)) {
    echo ('A station ID does not match!');
    http_response_code(404);
    die;
}

$json = [];

for ($i = 0; $i < count($ids); $i++) {
    $data = Null;
    switch ($type) {
        case 'idro':
            $cfr = new SDT\HYDRO($ids[$i]);
            $data = $cfr->getStationData();
            break;

        case 'pluvio':
            $cfr = new SDT\PLUVIO($ids[$i]);
            $data = $cfr->getStationData();
            break;

        case 'termo':
            $cfr = new SDT\THERMO($ids[$i]);
            $data = $cfr->getStationData();
            break;

        case 'anemo':
            $cfr = new SDT\ANEMO($ids[$i]);
            $data = $cfr->getStationData();
            break;

        case 'igro':
            $cfr = new SDT\HYGRO($ids[$i]);
            $data = $cfr->getStationData();
            break;

        case 'radio':
            $cfr = new SDT\RADIO($ids[$i]);
            $data = $cfr->getStationData();
            break;

        default:
            http_response_code(404);
            die;
    }

    $json[$ids[$i]] = [
        'id' => $infos[$i]['id'],
        'type' => $cfr->type,
        'name' => $infos[$i]['name'],
        'province' => $infos[$i]['province'],
        'data' => $data
    ];
}
http_response_code(200);

echo (json_encode($json, JSON_PRETTY_PRINT));
