<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

class SHA1Op extends Op {
    public const OPCODE = 0x0a;

    public function apply(string $input): string {
        return hash('sha1', $input, true);
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        $writer->writeByte(self::OPCODE);
        return $writer->getData();
    }

    public static function fromData(BinaryReader $reader): self {
        return new self();
    }
}
