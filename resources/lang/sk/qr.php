<?php

declare(strict_types=1);

return [

    'title' => 'QR kód',

    /*
    | Predvolené prístupné popisy (<desc>) pre jednotlivé typy obsahu. Nikdy nevkladajú
    | tajomstvo, názov účtu, IBAN ani sumu.
    */
    'descriptions' => [
        'text' => 'QR kód s textom',
        'url' => 'QR kód s odkazom na :host',
        'email' => 'QR kód na napísanie e-mailu',
        'phone' => 'QR kód na zavolanie na telefónne číslo',
        'sms' => 'QR kód na odoslanie SMS správy',
        'wifi' => 'QR kód na pripojenie k sieti Wi-Fi',
        'vcard' => 'QR kód s kontaktnými údajmi',
        'geo' => 'QR kód s polohou na mape',
        'otpauth' => 'QR kód na nastavenie dvojfaktorového overenia',
        'epc' => 'Platobný QR kód (SEPA prevod)',
        'bysquare' => 'Platobný QR kód (PAY by square)',
    ],

    'validation' => [
        'iban' => 'Pole :attribute musí byť platný IBAN.',
        'bic' => 'Pole :attribute musí byť platný BIC.',
        'creditor_reference' => 'Pole :attribute musí byť platná referencia veriteľa (RF).',
        'iban_eea' => 'Pole :attribute musí byť IBAN z krajiny Európskeho hospodárskeho priestoru.',
        'fits_in_qr_code' => 'Pole :attribute je príliš dlhé, nezmestí sa do QR kódu.',
    ],

    /*
    | Jedna položka pre každý kľúč `$reason` výnimky. Aplikácie ich zobrazujú používateľom cez
    | __('qr::qr.errors.'.$exception->reason, ['field' => $exception->field]).
    */
    'errors' => [
        'error' => 'QR kód sa nepodarilo vygenerovať.',
        'invalid_config' => 'Konfigurácia QR kódu je neplatná.',
        'data_too_long' => 'Obsah je príliš dlhý, nezmestí sa do QR kódu.',
        'input_too_long' => 'Obsah je príliš dlhý, nezmestí sa do QR kódu.',
        'version_range' => 'Rozsah verzií QR kódu je neplatný.',
        'mask' => 'Maska QR kódu musí byť od 0 do 7.',
        'size' => 'Veľkosť QR kódu musí byť od 1 do 8192 pixelov.',
        'margin' => 'Okraj QR kódu musí mať od 0 do 64 modulov.',
        'radius' => 'Polomer zaoblenia modulu musí byť väčší ako 0 a najviac 0,5.',
        'attribute' => 'Tento atribút nie je pri QR kóde povolený.',
        'error_correction' => 'Úroveň korekcie chýb musí byť L, M, Q alebo H.',
        'locked_by_payload' => 'Možnosť :field je pevne určená týmto typom obsahu.',
        'out_of_bounds' => 'Modul leží mimo QR kódu.',
        'eci' => 'Identifikátor ECI musí byť od 0 do 999999.',
        'render_as' => 'QR kód je možné vykresliť ako „svg“ alebo „img“.',
        'color' => 'Farba :field nie je povolená.',
        'unencodable_character' => 'V obsahu je znak, ktorý zvolený režim nedokáže zakódovať.',
        'required' => 'Pole :field je povinné.',
        'too_long' => 'Pole :field je príliš dlhé.',
        'too_short' => 'Pole :field je príliš krátke.',
        'invalid_format' => 'Pole :field je neplatné.',
        'out_of_range' => 'Pole :field je mimo povoleného rozsahu.',
        'unsupported_scheme' => 'Pole :field používa typ odkazu, ktorý nie je povolený.',
        'mutually_exclusive' => 'Pole :field nie je možné kombinovať s iným poľom.',
        'unsupported_in_version' => 'Pole :field táto verzia štandardu nepodporuje.',
        'unrepresentable' => 'Pole :field obsahuje znaky, ktoré v danej znakovej sade nie je možné zapísať.',
        'iban_format' => 'IBAN nie je platný.',
        'iban_country' => 'IBAN pochádza z krajiny, ktorá nie je podporovaná.',
        'iban_length' => 'IBAN má nesprávnu dĺžku pre svoju krajinu.',
        'iban_checksum' => 'IBAN má nesprávne kontrolné číslice.',
        'bic_format' => 'BIC nie je platný.',
        'bic_country' => 'BIC obsahuje neznámy kód krajiny.',
        'creditor_reference_format' => 'Referencia veriteľa nie je platná.',
        'creditor_reference_checksum' => 'Referencia veriteľa má nesprávne kontrolné číslice.',
        'currency_not_eur' => 'Suma musí byť v eurách.',
        'currency_not_iso' => 'Táto mena nie je pri bankových platbách podporovaná.',
        'currency_mismatch' => 'Pole :field musí byť v rovnakej mene ako platba.',
        'payload_too_long' => 'Platobné údaje sú pre platobný QR kód príliš dlhé.',
        'bysquare_base32hex' => 'Kód PAY by square nie je platný.',
        'bysquare_header' => 'Kód PAY by square neobsahuje podporovaný platobný doklad.',
        'bysquare_length' => 'Kód PAY by square je poškodený.',
        'bysquare_lzma' => 'Kód PAY by square je poškodený.',
        'bysquare_crc' => 'Kód PAY by square je poškodený.',
        'bysquare_fields' => 'Kód PAY by square má neočakávanú štruktúru.',
        'bysquare_value' => 'Kód PAY by square obsahuje neplatnú hodnotu poľa :field.',
        'decode_size' => 'QR kód má neplatnú veľkosť.',
        'decode_format' => 'Informácie o formáte QR kódu sa nedajú prečítať.',
        'decode_version' => 'Informácie o verzii QR kódu sa nedajú prečítať.',
        'decode_error_correction' => 'Údaje QR kódu sú poškodené tak, že ich nie je možné opraviť.',
        'decode_segment' => 'Údaje QR kódu majú nesprávny formát.',
    ],

];
