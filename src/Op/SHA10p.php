<?php

namespace OpenTimestamps\Ops;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

/**
 * SHA1 hashing operation.
 * OTS OP CODE: 0x02
 */
class SHA1Op extends Op
{
    public const OPCODE = 0x02;

    public function apply(string $data): string
    {
        return sha1($data, true); // raw output
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeVarInt(self::OPCODE);
        // SHA1 has no arguments, so nothing else to serialize
    }

    public static function deserialize(BinaryReader $reader): self
    {
        // No payload for SHA1 operations
        return new self();
    }
}

