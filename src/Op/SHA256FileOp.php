<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Serialization\BinaryReader;

/**
 * SHA256FileOp
 *
 * Computes SHA256 over raw file bytes.
 */
class SHA256FileOp extends Op
{
    public static function opName(): string
    {
        return 'sha256-file';
    }

    public function apply(string $data): string
    {
        if (strlen($data) === 32 && ctype_xdigit(bin2hex($data))) {
            return $data; // already a digest
        }

        return hash('sha256', $data, true); // raw 32-byte SHA256
    }

    public function serialize(): string
    {
        return ''; // no payload
    }

    public static function fromData(BinaryReader $reader): self
    {
        return new self();
    }
}
