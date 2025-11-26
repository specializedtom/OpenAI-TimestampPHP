<?php

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;
use OpenTimestamps\Exception\SerializationException;

class SHA256Op extends Op {
    public const OPCODE = 0x01;

    public function apply(string $input): string {
        return hash('sha256', $input, true);
    }

    public function serialize(): string {
        return chr(self::OPCODE);
    }

    public static function fromData(string $data): self {
        return new self();
    }
}