<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Enums\SmsFormat;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Payloads\Email;
use RoundlyConsulting\Qr\Payloads\Geo;
use RoundlyConsulting\Qr\Payloads\Phone;
use RoundlyConsulting\Qr\Payloads\Sms;
use RoundlyConsulting\Qr\Payloads\Text;
use RoundlyConsulting\Qr\Payloads\Url;
use RoundlyConsulting\Qr\Payloads\VCard;
use RoundlyConsulting\Qr\Payloads\Wifi;

function translator(): Translator
{
    return app('translator');
}

it('encodes text as given and sniffs secrets', function (string $text, Sensitivity $sensitivity, bool $locked, string $description): void {
    $payload = new Text($text);

    expect($payload->toQrString())->toBe($text)
        ->and($payload->sensitivity())->toBe($sensitivity)
        ->and($payload->requirements()->locks('sensitivity'))->toBe($locked)
        ->and($payload->description(translator()))->toBe($description);
})->with([
    ['', Sensitivity::Public, false, 'QR code containing text'],
    ['Table 12 — scan to order', Sensitivity::Public, false, 'QR code containing text'],
    ['  OTPAUTH://totp/x?secret=AB', Sensitivity::Secret, true, 'QR code to set up two-factor authentication'],
    ['otpauth-migration://offline?data=x', Sensitivity::Secret, true, 'QR code to set up two-factor authentication'],
    ['wifi:T:WPA;S:x;P:y;;', Sensitivity::Secret, false, 'QR code to join a Wi-Fi network'],
]);

it('accepts http(s) URLs including non-ASCII paths and hosts', function (string $url, string $host): void {
    $payload = new Url($url);

    expect($payload->toQrString())->toBe($url)
        ->and($payload->host)->toBe($host)
        ->and($payload->sensitivity())->toBe(Sensitivity::Public)
        ->and($payload->requirements()->segmentation)->toBe(Segmentation::Optimal)
        ->and($payload->description(translator()))->toBe('QR code linking to '.$host);
})->with([
    ['https://vetapp.sk/zvieratá', 'vetapp.sk'],
    ['HTTP://EXAMPLE.COM/A?B=1#C', 'EXAMPLE.COM'],
    ['https://čšž.example/', 'čšž.example'],
    ['https://user:pw@example.com:8443/x', 'example.com'],
    ['http://[::1]:8080/', '[::1]'],
]);

it('allows other schemes only when opted in', function (): void {
    $payload = new Url('mailto:team@example.com', ['mailto']);

    expect($payload->scheme)->toBe('mailto')
        ->and($payload->description(translator()))->toBe('QR code linking to mailto');
});

it('treats opted-in otpauth and Wi-Fi URLs as secrets, like raw text', function (string $url, bool $locked, string $description): void {
    $payload = new Url($url, ['otpauth', 'otpauth-migration', 'wifi']);

    expect($payload->toQrString())->toBe($url)
        ->and($payload->sensitivity())->toBe(Sensitivity::Secret)
        ->and($payload->requirements()->locks('sensitivity'))->toBe($locked)
        ->and($payload->requirements()->segmentation)->toBe(Segmentation::Optimal)
        ->and($payload->description(translator()))->toBe($description);
})->with([
    'totp' => ['otpauth://totp/Acme:u?secret=JBSWY3DPEHPK3PXP&issuer=Acme', true, 'QR code to set up two-factor authentication'],
    'upper-case scheme' => ['OTPAUTH://hotp/x?secret=AB', true, 'QR code to set up two-factor authentication'],
    'migration' => ['otpauth-migration://offline?data=abc', true, 'QR code to set up two-factor authentication'],
    'wifi' => ['WIFI:T:WPA;S:x;P:12345678;;', false, 'QR code to join a Wi-Fi network'],
]);

