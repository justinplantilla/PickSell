<?php

/*
| Starting links between province hubs (PSGC province/district codes): provinces that share a land
| border, plus the main ferry crossings, so every hub can reach every other one by relay. Hours are
| rough truck (or ferry, incl. waiting) estimates for HQ to refine under Province links.
| [province A, province B, 'land'|'sea', hours]
*/

return [
    // Ilocos, Cordillera, Cagayan Valley
    ['012800000', '012900000', 'land', 2],   // Ilocos Norte – Ilocos Sur
    ['012800000', '148100000', 'land', 4],   // Ilocos Norte – Apayao
    ['012800000', '140100000', 'land', 4],   // Ilocos Norte – Abra
    ['012800000', '021500000', 'land', 5],   // Ilocos Norte – Cagayan
    ['012900000', '013300000', 'land', 2],   // Ilocos Sur – La Union
    ['012900000', '140100000', 'land', 2],   // Ilocos Sur – Abra
    ['012900000', '144400000', 'land', 4],   // Ilocos Sur – Mountain Province
    ['012900000', '141100000', 'land', 4],   // Ilocos Sur – Benguet
    ['013300000', '015500000', 'land', 2],   // La Union – Pangasinan
    ['013300000', '141100000', 'land', 2],   // La Union – Benguet
    ['015500000', '036900000', 'land', 2],   // Pangasinan – Tarlac
    ['015500000', '034900000', 'land', 3],   // Pangasinan – Nueva Ecija
    ['015500000', '037100000', 'land', 3],   // Pangasinan – Zambales
    ['015500000', '141100000', 'land', 3],   // Pangasinan – Benguet
    ['015500000', '025000000', 'land', 4],   // Pangasinan – Nueva Vizcaya
    ['020900000', '021500000', 'sea', 12],   // Batanes – Cagayan
    ['021500000', '148100000', 'land', 3],   // Cagayan – Apayao
    ['021500000', '143200000', 'land', 3],   // Cagayan – Kalinga
    ['021500000', '023100000', 'land', 3],   // Cagayan – Isabela
    ['023100000', '143200000', 'land', 3],   // Isabela – Kalinga
    ['023100000', '144400000', 'land', 4],   // Isabela – Mountain Province
    ['023100000', '142700000', 'land', 4],   // Isabela – Ifugao
    ['023100000', '025000000', 'land', 3],   // Isabela – Nueva Vizcaya
    ['023100000', '025700000', 'land', 2],   // Isabela – Quirino
    ['023100000', '037700000', 'land', 6],   // Isabela – Aurora
    ['025000000', '025700000', 'land', 2],   // Nueva Vizcaya – Quirino
    ['025000000', '142700000', 'land', 3],   // Nueva Vizcaya – Ifugao
    ['025000000', '141100000', 'land', 4],   // Nueva Vizcaya – Benguet
    ['025000000', '034900000', 'land', 3],   // Nueva Vizcaya – Nueva Ecija
    ['025000000', '037700000', 'land', 5],   // Nueva Vizcaya – Aurora
    ['025700000', '037700000', 'land', 5],   // Quirino – Aurora
    ['140100000', '148100000', 'land', 4],   // Abra – Apayao
    ['140100000', '143200000', 'land', 4],   // Abra – Kalinga
    ['140100000', '144400000', 'land', 4],   // Abra – Mountain Province
    ['148100000', '143200000', 'land', 3],   // Apayao – Kalinga
    ['143200000', '144400000', 'land', 3],   // Kalinga – Mountain Province
    ['144400000', '142700000', 'land', 2],   // Mountain Province – Ifugao
    ['144400000', '141100000', 'land', 3],   // Mountain Province – Benguet
    ['142700000', '141100000', 'land', 4],   // Ifugao – Benguet

    // Central Luzon
    ['030800000', '035400000', 'land', 2],   // Bataan – Pampanga
    ['030800000', '037100000', 'land', 2],   // Bataan – Zambales
    ['031400000', '035400000', 'land', 2],   // Bulacan – Pampanga
    ['031400000', '034900000', 'land', 3],   // Bulacan – Nueva Ecija
    ['031400000', '037700000', 'land', 5],   // Bulacan – Aurora
    ['031400000', '045600000', 'land', 5],   // Bulacan – Quezon
    ['031400000', '045800000', 'land', 3],   // Bulacan – Rizal
    ['034900000', '036900000', 'land', 2],   // Nueva Ecija – Tarlac
    ['034900000', '035400000', 'land', 2],   // Nueva Ecija – Pampanga
    ['034900000', '037700000', 'land', 4],   // Nueva Ecija – Aurora
    ['035400000', '036900000', 'land', 2],   // Pampanga – Tarlac
    ['035400000', '037100000', 'land', 3],   // Pampanga – Zambales
    ['036900000', '037100000', 'land', 3],   // Tarlac – Zambales
    ['037700000', '045600000', 'land', 6],   // Aurora – Quezon

    // Metro Manila districts
    ['133900000', '137400000', 'land', 1],   // Capital – Eastern
    ['133900000', '137500000', 'land', 1],   // Capital – Northern
    ['133900000', '137600000', 'land', 1],   // Capital – Southern
    ['137400000', '137500000', 'land', 1],   // Eastern – Northern
    ['137400000', '137600000', 'land', 1],   // Eastern – Southern
    ['137400000', '045800000', 'land', 1],   // Eastern – Rizal
    ['137400000', '031400000', 'land', 2],   // Eastern – Bulacan
    ['137500000', '031400000', 'land', 1],   // Northern – Bulacan
    ['137600000', '042100000', 'land', 2],   // Southern – Cavite
    ['137600000', '043400000', 'land', 2],   // Southern – Laguna
    ['137600000', '045800000', 'land', 2],   // Southern – Rizal

    // CALABARZON, MIMAROPA
    ['041000000', '042100000', 'land', 2],   // Batangas – Cavite
    ['041000000', '043400000', 'land', 2],   // Batangas – Laguna
    ['041000000', '045600000', 'land', 3],   // Batangas – Quezon
    ['041000000', '175200000', 'sea', 3],    // Batangas – Oriental Mindoro (Calapan)
    ['041000000', '175100000', 'sea', 4],    // Batangas – Occidental Mindoro (Abra de Ilog)
    ['041000000', '175900000', 'sea', 10],   // Batangas – Romblon
    ['041000000', '175300000', 'sea', 20],   // Batangas – Palawan
    ['042100000', '043400000', 'land', 2],   // Cavite – Laguna
    ['043400000', '045600000', 'land', 3],   // Laguna – Quezon
    ['043400000', '045800000', 'land', 2],   // Laguna – Rizal
    ['045600000', '045800000', 'land', 3],   // Quezon – Rizal
    ['045600000', '051600000', 'land', 4],   // Quezon – Camarines Norte
    ['045600000', '051700000', 'land', 5],   // Quezon – Camarines Sur
    ['045600000', '174000000', 'sea', 4],    // Quezon – Marinduque (Lucena)
    ['175100000', '175200000', 'land', 4],   // Occidental – Oriental Mindoro
    ['175200000', '175900000', 'sea', 4],    // Oriental Mindoro – Romblon
    ['175100000', '175300000', 'sea', 8],    // Occidental Mindoro – Palawan (Coron)

    // Bicol
    ['051600000', '051700000', 'land', 2],   // Camarines Norte – Camarines Sur
    ['051700000', '050500000', 'land', 2],   // Camarines Sur – Albay
    ['050500000', '052000000', 'sea', 4],    // Albay – Catanduanes (Tabaco)
    ['050500000', '056200000', 'land', 2],   // Albay – Sorsogon
    ['056200000', '054100000', 'sea', 5],    // Sorsogon – Masbate (Pilar)
    ['056200000', '084800000', 'sea', 2],    // Sorsogon – Northern Samar (Matnog–Allen)

    // Western Visayas
    ['175900000', '060400000', 'sea', 4],    // Romblon – Aklan (Caticlan)
    ['060400000', '061900000', 'land', 2],   // Aklan – Capiz
    ['060400000', '060600000', 'land', 3],   // Aklan – Antique
    ['060600000', '061900000', 'land', 3],   // Antique – Capiz
    ['060600000', '063000000', 'land', 2],   // Antique – Iloilo
    ['061900000', '063000000', 'land', 2],   // Capiz – Iloilo
    ['063000000', '067900000', 'sea', 1],    // Iloilo – Guimaras
    ['063000000', '064500000', 'sea', 2],    // Iloilo – Negros Occidental (Bacolod)
    ['067900000', '064500000', 'sea', 2],    // Guimaras – Negros Occidental
    ['064500000', '074600000', 'land', 4],   // Negros Occidental – Negros Oriental
    ['064500000', '072200000', 'sea', 3],    // Negros Occidental – Cebu (San Carlos–Toledo)

    // Central and Eastern Visayas
    ['054100000', '072200000', 'sea', 8],    // Masbate – Cebu
    ['074600000', '072200000', 'sea', 1],    // Negros Oriental – Cebu (Dumaguete–Santander)
    ['074600000', '076100000', 'sea', 2],    // Negros Oriental – Siquijor
    ['076100000', '071200000', 'sea', 2],    // Siquijor – Bohol
    ['072200000', '071200000', 'sea', 2],    // Cebu – Bohol
    ['072200000', '083700000', 'sea', 3],    // Cebu – Leyte (Ormoc)
    ['071200000', '083700000', 'sea', 3],    // Bohol – Leyte (Ubay–Hilongos)
    ['083700000', '086400000', 'land', 2],   // Leyte – Southern Leyte
    ['083700000', '086000000', 'land', 2],   // Leyte – Samar (San Juanico)
    ['083700000', '087800000', 'land', 1],   // Leyte – Biliran
    ['086000000', '084800000', 'land', 3],   // Samar – Northern Samar
    ['086000000', '082600000', 'land', 3],   // Samar – Eastern Samar
    ['084800000', '082600000', 'land', 4],   // Northern Samar – Eastern Samar

    // Mindanao: Caraga, Northern Mindanao
    ['086400000', '166700000', 'sea', 3],    // Southern Leyte – Surigao del Norte (Liloan–Lipata)
    ['166700000', '168500000', 'sea', 1],    // Surigao del Norte – Dinagat Islands
    ['166700000', '160200000', 'land', 2],   // Surigao del Norte – Agusan del Norte
    ['166700000', '166800000', 'land', 3],   // Surigao del Norte – Surigao del Sur
    ['160200000', '160300000', 'land', 2],   // Agusan del Norte – Agusan del Sur
    ['160200000', '104300000', 'land', 3],   // Agusan del Norte – Misamis Oriental
    ['160200000', '166800000', 'land', 3],   // Agusan del Norte – Surigao del Sur
    ['160300000', '166800000', 'land', 2],   // Agusan del Sur – Surigao del Sur
    ['160300000', '101300000', 'land', 4],   // Agusan del Sur – Bukidnon
    ['160300000', '118200000', 'land', 3],   // Agusan del Sur – Davao de Oro
    ['160300000', '112300000', 'land', 4],   // Agusan del Sur – Davao del Norte
    ['160300000', '104300000', 'land', 4],   // Agusan del Sur – Misamis Oriental
    ['166800000', '112500000', 'land', 4],   // Surigao del Sur – Davao Oriental
    ['071200000', '101800000', 'sea', 3],    // Bohol – Camiguin (Jagna–Benoni)
    ['071200000', '104300000', 'sea', 6],    // Bohol – Misamis Oriental (Cagayan de Oro)
    ['104300000', '101800000', 'sea', 2],    // Misamis Oriental – Camiguin
    ['104300000', '101300000', 'land', 2],   // Misamis Oriental – Bukidnon
    ['104300000', '103500000', 'land', 2],   // Misamis Oriental – Lanao del Norte
    ['101300000', '153600000', 'land', 4],   // Bukidnon – Lanao del Sur
    ['101300000', '124700000', 'land', 4],   // Bukidnon – Cotabato
    ['101300000', '112300000', 'land', 4],   // Bukidnon – Davao del Norte
    ['101300000', '112400000', 'land', 4],   // Bukidnon – Davao del Sur
    ['103500000', '153600000', 'land', 2],   // Lanao del Norte – Lanao del Sur
    ['103500000', '104200000', 'sea', 1],    // Lanao del Norte – Misamis Occidental (Mukas–Ozamiz)
    ['103500000', '097300000', 'land', 3],   // Lanao del Norte – Zamboanga del Sur

    // Zamboanga Peninsula, Sulu archipelago
    ['074600000', '097200000', 'sea', 4],    // Negros Oriental – Zamboanga del Norte (Dumaguete–Dapitan)
    ['104200000', '097200000', 'land', 3],   // Misamis Occidental – Zamboanga del Norte
    ['104200000', '097300000', 'land', 3],   // Misamis Occidental – Zamboanga del Sur
    ['097200000', '097300000', 'land', 4],   // Zamboanga del Norte – Zamboanga del Sur
    ['097200000', '098300000', 'land', 4],   // Zamboanga del Norte – Zamboanga Sibugay
    ['097300000', '098300000', 'land', 3],   // Zamboanga del Sur – Zamboanga Sibugay
    ['097300000', '150700000', 'sea', 2],    // Zamboanga del Sur – Basilan
    ['097300000', '156600000', 'sea', 8],    // Zamboanga del Sur – Sulu
    ['150700000', '156600000', 'sea', 6],    // Basilan – Sulu
    ['156600000', '157000000', 'sea', 6],    // Sulu – Tawi-Tawi

    // Davao, SOCCSKSARGEN, BARMM mainland
    ['153600000', '153800000', 'land', 4],   // Lanao del Sur – Maguindanao
    ['153600000', '124700000', 'land', 4],   // Lanao del Sur – Cotabato
    ['153800000', '124700000', 'land', 2],   // Maguindanao – Cotabato
    ['153800000', '126500000', 'land', 2],   // Maguindanao – Sultan Kudarat
    ['124700000', '112400000', 'land', 3],   // Cotabato – Davao del Sur
    ['124700000', '126500000', 'land', 3],   // Cotabato – Sultan Kudarat
    ['124700000', '126300000', 'land', 3],   // Cotabato – South Cotabato
    ['126300000', '126500000', 'land', 2],   // South Cotabato – Sultan Kudarat
    ['126300000', '128000000', 'land', 1],   // South Cotabato – Sarangani
    ['126300000', '112400000', 'land', 3],   // South Cotabato – Davao del Sur
    ['128000000', '118600000', 'land', 3],   // Sarangani – Davao Occidental
    ['128000000', '112400000', 'land', 3],   // Sarangani – Davao del Sur
    ['112400000', '112300000', 'land', 2],   // Davao del Sur – Davao del Norte
    ['112400000', '118600000', 'land', 3],   // Davao del Sur – Davao Occidental
    ['112300000', '118200000', 'land', 2],   // Davao del Norte – Davao de Oro
    ['118200000', '112500000', 'land', 3],   // Davao de Oro – Davao Oriental
];
