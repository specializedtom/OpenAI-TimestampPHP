<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

class SHA256Op extends Op {
    public const OPCODE = 0x01;

    public function apply(string $input): string {
        return hash('sha256', $input, true);
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        $writer->writeByte(self::OPCODE);
        return $writer->getData();
    }

    public static function fromData(BinaryReader $reader): self {
        return new self(); // SHA256Op has no extra data
    }
}
