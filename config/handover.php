<?php

$prefixMap = [
    'JNE' => ['CM', 'JT', 'TG', '151', '881', 'JY','JNEB'],
    'ShopeeXpress' => ['SPX'],
    'SICEPAT' => ['004', '002', '005', 'TKP5'],
    'SAPX' => ['TKP4','TKP5'],
    'JNT Cargo' => ['570','320'],
    'JNT Express' => ['JX','JO'],
    'Anteraja' => ['TSA'],
    'ID Express' => ['TKP8'],
    'LEX ID' => ['LID'],
    'LEX' => ['LXAD', 'NLIDAP', 'JZ','JNAP'],
    'GTL' => ['GTL'],
    'REX' => ['2222'],
    'SF EXPRESS' => ['ZLM'],
    'Ninjavan Standard' => ['SHP'],
];

return [
    'prefix_map' => $prefixMap,
    'all_carriers' => array_keys($prefixMap) + ['Other 3PL'],
    'cancelled_awbs' => ['TKP50001', 'LID99999', 'ZZSAMPLE'],
];