it('rejects unsafe or malformed URLs', function (string $url, string $reason): void {
    try {
        new Url($url);
    } catch (InvalidPayloadException $e) {
        expect($e->reason)->toBe($reason)->and($e->getMessage())->not->toContain($url === '' ? 'x' : $url);

        return;
    }

    $this->fail('expected a failure');
})->with([
    ['', 'required'],
    ['javascript:alert(1)', 'unsupported_scheme'],
    ['data:text/html,x', 'unsupported_scheme'],
    ['file:///etc/passwd', 'unsupported_scheme'],
    ['https://exa mple.com', 'invalid_format'],
    ["https://example.com/\n", 'invalid_format'],
    ['no-scheme.example', 'invalid_format'],
    ['https:///path', 'invalid_format'],
    ['http://:80', 'invalid_format'],
    ['http:no-authority', 'invalid_format'],
    ['https://example.com/'.str_repeat('a', 4100), 'too_long'],
]);

it('builds mailto links', function (): void {
    expect((new Email('jana@example.sk'))->toQrString())->toBe('mailto:jana@example.sk')
        ->and((new Email('jana@example.sk', 'Ahoj & hi', 'Riadok 1'))->toQrString())->toBe('mailto:jana@example.sk?subject=Ahoj%20%26%20hi&body=Riadok%201')
        ->and((new Email('jana@example.sk', null, 'x'))->toQrString())->toBe('mailto:jana@example.sk?body=x')
        ->and((new Email('jána@priklad.sk'))->sensitivity())->toBe(Sensitivity::Personal)
        ->and((new Email('a@b.co'))->description(translator()))->toBe('QR code to write an e-mail');
});

it('percent-encodes the mailto address so it cannot add headers (RFC 6068 §2)', function (string $to, string $expected): void {
    expect((new Email($to, 'Hi'))->toQrString())->toBe($expected);
})->with([
    'query delimiters' => ['x?bcc=evil%40evil.com&a@example.com', 'mailto:x%3Fbcc%3Devil%2540evil.com%26a@example.com?subject=Hi'],
    'fragment and slash' => ['a#b/c@example.com', 'mailto:a%23b%2Fc@example.com?subject=Hi'],
    'plus and dot stay' => ['jana.nova+tag@example.sk', 'mailto:jana.nova+tag@example.sk?subject=Hi'],
    'non-ASCII as UTF-8' => ['jána@priklad.sk', 'mailto:j%C3%A1na@priklad.sk?subject=Hi'],
]);

it('rejects invalid e-mail addresses', function (string $to): void {
    new Email($to);
})->throws(InvalidPayloadException::class)->with(['', 'not-an-address', 'a@', '<a@b.c>']);

it('builds tel links from formatted numbers', function (string $input, string $expected): void {
    $phone = new Phone($input);

    expect($phone->toQrString())->toBe($expected)
        ->and($phone->sensitivity())->toBe(Sensitivity::Personal)
        ->and($phone->description(translator()))->toBe('QR code to call a phone number');
})->with([
    ['+421 900 123 456', 'tel:+421900123456'],
    ['(02) 5020-1234', 'tel:0250201234'],
    ["0900\u{00A0}111.222/3", 'tel:09001112223'],
]);

it('rejects invalid phone numbers', function (string $number, string $reason): void {
    expect(fn () => new Phone($number))->toThrow(InvalidPayloadException::class, $reason === 'required' ? 'is required' : 'valid format');
})->with([['', 'required'], [' - ', 'required'], ['12', 'format'], ['+421 abc', 'format'], ['++421900', 'format']]);

it('builds text message codes in both formats', function (): void {
    expect((new Sms('+421 900 111 222', 'Hi: there'))->toQrString())->toBe('SMSTO:+421900111222:Hi: there')
        ->and((new Sms('+421900111222'))->toQrString())->toBe('SMSTO:+421900111222')
        ->and((new Sms('+421900111222', 'Hi there', SmsFormat::Uri))->toQrString())->toBe('sms:+421900111222?body=Hi%20there')
        ->and((new Sms('+421900111222', '', SmsFormat::Uri))->toQrString())->toBe('sms:+421900111222')
        ->and((new Sms('0900111222'))->sensitivity())->toBe(Sensitivity::Personal)
        ->and((new Sms('0900111222'))->description(translator()))->toBe('QR code to send a text message');
});

