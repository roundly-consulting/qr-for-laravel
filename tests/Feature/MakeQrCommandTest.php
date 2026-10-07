<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('prints the SVG to stdout', function (): void {
    Artisan::call('qr:make', ['data' => 'hello', '--size' => '120', '--margin' => '2', '--ecc' => 'H', '--fg' => '#111', '--bg' => 'white', '--mask' => '3', '--min-version' => '2']);
    $output = Artisan::output();

    expect($output)->toStartWith('<?xml version="1.0" encoding="UTF-8"?><svg')
        ->and($output)->toContain('width="120"')
        ->and($output)->toContain('fill="#111"');
});

it('prints encoding details', function (): void {
    $this->artisan('qr:make', ['data' => 'HELLO WORLD', '--info' => true, '--mask' => '1'])
        ->expectsOutputToContain('Version')
        ->expectsOutputToContain('forced')
        ->assertSuccessful();

    $this->artisan('qr:make', ['data' => 'Žlté', '--info' => true, '--max-version' => '5'])
        ->expectsOutputToContain('26')
        ->assertSuccessful();
});

it('writes to a file and refuses to overwrite without --force', function (): void {
    $path = sys_get_temp_dir().'/qr-command-'.getmypid().'.svg';
    @unlink($path);

    $this->artisan('qr:make', ['data' => 'file', '--output' => $path])->assertSuccessful();
    expect((string) file_get_contents($path))->toStartWith('<?xml');

    $this->artisan('qr:make', ['data' => 'file', '--output' => $path])->assertFailed();
    $this->artisan('qr:make', ['data' => 'again', '--output' => $path, '--force' => true])->assertSuccessful();

    @unlink($path);
});

it('reads the data from STDIN', function (): void {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "from stdin\n");
    rewind($stream);

    $input = new ArrayInput(['command' => 'qr:make', 'data' => '-', '--info' => true]);
    $input->setStream($stream);
    $output = new BufferedOutput;

    expect(app(Kernel::class)->handle($input, $output))->toBe(0)
        ->and($output->fetch())->toContain('byte ×10');
});

it('fails cleanly on invalid input', function (array $arguments): void {
    $this->artisan('qr:make', $arguments)->assertFailed();
})->with([
    [['data' => 'x', '--ecc' => 'Z']],
    [['data' => 'x', '--mask' => 'abc']],
    [['data' => 'x', '--fg' => 'url(#x)']],
    [['data' => str_repeat('x', 8000)]],
    [['data' => 'x', '--output' => '/nonexistent-dir/qr.svg']],
]);

it('honours integer option values passed programmatically', function (): void {
    Artisan::call('qr:make', ['data' => 'https://example.com', '--mask' => 3, '--min-version' => 4, '--max-version' => 6, '--info' => true]);
    $info = Artisan::output();

    Artisan::call('qr:make', ['data' => 'https://example.com', '--size' => 240, '--margin' => 0]);
    $svg = Artisan::output();

    expect($info)->toMatch('/Version\W+4\b/')
        ->and($info)->toMatch('/Mask\W+3\b/')
        ->and($info)->toContain('forced')
        ->and($svg)->toContain('width="240"')
        ->and($svg)->toContain('viewBox="0 0 25 25"'); // version 2 with --margin 0
});

it('encodes integer data passed programmatically', function (): void {
    Artisan::call('qr:make', ['data' => 12345, '--info' => true]);

    expect(Artisan::output())->toContain('numeric ×5');
});

it('rejects option values that are not integers', function (mixed $value): void {
    $this->artisan('qr:make', ['data' => 'x', '--mask' => $value])
        ->expectsOutputToContain('The --mask option must be an integer.')
        ->assertFailed();
})->with([
    'float' => [3.5],
    'array' => [[3]],
    'signed text' => ['+3'],
]);

it('keeps the configured other version bound when only one version option is given', function (): void {
    config(['qr.versions.min' => 5, 'qr.versions.max' => 12]);

    Artisan::call('qr:make', ['data' => 'x', '--max-version' => '8', '--info' => true]);
    expect(Artisan::output())->toMatch('/Version[ .]+5\s/');

    Artisan::call('qr:make', ['data' => 'x', '--min-version' => '7', '--info' => true]);
    expect(Artisan::output())->toMatch('/Version[ .]+7\s/');

    Artisan::call('qr:make', ['data' => 'x', '--max-version' => '3', '--info' => true]);
    expect(Artisan::output())->toMatch('/Version[ .]+3\s/');

    expect(Artisan::call('qr:make', ['data' => str_repeat('x', 300), '--min-version' => '10', '--info' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('versions 10-12');
});
