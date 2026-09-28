<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use RoundlyConsulting\Qr\Contracts\QrFactory;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Exceptions\QrException;
use RoundlyConsulting\Qr\ValueObjects\EncodingInfo;
use RoundlyConsulting\Qr\ValueObjects\SegmentInfo;

final class MakeQrCommand extends Command
{
    protected $signature = 'qr:make
        {data : Text to encode; "-" reads STDIN}
        {--ecc= : Error correction level: L, M, Q or H}
        {--min-version= : Smallest allowed version (1-40)}
        {--max-version= : Largest allowed version (1-40)}
        {--mask= : Force a mask (0-7)}
        {--size= : Width/height in pixels}
        {--margin= : Quiet zone in modules}
        {--fg= : Foreground colour}
        {--bg= : Background colour}
        {--output= : Write the SVG to this path}
        {--force : Overwrite the --output file}
        {--info : Print encoding details instead of the SVG}';

    protected $description = 'Render a QR code as SVG';

    public function handle(QrFactory $qr): int
    {
        $data = self::stringValue($this->argument('data'));

        if ($data === '-') {
            $stream = method_exists($this->input, 'getStream') ? $this->input->getStream() : null;
            $data = rtrim((string) stream_get_contents(is_resource($stream) ? $stream : STDIN), "\r\n");
        }

        try {
            $pending = $qr->make($data);

            if (is_string($ecc = $this->option('ecc'))) {
                $pending = $pending->errorCorrection($ecc);
            }

            if ($this->option('min-version') !== null || $this->option('max-version') !== null) {
                $pending = $pending->versions($this->intOption('min-version') ?? 1, $this->intOption('max-version') ?? 40);
            }

            if (($mask = $this->intOption('mask')) !== null) {
                $pending = $pending->mask($mask);
            }

            if (($size = $this->intOption('size')) !== null) {
                $pending = $pending->size($size);
            }

            if (($margin = $this->intOption('margin')) !== null) {
                $pending = $pending->margin($margin);
            }

            if (is_string($fg = $this->option('fg'))) {
                $pending = $pending->foreground($fg);
            }

            if (is_string($bg = $this->option('bg'))) {
                $pending = $pending->background($bg);
            }

            if ($this->option('info') === true) {
                return $this->printInfo($pending->info());
            }

            $svg = $pending->svg()->withXmlDeclaration()->toString();
        } catch (QrException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $output = $this->option('output');

        if (! is_string($output) || $output === '') {
            $this->output->writeln($svg, OutputStyle::OUTPUT_RAW);

            return self::SUCCESS;
        }

        if (file_exists($output) && $this->option('force') !== true) {
            $this->components->error("[{$output}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        if (@file_put_contents($output, $svg) === false) {
            $this->components->error("Could not write [{$output}].");

            return self::FAILURE;
        }

        $this->components->info("QR code written to [{$output}].");

        return self::SUCCESS;
    }

    private function printInfo(EncodingInfo $info): int
    {
        $this->components->twoColumnDetail('Version', (string) $info->version);
        $this->components->twoColumnDetail('Error correction', $info->errorCorrection->value.($info->errorCorrectionBoosted ? ' (boosted)' : ''));
        $this->components->twoColumnDetail('Mask', (string) $info->mask);
        $this->components->twoColumnDetail('Segments', implode(', ', array_map(
            static fn (SegmentInfo $segment): string => $segment->mode->key().' ×'.$segment->characterCount,
            $info->segments,
        )));
        $this->components->twoColumnDetail('Data bits', $info->dataBits.' / '.$info->capacityBits);
        $this->components->twoColumnDetail('ECI', $info->eciDesignator === null ? 'none' : (string) $info->eciDesignator);
        $this->components->twoColumnDetail('Mask penalties', $info->maskPenalties === [] ? 'forced' : implode(' ', $info->maskPenalties));

        return self::SUCCESS;
    }

    /**
     * Console argument types differ between Laravel majors, and `Artisan::call()` passes
     * values through untouched; accept a number as well as a string.
     */
    private static function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }

    /**
     * From the command line an option is a string; `Artisan::call()` may pass an int. Any
     * other value is rejected rather than silently ignored.
     */
    private function intOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^-?\d+$/', $value) !== 1) {
            throw new InvalidOptionException("The --{$name} option must be an integer.", InvalidOptionException::REASON_ERROR, $name);
        }

        return (int) $value;
    }
}
