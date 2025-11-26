<?php

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;
use OpenTimestamps\Exception\SerializationException;

class RIPEMD160Op extends Op {
    public const OPCODE = 0x05;

    public function apply(string $input): string {
        return hash('ripemd160', $input, true);
    }

    public function serialize(): string {
        return chr(self::OPCODE);
    }

    public static function fromData(string $data): self {
        return new self();
    }
}