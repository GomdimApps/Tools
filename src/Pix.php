<?php

namespace GomdimApps\Tools;

use Illuminate\Support\Str;

class Pix
{
    private const GUI = 'br.gov.bcb.pix';

    private ?float $amount = null;

    private ?string $txId = null;

    private ?string $description = null;

    private function __construct(
        private readonly string $keyType,
        private readonly string $pixKey,
        private readonly string $merchantName,
        private readonly string $merchantCity,
    ) {
    }

    public static function make(string $keyType, string $pixKey, string $merchantName, string $merchantCity): self
    {
        return new self($keyType, $pixKey, $merchantName, $merchantCity);
    }

    public function amount(?float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function txId(?string $txId): self
    {
        $this->txId = $txId;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function build(): string
    {
        $payload = $this->field('00', '01')
            .$this->field('01', '11')
            .$this->merchantAccountInfo()
            .$this->field('52', '0000')
            .$this->field('53', '986')
            .($this->amount !== null ? $this->field('54', number_format($this->amount, 2, '.', '')) : '')
            .$this->field('58', 'BR')
            .$this->field('59', $this->sanitize($this->merchantName, 25))
            .$this->field('60', $this->sanitize($this->merchantCity, 15))
            .$this->additionalData();

        return $payload.'6304'.$this->crc16($payload.'6304');
    }

    private function merchantAccountInfo(): string
    {
        $value = $this->field('00', self::GUI).$this->field('01', $this->normalizeKey());

        if ($this->description) {
            $value .= $this->field('02', $this->sanitize($this->description, 40));
        }

        return $this->field('26', $value);
    }

    private function normalizeKey(): string
    {
        return match ($this->keyType) {
            'document' => preg_replace('/\D/', '', $this->pixKey),
            'phone' => '+55'.preg_replace('/\D/', '', str_replace('+55', '', $this->pixKey)),
            default => $this->pixKey,
        };
    }

    private function additionalData(): string
    {
        $txId = $this->txId ? preg_replace('/[^A-Za-z0-9]/', '', $this->txId) : '';

        return $this->field('62', $this->field('05', $txId !== '' ? $txId : '***'));
    }

    private function field(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    private function sanitize(string $value, int $maxLength): string
    {
        $value = preg_replace('/[^A-Z0-9 ]/', '', strtoupper(Str::ascii($value)));

        return mb_substr(trim($value), 0, $maxLength);
    }

    /**
     * CRC-16/CCITT-FALSE (init 0xFFFF, poly 0x1021), as required by the Pix BR Code spec.
     */
    private function crc16(string $payload): string
    {
        $crc = 0xFFFF;

        foreach (str_split($payload) as $char) {
            $crc ^= ord($char) << 8;

            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
