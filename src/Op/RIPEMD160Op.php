<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;
use OpenTimestamps\Exception\SerializationException;

class RIPEMD160Op extends Op {
    public const OPCODE = 0x05;

    public function apply(string $input): string {
        return hash('ripemd160', $input, true);
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        $writer->writeByte(self::OPCODE);
        $writer->writeVarBytes($this->digest);
        return $writer->getData();
    }

    public static function fromData(BinaryReader $reader): self {
        // If there is extra serialized data, read here
        return new self();
    }
}