it('builds escaped Wi-Fi codes', function (Wifi $wifi, string $expected): void {
    expect($wifi->toQrString())->toBe($expected)
        ->and($wifi->sensitivity())->toBe(Sensitivity::Secret)
        ->and($wifi->description(translator()))->toBe('QR code to join a Wi-Fi network');
})->with([
    'wpa' => [fn () => new Wifi('Clinic', 'secret-password'), 'WIFI:T:WPA;S:Clinic;P:secret-password;;'],
    'escaped' => [fn () => new Wifi('My;Net', 'p\\a:s,s"word'), 'WIFI:T:WPA;S:My\;Net;P:p\\\\a\:s\,s\"word;;'],
    'hex quoted' => [fn () => new Wifi('CAFE01', 'DEADBEEF'), 'WIFI:T:WPA;S:"CAFE01";P:"DEADBEEF";;'],
    'hidden sae' => [fn () => new Wifi('Lab', 'x', WifiSecurity::Sae, true), 'WIFI:T:SAE;S:Lab;P:x;H:true;;'],
    'wep' => [fn () => new Wifi('Old', 'wepkey', WifiSecurity::Wep), 'WIFI:T:WEP;S:Old;P:wepkey;;'],
    'open' => [fn () => new Wifi('Guest', null, WifiSecurity::None), 'WIFI:T:nopass;S:Guest;;'],
]);

it('writes an opted-in raw hex key unquoted', function (Wifi $wifi, string $expected): void {
    expect($wifi->toQrString())->toBe($expected)
        ->and($wifi->hexKey)->toBeTrue()
        ->and($wifi->sensitivity())->toBe(Sensitivity::Secret);
})->with([
    'wpa psk' => [fn () => Wifi::withHexKey('Clinic', str_repeat('a1', 32)), 'WIFI:T:WPA;S:Clinic;P:'.str_repeat('a1', 32).';;'],
    'wep 64-bit' => [fn () => Wifi::withHexKey('Old', '0123456789', WifiSecurity::Wep), 'WIFI:T:WEP;S:Old;P:0123456789;;'],
    'wep 128-bit hidden' => [fn () => Wifi::withHexKey('Old', str_repeat('AB', 13), WifiSecurity::Wep, true), 'WIFI:T:WEP;S:Old;P:'.str_repeat('AB', 13).';H:true;;'],
    'wep 256-bit' => [fn () => Wifi::withHexKey('Old', str_repeat('c', 58), WifiSecurity::Wep), 'WIFI:T:WEP;S:Old;P:'.str_repeat('c', 58).';;'],
]);

it('rejects raw hex keys of the wrong shape', function (Closure $build, string $reason): void {
    try {
        $build();
    } catch (InvalidPayloadException $e) {
        expect($e->reason)->toBe($reason)->and($e->field)->toBe($reason === 'mutually_exclusive' ? 'hexKey' : 'password');

        return;
    }

    $this->fail('expected a failure');
})->with([
    'wpa 63 hex' => [fn () => Wifi::withHexKey('Net', str_repeat('a', 63)), 'invalid_format'],
    'wpa not hex' => [fn () => Wifi::withHexKey('Net', str_repeat('g', 64)), 'invalid_format'],
    'wep 12 hex' => [fn () => Wifi::withHexKey('Net', str_repeat('a', 12), WifiSecurity::Wep), 'invalid_format'],
    'sae' => [fn () => Wifi::withHexKey('Net', str_repeat('a', 64), WifiSecurity::Sae), 'mutually_exclusive'],
    'open' => [fn () => Wifi::withHexKey('Net', str_repeat('a', 64), WifiSecurity::None), 'mutually_exclusive'],
]);

