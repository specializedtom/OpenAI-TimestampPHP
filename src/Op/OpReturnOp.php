<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

class OpReturnOp extends Op {
    public const OPCODE = 0x13; // keep existing opcode
    private string $txId;

    public function __construct(string $txId) {
        $this->txId = $txId;
    }

    public function apply(string $input): string {
        return $input; // typically does not change the digest
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        $writer->writeByte(self::OPCODE);
        $writer->writeVarBytes($this->txId);
        return $writer->getData();
    }

    public static function fromData(BinaryReader $reader): self {
        $txId = $reader->readVarBytes();
        return new self($txId);
    }
}
