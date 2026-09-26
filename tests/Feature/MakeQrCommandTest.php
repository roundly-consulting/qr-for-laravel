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