it('measures a WPA passphrase in bytes, 8 to 63', function (string $password): void {
    expect((new Wifi('Net', $password))->toQrString())->toBe('WIFI:T:WPA;S:Net;P:'.$password.';;');
})->with([
    '8 bytes in 4 chars' => [str_repeat('ž', 4)],
    '63 bytes in 32 chars' => [str_repeat('ž', 31).'a'],
    '63 ascii' => [str_repeat('p', 63)],
]);

it('validates Wi-Fi credentials', function (Closure $build, string $reason): void {
    try {
        $build();
    } catch (InvalidPayloadException $e) {
        expect($e->reason)->toBe($reason)->and($e->getMessage())->not->toContain('hunter');

        return;
    }

    $this->fail('expected a failure');
})->with([
    [fn () => new Wifi('', 'password1'), 'required'],
    [fn () => new Wifi(str_repeat('s', 33), 'password1'), 'too_long'],
    [fn () => new Wifi('Net'), 'required'],
    [fn () => new Wifi('Net', 'hunter'), 'too_short'],
    [fn () => new Wifi('Net', str_repeat('hunter', 11)), 'too_long'],
    'wpa 64 bytes in 32 chars' => [fn () => new Wifi('Net', str_repeat('ž', 32)), 'too_long'],
    'wpa 7 bytes in 4 chars' => [fn () => new Wifi('Net', 'žžža'), 'too_short'],
    [fn () => new Wifi('Net', 'hunter22', WifiSecurity::None), 'mutually_exclusive'],
]);

it('builds vCard 3.0 contacts', function (): void {
    $card = new VCard(
        name: 'Ján Kováč; MVDr.',
        organization: 'VetClinic, s.r.o.',
        title: 'Veterinár',
        phones: ['+421 900 111 222'],
        emails: ['jan@vet.sk'],
        url: 'https://vet.sk',
        address: "Hlavná 1\nKošice",
        note: 'Back\\slash',
    );

    expect($card->toQrString())->toBe(implode("\r\n", [
        'BEGIN:VCARD',
        'VERSION:3.0',
        'N:;Ján Kováč\; MVDr.;;;',
        'FN:Ján Kováč\; MVDr.',
        'ORG:VetClinic\, s.r.o.',
        'TITLE:Veterinár',
        'TEL:+421900111222',
        'EMAIL:jan@vet.sk',
        'URL:https://vet.sk',
        'ADR:;;Hlavná 1\nKošice;;;;',
        'NOTE:Back\\\\slash',
        'END:VCARD',
    ]))
        ->and($card->requirements()->segmentation)->toBe(Segmentation::Byte)
        ->and($card->sensitivity())->toBe(Sensitivity::Personal)
        ->and($card->description(translator()))->toBe('QR code with contact details')
        ->and((new VCard('Jana'))->toQrString())->toBe("BEGIN:VCARD\r\nVERSION:3.0\r\nN:;Jana;;;\r\nFN:Jana\r\nEND:VCARD");
});

it('writes structured name parts into N and keeps FN as the display name', function (): void {
    $card = new VCard(
        name: 'MVDr. Jana Mária Nováková, PhD.',
        familyName: 'Nováková',
        givenName: 'Jana',
        additionalNames: 'Mária',
        honorificPrefixes: 'MVDr.',
        honorificSuffixes: 'PhD.',
    );

    expect(explode("\r\n", $card->toQrString()))->toContain('N:Nováková;Jana;Mária;MVDr.;PhD.')
        ->toContain('FN:MVDr. Jana Mária Nováková\, PhD.')
        ->and(explode("\r\n", (new VCard('Nováková, Jana', familyName: 'Nováková', givenName: 'Jana'))->toQrString()))
        ->toContain('N:Nováková;Jana;;;')
        ->and(explode("\r\n", (new VCard('A; B', familyName: "Van; der\nBerg", givenName: 'Ann,Marie'))->toQrString()))
        ->toContain('N:Van\; der\nBerg;Ann\,Marie;;;');
});

