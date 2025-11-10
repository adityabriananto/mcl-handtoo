<?php

$prefixMap = [
    'JNE' => ['CM', 'JT', 'TG', '151', '881', 'JY'],
    'ShopeeXpress' => ['SPX'],
    'SICEPAT' => ['004', '002', '005', 'TKP5'],
    'SAPX' => ['TKP4'],
    'JNT Cargo' => ['570'],
    'JNT Express' => ['JX'],
    'Anteraja' => ['TSA'],
    'ID Express' => ['TKP8'],
    'LEX ID' => ['LID'],
    'LEX' => ['LXAD', 'NLIDAP', 'JZ'],
    'GTL' => ['GTL'],
    'REX' => ['2222'],
];

return [
    'prefix_map' => $prefixMap,
    'all_carriers' => array_keys($prefixMap) + ['Other 3PL'],
    'cancelled_awbs' => ['TKP50001', 'LID99999', 'ZZSAMPLE'],
];
