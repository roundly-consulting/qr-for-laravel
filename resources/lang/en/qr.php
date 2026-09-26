<?php

declare(strict_types=1);

return [

    'title' => 'QR code',

    /*
    | Default accessible descriptions (<desc>) per payload type. They never interpolate a
    | secret, an account name, an IBAN or an amount.
    */
    'descriptions' => [
        'text' => 'QR code containing text',
        'url' => 'QR code linking to :host',
        'email' => 'QR code to write an e-mail',
        'phone' => 'QR code to call a phone number',
        'sms' => 'QR code to send a text message',
        'wifi' => 'QR code to join a Wi-Fi network',
        'vcard' => 'QR code with contact details',
        'geo' => 'QR code with a map location',
        'otpauth' => 'QR code to set up two-factor authentication',
        'epc' => 'Payment QR code (SEPA credit transfer)',
        'bysquare' => 'Payment QR code (PAY by square)',
    ],

    'validation' => [
        'iban' => 'The :attribute must be a valid IBAN.',
        'bic' => 'The :attribute must be a valid BIC.',
        'creditor_reference' => 'The :attribute must be a valid creditor reference (RF).',
        'fits_in_qr_code' => 'The :attribute is too long to fit in a QR code.',
    ],

    /*
    | One entry per exception `$reason` key. Hosts show these to end users with
    | __('qr::qr.errors.'.$exception->reason, ['field' => $exception->field]).
    */
    'errors' => [
        'error' => 'The QR code could not be generated.',
        'invalid_config' => 'The QR code configuration is invalid.',
        'data_too_long' => 'The content is too long to fit in a QR code.',
        'input_too_long' => 'The content is too long to fit in a QR code.',
        'version_range' => 'The QR code version range is invalid.',
        'mask' => 'The QR code mask must be between 0 and 7.',
        'size' => 'The QR code size must be between 1 and 8192 pixels.',
        'margin' => 'The QR code margin must be between 0 and 64 modules.',
        'radius' => 'The module radius must be greater than 0 and at most 0.5.',
        'attribute' => 'This attribute is not allowed on a QR code.',
        'error_correction' => 'The error correction level must be L, M, Q or H.',
        'locked_by_payload' => 'The :field option is fixed by the payment standard.',
        'out_of_bounds' => 'The module is outside the QR code.',
        'render_as' => 'A QR code can be rendered as "svg" or "img".',
        'color' => 'The :field colour is not allowed.',
        'unencodable_character' => 'The content contains a character the chosen mode cannot encode.',
        'required' => 'The :field field is required.',
        'too_long' => 'The :field field is too long.',
        'too_short' => 'The :field field is too short.',
        'invalid_format' => 'The :field field is invalid.',
        'out_of_range' => 'The :field field is out of range.',
        'unsupported_scheme' => 'The :field field uses a link type that is not allowed.',
        'mutually_exclusive' => 'The :field field cannot be combined with another field.',
        'unsupported_in_version' => 'The :field field is not supported by this version of the standard.',
        'unrepresentable' => 'The :field field contains characters the character set cannot represent.',
        'decode_size' => 'The QR code has an invalid size.',
        'decode_format' => 'The QR code format information is unreadable.',
        'decode_version' => 'The QR code version information is unreadable.',
        'decode_error_correction' => 'The QR code data is damaged beyond repair.',
        'decode_segment' => 'The QR code data is malformed.',
    ],

];