it('puts a single-string name into the given-name slot of N', function (): void {
    // No silent splitting: FN carries the full display name, N holds it as the given name.
    expect(explode("\r\n", (new VCard('Jana Nováková'))->toQrString()))
        ->toContain('N:;Jana Nováková;;;')
        ->toContain('FN:Jana Nováková');
});

it('validates structured name parts', function (Closure $build, string $field): void {
    try {
        $build();
    } catch (InvalidPayloadException $e) {
        expect($e->field)->toBe($field)->and($e->reason)->toBe('too_long');

        return;
    }

    $this->fail('expected a failure');
})->with([
    [fn () => new VCard('Jana', familyName: str_repeat('a', 256)), 'familyName'],
    [fn () => new VCard('Jana', honorificSuffixes: str_repeat('a', 256)), 'honorificSuffixes'],
]);

it('writes the vCard URL as a URI value, not escaped text (RFC 2426 §3.6.8)', function (string $url, string $line): void {
    $lines = explode("\r\n", (new VCard('Jana', url: $url))->toQrString());

    expect($lines)->toContain($line)
        ->and($lines)->toHaveCount(6);
})->with([
    'commas and semicolons stay' => ['https://vet.sk/a;b,c?x=1,2', 'URL:https://vet.sk/a;b,c?x=1,2'],
    'backslash stays' => ['https://vet.sk/a\\b', 'URL:https://vet.sk/a\\b'],
    'line breaks cannot start a property' => ["https://vet.sk/\r\nNOTE:x", 'URL:https://vet.sk/%0D%0ANOTE:x'],
    'spaces and controls percent-encoded' => ["https://vet.sk/a b\tc", 'URL:https://vet.sk/a%20b%09c'],
]);

it('validates vCard fields', function (Closure $build): void {
    expect($build)->toThrow(InvalidPayloadException::class);
})->with([
    fn () => new VCard(' '),
    fn () => new VCard(str_repeat('a', 256)),
    fn () => new VCard('Jana', emails: ['nope']),
    fn () => new VCard('Jana', phones: ['12']),
]);

it('formats geo URIs independently of the locale', function (): void {
    $previous = setlocale(LC_NUMERIC, '0');
    setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'sk_SK.UTF-8');

    try {
        expect((new Geo(48.1486, 17.1077))->toQrString())->toBe('geo:48.1486,17.1077')
            ->and((new Geo(-33.868819999, 151.2092955))->toQrString())->toBe('geo:-33.86882,151.2092955')
            ->and((new Geo(-0.0, 0))->toQrString())->toBe('geo:0,0')
            ->and((new Geo(90, -180))->toQrString())->toBe('geo:90,-180');
    } finally {
        setlocale(LC_NUMERIC, (string) $previous);
    }

    expect((new Geo(1, 1))->sensitivity())->toBe(Sensitivity::Personal)
        ->and((new Geo(1, 1))->description(translator()))->toBe('QR code with a map location');
});

it('rejects coordinates out of range', function (float $lat, float $lng, string $field): void {
    expect(fn () => new Geo($lat, $lng))->toThrow(InvalidPayloadException::class, $field);
})->with([[90.1, 0, 'latitude'], [-91, 0, 'latitude'], [0, 180.5, 'longitude'], [NAN, 0, 'latitude'], [0, INF, 'longitude']]);

it('rejects a backslash in a web URL authority, where browsers end the host', function (): void {
    foreach (['https://evil.example\@bank.example/', 'http://evil.example\bank.example', 'https://\evil.example/'] as $url) {
        try {
            new Url($url);
            $this->fail("expected {$url} to fail");
        } catch (InvalidPayloadException $e) {
            expect($e->reason)->toBe('invalid_format')->and($e->field)->toBe('url');
        }
    }

    expect((new Url('https://user@bank.example/'))->host)->toBe('bank.example')
        ->and((new Url('https://bank.example/a\b'))->host)->toBe('bank.example')
        ->and((new Url('myapp://evil.example\@bank.example/', ['myapp']))->host)->toBe('bank.example');
});
